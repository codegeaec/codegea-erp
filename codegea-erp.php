<?php
/**
 * Plugin Name: Codegea ERP
 * Plugin URI: https://codegea.com
 * Description: Conecta WooCommerce con el ERP de Codegea: envía los pedidos (borrador, venta confirmada o facturada) y mantiene el stock alineado con la bodega del ERP.
 * Version: 1.0.0
 * Author: Codegea
 * Author URI: https://codegea.com
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * Text Domain: codegea-erp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

define('CODEGEA_ERP_VERSION', '1.0.0');
define('CODEGEA_ERP_FILE', __FILE__);
define('CODEGEA_ERP_DIR', plugin_dir_path(__FILE__));

require_once CODEGEA_ERP_DIR . 'includes/class-codegea-erp-settings.php';
require_once CODEGEA_ERP_DIR . 'includes/class-codegea-erp-log.php';
require_once CODEGEA_ERP_DIR . 'includes/class-codegea-erp-client.php';
require_once CODEGEA_ERP_DIR . 'includes/class-codegea-erp-orders.php';
require_once CODEGEA_ERP_DIR . 'includes/class-codegea-erp-stock.php';

// Compatible con el almacenamiento de pedidos de alto rendimiento (HPOS) de WooCommerce.
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

add_action('plugins_loaded', function () {
	if (!class_exists('WooCommerce')) {
		return;
	}
	Codegea_ERP_Orders::init();
	Codegea_ERP_Stock::init();
	if (is_admin()) {
		require_once CODEGEA_ERP_DIR . 'admin/class-codegea-erp-admin.php';
		Codegea_ERP_Admin::init();
	}
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=codegea-erp')) . '">' . esc_html__('Ajustes', 'codegea-erp') . '</a>');
	return $links;
});
