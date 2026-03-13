<?php
/**
 * Campaigns admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Campaigns implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-campaigns'; }
	public function get_title() { return 'Lead Capture Campaigns'; }
	public function get_menu_title() { return 'Campaigns'; }
	public function is_default() { return false; }

	public function render() {
		$campaigns = get_posts( array(
			'post_type'      => CMLC_Campaigns::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 50,
			'meta_key'       => 'cmlc_priority',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		) );

		$add_new_url = admin_url( 'post-new.php?post_type=' . CMLC_Campaigns::POST_TYPE );
		?>
		<div class="cmlc-section" style="margin-top: 16px;">
			<p>
				<a href="<?php echo esc_url( $add_new_url ); ?>" class="button button-primary">Add New Campaign</a>
			</p>

			<?php if ( empty( $campaigns ) ) : ?>
				<p>No campaigns yet. Create your first campaign to start capturing leads.</p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top: 12px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campaign', 'coppermont-lead-capture' ); ?></th>
							<th><?php esc_html_e( 'Status', 'coppermont-lead-capture' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'coppermont-lead-capture' ); ?></th>
							<th><?php esc_html_e( 'Headline', 'coppermont-lead-capture' ); ?></th>
							<th><?php esc_html_e( 'Targeting', 'coppermont-lead-capture' ); ?></th>
							<th><?php esc_html_e( 'Schedule', 'coppermont-lead-capture' ); ?></th>
							<th><?php esc_html_e( 'Shortcode', 'coppermont-lead-capture' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $campaigns as $post ) :
							$campaign = CMLC_Campaigns::get_campaign( $post->ID );
							if ( ! $campaign ) {
								$campaign = CMLC_Campaigns::defaults();
							}
							$edit_url = get_edit_post_link( $post->ID );
							$status   = $campaign['status'] ?? 'inactive';

							// Targeting summary.
							$target_mode = $campaign['page_target_mode'] ?? 'all';
							$page_ids    = $campaign['page_ids'] ?? '';
							if ( 'all' === $target_mode ) {
								$targeting = 'All pages';
							} elseif ( 'include' === $target_mode ) {
								$targeting = 'Include: ' . ( $page_ids ?: 'none' );
							} else {
								$targeting = 'Exclude: ' . ( $page_ids ?: 'none' );
							}

							// Schedule summary.
							$sched_start = $campaign['schedule_start'] ?? '';
							$sched_end   = $campaign['schedule_end'] ?? '';
							if ( $sched_start || $sched_end ) {
								$schedule = ( $sched_start ?: 'now' ) . ' &mdash; ' . ( $sched_end ?: 'ongoing' );
							} else {
								$schedule = 'Always';
							}
						?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $post->post_title ); ?></a></strong>
								</td>
								<td>
									<?php if ( 'active' === $status ) : ?>
										<span style="color: #065f46; font-weight: 600;">Active</span>
									<?php else : ?>
										<span style="color: #6b7280;">Inactive</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $campaign['priority'] ); ?></td>
								<td><?php echo esc_html( $campaign['headline'] ); ?></td>
								<td><?php echo esc_html( $targeting ); ?></td>
								<td><?php echo wp_kses( $schedule, array( 'span' => array() ) ); ?></td>
								<td><code>[coppermont_infobar campaign_id="<?php echo esc_attr( $post->ID ); ?>"]</code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'campaigns',
				'title'   => 'Campaign setup',
				'content' => 'Create multiple campaigns with different headlines, targeting, and triggers. The highest-priority active campaign matching the current page will display automatically. Use the shortcode to place a specific campaign inline.',
			),
		);
	}
}
