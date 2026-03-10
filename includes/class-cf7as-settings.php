<?php
/**
 * Settings page and options management.
 *
 * @package CF7_Anti_Spam_Shield
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the admin settings page and option retrieval.
 */
class CF7AS_Settings {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cf7as_options';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_cf7as_clear_log', array( __CLASS__, 'handle_clear_log' ) );
	}

	/**
	 * Get default option values.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'min_time'         => 3,
			'max_urls'         => 2,
			'rate_limit'       => 5,
			'block_cyrillic'   => false,
			'enable_logging'   => true,
			'disable_submit'   => false,
			'disallowed_words' => '',
		);
	}

	/**
	 * Get a single option value.
	 *
	 * @param string $key      Option key.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$defaults = self::get_defaults();
		$options  = get_option( self::OPTION_KEY, $defaults );

		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $fallback;
	}

	/**
	 * Register the settings page under Settings menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'CF7 Anti-Spam Shield', 'cf7-anti-spam-shield' ),
			__( 'CF7 Anti-Spam', 'cf7-anti-spam-shield' ),
			'manage_options',
			'cf7-anti-spam-shield',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register setting.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'cf7as_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize options on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults  = self::get_defaults();
		$sanitized = array();

		$sanitized['min_time']         = isset( $input['min_time'] ) ? absint( $input['min_time'] ) : $defaults['min_time'];
		$sanitized['max_urls']         = isset( $input['max_urls'] ) ? absint( $input['max_urls'] ) : $defaults['max_urls'];
		$sanitized['rate_limit']       = isset( $input['rate_limit'] ) ? absint( $input['rate_limit'] ) : $defaults['rate_limit'];
		$sanitized['block_cyrillic']   = ! empty( $input['block_cyrillic'] );
		$sanitized['disable_submit']   = ! empty( $input['disable_submit'] );
		$sanitized['enable_logging']   = ! empty( $input['enable_logging'] );
		$sanitized['disallowed_words'] = isset( $input['disallowed_words'] ) ? sanitize_textarea_field( $input['disallowed_words'] ) : '';

		// Clamp values to reasonable ranges.
		$sanitized['min_time']   = max( 1, min( 30, $sanitized['min_time'] ) );
		$sanitized['max_urls']   = max( 0, min( 20, $sanitized['max_urls'] ) );
		$sanitized['rate_limit'] = max( 1, min( 100, $sanitized['rate_limit'] ) );

		return $sanitized;
	}

	/**
	 * Handle clearing the spam log.
	 *
	 * @return void
	 */
	public static function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cf7-anti-spam-shield' ) );
		}

		check_admin_referer( 'cf7as_clear_log' );

		CF7AS_Logger::clear_log();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'cf7-anti-spam-shield',
					'tab'         => 'log',
					'log_cleared' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		$options = get_option( self::OPTION_KEY, self::get_defaults() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF7 Anti-Spam Shield', 'cf7-anti-spam-shield' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cf7-anti-spam-shield&tab=settings' ) ); ?>"
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'cf7-anti-spam-shield' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cf7-anti-spam-shield&tab=log' ) ); ?>"
					class="nav-tab <?php echo 'log' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Spam Log', 'cf7-anti-spam-shield' ); ?>
				</a>
			</nav>

			<?php
			if ( 'log' === $active_tab ) {
				self::render_log_tab();
			} else {
				self::render_settings_tab( $options );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab.
	 *
	 * @param array $options Current options.
	 * @return void
	 */
	private static function render_settings_tab( $options ) {
		$defaults = self::get_defaults();
		$stats    = CF7AS_Logger::get_stats();
		?>

		<?php if ( $stats['total'] > 0 ) : ?>
		<div class="notice notice-info">
			<p>
				<?php
				printf(
					/* translators: 1: total blocked, 2: blocked today */
					esc_html__( 'Total spam blocked: %1$d | Blocked today: %2$d', 'cf7-anti-spam-shield' ),
					(int) $stats['total'],
					(int) $stats['today']
				);
				?>
			</p>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'cf7as_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Minimum submit time (seconds)', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<input type="number" name="cf7as_options[min_time]"
							value="<?php echo esc_attr( isset( $options['min_time'] ) ? $options['min_time'] : $defaults['min_time'] ); ?>"
							min="1" max="30" class="small-text" />
						<p class="description"><?php esc_html_e( 'Reject submissions faster than this. Bots typically submit in under 1 second.', 'cf7-anti-spam-shield' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max URLs allowed in message', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<input type="number" name="cf7as_options[max_urls]"
							value="<?php echo esc_attr( isset( $options['max_urls'] ) ? $options['max_urls'] : $defaults['max_urls'] ); ?>"
							min="0" max="20" class="small-text" />
						<p class="description"><?php esc_html_e( 'Reject messages containing more than this many URLs.', 'cf7-anti-spam-shield' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rate limit (per IP per hour)', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<input type="number" name="cf7as_options[rate_limit]"
							value="<?php echo esc_attr( isset( $options['rate_limit'] ) ? $options['rate_limit'] : $defaults['rate_limit'] ); ?>"
							min="1" max="100" class="small-text" />
						<p class="description"><?php esc_html_e( 'Block IPs exceeding this many form submissions per hour.', 'cf7-anti-spam-shield' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Block Cyrillic text', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cf7as_options[block_cyrillic]" value="1"
								<?php checked( ! empty( $options['block_cyrillic'] ) ); ?> />
							<?php esc_html_e( 'Block submissions containing Cyrillic characters', 'cf7-anti-spam-shield' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Disable submit button on submit', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cf7as_options[disable_submit]" value="1"
								<?php checked( ! empty( $options['disable_submit'] ) ); ?> />
							<?php esc_html_e( 'Disable the submit button while the form is being processed to prevent double submissions', 'cf7-anti-spam-shield' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable spam logging', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cf7as_options[enable_logging]" value="1"
								<?php checked( ! empty( $options['enable_logging'] ) || ! isset( $options['enable_logging'] ) ); ?> />
							<?php esc_html_e( 'Log blocked submissions for review in the Spam Log tab', 'cf7-anti-spam-shield' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Disallowed words / phrases', 'cf7-anti-spam-shield' ); ?></th>
					<td>
						<textarea name="cf7as_options[disallowed_words]" rows="8" cols="50" class="large-text"><?php echo esc_textarea( isset( $options['disallowed_words'] ) ? $options['disallowed_words'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One word or phrase per line. Submissions containing any of these will be blocked. A built-in list of common spam terms is always active.', 'cf7-anti-spam-shield' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the spam log tab.
	 *
	 * @return void
	 */
	private static function render_log_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display notice only.
		if ( isset( $_GET['log_cleared'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Spam log cleared.', 'cf7-anti-spam-shield' ) . '</p></div>';
		}

		$log = CF7AS_Logger::get_log( 100 );
		?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 15px 0;">
			<?php wp_nonce_field( 'cf7as_clear_log' ); ?>
			<input type="hidden" name="action" value="cf7as_clear_log" />
			<?php
			submit_button(
				__( 'Clear Log', 'cf7-anti-spam-shield' ),
				'delete',
				'submit',
				false,
				array( 'onclick' => 'return confirm("' . esc_js( __( 'Are you sure you want to clear the spam log?', 'cf7-anti-spam-shield' ) ) . '");' )
			);
			?>
		</form>

		<?php if ( empty( $log ) ) : ?>
			<p><?php esc_html_e( 'No spam blocked yet. Entries will appear here when submissions are blocked.', 'cf7-anti-spam-shield' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'cf7-anti-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'cf7-anti-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'cf7-anti-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Page', 'cf7-anti-spam-shield' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $entry['time'] ) ? $entry['time'] : '—' ); ?></td>
						<td><code><?php echo esc_html( isset( $entry['reason'] ) ? $entry['reason'] : '—' ); ?></code></td>
						<td><?php echo esc_html( isset( $entry['ip'] ) ? $entry['ip'] : '—' ); ?></td>
						<td><?php echo esc_html( isset( $entry['uri'] ) ? $entry['uri'] : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
