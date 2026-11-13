<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Llamadas al ERP. Cada body va firmado con HMAC-SHA256 y el secreto compartido (header X-WC-Signature);
 * es lo que el ERP valida para aceptar la llamada.
 */
final class Nimbus_ERP_Client {
	const BASE_PATH = '/api/plugins/wordpress/webhook/';

	/**
	 * @return array{ok:bool,code:int,body:mixed,error:string}
	 */
	public static function post($endpoint, array $data, $timeout = 30) {
		if (!Nimbus_ERP_Settings::is_configured()) {
			return array('ok' => false, 'code' => 0, 'body' => null, 'error' => 'Falta la URL del ERP o el secreto.');
		}
		$body = wp_json_encode($data);
		$response = wp_remote_post(Nimbus_ERP_Settings::erp_base_url() . self::BASE_PATH . $endpoint, array(
			'timeout' => $timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-WC-Signature' => 'sha256=' . hash_hmac('sha256', $body, (string) Nimbus_ERP_Settings::get('secret')),
				'User-Agent' => 'nimbus-erp-for-woocommerce/' . NIMBUS_ERP_VERSION . '; ' . home_url(),
			),
			'body' => $body,
		));
		if (is_wp_error($response)) {
			return array('ok' => false, 'code' => 0, 'body' => null, 'error' => $response->get_error_message());
		}
		$code = (int) wp_remote_retrieve_response_code($response);
		$decoded = json_decode(wp_remote_retrieve_body($response), true);
		$ok = $code >= 200 && $code < 300;
		$error = $ok ? '' : (is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : 'HTTP ' . $code);
		return array('ok' => $ok, 'code' => $code, 'body' => $decoded, 'error' => $error);
	}

	/**
	 * «Probar conexión»: guarda lo que devuelve el ERP (empresa, bodegas) o el error con una explicación de qué
	 * hacer, para mostrarlo en las pantallas sin volver a llamar al ERP.
	 */
	public static function ping() {
		$result = self::post('ping', array('site' => home_url(), 'plugin_version' => NIMBUS_ERP_VERSION), 15);
		$info = array('ok' => $result['ok'], 'checked_at' => current_time('mysql'));
		if ($result['ok'] && is_array($result['body'])) {
			$info = array_merge($result['body'], $info);
			self::refresh_warehouse_name($info);
		} else {
			// Se conserva lo último bueno (bodegas) para no perder la selección por un corte momentáneo.
			$info = array_merge(Nimbus_ERP_Settings::erp_info(), $info, array('error' => self::explain($result)));
		}
		update_option(Nimbus_ERP_Settings::ERP_INFO, $info, false);
		Nimbus_ERP_Log::add('conexión', home_url(), $result['ok'], $result['ok'] ? 'Conectado con ' . ($info['company'] ?: 'el ERP') : $info['error']);
		return $result;
	}

	/** El nombre de la bodega guardada puede haber cambiado en el ERP. */
	private static function refresh_warehouse_name(array $info) {
		$id = (int) Nimbus_ERP_Settings::get('warehouse_id');
		if (!$id) {
			return;
		}
		foreach ((array) ($info['warehouses'] ?? array()) as $w) {
			if ((int) $w['id'] === $id) {
				Nimbus_ERP_Settings::update(array('warehouse_name' => $w['name']));
				return;
			}
		}
		// La bodega ya no existe en el ERP: se vuelve a la del ERP.
		Nimbus_ERP_Settings::update(array('warehouse_id' => 0, 'warehouse_name' => ''));
	}

	private static function explain(array $result) {
		switch (true) {
			case $result['code'] === 401:
				return 'El secreto no coincide con el del ERP. Cópialo de nuevo desde el ERP (Configuración → Plugins → WordPress / WooCommerce → Pedidos).';
			case $result['code'] === 404:
				return 'Esa dirección no corresponde a una empresa del ERP, o el plugin WordPress no está activo allá. Revisa la URL.';
			case $result['code'] === 0:
				return 'No se pudo llegar al ERP (' . $result['error'] . '). Revisa la URL y que el servidor esté en línea.';
			default:
				return 'El ERP respondió con un error: ' . $result['error'];
		}
	}
}
