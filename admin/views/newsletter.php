<?php
if (!defined('ABSPATH')) {
	exit;
}
$s = Codegea_ERP_Settings::all();
$info = Codegea_ERP_Settings::erp_info();
$known = Codegea_ERP_Settings::is_connected() && array_key_exists('newsletter', $info);
?>
<?php if (!Codegea_ERP_Settings::is_connected()) : ?>
	<div class="notice notice-warning inline cerp-notice">
		<p>Para sumar suscriptores, primero <a href="<?php echo esc_url(Codegea_ERP_Admin::url('conexion')); ?>">conecta la tienda con el ERP</a>.</p>
	</div>
<?php elseif ($known && empty($info['newsletter'])) : ?>
	<div class="notice notice-warning inline cerp-notice">
		<p>El plugin <strong>Newsletter</strong> no está activo en el ERP, así que la casilla del checkout y el formulario están ocultos en la tienda. Actívalo en el ERP (Configuración → Plugins) y prueba la conexión otra vez; si no, la tienda vuelve a consultarlo sola cada 12 horas.</p>
		<?php Codegea_ERP_Admin::ping_button('newsletter'); ?>
	</div>
<?php elseif (!$known) : ?>
	<div class="notice notice-info inline cerp-notice">
		<p>Prueba la conexión otra vez para saber si el newsletter está activo en el ERP.</p>
		<?php Codegea_ERP_Admin::ping_button('newsletter'); ?>
	</div>
<?php endif; ?>

<?php Codegea_ERP_Admin::form_open('newsletter'); ?>
<section class="cerp-card">
	<h2>Casilla en el checkout</h2>
	<label class="cerp-check">
		<input type="checkbox" name="newsletter_checkout" value="1" <?php checked($s['newsletter_checkout'], 'yes'); ?>>
		<span><strong>Ofrecer suscribirse al newsletter al comprar</strong>
			<small>Aparece sin marcar sobre el botón de pagar (checkout clásico y de bloques). Si el cliente la marca, el ERP le manda un correo para confirmar la suscripción.</small></span>
	</label>
	<div class="cerp-field">
		<label for="cerp-nl-label">Texto de la casilla</label>
		<input id="cerp-nl-label" type="text" class="regular-text" name="newsletter_label" value="<?php echo esc_attr($s['newsletter_label']); ?>" placeholder="<?php echo esc_attr(Codegea_ERP_Newsletter::DEFAULT_LABEL); ?>">
		<p class="description">Es lo que acepta el cliente: queda guardado en el ERP como su consentimiento. Se usa también en el formulario del shortcode.</p>
	</div>
</section>

<section class="cerp-card">
	<h2>Formulario de suscripción</h2>
	<p class="cerp-muted">Pega este shortcode en una página, una entrada o un widget para mostrar un formulario con correo, nombre y la casilla de aceptación:</p>
	<p><code>[codegea_erp_newsletter]</code></p>
	<p class="cerp-muted">Opciones: <code>button="Quiero novedades"</code> cambia el texto del botón y <code>name="no"</code> oculta el campo del nombre.</p>
</section>
<p class="cerp-actions"><button type="submit" class="button button-primary">Guardar</button></p>
</form>
