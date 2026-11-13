<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Pedidos → ERP. Al pasar un pedido a uno de los estados elegidos se encola el envío con Action Scheduler
 * (incluido en WooCommerce): el checkout no espera al ERP y, si el ERP no responde 2xx, se reintenta
 * (1, 5, 15, 60 y 180 minutos). El ERP descarta duplicados por `wc_order_id`, así que reenviar es seguro; si
 * el pedido había llegado sin pagar y ya se pagó, el ERP confirma (o factura) la venta que estaba en borrador.
 */
final class Nimbus_ERP_Orders {
	const ACTION = 'nimbus_erp_send_order';
	const SENT_META = '_nimbus_erp_sent';
	/** Marca de «ya enviado» de las versiones con el nombre Codegea ERP (antes de 1.2.0). */
	const LEGACY_SENT_META = '_codegea_erp_sent';
	/** Estado con el que se envió el pedido (para volver a avisar cuando un pedido sin pagar se paga). */
	const SENT_STATUS_META = '_nimbus_erp_sent_status';
	const PAID_STATUSES = array('processing', 'completed');
	const RETRY_MINUTES = array(1, 5, 15, 60, 180);

	public static function init() {
		add_action('woocommerce_order_status_changed', array(__CLASS__, 'on_status_changed'), 10, 3);
		add_action(self::ACTION, array(__CLASS__, 'send'), 10, 2);
		add_filter('woocommerce_order_actions', array(__CLASS__, 'order_actions'));
		add_action('woocommerce_order_action_nimbus_erp_resend', array(__CLASS__, 'resend'));
	}

	public static function on_status_changed($order_id, $from, $to) {
		if (!in_array($to, (array) Nimbus_ERP_Settings::get('order_statuses'), true)) {
			return;
		}
		$order = wc_get_order($order_id);
		// Un pedido que ya llegó al ERP no se reenvía solo por cambiar de estado (ej. procesando → completado).
		if (!$order) {
			return;
		}
		// Ya enviado: solo se vuelve a avisar si llegó sin pagar (ej. «En espera») y ahora se pagó, para que el
		// ERP confirme la venta que quedó en borrador. Procesando → Completado no se reenvía.
		if ($order->get_meta(self::SENT_META) || $order->get_meta(self::LEGACY_SENT_META)) {
			$was_paid = in_array((string) $order->get_meta(self::SENT_STATUS_META), self::PAID_STATUSES, true);
			if ($was_paid || !in_array($to, self::PAID_STATUSES, true)) {
				return;
			}
		}
		self::enqueue($order_id, 0, 0);
	}

	public static function order_actions($actions) {
		$actions['nimbus_erp_resend'] = __('Enviar al ERP', 'nimbus-erp-for-woocommerce');
		return $actions;
	}

	public static function resend($order) {
		self::send($order->get_id(), count(self::RETRY_MINUTES));
	}

	private static function enqueue($order_id, $attempt, $delay_minutes) {
		$args = array('order_id' => (int) $order_id, 'attempt' => (int) $attempt);
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action(time() + $delay_minutes * MINUTE_IN_SECONDS, self::ACTION, $args, 'nimbus-erp-for-woocommerce');
		} else {
			self::send($args['order_id'], $args['attempt']);
		}
	}

	public static function send($order_id, $attempt = 0) {
		$order = wc_get_order($order_id);
		if (!$order || !Nimbus_ERP_Settings::is_configured()) {
			return;
		}
		$result = Nimbus_ERP_Client::post('orders', self::payload($order));
		if ($result['ok']) {
			$erp_order = is_array($result['body']) ? ($result['body']['orderId'] ?? null) : null;
			$order->update_meta_data(self::SENT_META, current_time('mysql'));
			$order->update_meta_data(self::SENT_STATUS_META, $order->get_status());
			$order->save();
			$order->add_order_note($erp_order ? sprintf('Pedido enviado al ERP (orden #%d).', $erp_order) : 'Pedido enviado al ERP.');
			Nimbus_ERP_Log::add('pedido', '#' . $order_id, true, $erp_order ? 'Orden #' . $erp_order : '');
			return;
		}
		Nimbus_ERP_Log::add('pedido', '#' . $order_id, false, $result['error']);
		if ($attempt < count(self::RETRY_MINUTES)) {
			self::enqueue($order_id, $attempt + 1, self::RETRY_MINUTES[$attempt]);
			return;
		}
		$order->add_order_note('No se pudo enviar el pedido al ERP: ' . $result['error']);
	}

	/** Cupones del pedido con lo que descontó cada uno (con impuestos): el ERP registra su uso en Fidelidad. */
	private static function coupons(WC_Order $order) {
		$coupons = array();
		foreach ($order->get_items('coupon') as $item) {
			/** @var WC_Order_Item_Coupon $item */
			$coupons[] = array(
				'code' => strtoupper((string) $item->get_code()),
				'discount' => round((float) $item->get_discount() + (float) $item->get_discount_tax(), 2),
			);
		}
		return $coupons;
	}

	/**
	 * Formato que espera el ERP (`POST /api/plugins/wordpress/webhook/orders`). Montos sin impuestos y la tasa
	 * que cobró la tienda por línea, para que la venta del ERP sume lo mismo que pagó el cliente.
	 */
	public static function payload(WC_Order $order) {
		$items = array();
		foreach ($order->get_items() as $item) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();
			$qty = max(1, (int) $item->get_quantity());
			$subtotal = (float) $item->get_subtotal();
			$total = (float) $item->get_total();
			$tax = (float) $item->get_total_tax();
			$items[] = array(
				'sku' => $product ? $product->get_sku() : '',
				'quantity' => $qty,
				'unit_price' => round($subtotal / $qty, 4),
				'discount_pct' => $subtotal > 0 ? round(($subtotal - $total) / $subtotal * 100, 4) : 0,
				'tax_rate' => $total > 0 ? round($tax / $total * 100, 4) : 0,
				'name' => $item->get_name(),
			);
		}

		$name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		$ruc = (string) $order->get_meta('_billing_cedula_ruc');
		/** Para tiendas que guardan la cédula/RUC en otro campo del checkout. */
		$ruc = (string) apply_filters('nimbus_erp_client_ruc', $ruc, $order);

		$payload = array(
			'wc_order_id' => (int) $order->get_id(),
			'status' => $order->get_status(),
			'order_mode' => (string) Nimbus_ERP_Settings::get('order_mode'),
			'payment_method' => $order->get_payment_method(),
			'currency' => $order->get_currency(),
			'client' => array(
				'name' => $name !== '' ? $name : $order->get_billing_company(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
				'ruc' => $ruc !== '' ? $ruc : null,
			),
			'items' => $items,
			'shipping' => (float) $order->get_shipping_total(),
			'shipping_tax' => (float) $order->get_shipping_tax(),
			'total' => (float) $order->get_total(),
			'notes' => $order->get_customer_note(),
			'coupons' => self::coupons($order),
		);
		$warehouse = (int) Nimbus_ERP_Settings::get('warehouse_id');
		if ($warehouse > 0) {
			$payload['warehouse_id'] = $warehouse;
		}
		return apply_filters('nimbus_erp_order_payload', $payload, $order);
	}
}
