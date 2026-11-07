<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Pantallas en WooCommerce → Codegea ERP: Conexión, Pedidos, Stock y Registro. */
final class Codegea_ERP_Admin {
	const PAGE = 'codegea-erp';
	const TABS = array('conexion' => 'Conexión', 'pedidos' => 'Pedidos', 'stock' => 'Stock', 'registro' => 'Registro');

	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'menu'), 99);
		add_action('admin_post_codegea_erp_save', array(__CLASS__, 'save'));
		add_action('admin_post_codegea_erp_ping', array(__CLASS__, 'ping'));
		add_action('admin_post_codegea_erp_clear_log', array(__CLASS__, 'clear_log'));
	}

	public static function menu() {
		add_submenu_page('woocommerce', 'Codegea ERP', 'Codegea ERP', 'manage_woocommerce', self::PAGE, array(__CLASS__, 'render'));
	}

	private static function url($tab, array $extra = array()) {
		return add_query_arg(array_merge(array('page' => self::PAGE, 'tab' => $tab), $extra), admin_url('admin.php'));
	}

	private static function check() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('No tienes permiso para esto.', 'codegea-erp'));
		}
		check_admin_referer('codegea_erp');
	}

	public static function save() {
		self::check();
		$tab = sanitize_key($_POST['tab'] ?? 'conexion');
		$in = wp_unslash($_POST);
		$values = array();
		if ($tab === 'conexion') {
			$values['erp_url'] = esc_url_raw(trim($in['erp_url'] ?? ''));
			// El secreto no se muestra: vacío = conservar el guardado.
			if (!empty($in['secret'])) {
				$values['secret'] = sanitize_text_field($in['secret']);
			}
		} elseif ($tab === 'pedidos') {
			$mode = sanitize_key($in['order_mode'] ?? 'confirm');
			$values['order_mode'] = isset(Codegea_ERP_Settings::ORDER_MODES[$mode]) ? $mode : 'confirm';
			$statuses = array_map('sanitize_key', (array) ($in['order_statuses'] ?? array()));
			$values['order_statuses'] = array_values(array_intersect($statuses, array('processing', 'completed', 'on-hold')));
		} elseif ($tab === 'stock') {
			$values['warehouse_code'] = sanitize_text_field($in['warehouse_code'] ?? '');
			$values['stock_to_erp'] = !empty($in['stock_to_erp']) ? 'yes' : 'no';
		}
		Codegea_ERP_Settings::update($values);
		$extra = array('saved' => 1);
		if ($tab === 'conexion' && Codegea_ERP_Settings::is_connected()) {
			$extra['ping'] = Codegea_ERP_Client::ping()['ok'] ? 'ok' : 'fail';
		}
		wp_safe_redirect(self::url($tab, $extra));
		exit;
	}

	public static function ping() {
		self::check();
		$ok = Codegea_ERP_Client::ping()['ok'];
		wp_safe_redirect(self::url('conexion', array('ping' => $ok ? 'ok' : 'fail')));
		exit;
	}

	public static function clear_log() {
		self::check();
		Codegea_ERP_Log::clear();
		wp_safe_redirect(self::url('registro'));
		exit;
	}

	public static function render() {
		$tab = sanitize_key($_GET['tab'] ?? 'conexion');
		if (!isset(self::TABS[$tab])) {
			$tab = 'conexion';
		}
		echo '<div class="wrap"><h1>Codegea ERP</h1>';
		self::notices();
		echo '<nav class="nav-tab-wrapper">';
		foreach (self::TABS as $key => $label) {
			printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url(self::url($key)), $key === $tab ? ' nav-tab-active' : '', esc_html($label));
		}
		echo '</nav>';
		require CODEGEA_ERP_DIR . "admin/views/{$tab}.php";
		echo '</div>';
	}

	private static function notices() {
		if (isset($_GET['saved'])) {
			echo '<div class="notice notice-success is-dismissible"><p>Cambios guardados.</p></div>';
		}
		if (isset($_GET['ping'])) {
			$info = Codegea_ERP_Settings::erp_info();
			if ($_GET['ping'] === 'ok') {
				printf('<div class="notice notice-success"><p>Conectado con <strong>%s</strong>.</p></div>', esc_html($info['company'] ?? 'el ERP'));
			} else {
				$last = Codegea_ERP_Log::all()[0] ?? array();
				printf('<div class="notice notice-error"><p>No se pudo conectar con el ERP: %s</p></div>', esc_html($last['message'] ?? ''));
			}
		}
	}

	/** Abre el formulario de una pestaña (nonce + acción de guardado). */
	public static function form_open($tab) {
		printf('<form method="post" action="%s">', esc_url(admin_url('admin-post.php')));
		wp_nonce_field('codegea_erp');
		printf('<input type="hidden" name="action" value="codegea_erp_save"><input type="hidden" name="tab" value="%s">', esc_attr($tab));
	}
}
