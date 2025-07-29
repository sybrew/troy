<?php
/**
 * @package Troy\Client
 * @access  private
 */

namespace Troy\Client;

\defined( 'Troy\Client\ABSPATH' ) or die;

/**
 * Troy Client
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
 * Class Troy\Client\Headers.
 *
 * @since 0.0.1184
 */
final class Headers {

	/**
	 * Registers the plugin headers.
	 *
	 * WordPress unpacks this via `array_combine( $value, $value )` in `get_file_data()`.
	 *
	 * @hook extra_plugin_headers 10
	 * @note Filter is registered as `extra_{$context}_headers` WP Core `get_file_data()`.
	 * @since 0.0.1184
	 *
	 * @param array $headers The plugin headers.
	 * @return array The extra plugin headers.
	 */
	public static function register_plugin_headers( $headers ) {
		// In PHP 8.1+ we can unpack string-keyed arrays.
		return array_merge(
			$headers,
			TROY_PLUGIN_HEADERS['repo'],
			TROY_PLUGIN_HEADERS['dependencies'],
		);
	}
}
