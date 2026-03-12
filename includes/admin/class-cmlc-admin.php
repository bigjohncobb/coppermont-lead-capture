<?php
/**
 * Admin menu and page orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin {
	/**
	 * @var array<string,CMLC_Admin_Page>
	 */
	private $pages = array();

	/**
	 * @var array<string,string>
	 */
	private $hooks = array();

	/**
	 * @param array<int,CMLC_Admin_Page> $pages Page objects.
	 */
	public function __construct( $pages ) {
		foreach ( $pages as $page ) {
			$this->pages[ $page->get_slug() ] = $page;
		}

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Registers top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( empty( $this->pages ) ) {
			return;
		}

		$default = $this->get_default_page();
		if ( ! $default ) {
			return;
		}

		$default_slug = $default->get_slug();
		$hook         = add_menu_page(
			'Coppermont Lead Capture',
			'Lead Capture',
			'manage_options',
			$default_slug,
			array( $this, 'render_current_page' ),
			'dashicons-email-alt2',
			58
		);

		$this->hooks[ $default_slug ] = $hook;
		add_action( 'load-' . $hook, array( $this, 'register_help_tabs' ) );

		foreach ( $this->pages as $slug => $page ) {
			if ( $slug === $default_slug ) {
				add_submenu_page(
					$default_slug,
					$page->get_title(),
					$page->get_menu_title(),
					'manage_options',
					$slug,
					array( $this, 'render_current_page' )
				);
				continue;
			}

			$sub_hook = add_submenu_page(
				$default_slug,
				$page->get_title(),
				$page->get_menu_title(),
				'manage_options',
				$slug,
				array( $this, 'render_current_page' )
			);

			$this->hooks[ $slug ] = $sub_hook;
			add_action( 'load-' . $sub_hook, array( $this, 'register_help_tabs' ) );
		}
	}

	/**
	 * Renders current page.
	 *
	 * @return void
	 */
	public function render_current_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$page = isset( $this->pages[ $slug ] ) ? $this->pages[ $slug ] : $this->get_default_page();

		if ( ! $page ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $page->get_title() ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Manage all lead capture tools from the sections below.', 'coppermont-lead-capture' ) . '</p>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $this->pages as $item ) {
			$active = $item->get_slug() === $page->get_slug() ? ' nav-tab-active' : '';
			$url    = admin_url( 'admin.php?page=' . $item->get_slug() );
			echo '<a class="nav-tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $item->get_menu_title() ) . '</a>';
		}
		echo '</nav>';

		$page->render();
		echo '</div>';
	}

	/**
	 * Registers contextual help tabs.
	 *
	 * @return void
	 */
	public function register_help_tabs() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$page = isset( $this->pages[ $slug ] ) ? $this->pages[ $slug ] : null;

		if ( ! $page ) {
			return;
		}

		foreach ( $page->get_help_tabs() as $help_tab ) {
			$screen->add_help_tab(
				array(
					'id'      => sanitize_key( $page->get_slug() . '-' . $help_tab['id'] ),
					'title'   => $help_tab['title'],
					'content' => '<p>' . esc_html( $help_tab['content'] ) . '</p>',
				)
			);
		}
	}

	/**
	 * Retrieves default page.
	 *
	 * @return CMLC_Admin_Page|null
	 */
	private function get_default_page() {
		foreach ( $this->pages as $page ) {
			if ( $page->is_default() ) {
				return $page;
			}
		}

		return null;
	}
}
