<?php
/**
 * Base admin page class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CMLC_Admin_Page {
	/**
	 * Gets menu slug.
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * Gets page title.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Gets page description.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Gets contextual help content.
	 *
	 * @return string
	 */
	abstract public function get_help_content();

	/**
	 * Renders page-specific body content.
	 *
	 * @return void
	 */
	abstract public function render_content();
}
