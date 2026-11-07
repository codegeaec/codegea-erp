<?php
if (!defined('ABSPATH')) {
	exit;
}
$entries = Codegea_ERP_Log::all();
?>
<p>Los últimos <?php echo (int) Codegea_ERP_Log::MAX; ?> envíos al ERP.</p>
<?php if (!$entries) : ?>
	<p><em>Todavía no hay envíos.</em></p>
<?php else : ?>
	<table class="widefat striped">
		<thead><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Resultado</th><th>Detalle</th></tr></thead>
		<tbody>
		<?php foreach ($entries as $e) : ?>
			<tr>
				<td><?php echo esc_html($e['time']); ?></td>
				<td><?php echo esc_html($e['type']); ?></td>
				<td><?php echo esc_html($e['ref']); ?></td>
				<td><?php echo $e['ok'] ? '<span style="color:#008a20">OK</span>' : '<span style="color:#d63638">Error</span>'; ?></td>
				<td><?php echo esc_html($e['message']); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1em">
		<?php wp_nonce_field('codegea_erp'); ?>
		<input type="hidden" name="action" value="codegea_erp_clear_log">
		<?php submit_button('Vaciar registro', 'secondary', 'submit', false); ?>
	</form>
<?php endif; ?>
