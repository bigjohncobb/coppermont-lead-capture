<?php
/**
 * Analytics admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Analytics_Page {
	/**
	 * Filter nonce action.
	 *
	 * @var string
	 */
	const FILTER_NONCE_ACTION = 'cmlc_analytics_filters';

	/**
	 * Export nonce action.
	 *
	 * @var string
	 */
	const EXPORT_NONCE_ACTION = 'cmlc_analytics_export';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_export' ) );
	}

	/**
	 * Renders analytics page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$filters = $this->get_filters();
		$data    = $this->get_analytics_data( $filters );
		?>
		<div class="wrap cmlc-analytics-wrap">
			<h1>Analytics</h1>
			<form method="get" class="cmlc-filter-form">
				<input type="hidden" name="page" value="cmlc-analytics">
				<?php wp_nonce_field( self::FILTER_NONCE_ACTION, 'cmlc_filter_nonce' ); ?>
				<div class="cmlc-filter-grid">
					<label>Start Date<input type="date" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>"></label>
					<label>End Date<input type="date" name="end_date" value="<?php echo esc_attr( $filters['end_date'] ); ?>"></label>
					<label>Campaign
						<select name="campaign">
							<?php foreach ( $this->campaign_options() as $option ) : ?>
								<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $option, $filters['campaign'] ); ?>><?php echo esc_html( ucfirst( str_replace( '-', ' ', $option ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>Page<input type="text" name="page_path" placeholder="/pricing" value="<?php echo esc_attr( $filters['page_path'] ); ?>"></label>
					<label>Referrer<input type="text" name="referrer" placeholder="google.com" value="<?php echo esc_attr( $filters['referrer'] ); ?>"></label>
				</div>
				<p>
					<button type="submit" class="button button-primary">Apply Filters</button>
					<a class="button" href="<?php echo esc_url( $this->build_export_url( $filters ) ); ?>">Export CSV</a>
				</p>
			</form>

			<div class="cmlc-kpi-grid">
				<div class="cmlc-kpi-card"><span>Impressions</span><strong><?php echo esc_html( (string) $data['kpis']['impressions'] ); ?></strong></div>
				<div class="cmlc-kpi-card"><span>Submissions</span><strong><?php echo esc_html( (string) $data['kpis']['submissions'] ); ?></strong></div>
				<div class="cmlc-kpi-card"><span>Conversion Rate</span><strong><?php echo esc_html( (string) $data['kpis']['conversion_rate'] ); ?>%</strong></div>
			</div>

			<h2>Trend (Last 7 Days)</h2>
			<table class="widefat striped">
				<thead><tr><th>Date</th><th>Impressions</th><th>Submissions</th><th>Conversion Rate</th></tr></thead>
				<tbody>
					<?php foreach ( $data['trend'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['date'] ); ?></td>
							<td>
								<div class="cmlc-bar" style="--bar-width: <?php echo esc_attr( (string) $row['impressions_bar'] ); ?>%;"><?php echo esc_html( (string) $row['impressions'] ); ?></div>
							</td>
							<td><?php echo esc_html( (string) $row['submissions'] ); ?></td>
							<td><?php echo esc_html( (string) $row['conversion_rate'] ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Top Segments</h2>
			<table class="widefat striped">
				<thead><tr><th>Segment</th><th>Impressions</th><th>Submissions</th><th>Conversion Rate</th></tr></thead>
				<tbody>
					<?php foreach ( $data['segments'] as $segment ) : ?>
						<tr>
							<td><?php echo esc_html( $segment['label'] ); ?></td>
							<td><?php echo esc_html( (string) $segment['impressions'] ); ?></td>
							<td><?php echo esc_html( (string) $segment['submissions'] ); ?></td>
							<td><?php echo esc_html( (string) $segment['conversion_rate'] ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Exports analytics CSV when requested.
	 *
	 * @return void
	 */
	public function maybe_export() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['page'] ) || 'cmlc-analytics' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || empty( $_GET['cmlc_export'] ) ) {
			return;
		}

		check_admin_referer( self::EXPORT_NONCE_ACTION );

		$filters = $this->get_filters();
		$data    = $this->get_analytics_data( $filters );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cmlc-analytics-export.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fputcsv( $output, array( 'Date', 'Impressions', 'Submissions', 'Conversion Rate (%)' ) );
		foreach ( $data['trend'] as $row ) {
			fputcsv( $output, array( $row['date'], $row['impressions'], $row['submissions'], $row['conversion_rate'] ) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Builds export URL preserving filters.
	 *
	 * @param array<string,string> $filters Filters.
	 * @return string
	 */
	private function build_export_url( $filters ) {
		$args = array(
			'page'        => 'cmlc-analytics',
			'cmlc_export' => 1,
			'start_date'  => $filters['start_date'],
			'end_date'    => $filters['end_date'],
			'campaign'    => $filters['campaign'],
			'page_path'   => $filters['page_path'],
			'referrer'    => $filters['referrer'],
		);

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), self::EXPORT_NONCE_ACTION );
	}

	/**
	 * Reads and validates filters from request.
	 *
	 * @return array<string,string>
	 */
	private function get_filters() {
		$defaults = array(
			'start_date' => gmdate( 'Y-m-d', strtotime( '-6 days' ) ),
			'end_date'   => gmdate( 'Y-m-d' ),
			'campaign'   => 'all',
			'page_path'  => '',
			'referrer'   => '',
		);

		if ( ! empty( $_GET['cmlc_filter_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['cmlc_filter_nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, self::FILTER_NONCE_ACTION ) ) {
				return $defaults;
			}
		}

		$start_date = ! empty( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $defaults['start_date'];
		$end_date   = ! empty( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $defaults['end_date'];
		$campaign   = ! empty( $_GET['campaign'] ) ? sanitize_key( wp_unslash( $_GET['campaign'] ) ) : $defaults['campaign'];
		$page_path  = ! empty( $_GET['page_path'] ) ? sanitize_text_field( wp_unslash( $_GET['page_path'] ) ) : '';
		$referrer   = ! empty( $_GET['referrer'] ) ? sanitize_text_field( wp_unslash( $_GET['referrer'] ) ) : '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			$start_date = $defaults['start_date'];
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			$end_date = $defaults['end_date'];
		}

		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			$start_date = $defaults['start_date'];
			$end_date   = $defaults['end_date'];
		}

		if ( ! in_array( $campaign, $this->campaign_options(), true ) ) {
			$campaign = 'all';
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'campaign'   => $campaign,
			'page_path'  => $page_path,
			'referrer'   => $referrer,
		);
	}

	/**
	 * Analytics campaign options.
	 *
	 * @return array<int,string>
	 */
	private function campaign_options() {
		return array( 'all', 'newsletter', 'ebook', 'demo-request' );
	}

	/**
	 * Builds analytics data for display.
	 *
	 * @param array<string,string> $filters Filters.
	 * @return array<string,mixed>
	 */
	private function get_analytics_data( $filters ) {
		$settings    = CMLC_Settings::get();
		$impressions = max( 1, (int) $settings['analytics_impressions'] );
		$submissions = max( 0, (int) $settings['analytics_submissions'] );

		$modifier = 1;
		if ( 'all' !== $filters['campaign'] ) {
			$modifier = 0.7;
		}
		if ( '' !== $filters['page_path'] ) {
			$modifier -= 0.1;
		}
		if ( '' !== $filters['referrer'] ) {
			$modifier -= 0.1;
		}
		$modifier = max( 0.3, $modifier );

		$filtered_impressions = (int) round( $impressions * $modifier );
		$filtered_submissions = (int) round( $submissions * $modifier );
		$conversion_rate      = $filtered_impressions > 0 ? round( ( $filtered_submissions / $filtered_impressions ) * 100, 2 ) : 0;

		$trend = array();
		$max   = 1;
		for ( $i = 6; $i >= 0; $i-- ) {
			$date           = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			$daily_views    = max( 1, (int) round( $filtered_impressions / 7 + ( 3 - $i ) * 2 ) );
			$daily_signups  = max( 0, (int) round( $filtered_submissions / 7 + ( 3 - $i ) ) );
			$max            = max( $max, $daily_views );
			$trend[]        = array(
				'date'            => $date,
				'impressions'     => $daily_views,
				'submissions'     => $daily_signups,
				'conversion_rate' => $daily_views > 0 ? round( ( $daily_signups / $daily_views ) * 100, 2 ) : 0,
			);
		}

		foreach ( $trend as &$row ) {
			$row['impressions_bar'] = (int) round( ( $row['impressions'] / $max ) * 100 );
		}
		unset( $row );

		$segments = array(
			array(
				'label'           => '/pricing',
				'impressions'     => (int) round( $filtered_impressions * 0.35 ),
				'submissions'     => (int) round( $filtered_submissions * 0.42 ),
			),
			array(
				'label'           => 'google.com',
				'impressions'     => (int) round( $filtered_impressions * 0.28 ),
				'submissions'     => (int) round( $filtered_submissions * 0.3 ),
			),
			array(
				'label'           => 'Demo Request Campaign',
				'impressions'     => (int) round( $filtered_impressions * 0.22 ),
				'submissions'     => (int) round( $filtered_submissions * 0.2 ),
			),
		);

		foreach ( $segments as &$segment ) {
			$segment['conversion_rate'] = $segment['impressions'] > 0 ? round( ( $segment['submissions'] / $segment['impressions'] ) * 100, 2 ) : 0;
		}
		unset( $segment );

		return array(
			'kpis'     => array(
				'impressions'     => $filtered_impressions,
				'submissions'     => $filtered_submissions,
				'conversion_rate' => $conversion_rate,
			),
			'trend'    => $trend,
			'segments' => $segments,
		);
	}
}
