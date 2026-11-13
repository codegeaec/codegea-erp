<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Últimos envíos al ERP (pestaña Registro): sirve para diagnosticar sin entrar al servidor. */
final class Nimbus_ERP_Log {
	const OPTION = 'nimbus_erp_log';
	const MAX = 50;

	public static function add($type, $ref, $ok, $message = '') {
		$entries = self::all();
		array_unshift($entries, array(
			'time' => current_time('mysql'),
			'type' => (string) $type,
			'ref' => (string) $ref,
			'ok' => (bool) $ok,
			'message' => substr((string) $message, 0, 300),
		));
		update_option(self::OPTION, array_slice($entries, 0, self::MAX), false);
	}

	public static function all() {
		$entries = get_option(self::OPTION, array());
		return is_array($entries) ? $entries : array();
	}

	public static function clear() {
		delete_option(self::OPTION);
	}
}
