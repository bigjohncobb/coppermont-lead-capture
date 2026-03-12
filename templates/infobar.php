<?php
/**
 * Infobar template.
 *
 * @var array<string,mixed> $settings
 * @var string              $style
 */
?>
<div class="cmlc-infobar" data-cmlc-infobar style="<?php echo esc_attr( $style ); ?>" aria-hidden="true">
	<button type="button" class="cmlc-close" data-cmlc-close aria-label="Close infobar">×</button>
	<div class="cmlc-content">
		<h3 class="cmlc-headline"><?php echo esc_html( $settings['headline'] ); ?></h3>
		<p class="cmlc-body"><?php echo esc_html( $settings['body'] ); ?></p>
	</div>
	<form class="cmlc-form" data-cmlc-form>
		<input type="email" name="email" required placeholder="Email address" aria-label="Email address">
		<input type="text" name="cmlc_website" class="cmlc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
		<input type="hidden" name="cmlc_form_token" value="<?php echo esc_attr( CMLC_Ajax::create_form_timestamp_token() ); ?>">
		<input type="hidden" name="captcha_token" value="">
		<button type="submit"><?php echo esc_html( $settings['button_text'] ); ?></button>
	</form>
	<p class="cmlc-status" data-cmlc-status aria-live="polite"></p>
</div>
