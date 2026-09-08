<?php
/**
 * @package Troy\Server\Endpoints
 * @access  public
 */

namespace Troy\Server\Endpoints;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\API;

/**
 * Troy Server
 *
 * Copyright (c) 2025 - 2026 Sybre Waaijer, CyberWire B.V.
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
 * Abstract base class for Troy Server public API endpoints.
 *
 * @since 0.0.1184
 */
abstract class Base_Endpoint {

	/**
	 * Handle the API request.
	 *
	 * @since 0.0.1184
	 * @abstract
	 */
	abstract public function handle_request();

	/**
	 * Cleans the response by clearing all output buffers.
	 *
	 * @since 0.0.1184
	 * @since 1.8.1184 Deprecated. Use API\Response::clean_response_header().
	 * @deprecated 1.8.1184 Use API\Response::clean_response_header().
	 */
	#[\Deprecated(
		message: 'Use API\Response::clean_response_header().',
		since:   '1.8.1184',
	)]
	protected function clean_response_header() {
		API\Response::clean_response_header();
	}

	/**
	 * Sends a JSON response with cache, CORS, and robots headers, then exits.
	 *
	 * @since 0.0.1184
	 * @since 1.6.1184 Added Access-Control-Allow-Origin header for CORS support.
	 * @since 1.7.1184 Added X-Robots-Tag header.
	 * @since 1.8.1184 Deprecated. Use API\Response::send_response().
	 * @deprecated 1.8.1184 Use API\Response::send_response().
	 *
	 * @param mixed $data   The data to send.
	 * @param int   $status HTTP status code.
	 */
	#[\Deprecated(
		message: 'Use API\Response::send_response().',
		since:   '1.8.1184',
	)]
	protected function send_json_response( $data, $status = 200 ) {
		API\Response::send_response( $data, $status );
	}

	/**
	 * Sends a JSON error response, then exits.
	 *
	 * @since 0.0.1184
	 * @since 1.8.1184 Deprecated. Use API\Response::send_error().
	 * @deprecated 1.8.1184 Use API\Response::send_error().
	 *
	 * @param string $message The error message.
	 * @param int    $status  HTTP status code.
	 */
	#[\Deprecated(
		message: 'Use API\Response::send_error().',
		since:   '1.8.1184',
	)]
	protected function send_error( $message, $status = 400 ) {
		API\Response::send_error( $message, $status );
	}

	/**
	 * Sends a CORS preflight response for an OPTIONS request, then exits.
	 *
	 * @since 1.6.1184
	 * @since 1.7.1184 Added X-Robots-Tag header.
	 * @since 1.8.1184 Deprecated. Use API\Response::send_preflight_response().
	 * @deprecated 1.8.1184 Use API\Response::send_preflight_response().
	 *
	 * @param string $allowed_methods Comma-separated HTTP methods allowed for this endpoint.
	 */
	#[\Deprecated(
		message: 'Use API\Response::send_preflight_response().',
		since:   '1.8.1184',
	)]
	protected function send_preflight_response( $allowed_methods ) {
		API\Response::send_preflight_response( $allowed_methods );
	}

	/**
	 * Get and sanitize the client UUID from request headers.
	 *
	 * The UUID format is "{epoch}-{64charHexString}", where epoch is 4-5 digits.
	 * This method extracts the epoch from the UUID for efficient epoch-based aggregation.
	 *
	 * @since 0.0.1184
	 *
	 * @return array {
	 *     Client UUID data.
	 *
	 *     @type int    $epoch The epoch extracted from the UUID, or this epoch as fallback.
	 *     @type string $uuid  The full UUID string, or empty if invalid.
	 * }
	 */
	protected function get_client_uuid() {

		$uuid = $_SERVER['HTTP_X_TROY_CLIENT_ID'] ?? '';

		// Sanitize UUID: should be epoch-hex-string format like "2345[6]-64charHexString"
		// This is future proofed to allow 4 or 5 digit epoch values (4 digits would only be valid until 2161)
		if ( ! $uuid || ! preg_match( '/^\d{4,5}-[a-f0-9]{60,64}$/', $uuid ) )
			return [
				'epoch' => API\Utils::get_epoch(),
				'uuid'  => '',
			];

		$epoch = (int) strtok( $uuid, '-' );

		return [
			'epoch' => $epoch ?: API\Utils::get_epoch(),
			'uuid'  => $uuid,
		];
	}
}
