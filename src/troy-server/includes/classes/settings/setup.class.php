<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	REST_NS,
	VERSION,
	MAIN_FILE,
};

/**
 * Troy Server
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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
 * Class Troy\Server\Settings\Setup.
 *
 * Provides asset enqueuing for the Setup settings tab.
 *
 * @since 1.7.1184
 */
final class Setup {

	/**
	 * Enqueues setup page assets.
	 *
	 * @since 1.7.1184
	 */
	public static function enqueue_assets() {

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_script(
			'troy-server-settings-setup-js',
			"{$dir_url}library/js/settings/setup{$min}.js",
			[ 'wp-api-fetch', 'wp-i18n' ],
			VERSION,
			true,
		);
		\wp_localize_script(
			'troy-server-settings-setup-js',
			'troyServerSetup',
			[
				'restBase' => \rest_url( REST_NS['settings']['namespace'] . '/' . REST_NS['settings']['base'] ),
			],
		);
	}
}
