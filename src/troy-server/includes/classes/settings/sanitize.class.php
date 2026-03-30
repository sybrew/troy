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
 * Holds settings sanitization methods.
 *
 * Provides a per-field sanitizer registry for `troy_server_settings`.
 * Sanitizers are registered JIT on first use. Unregistered keys are
 * dropped (whitelist approach) so only recognized settings survive.
 *
 * Unlike API\Sanitize, these sanitizers are not meant to be generally reusable
 * and focus on sanitizing settings values specifically, often with dynamic fallbacks.
 *
 * @source Taken from The SEO Framework by Sybre Waaijer, CyberWire B.V.
 * @since 1.7.1184
 */
final class Sanitize {

	/**
	 * Per-option sanitizer callbacks.
	 *
	 * @since 1.7.1184
	 * @var array<string, callable[]>
	 */
	private static $sanitizers = [];

	/**
	 * Filters settings whenever updated via update_option().
	 *
	 * Sanitizes all values through the registered sanitizer callbacks,
	 * whitelist-style. Unregistered keys are dropped. Flushes the
	 * settings runtime cache so any same-request reads get fresh data.
	 *
	 * @hook sanitize_option_troy_server_settings 10
	 * @since 1.7.1184
	 *
	 * @param mixed  $value          The new option value.
	 * @param string $option         The option name.
	 * @param mixed  $original_value The original value passed to update_option().
	 * @return array The sanitized settings.
	 */
	public static function filter_settings_update( $value, $option, $original_value ) {

		if ( empty( $value ) || ! \is_array( $value ) )
			return $original_value;

		self::register_sanitizers_jit();

		// Use merged defaults + current as the authoritative fallback.
		$original_value = array_merge(
			Data::get_default_settings(),
			Data::get_server_settings(),
		);

		$store = [];

		foreach ( self::$sanitizers as $sub_option => $callbacks ) {
			foreach ( $callbacks as $callback )
				$store[ $sub_option ] = \call_user_func_array(
					$callback,
					[
						$value[ $sub_option ] ?? '',
						$original_value[ $sub_option ],
						$sub_option,
					],
				);
		}

		// Flush memo so post-update reads re-fetch from database.
		Data::flush_settings_cache();

		return $store;
	}

	/**
	 * Registers sanitizer callbacks for settings keys.
	 *
	 * Will not overwrite previously registered sanitizers for a key,
	 * allowing external code to register custom sanitizers first.
	 *
	 * @since 1.7.1184
	 *
	 * @param array<string, callable|callable[]> $filters Map of option key => callback(s).
	 */
	public static function register_sanitizers( $filters ) {

		// Remit FETCH_STATIC_PROP_R opcode calls every time we'd otherwise use self::$sanitizers hereinafter.
		$_sanitizers = &self::$sanitizers;

		foreach ( $filters as $option => $callbacks ) {
			if ( \is_array( $callbacks[0] ) ) {
				$_sanitizers[ $option ] ??= $callbacks;
			} else {
				$_sanitizers[ $option ] ??= [ $callbacks ];
			}
		}
	}

	/**
	 * Registers built-in sanitizers on first use.
	 *
	 * Discrepancy: TSF supports shorthand string callbacks resolved against $sanitizer_class.
	 * We skip that until we have enough sanitizers to warrant the indirection.
	 *
	 * @since 1.7.1184
	 */
	private static function register_sanitizers_jit() {

		static $registered = false;

		if ( $registered )
			return;

		$registered = true;

		self::register_sanitizers( [
			'composer_vendor' => [ self::class, 'slug_with_fallback' ],
		] );
	}

	/**
	 * Sanitizes a slug value with a dynamic fallback.
	 *
	 * Uses `API\Sanitize::slug()` and falls back to the site slug
	 * derived from the WordPress site name if the result is empty.
	 *
	 * @since 1.7.1184
	 *
	 * @param mixed $value The raw value.
	 * @return string The sanitized slug.
	 */
	public static function slug_with_fallback( $value ) {
		return API\Sanitize::slug( \is_string( $value ) ? $value : '' )
			?: API\Server::get_site_slug();
	}
}
