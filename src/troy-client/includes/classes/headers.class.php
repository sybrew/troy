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
	 * Returns whether the default get_plugins() cache includes Troy headers.
	 *
	 * @since 0.0.1184
	 * @since 1.8.1184 Now inspects the get_plugins() cache, not whether extra_plugin_headers has run.
	 *                 See: https://core.trac.wordpress.org/ticket/66057.
	 *
	 * @return bool True if the cached plugin data includes Troy headers.
	 */
	public static function is_filtered() {

		$cache = \wp_cache_get( 'plugins', 'plugins' );

		// get_plugins( $plugin_folder = '' ) keys this by folder. '' is "all plugins".
		// A non-empty $plugin_folder is a separate cold scan; it never reads from this entry.
		if ( empty( $cache[''] ) )
			return false;

		// PHP 8.5+: array_first().
		return \array_key_exists( TROY_PLUGIN_HEADERS['repo'][0], reset( $cache[''] ) );
	}

	/**
	 * Flushes the get_plugins() cache when it lacks Troy headers.
	 *
	 * @since 1.8.1184
	 */
	public static function flush_plugin_cache() {
		self::is_filtered() or \wp_cache_delete( 'plugins', 'plugins' );
	}

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
		// In PHP 8.1+, we can unpack string-keyed arrays.
		return array_merge(
			$headers,
			TROY_PLUGIN_HEADERS['repo'],
			TROY_PLUGIN_HEADERS['dependencies'],
		);
	}
}
