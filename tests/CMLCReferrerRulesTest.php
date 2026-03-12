<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

require_once dirname( __DIR__ ) . '/includes/class-cmlc-renderer.php';

class CMLCReferrerRulesTest extends TestCase {
	public function test_normalize_allowed_referrer_domains() {
		$rules = CMLC_Renderer::normalize_allowed_referrer_domains( ' Example.COM , *.Sub.Example.com, , 例え.テスト ' );

		$this->assertContains( 'example.com', $rules );
		$this->assertContains( '*.sub.example.com', $rules );
		$this->assertNotContains( '', $rules );
	}

	public function test_exact_match_allows_only_exact_host() {
		$this->assertTrue( CMLC_Renderer::host_matches_referrer_rule( 'example.com', 'example.com' ) );
		$this->assertFalse( CMLC_Renderer::host_matches_referrer_rule( 'evil-example.com', 'example.com' ) );
		$this->assertFalse( CMLC_Renderer::host_matches_referrer_rule( 'example.com.evil.tld', 'example.com' ) );
		$this->assertFalse( CMLC_Renderer::host_matches_referrer_rule( 'sub.example.com', 'example.com' ) );
	}

	public function test_wildcard_only_matches_subdomains() {
		$this->assertTrue( CMLC_Renderer::host_matches_referrer_rule( 'sub.example.com', '*.example.com' ) );
		$this->assertTrue( CMLC_Renderer::host_matches_referrer_rule( 'deep.sub.example.com', '*.example.com' ) );
		$this->assertFalse( CMLC_Renderer::host_matches_referrer_rule( 'example.com', '*.example.com' ) );
		$this->assertFalse( CMLC_Renderer::host_matches_referrer_rule( 'evil-example.com', '*.example.com' ) );
	}
}
