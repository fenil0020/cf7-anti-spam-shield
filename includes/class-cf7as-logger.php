<?php
/**
 * Spam logging.
 *
 * @package CF7_Anti_Spam_Shield
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles logging of blocked spam submissions.
 */
class CF7AS_Logger {

	/**
	 * Option key for the spam log.
	 *
	 * @var string
	 */
	const LOG_OPTION = 'cf7as_spam_log';

	/**
	 * Maximum number of log entries to keep.
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 100;

	/**
	 * Log a blocked spam attempt.
	 *
	 * @param string $reason Spam reason code.
	 * @return void
	 */
	public static function log( $reason ) {
		$ip = CF7AS_Checks::get_client_ip();

		$entry = array(
			'time'   => current_time( 'mysql' ),
			'reason' => sanitize_text_field( $reason ),
			'ip'     => $ip,
			'uri'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		);

		/**
		 * Filter a spam log entry before it is saved.
		 *
		 * Return false to skip logging this entry.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $entry  Log entry data.
		 * @param string $reason Spam reason code.
		 */
		$entry = apply_filters( 'cf7as_log_entry', $entry, $reason );

		if ( false === $entry ) {
			return;
		}

		if ( CF7AS_Settings::get( 'enable_logging', true ) ) {
			$log   = get_option( self::LOG_OPTION, array() );
			$log[] = $entry;

			if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
				$log = array_slice( $log, -self::MAX_LOG_ENTRIES );
			}

			update_option( self::LOG_OPTION, $log, false );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
			error_log(
				sprintf(
					'[CF7 Anti-Spam Shield] Blocked: %s | IP: %s | URI: %s',
					$reason,
					$ip,
					$entry['uri']
				)
			);
		}
	}

	/**
	 * Get the spam log entries.
	 *
	 * @param int $limit Number of entries to return. 0 for all.
	 * @return array
	 */
	public static function get_log( $limit = 0 ) {
		$log = get_option( self::LOG_OPTION, array() );
		$log = array_reverse( $log );

		if ( $limit > 0 ) {
			$log = array_slice( $log, 0, $limit );
		}

		return $log;
	}

	/**
	 * Clear the spam log.
	 *
	 * @return void
	 */
	public static function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Get spam statistics.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$log = get_option( self::LOG_OPTION, array() );

		$stats = array(
			'total'   => count( $log ),
			'today'   => 0,
			'reasons' => array(),
		);

		$today = current_time( 'Y-m-d' );

		foreach ( $log as $entry ) {
			if ( isset( $entry['time'] ) && 0 === strpos( $entry['time'], $today ) ) {
				++$stats['today'];
			}

			if ( isset( $entry['reason'] ) ) {
				$reason_key = explode( ':', $entry['reason'] )[0];
				if ( ! isset( $stats['reasons'][ $reason_key ] ) ) {
					$stats['reasons'][ $reason_key ] = 0;
				}
				++$stats['reasons'][ $reason_key ];
			}
		}

		return $stats;
	}
}
