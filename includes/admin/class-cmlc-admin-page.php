<?php
/**
 * Admin page contract.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface CMLC_Admin_Page {
	/**
	 * Page slug.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Page title.
	 *
	 * @return string
	 */
	public function get_title();

	/**
	 * Menu title.
	 *
	 * @return string
	 */
	public function get_menu_title();

	/**
	 * Whether this page is the default menu page.
	 *
	 * @return bool
	 */
	public function is_default();

	/**
	 * Renders page content.
	 *
	 * @return void
	 */
	public function render();

	/**
	 * Help tabs config.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_help_tabs();
}
