<?php
if (!defined('ABSPATH')) {
	exit;
}
$s = Codegea_ERP_Settings::all();
$info = Codegea_ERP_Settings::erp_info();
$warehouses = Codegea_ERP_Settings::warehouses();
$erp_default = null;
foreach ($warehouses as $w) {
	if ((int) $w['id'] === (int) ($info['stock_warehouse_id'] ?? 0)) {
		$erp_default = $w;
	}
}
?>
<?php if (!Codegea_ERP_Settings::is_connected()) : ?>
	<div class="notice notice-warning inline cerp-notice">
		<p>Para elegir la bodega, primero <a href="<?php echo esc_url(Codegea_ERP_Admin::url('conexion')); ?>">conecta la tienda con el ERP</a>: las bodegas se listan desde allá.</p>
	</div>
<?php endif; ?>

<?php Codegea_ERP_Admin::form_open('stock'); ?>
<section class="cerp-card">
	<div class="cerp-card-head">
		<h2>Bodega de la tienda</h2>
	</div>
	<p class="cerp-muted">Los pedidos de la tienda descuentan el stock de esta bodega del ERP.</p>
	<?php if ($warehouses) : ?>
		<div class="cerp-options">
			<label class="cerp-option">
				<input type="radio" name="warehouse_id" value="0" <?php checked((int) $s['warehouse_id'], 0); ?>>
				<span><strong>La que elija el ERP</strong>
					<small><?php echo $erp_default ? esc_html('Hoy: ' . $erp_default['name']) : 'Hoy: la bodega por defecto de Ventas.'; ?></small></span>
			</label>
			<?php foreach ($warehouses as $w) : ?>
				<label class="cerp-option">
					<input type="radio" name="warehouse_id" value="<?php echo (int) $w['id']; ?>" <?php checked((int) $s['warehouse_id'], (int) $w['id']); ?>>
					<span>
						<strong><?php echo esc_html($w['name']); ?><?php if (!empty($w['is_main'])) : ?> <em class="cerp-tag">Principal</em><?php endif; ?></strong>
						<small>Código <?php echo esc_html($w['code']); ?> · ID <?php echo (int) $w['id']; ?></small>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="cerp-muted">Lista actualizada <?php echo esc_html(Codegea_ERP_Admin::ago($info['checked_at'] ?? '')); ?>. ¿Creaste una bodega nueva en el ERP? Actualiza la lista abajo.</p>
	<?php elseif (Codegea_ERP_Settings::is_connected()) : ?>
		<div class="notice notice-info inline cerp-notice"><p>El ERP todavía no tiene bodegas. Créalas en el ERP (Inventario → Bodegas) y actualiza la lista.</p></div>
	<?php endif; ?>
</section>

<section class="cerp-card">
	<h2>Cambios de stock hechos en la tienda</h2>
	<label class="cerp-check">
		<input type="checkbox" name="stock_to_erp" value="1" <?php checked($s['stock_to_erp'], 'yes'); ?>>
		<span><strong>Pasar al ERP los cambios de stock hechos a mano en WooCommerce</strong>
			<small>Útil mientras el inventario se siga llevando en la tienda. Los descuentos por pedidos no se pasan: el ERP ya los descuenta al recibir el pedido.</small></span>
	</label>
</section>
<p class="cerp-actions"><button type="submit" class="button button-primary">Guardar</button></p>
</form>

<?php if (Codegea_ERP_Settings::is_configured()) : ?>
	<div class="cerp-inline-action"><?php Codegea_ERP_Admin::ping_button('stock', 'Actualizar lista de bodegas'); ?></div>
<?php endif; ?>
