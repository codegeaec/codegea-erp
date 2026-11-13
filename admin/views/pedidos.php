<?php
if (!defined('ABSPATH')) {
	exit;
}
// Vista incluida desde Codegea_ERP_Admin::render(): sus variables son locales de ese método, no globales.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$s = Codegea_ERP_Settings::all();
$info = Codegea_ERP_Settings::erp_info();
?>
<?php if (!empty($info['orders_require_review'])) : ?>
	<div class="notice notice-warning inline cerp-notice">
		<p>En el ERP está activo <strong>«Revisar los pedidos antes de confirmarlos»</strong>: todos los pedidos llegan como borrador, sin importar lo que elijas aquí.</p>
	</div>
<?php endif; ?>

<?php Codegea_ERP_Admin::form_open('pedidos'); ?>
<section class="cerp-card">
	<h2>Qué hacer con un pedido pagado</h2>
	<div class="cerp-options">
		<?php foreach (Codegea_ERP_Settings::ORDER_MODES as $value => $mode) : ?>
			<label class="cerp-option">
				<input type="radio" name="order_mode" value="<?php echo esc_attr($value); ?>" <?php checked($s['order_mode'], $value); ?>>
				<span><strong><?php echo esc_html($mode['label']); ?></strong><small><?php echo esc_html($mode['help']); ?></small></span>
			</label>
		<?php endforeach; ?>
	</div>
</section>

<section class="cerp-card">
	<h2>¿En qué estado del pedido se manda al ERP?</h2>
	<p class="cerp-muted">El pedido se manda al ERP apenas llega a uno de los estados marcados. No se duplica: si pasa de
		<em>Procesando</em> a <em>Completado</em> no se vuelve a mandar, y si llegó <em>En espera</em> y luego se paga, el ERP confirma esa misma venta.</p>
	<div class="cerp-checks">
		<?php foreach (Codegea_ERP_Settings::ORDER_STATUSES as $value => $status) : ?>
			<label class="cerp-check">
				<input type="checkbox" name="order_statuses[]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, (array) $s['order_statuses'], true)); ?>>
				<span><strong><?php echo esc_html($status['label']); ?></strong> <small><?php echo esc_html($status['help']); ?></small></span>
			</label>
		<?php endforeach; ?>
	</div>
</section>
<p class="cerp-actions"><button type="submit" class="button button-primary">Guardar</button></p>
</form>
