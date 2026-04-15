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
 * Plugin tags endpoint for Troy Server.
 *
 * Returns available public plugin tags (released versions) with metadata.
 *
 * @since 0.0.1184
 */
final class Tags extends Base_Endpoint {

	/**
	 * Constructor.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug         The plugin slug.
	 * @param bool   $include_beta Whether to include beta releases.
	 * @param int    $limit        The maximum number of tags to return.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly bool $include_beta = false,
		public readonly int $limit = 100,
	) {}

	/**
	 * Handle the tags request.
	 *
	 * @rest plugin/get/tags/plugin-name(?include_beta=1&limit=100)? GET
	 * @since 0.0.1184
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

		$slug = API\Sanitize::slug( $this->slug );

		if ( ! $slug )
			$this->send_error( 'Invalid slug', 400 );

		$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

		if ( ! $plugin_id )
			$this->send_error( 'Plugin not found', 404 );

		$data = new Plugins\Data( $plugin_id );

		// Check plugin status - only serve tags for public/unlisted plugins
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

		$zips = $data->get_zips( min( $this->limit, 100 ) );

		// Sort by created_at descending (newest first)
		usort( $zips, fn( $a, $b ) => strtotime( $b->created_at ) <=> strtotime( $a->created_at ) );

		// Determine allowed types based on include_beta parameter
		$allowed_types = $this->include_beta
			? [ 'tag', 'beta' ]
			: [ 'tag' ];

		// Filter to only released versions and build response
		$tags = [];

		foreach ( $zips as $zip ) {
			if ( ! \in_array( $zip->type, $allowed_types, true ) )
				continue;

			$tags[] = [
				'version'      => $zip->version,
				'zip_url'      => Plugins\Files::get_plugin_zip_url_by_slug(
					$slug,
					$zip->version,
				),
				'file_size'    => (int) $zip->file_size,
				'tested_wp'    => $zip->tested_wp,
				'requires_wp'  => $zip->requires_wp,
				'requires_php' => $zip->requires_php,
				'checksums'    => json_decode( $zip->checksums, true ),
				'updated_at'   => $zip->updated_at,
			];
		}

		$this->send_json_response( $tags );
	}
}
