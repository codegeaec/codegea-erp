<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Ajustes del plugin (una sola opción `nimbus_erp_settings`). */
final class Nimbus_ERP_Settings {
	const OPTION = 'nimbus_erp_settings';
	/** Última respuesta de «Probar conexión» (empresa, bodegas) o su error. */
	const ERP_INFO = 'nimbus_erp_info';

	const ORDER_MODES = array(
		'draft' => array(
			'label' => 'Guardar como borrador',
			'help' => 'Llega al ERP para revisarla y confirmarla a mano. No descuenta stock todavía.',
		),
		'confirm' => array(
			'label' => 'Confirmar la venta',
			'help' => 'Queda confirmada y descuenta el stock de la bodega. La factura se hace desde el ERP.',
		),
		'invoice' => array(
			'label' => 'Confirmar y facturar',
			'help' => 'Además genera la factura. Requiere cédula/RUC del cliente y la facturación del ERP configurada.',
		),
	);

	const ORDER_STATUSES = array(
		'processing' => array('label' => 'Procesando', 'help' => 'El cliente ya pagó. Es el estado normal de un pedido nuevo: déjalo marcado.'),
		'completed' => array('label' => 'Completado', 'help' => 'Por si marcas pedidos como completados directamente, sin pasar por Procesando.'),
		'on-hold' => array('label' => 'En espera', 'help' => 'Pago pendiente de confirmar (ej. transferencia). Llega al ERP como borrador, sin descontar stock.'),
	);

	public static function defaults() {
		return array(
			'erp_url' => '',
			'secret' => '',
			'order_mode' => 'confirm',
			'order_statuses' => array('processing', 'completed', 'on-hold'),
			// Bodega del ERP: se guarda el id (el código puede cambiar) y el nombre para mostrarlo.
			'warehouse_id' => 0,
			'warehouse_name' => '',
			'stock_to_erp' => 'no',
			// Casilla «Quiero recibir novedades» en el checkout (newsletter del ERP).
			'newsletter_checkout' => 'no',
			'newsletter_label' => '',
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

	public static function is_configured() {
		$s = self::all();
		return $s['erp_url'] !== '' && $s['secret'] !== '';
	}

	/** Configurado y la última prueba de conexión salió bien. */
	public static function is_connected() {
		$info = self::erp_info();
		return self::is_configured() && !empty($info['ok']);
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

	/** @return array<int, array{id:int,code:string,name:string,is_main:bool}> */
	public static function warehouses() {
		$info = self::erp_info();
		return isset($info['warehouses']) && is_array($info['warehouses']) ? $info['warehouses'] : array();
	}

	/** Nombre de la bodega con la que trabaja la tienda (la elegida, o la del ERP). */
	public static function warehouse_label() {
		$s = self::all();
		if ((int) $s['warehouse_id'] > 0) {
			return $s['warehouse_name'] !== '' ? $s['warehouse_name'] : '#' . (int) $s['warehouse_id'];
		}
		$info = self::erp_info();
		foreach (self::warehouses() as $w) {
			if ((int) $w['id'] === (int) ($info['stock_warehouse_id'] ?? 0)) {
				return $w['name'] . ' (elegida en el ERP)';
			}
		}
		return 'la bodega por defecto del ERP';
	}
}
