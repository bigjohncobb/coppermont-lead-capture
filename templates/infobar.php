<?php
/**
 * Infobar / lightbox template.
 *
 * @var array<string,mixed> $settings
 * @var string              $style
 * @var string              $display_mode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display_mode = $settings['display_mode'] ?? 'bottom_bar';
?>
<?php if ( 'lightbox' === $display_mode ) : ?>
<div class="cmlc-overlay" data-cmlc-overlay aria-hidden="true"></div>
<div class="cmlc-lightbox" data-cmlc-infobar data-display-mode="lightbox" data-campaign-id="<?php echo esc_attr( (string) $settings['id'] ); ?>" style="<?php echo esc_attr( $style ); ?>" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $settings['headline'] ); ?>">
	<button type="button" class="cmlc-close" data-cmlc-close aria-label="Close">&times;</button>
	<div class="cmlc-lightbox-inner">
		<h3 class="cmlc-headline"><?php echo esc_html( $settings['headline'] ); ?></h3>
		<p class="cmlc-body"><?php echo esc_html( $settings['body'] ); ?></p>
		<form class="cmlc-lightbox-form" data-cmlc-form>
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $settings['id'] ); ?>">
			<input type="email" name="email" required placeholder="Email address" aria-label="Email address">
			<input type="text" name="cmlc_website" class="cmlc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<input type="hidden" name="cmlc_form_token" value="<?php echo esc_attr( CMLC_Ajax::create_form_timestamp_token() ); ?>">
			<input type="hidden" name="captcha_token" value="">
			<?php if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] ) ) : ?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" data-action="cmlc_submit"></div>
				<input type="hidden" name="turnstile_token" value="">
			<?php endif; ?>
			<button type="submit"><?php echo esc_html( $settings['button_text'] ); ?></button>
		</form>
		<p class="cmlc-status" data-cmlc-status aria-live="polite"></p>
	</div>
</div>
<?php else : ?>
<div class="cmlc-infobar" data-cmlc-infobar data-display-mode="<?php echo esc_attr( $display_mode ); ?>" data-campaign-id="<?php echo esc_attr( (string) $settings['id'] ); ?>" style="<?php echo esc_attr( $style ); ?>" aria-hidden="true">
	<button type="button" class="cmlc-close" data-cmlc-close aria-label="Close infobar">&times;</button>
	<div class="cmlc-content">
		<h3 class="cmlc-headline"><?php echo esc_html( $settings['headline'] ); ?></h3>
		<p class="cmlc-body"><?php echo esc_html( $settings['body'] ); ?></p>
	</div>
	<form class="cmlc-form" data-cmlc-form>
		<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $settings['id'] ); ?>">
		<input type="email" name="email" required placeholder="Email address" aria-label="Email address">
		<input type="text" name="cmlc_website" class="cmlc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
		<input type="hidden" name="cmlc_form_token" value="<?php echo esc_attr( CMLC_Ajax::create_form_timestamp_token() ); ?>">
		<input type="hidden" name="captcha_token" value="">
		<?php if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] ) ) : ?>
			<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" data-action="cmlc_submit"></div>
			<input type="hidden" name="turnstile_token" value="">
		<?php endif; ?>
		<button type="submit"><?php echo esc_html( $settings['button_text'] ); ?></button>
	</form>
	<p class="cmlc-status" data-cmlc-status aria-live="polite"></p>
</div>
<?php endif; ?>
