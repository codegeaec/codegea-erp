<?php
if (!defined('ABSPATH')) {
	exit;
}
// Vista incluida desde Codegea_ERP_Admin::render(): sus variables son locales de ese método, no globales.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/** Ayuda breve de la pestaña actual (columna lateral). */
$help = array(
	'conexion' => array(
		'title' => '¿Cómo funciona?',
		'items' => array(
			'El ERP envía a la tienda productos, precios, fotos y stock.',
			'Este plugin le avisa al ERP de cada pedido y de los cambios de stock.',
			'Si el ERP no responde, los envíos se reintentan solos.',
		),
	),
	'pedidos' => array(
		'title' => 'Sobre los pedidos',
		'items' => array(
			'El IVA, los cupones y el envío llegan tal cual: la venta del ERP suma lo mismo que pagó el cliente.',
			'Un pedido se puede reenviar desde su pantalla: Acciones del pedido → Enviar al ERP.',
			'Reenviar es seguro: el ERP no duplica pedidos.',
		),
	),
	'stock' => array(
		'title' => 'Sobre el stock',
		'items' => array(
			'La tienda muestra el stock de una sola bodega del ERP.',
			'Las ventas, compras y ajustes de esa bodega en el ERP se reflejan aquí en menos de un minuto (si el ERP tiene activa la sincronización de stock).',
			'Lo que cambia el ERP no se le devuelve: no hay ciclos.',
		),
	),
	'newsletter' => array(
		'title' => 'Sobre el newsletter',
		'items' => array(
			'Quien se suscribe recibe un correo del ERP para confirmar: nadie queda suscrito sin confirmar su correo.',
			'Los contactos aparecen en el ERP, en Newsletter → Contactos, con origen «Tienda».',
			'Cada alta queda en la pestaña Registro.',
		),
	),
	'registro' => array(
		'title' => 'Sobre el registro',
		'items' => array(
			'Guarda los últimos 50 envíos.',
			'Un error con «HTTP 401» es un secreto que no coincide; revisa la pestaña Conexión.',
		),
	),
);
$current = $help[$tab] ?? $help['conexion'];
?>
<div class="cerp-card cerp-help">
	<h3><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> <?php echo esc_html($current['title']); ?></h3>
	<ul>
		<?php foreach ($current['items'] as $item) : ?>
			<li><?php echo esc_html($item); ?></li>
		<?php endforeach; ?>
	</ul>
</div>
