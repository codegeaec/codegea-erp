<?php
if (!defined('ABSPATH')) {
	exit;
}

/** Pantallas en WooCommerce → Codegea ERP: Conexión, Pedidos, Stock y Registro. */
final class Codegea_ERP_Admin {
	const PAGE = 'codegea-erp';
	const TABS = array(
		'conexion' => array('label' => 'Conexión', 'icon' => 'admin-links'),
		'pedidos' => array('label' => 'Pedidos', 'icon' => 'cart'),
		'stock' => array('label' => 'Stock', 'icon' => 'archive'),
		'registro' => array('label' => 'Registro', 'icon' => 'list-view'),
	);

	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'menu'), 99);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
		add_action('admin_post_codegea_erp_save', array(__CLASS__, 'save'));
		add_action('admin_post_codegea_erp_ping', array(__CLASS__, 'ping'));
		add_action('admin_post_codegea_erp_clear_log', array(__CLASS__, 'clear_log'));
	}

	public static function menu() {
		add_submenu_page('woocommerce', 'Codegea ERP', 'Codegea ERP', 'manage_woocommerce', self::PAGE, array(__CLASS__, 'render'));
	}

	public static function assets($hook) {
		if (strpos((string) $hook, self::PAGE) === false) {
			return;
		}
		wp_enqueue_style('codegea-erp-admin', plugins_url('admin/css/admin.css', CODEGEA_ERP_FILE), array(), CODEGEA_ERP_VERSION);
	}

	public static function url($tab, array $extra = array()) {
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
			// El secreto no se vuelve a mostrar: vacío = conservar el guardado.
			if (!empty($in['secret'])) {
				$values['secret'] = sanitize_text_field($in['secret']);
			}
		} elseif ($tab === 'pedidos') {
			$mode = sanitize_key($in['order_mode'] ?? 'confirm');
			$values['order_mode'] = isset(Codegea_ERP_Settings::ORDER_MODES[$mode]) ? $mode : 'confirm';
			$statuses = array_map('sanitize_key', (array) ($in['order_statuses'] ?? array()));
			$values['order_statuses'] = array_values(array_intersect($statuses, array_keys(Codegea_ERP_Settings::ORDER_STATUSES)));
		} elseif ($tab === 'stock') {
			$id = absint($in['warehouse_id'] ?? 0);
			$name = '';
			foreach (Codegea_ERP_Settings::warehouses() as $w) {
				if ((int) $w['id'] === $id) {
					$name = $w['name'];
				}
			}
			$values['warehouse_id'] = $name !== '' ? $id : 0;
			$values['warehouse_name'] = $name;
			$values['stock_to_erp'] = !empty($in['stock_to_erp']) ? 'yes' : 'no';
		}
		Codegea_ERP_Settings::update($values);
		if ($tab === 'conexion' && Codegea_ERP_Settings::is_configured()) {
			Codegea_ERP_Client::ping();
		}
		wp_safe_redirect(self::url($tab, array('saved' => 1)));
		exit;
	}

	public static function ping() {
		self::check();
		Codegea_ERP_Client::ping();
		$back = sanitize_key($_POST['back'] ?? 'conexion');
		wp_safe_redirect(self::url(isset(self::TABS[$back]) ? $back : 'conexion', array('pinged' => 1)));
		exit;
	}

	public static function clear_log() {
		self::check();
		Codegea_ERP_Log::clear();
		wp_safe_redirect(self::url('registro'));
		exit;
	}

	public static function render() {
		$tab = sanitize_key($_GET['tab'] ?? '');
		if (!isset(self::TABS[$tab])) {
			$tab = Codegea_ERP_Settings::is_connected() ? 'pedidos' : 'conexion';
		}
		echo '<div class="wrap cerp">';
		self::header();
		echo '<hr class="wp-header-end">';
		if (isset($_GET['saved'])) {
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
		require CODEGEA_ERP_DIR . "admin/views/{$tab}.php";
		echo '</div><aside class="cerp-aside">';
		require CODEGEA_ERP_DIR . 'admin/views/help.php';
		echo '</aside></div></div>';
	}

	/** Título y estado de la conexión, visible en todas las pestañas. */
	private static function header() {
		$info = Codegea_ERP_Settings::erp_info();
		if (Codegea_ERP_Settings::is_connected()) {
			$pill = array('ok', 'yes-alt', 'Conectado' . (!empty($info['company']) ? ' con ' . $info['company'] : ''));
		} elseif (!Codegea_ERP_Settings::is_configured()) {
			$pill = array('off', 'marker', 'Sin configurar');
		} elseif (!empty($info['checked_at'])) {
			$pill = array('error', 'warning', 'Sin conexión');
		} else {
			$pill = array('off', 'marker', 'Conexión sin probar');
		}
		?>
		<div class="cerp-header">
			<div class="cerp-brand">
				<span class="cerp-logo dashicons dashicons-randomize" aria-hidden="true"></span>
				<div>
					<h1>Codegea ERP</h1>
					<p>Pedidos y stock de tu tienda, al día en el ERP.</p>
				</div>
			</div>
			<span class="cerp-pill cerp-pill--<?php echo esc_attr($pill[0]); ?>"><span class="dashicons dashicons-<?php echo esc_attr($pill[1]); ?>" aria-hidden="true"></span><?php echo esc_html($pill[2]); ?></span>
		</div>
		<?php
	}

	/** Abre el formulario de una pestaña (nonce + acción de guardado). */
	public static function form_open($tab, $action = 'codegea_erp_save') {
		printf('<form method="post" action="%s">', esc_url(admin_url('admin-post.php')));
		wp_nonce_field('codegea_erp');
		printf('<input type="hidden" name="action" value="%s"><input type="hidden" name="tab" value="%s"><input type="hidden" name="back" value="%s">', esc_attr($action), esc_attr($tab), esc_attr($tab));
	}

	/** Botón «Probar conexión» como formulario propio (se usa en Conexión y en Stock). */
	public static function ping_button($back, $label = 'Probar conexión', $class = 'button') {
		self::form_open($back, 'codegea_erp_ping');
		printf('<button type="submit" class="%s"><span class="dashicons dashicons-update" aria-hidden="true"></span> %s</button></form>', esc_attr($class), esc_html($label));
	}

	/** «hace 5 minutos» a partir de una fecha local de WordPress. */
	public static function ago($mysql_date) {
		$ts = strtotime((string) $mysql_date);
		return $ts ? sprintf('hace %s', human_time_diff($ts, current_time('timestamp'))) : '';
	}
}
