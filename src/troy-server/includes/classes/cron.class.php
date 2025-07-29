<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server;

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
 * Class Troy\Server\Cron.
 * This class is meant to be extended to populate the CRON_JOBS constant.
 *
 * @since 0.0.1184
 */
class Cron {

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
		'troy_cron_clean_temp' => [
			'callback' => [ Zip_Extractor::class, 'cron_clean_old_temp_dirs' ],
			'schedule' => 'daily',
		],
	];

	/**
	 * Register custom cron schedules.
	 *
	 * @hook cron_schedules 10
	 * @since 0.0.1184
	 *
	 * @param array $schedules The existing cron schedules.
	 * @return array The modified cron schedules.
	 */
	public static function register_schedules( $schedules ) {

		foreach ( self::CRON_JOBS as $job ) {
			if (
				   isset( $job['interval'] )
				&& empty( $schedules[ $job['schedule'] ] )
			) {
				$schedules[ $job['schedule'] ] = [
					'interval' => $job['interval'],
					/* translators: %d: The interval in seconds. */
					'display'  => \sprintf( \__( 'Once every %d seconds', 'troy-server' ), $job['interval'] ),
				];
			}
		}

		return $schedules;
	}

	/**
	 * Register all cron jobs and ensure they're scheduled.
	 *
	 * @hook admin_init 10
	 * @since 0.0.1184
	 */
	public static function register_cron_tasks() {

		// Check if jobs need scheduling
		foreach ( self::CRON_JOBS as $hook => $job ) {
			if ( ! \wp_next_scheduled( $hook ) )
				\wp_schedule_event( time(), $job['schedule'], $hook );

			\add_action( $hook, $job['callback'] );
		}
	}

	/**
	 * Deactivate all cron jobs.
	 *
	 * This runs on plugin deactivation, which is after admin_init.
	 *
	 * @since 0.0.1184
	 */
	public static function remove_cron_jobs() {

		foreach ( self::CRON_JOBS as $hook => $job ) {
			$timestamp = \wp_next_scheduled( $hook );

			if ( $timestamp )
				\wp_unschedule_event( $timestamp, $hook );
		}
	}
}
