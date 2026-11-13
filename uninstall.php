<?php
// Al borrar el plugin se van sus ajustes, el registro y lo que devolvió el ERP.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}
delete_option('nimbus_erp_settings');
delete_option('nimbus_erp_info');
delete_option('nimbus_erp_log');
