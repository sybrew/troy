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
 * Ping endpoint for Troy Server health checks.
 *
 * Provides a simple health check endpoint that Troy Client uses to verify
 * server connectivity and availability.
 *
 * @since 0.0.1184
 */
final class Ping extends Base_Endpoint {

	/**
	 * Handle the ping request.
	 *
	 * @since 0.0.1184
	 */
	public function handle_request() {

		if ( 'GET' !== $_SERVER['REQUEST_METHOD'] )
			$this->send_error( 'Method not allowed', 405 );

		// Simple pong response
		$this->send_json_response( [
			'status'  => 'ok',
			'message' => 'pong',
			'time'    => \current_time( 'mysql', true ),
		] );
	}
}
