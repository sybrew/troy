<?php
/**
 * @package Troy\Server\Endpoints\Plugins
 * @access  public
 */

namespace Troy\Server\Endpoints\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Endpoints\Base_Endpoint,
	Plugins,
};

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
 * Plugin stats endpoint for Troy Server.
 *
 * Returns public plugin statistics including downloads, active installs, and ratings.
 *
 * @since 1.5.1184
 */
final class Stats extends Base_Endpoint {

	/**
	 * Constructor.
	 *
	 * @since 1.5.1184
	 *
	 * @param ?string $slug The plugin slug. Null for multi-slug POST requests.
	 */
	public function __construct(
		public readonly ?string $slug = null,
	) {}

	/**
	 * Handle the stats request.
	 *
	 * Supports GET for a single plugin (slug via constructor) and POST for
	 * multiple plugins (slugs via JSON body). Invalid, missing, or unavailable
	 * slugs are silently omitted from the POST response.
	 *
	 * @rest plugin/get/stats/plugin-name GET
	 * @rest plugin/get/stats            POST {"slugs":["plugin-name",...]}
	 * @since 1.5.1184
	 * @since 1.6.1184 Now supports POST for multi-plugin stats.
	 */
	public function handle_request() {

		switch ( $_SERVER['REQUEST_METHOD'] ) {
			case 'GET':
				$slug = API\Sanitize::slug( $this->slug ?? '' );

				if ( ! $slug )
					$this->send_error( 'Invalid slug', 400 );

				$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

				if ( ! $plugin_id )
					$this->send_error( 'Plugin not found', 404 );

				$data = new Plugins\Data( $plugin_id );

				// Check plugin status - only serve stats for public/unlisted plugins
				switch ( $data->get_plugins_row()->status ) {
					case 'public':
					case 'unlisted':
						// Allowed statuses. Break to continue processing.
						break;
					case 'protected':
					case 'pending':
					case 'disabled':
					default:
						$this->send_error( 'Plugin not available', 403 );
				}

				$stats_totals = $data->get_stats_totals_row();
				$data_cache   = $data->get_data_caches_row();

				$this->send_json_response( [
					'slug'            => $slug,
					'downloads'       => (int) ( $stats_totals->downloads ?? 0 ),
					'active_installs' => (int) ( $data_cache->active_install_count ?? 0 ),
					'_comment'        => 'Ratings are not yet implemented, so they return zero values.',
					'rating'          => 0,
					'num_ratings'     => 0,
					'ratings'         => [
						5 => 0,
						4 => 0,
						3 => 0,
						2 => 0,
						1 => 0,
					],
				] );
				break;

			case 'POST':
				// phpcs:ignore TSF.Performance -- This reads a stream, not a file.
				$input = json_decode( file_get_contents( 'php://input' ), true );

				if ( ! \is_array( $input ) )
					$this->send_error( 'Invalid JSON input', 400 );

				$slugs = $input['slugs'] ?? [];

				if ( ! \is_array( $slugs ) )
					$this->send_error( 'Invalid slugs parameter', 400 );

				if ( \count( $slugs ) > 69 )
					$this->send_error( 'Too many slugs, maximum is 69', 400 );

				$response = [];

				foreach ( $slugs as $raw_slug ) {

					if ( ! \is_string( $raw_slug ) )
						continue;

					$slug = API\Sanitize::slug( $raw_slug );

					if ( ! $slug )
						continue;

					$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

					if ( ! $plugin_id )
						continue;

					$data = new Plugins\Data( $plugin_id );

					switch ( $data->get_plugins_row()->status ) {
						case 'public':
						case 'unlisted':
							break;
						case 'protected':
						case 'pending':
						case 'disabled':
						default:
							continue 2;
					}

					$stats_totals = $data->get_stats_totals_row();
					$data_cache   = $data->get_data_caches_row();

					$response[] = [
						'slug'            => $slug,
						'downloads'       => (int) ( $stats_totals->downloads ?? 0 ),
						'active_installs' => (int) ( $data_cache->active_install_count ?? 0 ),
						'_comment'        => 'Ratings are not yet implemented, so they return zero values.',
						'rating'          => 0,
						'num_ratings'     => 0,
						'ratings'         => [
							5 => 0,
							4 => 0,
							3 => 0,
							2 => 0,
							1 => 0,
						],
					];
				}

				$this->send_json_response( $response );
				break;

			case 'OPTIONS':
				$this->send_preflight_response( 'GET, POST, OPTIONS' );
				// No break. send_preflight_response() exits.

			default:
				$this->send_error( 'Method not allowed', 405 );
		}
	}
}
