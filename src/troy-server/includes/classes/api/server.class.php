<?php
/**
 * @package Troy\Server\API
 * @api
 */

namespace Troy\Server\API;

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
	 * Returns this server's origin URL.
	 * Forcing HTTPS.
	 *
	 * @since 0.0.1184
	 *
	 * @return string The origin URL.
	 */
	public static function get_origin_url() {

		static $memo;

		return $memo ??= Sanitize::make_fully_qualified_repo_url(
			\home_url( '', 'https' ),
		);
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
