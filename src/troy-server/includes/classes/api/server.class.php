<?php
/**
 * @package Troy\Server\API
 * @api
 */

namespace Troy\Server\API;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\API; // We explicitly prefix API methods, possibly easing adoption.

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
 * Holds server-related API methods.
 *
 * @since 0.0.1184
 */
final class Server {

	/**
	 * Returns the database version.
	 *
	 * @since 0.0.1184
	 *
	 * @return int The database version.
	 */
	public static function get_db_version() {
		return (int) \get_option( 'troy_server_db_version' );
	}

	/**
	 * Returns this server's repository URL in bare format.
	 *
	 * Returns a stripped format (domain/path only) for consistent storage and comparison.
	 * Use `Sanitize::fully_qualified_repo_url()` to reconstruct a full URL when needed.
	 *
	 * @since 0.0.1184
	 *
	 * @return string The repository URL (e.g., 'example.com/repo').
	 */
	public static function get_repo_url() {

		static $memo;

		return $memo ??= Sanitize::bare_repo_url(
			\home_url( '', 'https' ),
		);
	}

	/**
	 * Returns this server's fully qualified repository URL.
	 *
	 * @since 0.0.1184
	 *
	 * @return string The fully qualified repository URL (e.g., 'https://example.com/repo').
	 */
	public static function get_full_repo_url() {
		return API\Sanitize::fully_qualified_repo_url( self::get_repo_url() );
	}

	/**
	 * Returns the plugin settings array.
	 *
	 * @since 0.0.1184
	 *
	 * @return array The plugin settings.
	 */
	public static function get_server_settings() {

		static $memo;

		return $memo ??= \get_option( 'troy_server_settings' );
	}
}
