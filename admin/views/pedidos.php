<?php
if (!defined('ABSPATH')) {
	exit;
}
$s = Codegea_ERP_Settings::all();
$statuses = array(
	'processing' => 'Procesando (pagado)',
	'completed' => 'Completado',
	'on-hold' => 'En espera (ej. transferencia por confirmar: llega al ERP como borrador)',
);
?>
<p>Cada pedido se envía una vez, cuando pasa a uno de los estados marcados. Si el ERP no responde, se reintenta
	solo; también se puede reenviar desde el pedido (Acciones del pedido → <em>Enviar al ERP</em>).</p>

<?php Codegea_ERP_Admin::form_open('pedidos'); ?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Qué hacer con un pedido pagado</th>
		<td>
			<fieldset>
				<?php foreach (Codegea_ERP_Settings::ORDER_MODES as $value => $label) : ?>
					<label><input type="radio" name="order_mode" value="<?php echo esc_attr($value); ?>" <?php checked($s['order_mode'], $value); ?>> <?php echo esc_html($label); ?></label><br>
				<?php endforeach; ?>
			</fieldset>
			<p class="description">Facturar requiere que el cliente tenga cédula/RUC y que el ERP tenga la facturación electrónica configurada; si falta algo, la venta queda confirmada y el aviso se ve en Ventas del ERP.</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Enviar al pasar a</th>
		<td>
			<fieldset>
				<?php foreach ($statuses as $value => $label) : ?>
					<label><input type="checkbox" name="order_statuses[]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, (array) $s['order_statuses'], true)); ?>> <?php echo esc_html($label); ?></label><br>
				<?php endforeach; ?>
			</fieldset>
		</td>
	</tr>
</table>
<?php submit_button(); ?>
</form>
