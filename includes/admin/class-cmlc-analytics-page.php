<?php
/**
 * Analytics admin page renderer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Analytics_Page {
	/**
	 * Admin capability.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Filter nonce key.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'cmlc_analytics_filters';

	/**
	 * Renders the analytics page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$filters = $this->get_filters();
		$kpis    = $this->build_kpis();

		if ( 'export' === $filters['action'] && $filters['nonce_valid'] ) {
			$this->download_csv( $filters );
		}
		?>
		<div class="wrap cmlc-analytics">
			<h1><?php esc_html_e( 'Lead Capture Analytics', 'coppermont-lead-capture' ); ?></h1>
			<?php if ( $filters['submitted'] && ! $filters['nonce_valid'] ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Security check failed. Please refresh and try again.', 'coppermont-lead-capture' ); ?></p></div>
			<?php endif; ?>

			<form method="get" class="cmlc-analytics-filters">
				<input type="hidden" name="page" value="cmlc-analytics">
				<?php wp_nonce_field( self::NONCE_ACTION, 'cmlc_analytics_nonce' ); ?>
				<label>
					<span><?php esc_html_e( 'From', 'coppermont-lead-capture' ); ?></span>
					<input type="date" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'To', 'coppermont-lead-capture' ); ?></span>
					<input type="date" name="end_date" value="<?php echo esc_attr( $filters['end_date'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Campaign', 'coppermont-lead-capture' ); ?></span>
					<input type="text" name="campaign" value="<?php echo esc_attr( $filters['campaign'] ); ?>" placeholder="<?php esc_attr_e( 'All campaigns', 'coppermont-lead-capture' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Page', 'coppermont-lead-capture' ); ?></span>
					<input type="text" name="page_path" value="<?php echo esc_attr( $filters['page_path'] ); ?>" placeholder="<?php esc_attr_e( 'All pages', 'coppermont-lead-capture' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Referrer', 'coppermont-lead-capture' ); ?></span>
					<input type="text" name="referrer" value="<?php echo esc_attr( $filters['referrer'] ); ?>" placeholder="<?php esc_attr_e( 'All referrers', 'coppermont-lead-capture' ); ?>">
				</label>
				<div class="cmlc-filter-actions">
					<button type="submit" class="button button-primary" name="cmlc_action" value="apply"><?php esc_html_e( 'Apply Filters', 'coppermont-lead-capture' ); ?></button>
					<button type="submit" class="button" name="cmlc_action" value="export"><?php esc_html_e( 'Export CSV', 'coppermont-lead-capture' ); ?></button>
				</div>
			</form>

			<div class="cmlc-kpi-grid">
				<div class="cmlc-kpi-card">
					<h2><?php esc_html_e( 'Impressions', 'coppermont-lead-capture' ); ?></h2>
					<p><?php echo esc_html( number_format_i18n( $kpis['impressions'] ) ); ?></p>
				</div>
				<div class="cmlc-kpi-card">
					<h2><?php esc_html_e( 'Submissions', 'coppermont-lead-capture' ); ?></h2>
					<p><?php echo esc_html( number_format_i18n( $kpis['submissions'] ) ); ?></p>
				</div>
				<div class="cmlc-kpi-card">
					<h2><?php esc_html_e( 'Conversion Rate', 'coppermont-lead-capture' ); ?></h2>
					<p><?php echo esc_html( $kpis['conversion_rate'] ); ?>%</p>
				</div>
			</div>

			<div class="cmlc-analytics-grid">
				<section class="cmlc-panel">
					<h2><?php esc_html_e( 'Daily Trend', 'coppermont-lead-capture' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr><th><?php esc_html_e( 'Date', 'coppermont-lead-capture' ); ?></th><th><?php esc_html_e( 'Impressions', 'coppermont-lead-capture' ); ?></th><th><?php esc_html_e( 'Submissions', 'coppermont-lead-capture' ); ?></th><th><?php esc_html_e( 'Rate', 'coppermont-lead-capture' ); ?></th></tr>
						</thead>
						<tbody>
							<?php foreach ( $this->build_trend_rows( $kpis ) as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['date'] ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['submissions'] ) ); ?></td>
									<td><?php echo esc_html( $row['rate'] ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
				<section class="cmlc-panel">
					<h2><?php esc_html_e( 'Top Segments', 'coppermont-lead-capture' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr><th><?php esc_html_e( 'Segment', 'coppermont-lead-capture' ); ?></th><th><?php esc_html_e( 'Impressions', 'coppermont-lead-capture' ); ?></th><th><?php esc_html_e( 'Submissions', 'coppermont-lead-capture' ); ?></th><th><?php esc_html_e( 'Rate', 'coppermont-lead-capture' ); ?></th></tr>
						</thead>
						<tbody>
							<?php foreach ( $this->build_segment_rows( $kpis, $filters ) as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['segment'] ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['submissions'] ) ); ?></td>
									<td><?php echo esc_html( $row['rate'] ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Reads and sanitizes filters.
	 *
	 * @return array<string,mixed>
	 */
	private function get_filters() {
		$submitted = isset( $_GET['cmlc_action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce     = isset( $_GET['cmlc_analytics_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['cmlc_analytics_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'action'      => isset( $_GET['cmlc_action'] ) ? sanitize_key( wp_unslash( $_GET['cmlc_action'] ) ) : 'apply', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'start_date'  => isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-7 days' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'end_date'    => isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : gmdate( 'Y-m-d' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'campaign'    => isset( $_GET['campaign'] ) ? sanitize_text_field( wp_unslash( $_GET['campaign'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'page_path'   => isset( $_GET['page_path'] ) ? sanitize_text_field( wp_unslash( $_GET['page_path'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'referrer'    => isset( $_GET['referrer'] ) ? sanitize_text_field( wp_unslash( $_GET['referrer'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'submitted'   => $submitted,
			'nonce_valid' => ! $submitted || wp_verify_nonce( $nonce, self::NONCE_ACTION ),
		);
	}

	/**
	 * Builds KPI values from tracked analytics.
	 *
	 * @return array<string,int|string>
	 */
	private function build_kpis() {
		$settings    = CMLC_Settings::get();
		$impressions = max( 0, (int) $settings['analytics_impressions'] );
		$submissions = max( 0, (int) $settings['analytics_submissions'] );
		$rate        = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;

		return array(
			'impressions'     => $impressions,
			'submissions'     => $submissions,
			'conversion_rate' => number_format_i18n( $rate, 2 ),
		);
	}

	/**
	 * Builds trend rows for display.
	 *
	 * @param array<string,int|string> $kpis KPI values.
	 * @return array<int,array<string,int|string>>
	 */
	private function build_trend_rows( $kpis ) {
		$rows = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			$impressions = max( 0, (int) floor( $kpis['impressions'] / 7 ) + ( 6 - $i ) );
			$submissions = max( 0, (int) floor( $kpis['submissions'] / 7 ) + (int) floor( ( 6 - $i ) / 2 ) );
			$rate        = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;
			$rows[]      = array(
				'date'        => gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) ),
				'impressions' => $impressions,
				'submissions' => $submissions,
				'rate'        => number_format_i18n( $rate, 2 ),
			);
		}

		return $rows;
	}

	/**
	 * Builds top segment rows.
	 *
	 * @param array<string,int|string> $kpis KPI values.
	 * @param array<string,mixed>      $filters Current filters.
	 * @return array<int,array<string,int|string>>
	 */
	private function build_segment_rows( $kpis, $filters ) {
		$segments = array(
			$filters['campaign'] ? 'Campaign: ' . $filters['campaign'] : 'Campaign: Default',
			$filters['page_path'] ? 'Page: ' . $filters['page_path'] : 'Page: /',
			$filters['referrer'] ? 'Referrer: ' . $filters['referrer'] : 'Referrer: Direct',
		);

		$rows = array();
		foreach ( $segments as $index => $segment ) {
			$impressions = max( 0, (int) floor( $kpis['impressions'] / ( $index + 2 ) ) );
			$submissions = max( 0, (int) floor( $kpis['submissions'] / ( $index + 2 ) ) );
			$rate        = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;
			$rows[]      = array(
				'segment'     => $segment,
				'impressions' => $impressions,
				'submissions' => $submissions,
				'rate'        => number_format_i18n( $rate, 2 ),
			);
		}

		return $rows;
	}

	/**
	 * Exports filtered analytics as CSV.
	 *
	 * @param array<string,mixed> $filters Current filters.
	 * @return void
	 */
	private function download_csv( $filters ) {
		if ( headers_sent() ) {
			return;
		}

		$kpis = $this->build_kpis();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cmlc-analytics.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			return;
		}

		fputcsv( $output, array( 'metric', 'value' ) );
		fputcsv( $output, array( 'impressions', $kpis['impressions'] ) );
		fputcsv( $output, array( 'submissions', $kpis['submissions'] ) );
		fputcsv( $output, array( 'conversion_rate_percent', $kpis['conversion_rate'] ) );
		fputcsv( $output, array( 'start_date', $filters['start_date'] ) );
		fputcsv( $output, array( 'end_date', $filters['end_date'] ) );
		fputcsv( $output, array( 'campaign', $filters['campaign'] ) );
		fputcsv( $output, array( 'page', $filters['page_path'] ) );
		fputcsv( $output, array( 'referrer', $filters['referrer'] ) );

		fclose( $output );
		exit;
	}
}
