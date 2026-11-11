<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Newsletter del ERP: casilla «Quiero recibir novedades» en el checkout (clásico y de bloques) y el shortcode
 * [codegea_erp_newsletter] con un formulario de suscripción. Las dos llaman al ERP firmadas
 * (`/webhook/newsletter`) con la IP del cliente; el ERP manda el correo de confirmación (doble opt-in), así
 * que nadie queda suscrito sin confirmar su correo.
 */
final class Codegea_ERP_Newsletter {
	const ACTION = 'codegea_erp_newsletter_subscribe';
	const FIELD = 'codegea_erp_newsletter';
	/** Id del campo del checkout de bloques (Additional Checkout Fields API, WooCommerce 8.9+). */
	const BLOCK_FIELD = 'codegea-erp/newsletter';
	const DONE_META = '_codegea_erp_newsletter';
	/** Vuelve a preguntar al ERP si el newsletter está activo (Action Scheduler). */
	const RECHECK_ACTION = 'codegea_erp_newsletter_recheck';
	const RETRY_MINUTES = array(1, 5, 15);
	const DEFAULT_LABEL = 'Quiero recibir novedades y ofertas por correo. Puedo darme de baja cuando quiera.';

	public static function init() {
		add_action(self::ACTION, array(__CLASS__, 'send'), 10, 2);
		add_shortcode('codegea_erp_newsletter', array(__CLASS__, 'shortcode'));
		add_action('admin_post_nopriv_codegea_erp_newsletter', array(__CLASS__, 'form_submit'));
		add_action('admin_post_codegea_erp_newsletter', array(__CLASS__, 'form_submit'));
		add_action(self::RECHECK_ACTION, array('Codegea_ERP_Client', 'ping'));
		if (Codegea_ERP_Settings::get('newsletter_checkout') !== 'yes' || !self::available()) {
			return;
		}
		// Checkout clásico.
		add_action('woocommerce_review_order_before_submit', array(__CLASS__, 'checkout_field'));
		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'on_classic_checkout'), 10, 3);
		// Checkout de bloques.
		add_action('woocommerce_init', array(__CLASS__, 'register_block_field'));
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'on_block_checkout'));
	}

	/**
	 * Conectada y con el plugin Newsletter activo en el ERP (lo dice «Probar conexión»; si todavía no se sabe,
	 * se ofrece). Apagado en el ERP, la casilla y el formulario no se muestran; cada 12 horas se vuelve a
	 * consultar en segundo plano por si lo activaron.
	 */
	public static function available() {
		if (!Codegea_ERP_Settings::is_connected()) {
			return false;
		}
		$info = Codegea_ERP_Settings::erp_info();
		if (!array_key_exists('newsletter', $info) || !empty($info['newsletter'])) {
			return true;
		}
		$stale = strtotime((string) ($info['checked_at'] ?? '')) < current_time('timestamp') - 12 * HOUR_IN_SECONDS;
		if ($stale && function_exists('as_enqueue_async_action') && !get_transient(self::RECHECK_ACTION)) {
			set_transient(self::RECHECK_ACTION, 1, HOUR_IN_SECONDS);
			as_enqueue_async_action(self::RECHECK_ACTION, array(), 'codegea-erp');
		}
		return false;
	}

	/** El ERP dice en cada alta si el newsletter sigue activo: se guarda para ocultar o volver a mostrar. */
	private static function remember_available($active) {
		$info = Codegea_ERP_Settings::erp_info();
		if (!$info || ($info['newsletter'] ?? null) === $active) {
			return;
		}
		$info['newsletter'] = $active;
		update_option(Codegea_ERP_Settings::ERP_INFO, $info, false);
	}

	public static function label() {
		$label = trim((string) Codegea_ERP_Settings::get('newsletter_label'));
		return $label !== '' ? $label : self::DEFAULT_LABEL;
	}

	// ── Checkout ──

	public static function checkout_field() {
		// Sin marcar: el consentimiento tiene que ser un acto del cliente.
		printf(
			'<p class="form-row codegea-erp-newsletter"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox"><input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="%s" value="1"> <span>%s</span></label></p>',
			esc_attr(self::FIELD),
			esc_html(self::label())
		);
	}

	public static function on_classic_checkout($order_id, $posted, $order) {
		if (!empty($_POST[self::FIELD])) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce ya validó el nonce del checkout.
			self::from_order($order instanceof WC_Order ? $order : wc_get_order($order_id));
		}
	}

	public static function register_block_field() {
		if (!function_exists('woocommerce_register_additional_checkout_field')) {
			return;
		}
		woocommerce_register_additional_checkout_field(array(
			'id' => self::BLOCK_FIELD,
			'label' => self::label(),
			'location' => 'order',
			'type' => 'checkbox',
		));
	}

	public static function on_block_checkout($order) {
		$checked = $order->get_meta('_wc_other/' . self::BLOCK_FIELD) ?: $order->get_meta('_wc_additional/' . self::BLOCK_FIELD);
		if ($checked && $checked !== 'false') {
			self::from_order($order);
		}
	}

	private static function from_order($order) {
		if (!$order || $order->get_meta(self::DONE_META)) {
			return;
		}
		$order->update_meta_data(self::DONE_META, current_time('mysql'));
		$order->save();
		self::enqueue(array(
			'email' => $order->get_billing_email(),
			'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
			'consent' => true,
			'consent_text' => self::label(),
			'ip' => $order->get_customer_ip_address(),
		), 0, 0);
	}

	/** En segundo plano: el checkout no espera al ERP y, si no responde, se reintenta. */
	private static function enqueue(array $data, $attempt, $delay_minutes) {
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action(time() + $delay_minutes * MINUTE_IN_SECONDS, self::ACTION, array('data' => $data, 'attempt' => (int) $attempt), 'codegea-erp');
		} else {
			self::send($data, $attempt);
		}
	}

	public static function send($data, $attempt = 0) {
		$result = self::post((array) $data);
		if ($result['ok'] || $result['code'] !== 0 && $result['code'] < 500) {
			return; // Respondió (bien o con un rechazo que no cambia al reintentar).
		}
		if ($attempt < count(self::RETRY_MINUTES)) {
			self::enqueue((array) $data, $attempt + 1, self::RETRY_MINUTES[$attempt]);
		}
	}

	/** @return array{ok:bool,code:int,message:string} */
	private static function post(array $data) {
		$result = Codegea_ERP_Client::post('newsletter', $data, 15);
		$body = is_array($result['body']) ? $result['body'] : array();
		$ok = $result['ok'] && !empty($body['ok']);
		if ($result['ok'] && array_key_exists('newsletter', $body)) {
			self::remember_available((bool) $body['newsletter']);
		}
		$message = $result['ok'] ? (string) ($body['message'] ?? '') : $result['error'];
		Codegea_ERP_Log::add('newsletter', (string) ($data['email'] ?? ''), $ok, $message);
		return array('ok' => $ok, 'code' => (int) $result['code'], 'message' => $message);
	}

	// ── Shortcode ──

	/** [codegea_erp_newsletter button="Suscribirme" name="yes"] */
	public static function shortcode($atts) {
		if (!self::available()) {
			return ''; // Newsletter apagado en el ERP: no se muestra un formulario que no funcionaría.
		}
		$atts = shortcode_atts(array('button' => 'Suscribirme', 'name' => 'yes'), $atts, 'codegea_erp_newsletter');
		wp_enqueue_style('codegea-erp-newsletter', plugins_url('public/css/newsletter.css', CODEGEA_ERP_FILE), array(), CODEGEA_ERP_VERSION);
		$notice = '';
		$key = isset($_GET['cerp_nl']) ? sanitize_key(wp_unslash($_GET['cerp_nl'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$result = $key !== '' ? get_transient('cerp_nl_' . $key) : false;
		if (is_array($result)) {
			$notice = sprintf('<p class="cerp-nl-notice cerp-nl-notice--%s" role="status">%s</p>', $result['ok'] ? 'ok' : 'error', esc_html($result['message']));
		}
		ob_start();
		?>
		<form id="cerp-nl" class="cerp-nl" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- escapado arriba. ?>
			<input type="hidden" name="action" value="codegea_erp_newsletter">
			<input type="hidden" name="back" value="<?php echo esc_url(remove_query_arg('cerp_nl')); ?>">
			<input type="email" name="email" required placeholder="tu@correo.com" aria-label="Correo">
			<?php if ($atts['name'] !== 'no') : ?>
				<input type="text" name="name" placeholder="Tu nombre (opcional)" aria-label="Nombre">
			<?php endif; ?>
			<input type="text" name="website" tabindex="-1" autocomplete="off" class="cerp-nl-trap" aria-hidden="true">
			<label class="cerp-nl-consent"><input type="checkbox" name="consent" value="1" required> <span><?php echo esc_html(self::label()); ?></span></label>
			<button type="submit"><?php echo esc_html($atts['button']); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Envío del formulario del shortcode: se llama al ERP en el momento para mostrar su respuesta. Sin nonce a
	 * propósito (las páginas en caché lo vencerían): frenan a los bots la trampa `website` y el tope por IP del ERP.
	 */
	public static function form_submit() {
		$in = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification
		$back = wp_validate_redirect(esc_url_raw($in['back'] ?? ''), home_url('/'));
		if (!empty($in['website'])) {
			$result = array('ok' => true, 'message' => 'Te enviamos un correo para confirmar la suscripción.');
		} elseif (!Codegea_ERP_Settings::is_configured()) {
			$result = array('ok' => false, 'message' => 'La suscripción no está disponible en este momento.');
		} else {
			$result = self::post(array(
				'email' => sanitize_email($in['email'] ?? ''),
				'name' => sanitize_text_field($in['name'] ?? ''),
				'consent' => !empty($in['consent']),
				'consent_text' => self::label(),
				'ip' => class_exists('WC_Geolocation') ? WC_Geolocation::get_ip_address() : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
			));
			if (!$result['ok'] && ($result['code'] === 0 || $result['code'] >= 500 || $result['message'] === '')) {
				$result['message'] = 'No pudimos registrar tu suscripción. Intenta de nuevo más tarde.';
			}
		}
		$key = strtolower(wp_generate_password(12, false));
		set_transient('cerp_nl_' . $key, array('ok' => $result['ok'], 'message' => $result['message']), 10 * MINUTE_IN_SECONDS);
		wp_safe_redirect(add_query_arg('cerp_nl', $key, $back) . '#cerp-nl');
		exit;
	}
}
