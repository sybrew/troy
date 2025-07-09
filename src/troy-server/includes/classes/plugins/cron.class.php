<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server\Plugins;

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
 * Class Troy\Server\Plugins\Cron.
 *
 * @since 0.0.1184
 */
class Cron extends \Troy\Server\Cron {

	/**
	 * @since 0.0.1184
	 * @var array CRON_JOBS {
	 *     An array of cron jobs with their schedules, indexed by the hook name.
	 *
	 *     @type array {
	 *         @type callable $callback The callback function to execute.
	 *         @type int      $interval The interval in seconds.
	 *         @type string   $schedule The schedule type.
	 *     }
	 * }
	 */
	protected const CRON_JOBS = [
		// TODO reimplement?
		// 'troy_cron_plugins_process_zips_queue' => [
		// 	'callback' => [ Zip_Uploader::class, 'cron_process_zip_queue' ],
		// 	'interval' => \MINUTE_IN_SECONDS,
		// 	'schedule' => 'every_minute',
		// ],
	];
}
