<?php

use PHPUnit\Framework\TestCase;

class RendererReferrerRulesTest extends TestCase {
	/**
	 * @return CMLC_Renderer
	 */
	private function renderer() {
		return new CMLC_Renderer();
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return bool
	 */
	private function passes_referrer_rules( $settings ) {
		$renderer = $this->renderer();
		$method   = new ReflectionMethod( CMLC_Renderer::class, 'passes_referrer_rules' );
		$method->setAccessible( true );

		return (bool) $method->invoke( $renderer, $settings );
	}

	/**
	 * @param string $referrer
	 * @param string $allowlist
	 */
	private function assertRuleResult( $referrer, $allowlist, $expected ) {
		$GLOBALS['cmlc_test_referrer'] = $referrer;
		$result                        = $this->passes_referrer_rules(
			array(
				'allowed_referrers' => $allowlist,
			)
		);

		$this->assertSame( $expected, $result );
	}

	public function test_exact_domain_does_not_allow_partial_contains() {
		$this->assertRuleResult( 'https://evil-example.com/path', 'example.com', false );
		$this->assertRuleResult( 'https://example.com.evil.tld/path', 'example.com', false );
	}

	public function test_exact_domain_match_only() {
		$this->assertRuleResult( 'https://example.com/path', 'example.com', true );
		$this->assertRuleResult( 'https://sub.example.com/path', 'example.com', false );
	}

	public function test_explicit_wildcard_allows_subdomains() {
		$this->assertRuleResult( 'https://blog.example.com/path', '*.example.com', true );
		$this->assertRuleResult( 'https://deep.blog.example.com/path', '*.example.com', true );
		$this->assertRuleResult( 'https://example.com/path', '*.example.com', true );
	}

	public function test_idn_normalization_when_available() {
		if ( ! function_exists( 'idn_to_ascii' ) ) {
			$this->markTestSkipped( 'idn_to_ascii is not available in this PHP environment.' );
		}

		$this->assertRuleResult( 'https://xn--bcher-kva.de/path', 'bücher.de', true );
	}
}
