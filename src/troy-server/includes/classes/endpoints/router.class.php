<?php
/**
 * @package Troy\Server\Endpoints
 * @access  public
 */

namespace Troy\Server\Endpoints;

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
 * Router for Troy Server public API endpoints.
 *
 * Handles URL routing and request dispatching for the public plugin update API.
 * Uses direct URL parsing at 'init' hook for optimal performance, bypassing
 * WordPress's query parsing and template loading entirely.
 *
 * @since 0.0.1184
 */
final class Router {

	/**
	 * Handle API requests based on the endpoint using direct URL parsing.
	 *
	 * This bypasses WordPress's query parsing for better performance.
	 *
	 * POST requests' body extraction handled within the endpoint classes.
	 * GET requests are unpacked here.
	 *
	 * @hook init 10
	 * @since 0.0.1184
	 */
	public static function handle_api_requests() {

		// Get the request URI and remove query string
		$request_uri = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$home_path   = parse_url( \get_home_url(), PHP_URL_PATH ) ?? '';

		// Remove WordPress's base path from request URI to support subdirectory installations
		if ( $home_path && str_starts_with( $request_uri, $home_path ) )
			$request_uri = substr( $request_uri, \strlen( $home_path ) );

		// Remove leading/trailing slashes and normalize
		$request_path = trim( $request_uri, '/' );

		// Check if this is a Troy API endpoint
		if ( ! $request_path )
			return;

		// phpcs:disable WordPress.Security.NonceVerification -- Public API, no nonce needed.
		switch ( true ) {
			case 'ping' === $request_path:
				new Ping()->handle_request();
				break;

			case 'plugin/get/updates' === $request_path:
				new Plugins\Updates()->handle_request();
				break;

			case 'plugin/get/info' === $request_path:
				new Plugins\Info()->handle_request();
				break;

			case str_starts_with( $request_path, 'plugin/get/stats/' ):
				// Filter duplicated slashes and reset indexes.
				$path_parts = array_values( array_filter( explode( '/', $request_path ) ) );

				if ( \count( $path_parts ) >= 4 ) {
					new Plugins\Stats(
						$path_parts[3], // slug
					)->handle_request();
				}
				break;

			case str_starts_with( $request_path, 'plugin/get/tags/' ):
				// Filter duplicated slashes and reset indexes.
				$path_parts = array_values( array_filter( explode( '/', $request_path ) ) );

				if ( \count( $path_parts ) >= 4 ) {
					new Plugins\Tags(
						$path_parts[3], // slug
						! empty( $_GET['include_beta'] ),
						$_GET['limit'] ?? 100,
					)->handle_request();
				}
				break;

			case str_starts_with( $request_path, 'plugin/get/zip/' ):
				// Filter duplicated slashes and reset indexes.
				$path_parts = array_values( array_filter( explode( '/', $request_path ) ) );

				if ( \count( $path_parts ) >= 4 ) {
					new Plugins\Download(
						$path_parts[3], // slug
						( $path_parts[4] ?? null ) ?: 'latest', // version
					)->handle_request();
				}
				break;

			case str_starts_with( $request_path, 'package/get/zip/' ):
			case str_starts_with( $request_path, 'installer/get/zip/' ):
				// Filter duplicated slashes and reset indexes.
				$path_parts = array_values( array_filter( explode( '/', $request_path ) ) );

				if ( \count( $path_parts ) >= 4 ) {
					new Packages\Download(
						$path_parts[3], // slug
					)->handle_request();
				}
				break;

			default:
				// Not a Troy API endpoint, let WordPress handle it
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}
}
