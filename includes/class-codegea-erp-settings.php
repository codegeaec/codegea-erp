<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Ajustes del plugin (una sola opción `codegea_erp_settings`). */
final class Codegea_ERP_Settings {
	const OPTION = 'codegea_erp_settings';
	/** Datos que devolvió el ERP en «Probar conexión» (empresa, bodegas). */
	const ERP_INFO = 'codegea_erp_info';

	const ORDER_MODES = array(
		'draft' => 'Guardar como borrador (se revisa y confirma en el ERP)',
		'confirm' => 'Confirmar la venta (descuenta el stock del ERP)',
		'invoice' => 'Confirmar y facturar',
	);

	public static function defaults() {
		return array(
			'erp_url' => '',
			'secret' => '',
			'order_mode' => 'confirm',
			'order_statuses' => array('processing', 'completed', 'on-hold'),
			'warehouse_code' => '',
			'stock_to_erp' => 'no',
		);
	}

	public static function all() {
		$saved = get_option(self::OPTION, array());
		return wp_parse_args(is_array($saved) ? $saved : array(), self::defaults());
	}

	public static function get($key) {
		$all = self::all();
		return $all[$key] ?? null;
	}

	public static function update(array $values) {
		update_option(self::OPTION, array_merge(self::all(), $values), false);
	}

	public static function is_connected() {
		$s = self::all();
		return $s['erp_url'] !== '' && $s['secret'] !== '';
	}

	/**
	 * URL base del ERP. Acepta también la URL completa del webhook que muestra el ERP
	 * (…/api/plugins/wordpress/webhook/orders) y se queda con el dominio.
	 */
	public static function erp_base_url() {
		$url = trim((string) self::get('erp_url'));
		$url = preg_replace('#/api/plugins/wordpress/webhook/.*$#', '', $url);
		return untrailingslashit($url);
	}

	public static function erp_info() {
		$info = get_option(self::ERP_INFO, array());
		return is_array($info) ? $info : array();
	}
}
