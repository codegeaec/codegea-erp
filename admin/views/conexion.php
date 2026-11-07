<?php
if (!defined('ABSPATH')) {
	exit;
}
$s = Codegea_ERP_Settings::all();
$info = Codegea_ERP_Settings::erp_info();
?>
<p>La tienda le avisa al ERP de cada pedido y de los cambios de stock. Los datos salen del ERP:
	<strong>Configuración → Plugins → WordPress / WooCommerce → Pedidos</strong> (URL del webhook y secreto).</p>

<?php Codegea_ERP_Admin::form_open('conexion'); ?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="erp_url">URL del ERP</label></th>
		<td>
			<input id="erp_url" name="erp_url" type="url" class="regular-text" value="<?php echo esc_attr($s['erp_url']); ?>" placeholder="https://empresa.codegea.com">
			<p class="description">La dirección con la que entras al ERP. También sirve pegar la URL completa del webhook.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="secret">Secreto</label></th>
		<td>
			<input id="secret" name="secret" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $s['secret'] !== '' ? esc_attr('•••••••• (guardado)') : ''; ?>">
			<p class="description">Firma cada envío para que el ERP sepa que viene de tu tienda. Déjalo vacío para conservar el guardado.</p>
		</td>
	</tr>
</table>
<?php submit_button('Guardar y probar conexión'); ?>
</form>

<?php if (Codegea_ERP_Settings::is_connected()) : ?>
	<h2>Estado</h2>
	<?php if (!empty($info['company'])) : ?>
		<p>Conectado con <strong><?php echo esc_html($info['company']); ?></strong>
			<?php if (!empty($info['checked_at'])) : ?>(comprobado el <?php echo esc_html($info['checked_at']); ?>)<?php endif; ?>.</p>
		<?php if (!empty($info['orders_require_review'])) : ?>
			<div class="notice notice-info inline"><p>En el ERP está activo «Revisar los pedidos antes de confirmarlos»: todos los pedidos llegan como borrador, sin importar lo elegido en Pedidos.</p></div>
		<?php endif; ?>
	<?php else : ?>
		<p>Todavía no se probó la conexión.</p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('codegea_erp'); ?>
		<input type="hidden" name="action" value="codegea_erp_ping">
		<?php submit_button('Probar conexión', 'secondary', 'submit', false); ?>
	</form>
<?php endif; ?>
