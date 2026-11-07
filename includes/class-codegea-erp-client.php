<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Llamadas al ERP. Cada body va firmado con HMAC-SHA256 y el secreto compartido (header X-WC-Signature);
 * es lo que el ERP valida para aceptar la llamada.
 */
final class Codegea_ERP_Client {
	const BASE_PATH = '/api/plugins/wordpress/webhook/';

	/**
	 * @return array{ok:bool,code:int,body:mixed,error:string}
	 */
	public static function post($endpoint, array $data, $timeout = 30) {
		if (!Codegea_ERP_Settings::is_connected()) {
			return array('ok' => false, 'code' => 0, 'body' => null, 'error' => 'Falta la URL del ERP o el secreto.');
		}
		$body = wp_json_encode($data);
		$response = wp_remote_post(Codegea_ERP_Settings::erp_base_url() . self::BASE_PATH . $endpoint, array(
			'timeout' => $timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-WC-Signature' => 'sha256=' . hash_hmac('sha256', $body, (string) Codegea_ERP_Settings::get('secret')),
				'User-Agent' => 'codegea-erp/' . CODEGEA_ERP_VERSION . '; ' . home_url(),
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

	/** «Probar conexión»: guarda lo que devuelve el ERP (empresa y bodegas) para las pantallas. */
	public static function ping() {
		$result = self::post('ping', array('site' => home_url(), 'plugin_version' => CODEGEA_ERP_VERSION), 15);
		if ($result['ok'] && is_array($result['body'])) {
			update_option(Codegea_ERP_Settings::ERP_INFO, array_merge($result['body'], array('checked_at' => current_time('mysql'))), false);
		}
		Codegea_ERP_Log::add('conexión', home_url(), $result['ok'], $result['ok'] ? 'Conectado con ' . ($result['body']['company'] ?? 'el ERP') : $result['error']);
		return $result;
	}
}
