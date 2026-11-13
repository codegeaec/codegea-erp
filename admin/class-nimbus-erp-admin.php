<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Pantallas en WooCommerce → Nimbus ERP: Conexión, Pedidos, Stock, Newsletter y Registro. */
final class Nimbus_ERP_Admin {
	const PAGE = 'nimbus-erp-for-woocommerce';
	const TABS = array(
		'conexion' => array('label' => 'Conexión', 'icon' => 'admin-links'),
		'pedidos' => array('label' => 'Pedidos', 'icon' => 'cart'),
		'stock' => array('label' => 'Stock', 'icon' => 'archive'),
		'newsletter' => array('label' => 'Newsletter', 'icon' => 'email-alt'),
		'registro' => array('label' => 'Registro', 'icon' => 'list-view'),
	);

	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'menu'), 99);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
		add_action('admin_post_nimbus_erp_save', array(__CLASS__, 'save'));
		add_action('admin_post_nimbus_erp_ping', array(__CLASS__, 'ping'));
		add_action('admin_post_nimbus_erp_clear_log', array(__CLASS__, 'clear_log'));
	}

	public static function menu() {
		add_submenu_page('woocommerce', 'Nimbus ERP', 'Nimbus ERP', 'manage_woocommerce', self::PAGE, array(__CLASS__, 'render'));
	}

	public static function assets($hook) {
		if (strpos((string) $hook, self::PAGE) === false) {
			return;
		}
		wp_enqueue_style('nimbus-erp-for-woocommerce-admin', plugins_url('admin/css/admin.css', NIMBUS_ERP_FILE), array(), NIMBUS_ERP_VERSION);
	}

	public static function url($tab, array $extra = array()) {
		return add_query_arg(array_merge(array('page' => self::PAGE, 'tab' => $tab), $extra), admin_url('admin.php'));
	}

	/** Solo usuarios que gestionan WooCommerce; cada acción verifica además el nonce del formulario. */
	private static function check() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('No tienes permiso para esto.', 'nimbus-erp-for-woocommerce'));
		}
	}

	public static function save() {
		self::check();
		check_admin_referer('nimbus_erp');
		$tab = sanitize_key($_POST['tab'] ?? 'conexion');
		$in = wp_unslash($_POST);
		$values = array();
		if ($tab === 'conexion') {
			$values['erp_url'] = esc_url_raw(trim($in['erp_url'] ?? ''));
			// El secreto no se vuelve a mostrar: vacío = conservar el guardado.
			if (!empty($in['secret'])) {
				$values['secret'] = sanitize_text_field($in['secret']);
			}
		} elseif ($tab === 'pedidos') {
			$mode = sanitize_key($in['order_mode'] ?? 'confirm');
			$values['order_mode'] = isset(Nimbus_ERP_Settings::ORDER_MODES[$mode]) ? $mode : 'confirm';
			$statuses = array_map('sanitize_key', (array) ($in['order_statuses'] ?? array()));
			$values['order_statuses'] = array_values(array_intersect($statuses, array_keys(Nimbus_ERP_Settings::ORDER_STATUSES)));
		} elseif ($tab === 'stock') {
			$id = absint($in['warehouse_id'] ?? 0);
			$name = '';
			foreach (Nimbus_ERP_Settings::warehouses() as $w) {
				if ((int) $w['id'] === $id) {
					$name = $w['name'];
				}
			}
			$values['warehouse_id'] = $name !== '' ? $id : 0;
			$values['warehouse_name'] = $name;
			$values['stock_to_erp'] = !empty($in['stock_to_erp']) ? 'yes' : 'no';
		} elseif ($tab === 'newsletter') {
			$values['newsletter_checkout'] = !empty($in['newsletter_checkout']) ? 'yes' : 'no';
			$values['newsletter_label'] = sanitize_text_field($in['newsletter_label'] ?? '');
		}
		Nimbus_ERP_Settings::update($values);
		// «Actualizar lista» de Stock guarda lo elegido y vuelve a pedir las bodegas al ERP.
		if (($tab === 'conexion' || !empty($in['refresh'])) && Nimbus_ERP_Settings::is_configured()) {
			Nimbus_ERP_Client::ping();
		}
		wp_safe_redirect(self::url($tab, array('saved' => 1)));
		exit;
	}

	public static function ping() {
		self::check();
		check_admin_referer('nimbus_erp');
		Nimbus_ERP_Client::ping();
		$back = sanitize_key($_POST['back'] ?? 'conexion');
		wp_safe_redirect(self::url(isset(self::TABS[$back]) ? $back : 'conexion', array('pinged' => 1)));
		exit;
	}

	public static function clear_log() {
		self::check();
		check_admin_referer('nimbus_erp');
		Nimbus_ERP_Log::clear();
		wp_safe_redirect(self::url('registro'));
		exit;
	}

	public static function render() {
		// Solo elige la pestaña que se muestra: no cambia nada, no lleva nonce.
		$tab = sanitize_key(wp_unslash($_GET['tab'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!isset(self::TABS[$tab])) {
			$tab = Nimbus_ERP_Settings::is_connected() ? 'pedidos' : 'conexion';
		}
		echo '<div class="wrap cerp">';
		self::header();
		echo '<hr class="wp-header-end">';
		if (isset($_GET['saved'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible inline"><p>Cambios guardados.</p></div>';
		}
		echo '<nav class="cerp-tabs" aria-label="Secciones">';
		foreach (self::TABS as $key => $t) {
			printf(
				'<a href="%s" class="cerp-tab%s"><span class="dashicons dashicons-%s" aria-hidden="true"></span>%s</a>',
				esc_url(self::url($key)),
				$key === $tab ? ' is-active' : '',
				esc_attr($t['icon']),
				esc_html($t['label'])
			);
		}
		echo '</nav><div class="cerp-layout"><div class="cerp-main">';
		require NIMBUS_ERP_DIR . "admin/views/{$tab}.php";
		echo '</div><aside class="cerp-aside">';
		require NIMBUS_ERP_DIR . 'admin/views/help.php';
		echo '</aside></div></div>';
	}

	/** Título y estado de la conexión, visible en todas las pestañas. */
	private static function header() {
		$info = Nimbus_ERP_Settings::erp_info();
		if (Nimbus_ERP_Settings::is_connected()) {
			$pill = array('ok', 'yes-alt', 'Conectado' . (!empty($info['company']) ? ' con ' . $info['company'] : ''));
		} elseif (!Nimbus_ERP_Settings::is_configured()) {
			$pill = array('off', 'marker', 'Sin configurar');
		} elseif (!empty($info['checked_at'])) {
			$pill = array('error', 'warning', 'Sin conexión');
		} else {
			$pill = array('off', 'marker', 'Conexión sin probar');
		}
		?>
		<div class="cerp-header">
			<div class="cerp-brand">
				<img class="cerp-logo" src="<?php echo esc_url(plugins_url('admin/images/nimbus-mark.svg', NIMBUS_ERP_FILE)); ?>" alt="Nimbus ERP" width="40" height="40">
				<div>
					<h1>Nimbus ERP</h1>
					<p>Pedidos y stock de tu tienda, al día en el ERP · <a href="https://codegea.com" target="_blank" rel="noopener">por Codegea</a></p>
				</div>
			</div>
			<span class="cerp-pill cerp-pill--<?php echo esc_attr($pill[0]); ?>"><span class="dashicons dashicons-<?php echo esc_attr($pill[1]); ?>" aria-hidden="true"></span><?php echo esc_html($pill[2]); ?></span>
		</div>
		<?php
	}

	/** Abre el formulario de una pestaña (nonce + acción de guardado). */
	public static function form_open($tab, $action = 'nimbus_erp_save') {
		printf('<form method="post" action="%s">', esc_url(admin_url('admin-post.php')));
		wp_nonce_field('nimbus_erp');
		printf('<input type="hidden" name="action" value="%s"><input type="hidden" name="tab" value="%s"><input type="hidden" name="back" value="%s">', esc_attr($action), esc_attr($tab), esc_attr($tab));
	}

	/** Botón «Probar conexión» como formulario propio (se usa en Conexión y en Stock). */
	public static function ping_button($back, $label = 'Probar conexión', $class = 'button') {
		self::form_open($back, 'nimbus_erp_ping');
		printf('<button type="submit" class="%s cerp-btn-icon"><span class="dashicons dashicons-update" aria-hidden="true"></span>%s</button></form>', esc_attr($class), esc_html($label));
	}

	/** «hace 5 minutos» a partir de una fecha local de WordPress. */
	public static function ago($mysql_date) {
		$ts = strtotime((string) $mysql_date);
		return $ts ? sprintf('hace %s', human_time_diff($ts, current_time('timestamp'))) : '';
	}
}
