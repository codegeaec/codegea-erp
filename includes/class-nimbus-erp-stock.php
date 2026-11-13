<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Stock de la tienda → ERP, solo para cambios hechos a mano en WooCommerce (editar el producto, importar un
 * CSV…). No se envían:
 * - los descuentos/devoluciones de stock por pedidos: el ERP ya los descuenta al recibir el pedido (si no, se
 *   descontaría dos veces);
 * - los cambios que hace el propio ERP por la API REST (header X-Nimbus-Erp): volverían al ERP sin cambios.
 * El ERP ajusta la bodega elegida (pestaña Stock) al valor que tiene la tienda.
 */
final class Nimbus_ERP_Stock {
	const ACTION = 'nimbus_erp_send_stock';

	/** true mientras WooCommerce descuenta o repone stock por un pedido. */
	private static $order_stock_change = false;

	public static function init() {
		add_filter('woocommerce_can_reduce_order_stock', array(__CLASS__, 'order_change_start'), 999);
		add_filter('woocommerce_can_restore_order_stock', array(__CLASS__, 'order_change_start'), 999);
		add_action('woocommerce_reduce_order_stock', array(__CLASS__, 'order_change_end'));
		add_action('woocommerce_restore_order_stock', array(__CLASS__, 'order_change_end'));

		add_action('woocommerce_product_set_stock', array(__CLASS__, 'on_stock_set'));
		add_action('woocommerce_variation_set_stock', array(__CLASS__, 'on_stock_set'));
		add_action(self::ACTION, array(__CLASS__, 'send'));
	}

	public static function order_change_start($can) {
		if ($can) {
			self::$order_stock_change = true;
		}
		return $can;
	}

	public static function order_change_end() {
		self::$order_stock_change = false;
	}

	private static function comes_from_erp() {
		return defined('REST_REQUEST') && REST_REQUEST && (!empty($_SERVER['HTTP_X_NIMBUS_ERP']) || !empty($_SERVER['HTTP_X_CODEGEA_ERP']));
	}

	/** @param WC_Product $product */
	public static function on_stock_set($product) {
		if (Nimbus_ERP_Settings::get('stock_to_erp') !== 'yes' || self::$order_stock_change || self::comes_from_erp()) {
			return;
		}
		if (!$product->get_sku() || !$product->managing_stock()) {
			return;
		}
		$args = array('product_id' => (int) $product->get_id());
		// Varias ediciones seguidas del mismo producto → un solo envío con el valor final.
		if (function_exists('as_has_scheduled_action') && as_has_scheduled_action(self::ACTION, $args, 'nimbus-erp-for-woocommerce')) {
			return;
		}
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action(time() + 30, self::ACTION, $args, 'nimbus-erp-for-woocommerce');
		} else {
			self::send($args['product_id']);
		}
	}

	public static function send($product_id) {
		$product = wc_get_product($product_id);
		if (!$product || !$product->get_sku()) {
			return;
		}
		$data = array(
			'items' => array(array('sku' => $product->get_sku(), 'stock' => (float) $product->get_stock_quantity())),
			'warehouse_id' => (int) Nimbus_ERP_Settings::get('warehouse_id') ?: null,
		);
		$result = Nimbus_ERP_Client::post('stock', $data);
		Nimbus_ERP_Log::add('stock', $product->get_sku(), $result['ok'], $result['ok'] ? 'Stock ' . $product->get_stock_quantity() : $result['error']);
	}
}
