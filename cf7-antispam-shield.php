<?php
/**
 * CF7 Anti-Spam Shield
 *
 * @package           CF7AS_Plugin
 * @author            Supple
 * @copyright         2026 Supple
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       CF7 Anti-Spam Shield
 * Plugin URI:        https://supple.com.au/cf7-antispam-shield
 * Description:       Advanced anti-spam protection for Contact Form 7 — time-based check, hidden field trap, URL limit, disallowed words, and rate limiting. No external APIs required.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Supple
 * Author URI:        https://supple.com.au
 * Text Domain:       cf7-anti-spam-shield
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'CF7AS_VERSION', '1.0.0' );
define( 'CF7AS_PLUGIN_FILE', __FILE__ );
define( 'CF7AS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF7AS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CF7AS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin files.
 */
require_once CF7AS_PLUGIN_DIR . 'includes/class-cf7as-checks.php';
require_once CF7AS_PLUGIN_DIR . 'includes/class-cf7as-form.php';
require_once CF7AS_PLUGIN_DIR . 'includes/class-cf7as-logger.php';
require_once CF7AS_PLUGIN_DIR . 'includes/class-cf7as-settings.php';

/**
 * Main plugin class.
 */
final class CF7AS_Plugin {

	/**
	 * Single instance.
	 *
	 * @var CF7AS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return CF7AS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'activated_plugin', array( $this, 'check_dependency' ) );

		register_activation_hook( CF7AS_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Initialize the plugin after all plugins have loaded.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'plugin_action_links_' . CF7AS_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		CF7AS_Settings::init();

		if ( ! class_exists( 'WPCF7' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_cf7_notice' ) );
			return;
		}

		CF7AS_Form::init();

		/**
		 * Fires after CF7 Anti-Spam Shield is fully loaded.
		 *
		 * Use this hook to extend the plugin with custom checks or integrations.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cf7as_loaded' );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activate() {
		$defaults = CF7AS_Settings::get_defaults();
		if ( false === get_option( 'cf7as_options' ) ) {
			add_option( 'cf7as_options', $defaults );
		}
	}

	/**
	 * Check if CF7 is active after a plugin is activated.
	 *
	 * @param string $plugin Activated plugin basename.
	 * @return void
	 */
	public function check_dependency( $plugin ) {
		if ( CF7AS_PLUGIN_BASENAME === $plugin && ! class_exists( 'WPCF7' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_cf7_notice' ) );
		}
	}

	/**
	 * Show admin notice if CF7 is not active.
	 *
	 * @return void
	 */
	public function missing_cf7_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: Contact Form 7 plugin name. */
					esc_html__( '%s requires Contact Form 7 to be installed and activated.', 'cf7-anti-spam-shield' ),
					'<strong>CF7 Anti-Spam Shield</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=cf7-anti-spam-shield' ) ),
			esc_html__( 'Settings', 'cf7-anti-spam-shield' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}
}

CF7AS_Plugin::instance();
