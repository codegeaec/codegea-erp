<?php
if (!defined('ABSPATH')) {
	exit;
}
$s = Codegea_ERP_Settings::all();
$info = Codegea_ERP_Settings::erp_info();
$warehouses = isset($info['warehouses']) && is_array($info['warehouses']) ? $info['warehouses'] : array();
?>
<p>La tienda muestra el stock de <strong>una bodega del ERP</strong>. Los pedidos de la tienda la descuentan y,
	si lo activas, los cambios de stock hechos a mano aquí en WooCommerce también se pasan a esa bodega.</p>

<?php Codegea_ERP_Admin::form_open('stock'); ?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="warehouse_code">Bodega del ERP</label></th>
		<td>
			<?php if ($warehouses) : ?>
				<select id="warehouse_code" name="warehouse_code">
					<option value="">La que tenga configurada el ERP<?php echo !empty($info['stock_warehouse_code']) ? esc_html(' (' . $info['stock_warehouse_code'] . ')') : ''; ?></option>
					<?php foreach ($warehouses as $w) : ?>
						<option value="<?php echo esc_attr($w['code']); ?>" <?php selected($s['warehouse_code'], $w['code']); ?>>
							<?php echo esc_html($w['name'] . ' (' . $w['code'] . ')' . (!empty($w['is_main']) ? ' — principal' : '')); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input id="warehouse_code" name="warehouse_code" type="text" class="regular-text" value="<?php echo esc_attr($s['warehouse_code']); ?>" placeholder="Código de la bodega">
				<p class="description">Prueba la conexión (pestaña Conexión) para elegir la bodega de una lista.</p>
			<?php endif; ?>
			<p class="description">Vacío: la bodega que esté elegida en el ERP para la tienda online.</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Cambios de stock en la tienda</th>
		<td>
			<label><input type="checkbox" name="stock_to_erp" value="1" <?php checked($s['stock_to_erp'], 'yes'); ?>> Pasar al ERP los cambios de stock hechos a mano en WooCommerce</label>
			<p class="description">Útil si el inventario todavía se lleva en la tienda. Los descuentos por pedidos no se pasan (el ERP ya los descuenta al recibir el pedido).</p>
		</td>
	</tr>
</table>
<?php submit_button(); ?>
</form>
