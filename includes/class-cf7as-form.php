<?php
/**
 * Form integration — injects hidden fields and validates submissions.
 *
 * @package CF7_Anti_Spam_Shield
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CF7 form modifications and validation.
 */
class CF7AS_Form {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wpcf7_init', array( __CLASS__, 'register_form_fields' ) );
		add_filter( 'wpcf7_validate', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register hidden form fields via CF7's form elements filter.
	 *
	 * @return void
	 */
	public static function register_form_fields() {
		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'inject_fields' ) );
	}

	/**
	 * Inject anti-spam hidden fields into the form HTML.
	 *
	 * @param string $content Form HTML content.
	 * @return string
	 */
	public static function inject_fields( $content ) {
		$fields  = '<input type="hidden" name="cf7as_ts" value="" />';
		$fields .= '<div class="cf7as-hp-wrap" aria-hidden="true" tabindex="-1">';
		$fields .= '<label>' . esc_html__( 'Website URL', 'cf7-anti-spam-shield' );
		$fields .= ' <input type="text" name="cf7as_website_url" value="placeholder" autocomplete="off" tabindex="-1" />';
		$fields .= '</label></div>';

		/**
		 * Filter the anti-spam hidden fields HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string $fields  Hidden fields HTML.
		 * @param string $content Original form content.
		 */
		$fields = apply_filters( 'cf7as_hidden_fields', $fields, $content );

		return $content . $fields;
	}

	/**
	 * Validate the submission against spam checks.
	 *
	 * @param WPCF7_Validation $result Current validation result.
	 * @param array            $tags   Form tags.
	 * @return WPCF7_Validation
	 */
	public static function validate( $result, $tags ) {
		$spam_reason = CF7AS_Checks::run();

		if ( $spam_reason ) {
			CF7AS_Logger::log( $spam_reason );

			if ( ! empty( $tags ) ) {
				$result->invalidate(
					$tags[0],
					/**
					 * Filter the error message shown to blocked users.
					 *
					 * @since 1.0.0
					 *
					 * @param string $message     Error message.
					 * @param string $spam_reason  Internal reason code.
					 */
					apply_filters(
						'cf7as_error_message',
						__( 'Your message could not be sent. Please try again later.', 'cf7-anti-spam-shield' ),
						$spam_reason
					)
				);
			}

			add_filter( 'wpcf7_spam', '__return_true' );

			/**
			 * Fires when a submission is blocked as spam.
			 *
			 * @since 1.0.0
			 *
			 * @param string $spam_reason Reason the submission was blocked.
			 */
			do_action( 'cf7as_spam_blocked', $spam_reason );
		}

		return $result;
	}

	/**
	 * Enqueue frontend JS and CSS.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! class_exists( 'WPCF7' ) ) {
			return;
		}

		wp_enqueue_script(
			'cf7-anti-spam-shield',
			CF7AS_PLUGIN_URL . 'assets/js/cf7as-frontend.js',
			array(),
			CF7AS_VERSION,
			true
		);

		wp_localize_script(
			'cf7-anti-spam-shield',
			'cf7as_settings',
			array(
				'disable_submit' => (bool) CF7AS_Settings::get( 'disable_submit', false ),
			)
		);

		wp_enqueue_style(
			'cf7-anti-spam-shield',
			CF7AS_PLUGIN_URL . 'assets/css/cf7as-frontend.css',
			array(),
			CF7AS_VERSION
		);
	}
}
