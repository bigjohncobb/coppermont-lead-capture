<?php

define( 'ABSPATH', __DIR__ );

function add_action() {}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

$GLOBALS['cmlc_test_referrer'] = '';

function wp_get_referer() {
	return $GLOBALS['cmlc_test_referrer'];
}

require_once dirname( __DIR__ ) . '/includes/class-cmlc-renderer.php';
