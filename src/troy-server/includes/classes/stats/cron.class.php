<?php
/**
 * @package Troy\Server\Stats
 * @access  private
 */

namespace Troy\Server\Stats;

\defined( 'Troy\Server\ABSPATH' ) or die;

/**
 * Troy Server
 *
 * Copyright (c) 2025 Sybre Waaijer, CyberWire B.V.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class Troy\Server\Stats\Cron.
 *
 * Handles scheduling and execution of stats aggregation cron jobs.
 *
 * @since 0.0.1184
 */
final class Cron extends \Troy\Server\Cron {

	/**
	 * @since 0.0.1184
	 * @var array $cron_jobs {
	 *     An array of cron jobs with their schedules, indexed by the hook name.
	 *
	 *     @type array {
	 *         @type callable $callback The callback function to execute.
	 *         @type string   $schedule The schedule type.
	 *         @type int      $interval Optional. The interval in seconds for a custom schedule.
	 *     }
	 * }
	 */
	protected const CRON_JOBS = [
		'troy_server_cron_stats_take_snapshot'  => [
			'callback' => [ self::class, 'run_snapshot' ],
			'schedule' => 'halfhourly',
			'interval' => \HOUR_IN_SECONDS / 2,
		],
		'troy_server_cron_stats_finalize_epoch' => [
			'callback' => [ self::class, 'run_finalize_epoch' ],
			'schedule' => 'daily',
		],
	];

	/**
	 * Run a stats snapshot.
	 *
	 * Aggregates live data from all _live tables into aggregated tables.
	 * This runs every 30 minutes to keep stats near-realtime.
	 *
	 * @hook troy_server_cron_stats_take_snapshot 10
	 * @since 0.0.1184
	 */
	public static function run_snapshot() {

		if ( ! self::acquire_lock( 'troy_server_stats_snapshot.lock', 10 * \MINUTE_IN_SECONDS ) )
			return;

		Aggregator::snapshot_update_requests();
		Aggregator::snapshot_views();
		Aggregator::snapshot_downloads();
		Aggregator::update_active_install_counts();
		Aggregator::snapshot_to_date(); // Must run last

		self::release_lock( 'troy_server_stats_snapshot.lock' );
	}

	/**
	 * Run epoch finalization.
	 *
	 * Finalizes epochs that are older than 48 hours by deleting their live data.
	 * This runs daily and processes one epoch at a time.
	 *
	 * @hook troy_server_cron_stats_finalize_epoch 10
	 * @since 0.0.1184
	 */
	public static function run_finalize_epoch() {

		if ( ! self::acquire_lock( 'troy_server_stats_finalize.lock', 5 * \MINUTE_IN_SECONDS ) )
			return;

		Aggregator::finalize_old_epochs();

		self::release_lock( 'troy_server_stats_finalize.lock' );
	}

	/**
	 * Acquires a lock to prevent concurrent execution.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param string $lock_name      The name of the lock option.
	 * @param int    $release_timeout The timeout after which the lock is considered stale.
	 * @return bool True if the lock was acquired, false otherwise.
	 */
	private static function acquire_lock( $lock_name, $release_timeout ) {

		global $wpdb;

		$lock = $wpdb->query( $wpdb->prepare(
			"INSERT ignore INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'off') /* LOCK */",
			$lock_name,
			time(),
		) );

		if ( ! $lock ) {
			$lock_time = \get_option( $lock_name );

			if ( ! $lock_time || ( $lock_time > ( time() - $release_timeout ) ) )
				return false;

			self::release_lock( $lock_name );

			return self::acquire_lock( $lock_name, $release_timeout );
		}

		\update_option( $lock_name, time(), false );

		return true;
	}

	/**
	 * Releases a lock.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $lock_name The name of the lock option.
	 */
	private static function release_lock( $lock_name ) {
		\delete_option( $lock_name );
	}
}
