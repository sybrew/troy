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
 * Holds HTTP response methods for the public API.
 *
 * @since 1.8.1184
 */
final class Response {

	/**
	 * Cleans the response by clearing all output buffers.
	 *
	 * @since 1.8.1184
	 */
	public static function clean_response_header() {

		$level = ob_get_level();

		if ( $level ) while ( $level-- ) ob_end_clean();
	}

	/**
	 * Sends a JSON response with cache, CORS, and robots headers, then exits.
	 *
	 * @since 1.8.1184
	 *
	 * @param mixed $data   The data to send.
	 * @param int   $status HTTP status code.
	 */
	public static function send_response( $data, $status = 200 ) {

		API\Response::clean_response_header();

		http_response_code( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Mon, 13 Jan 2025 00:29:21 GMT' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Sends a JSON error response, then exits.
	 *
	 * @since 1.8.1184
	 *
	 * @param string $message The error message.
	 * @param int    $status  HTTP status code.
	 */
	public static function send_error( $message, $status = 400 ) {
		API\Response::send_response(
			[ 'error' => $message ],
			$status,
		);
	}

	/**
	 * Sends a CORS preflight response for an OPTIONS request, then exits.
	 *
	 * @since 1.8.1184
	 *
	 * @param string $allowed_methods Comma-separated HTTP methods allowed for this endpoint.
	 */
	public static function send_preflight_response( $allowed_methods ) {

		API\Response::clean_response_header();

		http_response_code( 204 );
		header( 'Access-Control-Allow-Origin: *' );
		header( "Access-Control-Allow-Methods: $allowed_methods" );
		header( 'Access-Control-Allow-Headers: *' );
		header( 'Access-Control-Max-Age: 86400' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		exit;
	}
}
