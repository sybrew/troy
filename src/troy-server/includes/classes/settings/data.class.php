<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\API;

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
 * Holds settings and cache data access methods.
 *
 * @source Modeled after The SEO Framework by Sybre Waaijer, CyberWire B.V.
 * @since 1.7.1184
 */
final class Data {

	/**
	 * @since 1.7.1184
	 * @var ?array Memoized server settings, merged with defaults.
	 */
	private static $settings_memo = null;

	/**
	 * @since 1.7.1184
	 * @var ?array Memoized server cache.
	 */
	private static $cache_memo = null;

	/**
	 * Returns the default plugin settings.
	 *
	 * @since 1.7.1184
	 *
	 * @return array The default settings.
	 */
	public static function get_default_settings() {
		return [
			'composer_vendor' => API\Server::get_site_slug(),
		];
	}

	/**
	 * Returns the plugin settings array, merged with defaults.
	 *
	 * @since 1.7.1184
	 *
	 * @return array The plugin settings.
	 */
	public static function get_server_settings() {

		if ( isset( self::$settings_memo ) )
			return self::$settings_memo;

		return self::$settings_memo = array_merge(
			self::get_default_settings(),
			\get_option( 'troy_server_settings' ) ?: [],
		);
	}

	/**
	 * Updates server settings. Also clears the settings memo.
	 *
	 * Merges the given key-value pairs over the latest database revision
	 * so concurrent changes are not silently discarded.
	 *
	 * @since 1.7.1184
	 *
	 * @param string|array $option The option key, or an array of key-value pairs.
	 * @param mixed        $value  The option value. Ignored when $option is an array.
	 * @return bool True on success, false on failure.
	 */
	public static function update_server_settings( $option, $value = '' ) {

		$settings = array_merge(
			\get_option( 'troy_server_settings' ) ?: self::get_default_settings(),
			\is_array( $option ) ? $option : [ $option => $value ],
		);

		self::$settings_memo = null;

		return \update_option( 'troy_server_settings', $settings, true );
	}

	/**
	 * Flushes the settings runtime cache.
	 *
	 * @since 1.7.1184
	 */
	public static function flush_settings_cache() {
		self::$settings_memo = null;
	}

	/**
	 * Returns the default server cache values.
	 *
	 * @since 1.7.1184
	 *
	 * @return array The default cache.
	 */
	public static function get_default_cache() {
		return [];
	}

	/**
	 * Returns a single cache value.
	 *
	 * @since 1.7.1184
	 *
	 * @param string $key The cache key.
	 * @return mixed The cache value, or null if not set.
	 */
	public static function get_server_cache( $key ) {
		return (
			self::$cache_memo ?? self::get_server_caches()
		)[ $key ] ?? null;
	}

	/**
	 * Returns all server caches.
	 *
	 * @since 1.7.1184
	 *
	 * @return array The server caches.
	 */
	public static function get_server_caches() {

		if ( isset( self::$cache_memo ) )
			return self::$cache_memo;

		return self::$cache_memo =
			   \get_option( 'troy_server_cache' )
			?: self::get_default_cache();
	}

	/**
	 * Updates a single cache value.
	 *
	 * Can return false if the cache is unchanged.
	 *
	 * @since 1.7.1184
	 *
	 * @param string|array $cache The cache key, or an array of key-value pairs.
	 * @param mixed        $value The cache value. Ignored when $cache is an array.
	 * @return bool True on success, false on failure.
	 */
	public static function update_server_cache( $cache, $value = '' ) {

		$site_cache = array_merge(
			\get_option( 'troy_server_cache' ) ?: self::get_default_cache(),
			\is_array( $cache ) ? $cache : [ $cache => $value ],
		);

		self::$cache_memo = null;

		return \update_option( 'troy_server_cache', $site_cache, true );
	}

	/**
	 * Sets the settings state cache to 'updated' when options actually change.
	 *
	 * @hook update_option_troy_server_settings
	 * @since 1.7.1184
	 */
	public static function set_settings_updated_state() {
		self::update_server_cache( 'settings_notice', 'updated' );
	}
}
