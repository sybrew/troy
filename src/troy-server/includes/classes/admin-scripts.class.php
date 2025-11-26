<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	MAIN_FILE,
	VERSION,
};

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
 * Class Troy\Server\Admin_Scripts.
 *
 * Handles global admin script registration for the Troy Server plugin.
 *
 * @since 0.0.1184
 */
final class Admin_Scripts {

	/**
	 * Registers global admin utility scripts.
	 *
	 * These scripts are registered early and unconditionally in admin so they
	 * can be enqueued as dependencies by other scripts.
	 *
	 * @hook admin_init 1
	 * @since 0.0.1184
	 */
	public static function register_utils() {

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_register_script(
			'troy-server-escape',
			"{$dir_url}library/js/utils/escape{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-sanitize',
			"{$dir_url}library/js/utils/sanitize{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-sort',
			"{$dir_url}library/js/utils/sort{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-timing',
			"{$dir_url}library/js/utils/timing{$min}.js",
			[],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-format',
			"{$dir_url}library/js/utils/format{$min}.js",
			[ 'wp-i18n' ],
			VERSION,
			true,
		);

		\wp_register_script(
			'troy-server-assign',
			"{$dir_url}library/js/utils/assign{$min}.js",
			[],
			VERSION,
			true,
		);
	}
}
