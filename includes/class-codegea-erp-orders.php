<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Pedidos → ERP. Al pasar un pedido a uno de los estados elegidos se encola el envío con Action Scheduler
 * (incluido en WooCommerce): el checkout no espera al ERP y, si el ERP no responde 2xx, se reintenta
 * (1, 5, 15, 60 y 180 minutos). El ERP descarta duplicados por `wc_order_id`, así que reenviar es seguro.
 */
final class Codegea_ERP_Orders {
	const ACTION = 'codegea_erp_send_order';
	const SENT_META = '_codegea_erp_sent';
	const RETRY_MINUTES = array(1, 5, 15, 60, 180);

	public static function init() {
		add_action('woocommerce_order_status_changed', array(__CLASS__, 'on_status_changed'), 10, 3);
		add_action(self::ACTION, array(__CLASS__, 'send'), 10, 2);
		add_filter('woocommerce_order_actions', array(__CLASS__, 'order_actions'));
		add_action('woocommerce_order_action_codegea_erp_resend', array(__CLASS__, 'resend'));
	}

	public static function on_status_changed($order_id, $from, $to) {
		if (!in_array($to, (array) Codegea_ERP_Settings::get('order_statuses'), true)) {
			return;
		}
		$order = wc_get_order($order_id);
		// Un pedido que ya llegó al ERP no se reenvía solo por cambiar de estado (ej. procesando → completado).
		if (!$order || $order->get_meta(self::SENT_META)) {
			return;
		}
		self::enqueue($order_id, 0, 0);
	}

	public static function order_actions($actions) {
		$actions['codegea_erp_resend'] = __('Enviar al ERP', 'codegea-erp');
		return $actions;
	}

	public static function resend($order) {
		self::send($order->get_id(), count(self::RETRY_MINUTES));
	}

	private static function enqueue($order_id, $attempt, $delay_minutes) {
		$args = array('order_id' => (int) $order_id, 'attempt' => (int) $attempt);
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action(time() + $delay_minutes * MINUTE_IN_SECONDS, self::ACTION, $args, 'codegea-erp');
		} else {
			self::send($args['order_id'], $args['attempt']);
		}
	}

	public static function send($order_id, $attempt = 0) {
		$order = wc_get_order($order_id);
		if (!$order || !Codegea_ERP_Settings::is_connected()) {
			return;
		}
		$result = Codegea_ERP_Client::post('orders', self::payload($order));
		if ($result['ok']) {
			$erp_order = is_array($result['body']) ? ($result['body']['orderId'] ?? null) : null;
			$order->update_meta_data(self::SENT_META, current_time('mysql'));
			$order->save();
			$order->add_order_note($erp_order ? sprintf('Pedido enviado al ERP (orden #%d).', $erp_order) : 'Pedido enviado al ERP.');
			Codegea_ERP_Log::add('pedido', '#' . $order_id, true, $erp_order ? 'Orden #' . $erp_order : '');
			return;
		}
		Codegea_ERP_Log::add('pedido', '#' . $order_id, false, $result['error']);
		if ($attempt < count(self::RETRY_MINUTES)) {
			self::enqueue($order_id, $attempt + 1, self::RETRY_MINUTES[$attempt]);
			return;
		}
		$order->add_order_note('No se pudo enviar el pedido al ERP: ' . $result['error']);
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
		$ruc = (string) apply_filters('codegea_erp_client_ruc', $ruc, $order);

		$payload = array(
			'wc_order_id' => (int) $order->get_id(),
			'status' => $order->get_status(),
			'order_mode' => (string) Codegea_ERP_Settings::get('order_mode'),
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
		);
		$warehouse = (string) Codegea_ERP_Settings::get('warehouse_code');
		if ($warehouse !== '') {
			$payload['warehouse_code'] = $warehouse;
		}
		return apply_filters('codegea_erp_order_payload', $payload, $order);
	}
}
