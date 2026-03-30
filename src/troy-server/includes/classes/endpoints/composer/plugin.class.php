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
	Plugins\Data,
	Plugins\Files,
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
 * Per-plugin Composer metadata endpoint for Troy Server.
 *
 * Serves individual plugin version metadata for Composer 2's `metadata-url`
 * protocol. When Composer resolves a `{vendor}/{slug}` package, it fetches
 * `/composer/get/{vendor}/{slug}.json` and receives only that
 * plugin's released versions.
 *
 * Returns 404 for unknown slugs or non-public plugins, which Composer
 * treats as "package not found." The vendor prefix from the URL is echoed
 * back in the response so Composer always sees a matching package name.
 *
 * @since 1.7.1184
 * @link https://getcomposer.org/doc/05-repositories.md#composer
 */
final class Plugin extends Base_Endpoint {

	/**
	 * Constructor.
	 *
	 * @since 1.7.1184
	 *
	 * @param string $vendor The vendor-type prefix from the URL (e.g., 'deploytroy-plugin').
	 * @param string $slug   The plugin slug extracted from the URL.
	 */
	public function __construct(
		public readonly string $vendor,
		public readonly string $slug,
	) {}

	/**
	 * Handle the per-plugin Composer metadata request.
	 *
	 * Looks up the plugin by slug, verifies it is public or unlisted,
	 * and returns all released versions in Composer's metadata-url format.
	 *
	 * @rest composer/get/{vendor}-plugin/{slug}.json GET
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

		$slug = API\Sanitize::slug( $this->slug );

		if ( ! $slug )
			$this->send_error( 'Plugin not found', 404 );

		$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

		if ( ! $plugin_id )
			$this->send_error( 'Plugin not found', 404 );

		$data = new Data( $plugin_id );

		switch ( $data->get_plugins_row()->status ) {
			case 'public':
			case 'unlisted':
				break;
			default:
				$this->send_error( 'Plugin not found', 404 );
		}

		$vendor   = API\Sanitize::slug( $this->vendor );
		$versions = $this->build_versions( $slug, $data, $vendor );

		if ( empty( $versions ) )
			$this->send_error( 'Plugin not found', 404 );

		$package_name = "$vendor/$slug";

		$this->send_json_response( [
			'packages' => [ $package_name => $versions ],
		] );
	}

	/**
	 * Builds Composer version entries for a plugin.
	 *
	 * Only includes released (tag-type) versions. Each version entry
	 * follows the Composer 2 metadata-url response format (array of
	 * version objects).
	 *
	 * @since 1.7.1184
	 *
	 * @param string $slug   The sanitized plugin slug.
	 * @param Data   $data   The plugin data accessor.
	 * @param string $vendor The configured vendor prefix.
	 * @return array[] Array of Composer version definition arrays.
	 */
	private function build_versions( $slug, $data, $vendor ) {

		$zips = $data->get_zips();

		if ( empty( $zips ) )
			return [];

		$package_name = "$vendor/$slug";
		$metas        = $data->get_metas_row();
		$versions     = [];

		foreach ( $zips as $zip ) {
			if ( 'tag' !== $zip->type )
				continue;

			$version_entry = [
				'name'    => $package_name,
				'version' => $zip->version,
				'type'    => 'wordpress-plugin',
				'dist'    => [
					'url'  => Files::get_plugin_zip_url_by_slug(
						$slug,
						$zip->version,
					),
					'type' => 'zip',
				],
			];

			$require = [];

			if ( $zip->requires_php )
				$require['php'] = ">=$zip->requires_php";

			if ( ! empty( $require ) )
				$version_entry['require'] = $require;

			if ( $metas?->permalink )
				$version_entry['homepage'] = $metas->permalink;

			if ( $metas?->short_description )
				$version_entry['description'] = $metas->short_description;

			$checksums = json_decode( $zip->checksums, true );

			if ( ! empty( $checksums['sha1'] ) )
				$version_entry['dist']['shasum'] = $checksums['sha1'];

			$versions[] = $version_entry;
		}

		return $versions;
	}
}
