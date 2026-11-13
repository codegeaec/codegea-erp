<?php
if (!defined('ABSPATH')) {
	exit;
}
// Vista incluida desde Codegea_ERP_Admin::render(): sus variables son locales de ese método, no globales.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$entries = Codegea_ERP_Log::all();
?>
<section class="cerp-card">
	<div class="cerp-card-head">
		<h2>Últimos envíos al ERP</h2>
		<?php if ($entries) : ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('codegea_erp'); ?>
				<input type="hidden" name="action" value="codegea_erp_clear_log">
				<button type="submit" class="button-link cerp-link-danger">Vaciar registro</button>
			</form>
		<?php endif; ?>
	</div>
	<?php if (!$entries) : ?>
		<div class="cerp-empty">
			<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
			<p>Todavía no hay envíos. Aquí verás cada pedido, cambio de stock y suscripción al newsletter que se manda al ERP.</p>
		</div>
	<?php else : ?>
		<table class="widefat striped cerp-table">
			<thead><tr><th>Cuándo</th><th>Qué</th><th>Referencia</th><th>Resultado</th></tr></thead>
			<tbody>
			<?php foreach ($entries as $e) : ?>
				<tr>
					<td title="<?php echo esc_attr($e['time']); ?>"><?php echo esc_html(Codegea_ERP_Admin::ago($e['time'])); ?></td>
					<td><?php echo esc_html(ucfirst($e['type'])); ?></td>
					<td><?php echo esc_html($e['ref']); ?></td>
					<td>
						<span class="cerp-badge cerp-badge--<?php echo $e['ok'] ? 'ok' : 'error'; ?>"><?php echo $e['ok'] ? 'OK' : 'Error'; ?></span>
						<?php if ($e['message'] !== '') : ?><span class="cerp-muted"><?php echo esc_html($e['message']); ?></span><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
