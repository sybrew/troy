<?php
/**
 * @package Troy\Server\Endpoints\Composer
 * @access  public
 */

namespace Troy\Server\Endpoints\Composer;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Endpoints\Base_Endpoint,
};

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
 * Composer repository index endpoint for Troy Server.
 *
 * Serves the root `packages.json` that Composer fetches when a Troy Server
 * is added as a repository. Contains no package data itself; instead declares
 * a `metadata-url` so Composer 2+ fetches only the specific packages it needs
 * via individual per-package endpoints.
 *
 * The `metadata-url` uses a generic `/composer/get/%package%.json`
 * pattern. Composer replaces `%package%` with the full name (e.g.,
 * `tsf/autodescription`), and the router dispatches to the appropriate
 * plugin or theme endpoint.
 *
 * @since 1.7.1184
 * @link https://getcomposer.org/doc/05-repositories.md#composer
 */
final class Composer extends Base_Endpoint {

	/**
	 * Handle the Composer packages.json request.
	 *
	 * Returns a minimal response with an empty packages object, a
	 * metadata-url pointing Composer to per-package JSON endpoints,
	 * and available-package-patterns so Composer knows which packages
	 * this repository provides without client-side `only` filters.
	 *
	 * @rest composer/get/packages.json GET
	 * @since 1.7.1184
	 */
	public function handle_request() {

		switch ( $_SERVER['REQUEST_METHOD'] ) {
			case 'GET':
				break;
			case 'OPTIONS':
				$this->send_preflight_response( 'GET, OPTIONS' );
				// No break. send_preflight_response() exits.
			default:
				$this->send_error( 'Method not allowed', 405 );
		}

		$vendor = API\Server::get_composer_vendor();

		// TODO: Add "$vendor-theme/*" pattern when theme endpoints are implemented.
		$this->send_json_response( [
			'packages'                   => (object) [],
			'metadata-url'               => '/composer/get/%package%.json',
			'available-package-patterns' => [
				"$vendor-plugin/*",
				"$vendor-package/*",
			],
		] );
	}
}
