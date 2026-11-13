<?php
/**
 * Plugin Name: Nimbus ERP for WooCommerce
 * Plugin URI: https://github.com/codegeaec/nimbus-erp-for-woocommerce
 * Description: Conecta WooCommerce con Nimbus ERP: envía los pedidos (borrador, venta confirmada o facturada) mantiene el stock alineado con la bodega del ERP y suma suscriptores a su newsletter.
 * Version: 1.2.1
 * Author: Codegea
 * Author URI: https://codegea.com
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * Text Domain: nimbus-erp-for-woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

define('NIMBUS_ERP_VERSION', '1.2.1');
define('NIMBUS_ERP_FILE', __FILE__);
define('NIMBUS_ERP_DIR', plugin_dir_path(__FILE__));

require_once NIMBUS_ERP_DIR . 'includes/class-nimbus-erp-settings.php';
require_once NIMBUS_ERP_DIR . 'includes/class-nimbus-erp-log.php';
require_once NIMBUS_ERP_DIR . 'includes/class-nimbus-erp-client.php';
require_once NIMBUS_ERP_DIR . 'includes/class-nimbus-erp-orders.php';
require_once NIMBUS_ERP_DIR . 'includes/class-nimbus-erp-stock.php';
require_once NIMBUS_ERP_DIR . 'includes/class-nimbus-erp-newsletter.php';

// Compatible con el almacenamiento de pedidos de alto rendimiento (HPOS) de WooCommerce.
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

/**
 * Migración desde «Codegea ERP» (nombre anterior a 1.2.0): copia su conexión, la info del ERP y el registro a las
 * opciones nuevas una sola vez, si todavía no existen. El plugin viejo puede desinstalarse después sin perder nada.
 */
function nimbus_erp_migrate_from_codegea() {
	foreach (array('settings', 'info', 'log') as $key) {
		$old = get_option('codegea_erp_' . $key);
		if (false !== $old && false === get_option('nimbus_erp_' . $key)) {
			add_option('nimbus_erp_' . $key, $old, '', false);
		}
	}
}

add_action('plugins_loaded', function () {
	nimbus_erp_migrate_from_codegea();
	if (!class_exists('WooCommerce')) {
		return;
	}
	Nimbus_ERP_Orders::init();
	Nimbus_ERP_Stock::init();
	Nimbus_ERP_Newsletter::init();
	if (is_admin()) {
		require_once NIMBUS_ERP_DIR . 'admin/class-nimbus-erp-admin.php';
		Nimbus_ERP_Admin::init();
	}
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=nimbus-erp-for-woocommerce')) . '">' . esc_html__('Ajustes', 'nimbus-erp-for-woocommerce') . '</a>');
	return $links;
});
