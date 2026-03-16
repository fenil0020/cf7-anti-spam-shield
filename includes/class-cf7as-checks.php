<?php
/**
 * Spam check logic.
 *
 * @package CF7_Anti_Spam_Shield
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs all spam checks against a CF7 submission.
 */
class CF7AS_Checks {

	/**
	 * Built-in disallowed words/phrases (case-insensitive).
	 *
	 * @var array
	 */
	private static $default_disallowed = array(
		'viagra',
		'cialis',
		'casino',
		'poker',
		'lottery',
		'cryptocurrency invest',
		'bitcoin profit',
		'earn money fast',
		'click here now',
		'SEO service',
		'web traffic',
		'buy followers',
	);

	/**
	 * Run all spam checks.
	 *
	 * Returns a string describing the spam reason, or false if the submission is clean.
	 *
	 * @return string|false
	 */
	public static function run() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- CF7 handles its own nonce.

		$checks = array(
			array( __CLASS__, 'check_honeypot' ),
			array( __CLASS__, 'check_timestamp' ),
			array( __CLASS__, 'check_rate_limit' ),
			array( __CLASS__, 'check_urls' ),
			array( __CLASS__, 'check_disallowed_words' ),
			array( __CLASS__, 'check_cyrillic' ),
		);

		/**
		 * Filter the list of spam checks to run.
		 *
		 * Each check must be a callable that returns a string (spam reason) or false (clean).
		 * Use this to add custom checks or remove built-in ones.
		 *
		 * @since 1.0.0
		 *
		 * @param array $checks Array of callables.
		 */
		$checks = apply_filters( 'cf7as_spam_checks', $checks );

		foreach ( $checks as $check ) {
			if ( is_callable( $check ) ) {
				$reason = call_user_func( $check );
				if ( $reason ) {
					return $reason;
				}
			}
		}

		// Passed all checks — increment rate counter.
		$ip = self::get_client_ip();
		if ( $ip ) {
			self::increment_rate( $ip );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return false;
	}

	/**
	 * Check the honeypot hidden field.
	 *
	 * @return string|false
	 */
	public static function check_honeypot() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$trap = isset( $_POST['cf7as_hp_field'] ) ? sanitize_text_field( wp_unslash( $_POST['cf7as_hp_field'] ) ) : '';
		if ( ! empty( $trap ) ) {
			return 'honeypot_filled';
		}
		return false;
	}

	/**
	 * Check the timestamp for minimum submit time.
	 *
	 * @return string|false
	 */
	public static function check_timestamp() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$timestamp = isset( $_POST['cf7as_ts'] ) ? absint( $_POST['cf7as_ts'] ) : 0;
		if ( 0 === $timestamp ) {
			return 'no_timestamp';
		}

		$min_time = (int) CF7AS_Settings::get( 'min_time', 3 );
		$elapsed  = time() - $timestamp;
		if ( $elapsed < $min_time ) {
			return 'too_fast:' . $elapsed . 's';
		}

		return false;
	}

	/**
	 * Check rate limiting by IP.
	 *
	 * @return string|false
	 */
	public static function check_rate_limit() {
		$ip = self::get_client_ip();
		if ( ! $ip ) {
			return false;
		}

		$key   = 'cf7as_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		$limit = (int) CF7AS_Settings::get( 'rate_limit', 5 );

		if ( $count >= $limit ) {
			return 'rate_limited:' . $ip;
		}

		return false;
	}

	/**
	 * Check the number of URLs in the submission.
	 *
	 * @return string|false
	 */
	public static function check_urls() {
		$all_text  = self::get_all_text_fields();
		$url_count = preg_match_all( '/https?:\/\//i', $all_text );
		$max_urls  = (int) CF7AS_Settings::get( 'max_urls', 2 );

		if ( $url_count > $max_urls ) {
			return 'too_many_urls:' . $url_count;
		}

		return false;
	}

	/**
	 * Check for disallowed words/phrases.
	 *
	 * @return string|false
	 */
	public static function check_disallowed_words() {
		$all_text = self::get_all_text_fields();
		if ( empty( $all_text ) ) {
			return false;
		}

		$custom_words = CF7AS_Settings::get( 'disallowed_words', '' );
		$disallowed   = self::$default_disallowed;
		if ( ! empty( $custom_words ) ) {
			$custom_array = array_filter( array_map( 'trim', explode( "\n", $custom_words ) ) );
			$disallowed   = array_merge( $disallowed, $custom_array );
		}

		/**
		 * Filter the disallowed words list.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $disallowed Array of disallowed words/phrases.
		 * @param string $all_text   Combined text of all submitted fields.
		 */
		$disallowed = apply_filters( 'cf7as_disallowed_words', $disallowed, $all_text );

		$text_lower = mb_strtolower( $all_text );
		foreach ( $disallowed as $word ) {
			$word = trim( mb_strtolower( $word ) );
			if ( ! empty( $word ) && false !== mb_strpos( $text_lower, $word ) ) {
				return 'disallowed_word:' . $word;
			}
		}

		return false;
	}

	/**
	 * Check for Cyrillic characters (optional).
	 *
	 * @return string|false
	 */
	public static function check_cyrillic() {
		if ( ! CF7AS_Settings::get( 'block_cyrillic', false ) ) {
			return false;
		}

		$all_text = self::get_all_text_fields();
		if ( preg_match( '/[\x{0400}-\x{04FF}]/u', $all_text ) ) {
			return 'cyrillic_text';
		}

		return false;
	}

	/**
	 * Combine all submitted text fields into a single string.
	 *
	 * @return string
	 */
	public static function get_all_text_fields() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}

		$skip = array(
			'cf7as_ts',
			'cf7as_hp_field',
			'_wpcf7',
			'_wpcf7_version',
			'_wpcf7_locale',
			'_wpcf7_unit_tag',
			'_wpcf7_container_post',
			'_wpcf7_posted_data_hash',
			'_wpcf7_nonce',
		);

		$texts = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $_POST as $key => $value ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$texts[] = sanitize_textarea_field( wp_unslash( $value ) );
			}
		}

		$cached = implode( ' ', $texts );
		return $cached;
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( false !== strpos( $ip, ',' ) ) {
					$parts = explode( ',', $ip );
					$ip    = trim( $parts[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}

	/**
	 * Increment the rate counter for an IP.
	 *
	 * @param string $ip Client IP.
	 * @return void
	 */
	private static function increment_rate( $ip ) {
		$key   = 'cf7as_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}
}
