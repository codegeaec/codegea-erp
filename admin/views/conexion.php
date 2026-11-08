<?php
if (!defined('ABSPATH')) {
	exit;
}
/*
 * Una sola acción por estado, para no confundir:
 * - Sin configurar: el formulario con «Conectar con el ERP» (guarda y prueba).
 * - Configurado: arriba el estado con «Probar de nuevo»; los datos quedan plegados en «Cambiar datos de
 *   conexión» (abiertos si la conexión falló, porque lo más probable es que haya que corregirlos).
 */
$s = Codegea_ERP_Settings::all();
$info = Codegea_ERP_Settings::erp_info();
$configured = Codegea_ERP_Settings::is_configured();
$connected = Codegea_ERP_Settings::is_connected();
$tested = !empty($info['checked_at']);
?>
<?php if ($configured) : ?>
	<section class="cerp-card">
		<div class="cerp-card-head">
			<h2>Estado de la conexión</h2>
			<?php Codegea_ERP_Admin::ping_button('conexion', 'Probar de nuevo'); ?>
		</div>
		<?php if ($connected) : ?>
			<div class="notice notice-success inline cerp-notice">
				<p><strong>Conectado<?php echo !empty($info['company']) ? ' con ' . esc_html($info['company']) : ''; ?>.</strong>
					Los pedidos y el stock ya llegan al ERP.</p>
			</div>
			<dl class="cerp-facts">
				<?php if (!empty($info['tenant'])) : ?><div><dt>Empresa en el ERP</dt><dd><?php echo esc_html($info['tenant']); ?></dd></div><?php endif; ?>
				<div><dt>Bodega de la tienda</dt><dd><?php echo esc_html(Codegea_ERP_Settings::warehouse_label()); ?> · <a href="<?php echo esc_url(Codegea_ERP_Admin::url('stock')); ?>">cambiar</a></dd></div>
				<div><dt>Comprobado</dt><dd><?php echo esc_html(Codegea_ERP_Admin::ago($info['checked_at'])); ?></dd></div>
			</dl>
		<?php elseif ($tested) : ?>
			<div class="notice notice-error inline cerp-notice">
				<p><strong>No se pudo conectar.</strong> <?php echo esc_html($info['error'] ?? ''); ?></p>
			</div>
			<p class="cerp-muted">Último intento <?php echo esc_html(Codegea_ERP_Admin::ago($info['checked_at'])); ?>. Mientras tanto los pedidos no se pierden: se reintentan solos.</p>
		<?php else : ?>
			<p class="cerp-muted">Todavía no se probó la conexión.</p>
		<?php endif; ?>
	</section>
<?php else : ?>
	<div class="notice notice-info inline cerp-notice">
		<p><strong>Para conectar la tienda:</strong> en el ERP abre <em>Configuración → Plugins → WordPress / WooCommerce → Pedidos</em>,
			copia la <strong>URL del ERP</strong> y el <strong>secreto</strong> y pégalos aquí.</p>
	</div>
<?php endif; ?>

<?php if ($configured) : ?>
<details class="cerp-card cerp-details" <?php echo $connected ? '' : 'open'; ?>>
	<summary><h2>Cambiar datos de conexión</h2></summary>
<?php else : ?>
<section class="cerp-card">
	<h2>Datos de conexión</h2>
<?php endif; ?>
	<?php Codegea_ERP_Admin::form_open('conexion'); ?>
	<div class="cerp-field">
		<label for="erp_url">URL del ERP</label>
		<input id="erp_url" name="erp_url" type="url" class="regular-text" required value="<?php echo esc_attr($s['erp_url']); ?>" placeholder="https://empresa.codegea.com">
		<p class="description">La dirección con la que entras al ERP.</p>
	</div>
	<div class="cerp-field">
		<label for="secret">Secreto</label>
		<input id="secret" name="secret" type="password" class="regular-text" autocomplete="new-password" <?php echo $s['secret'] === '' ? 'required' : ''; ?>
			placeholder="<?php echo $s['secret'] !== '' ? esc_attr('Guardado — escribe uno nuevo solo para cambiarlo') : ''; ?>">
		<p class="description">Firma cada envío para que el ERP sepa que viene de tu tienda.</p>
	</div>
	<p class="cerp-actions">
		<button type="submit" class="button button-primary"><?php echo $configured ? 'Guardar cambios' : 'Conectar con el ERP'; ?></button>
	</p>
	<?php if ($configured) : ?><p class="description">Al guardar se vuelve a probar la conexión.</p><?php endif; ?>
	</form>
<?php echo $configured ? '</details>' : '</section>'; ?>
