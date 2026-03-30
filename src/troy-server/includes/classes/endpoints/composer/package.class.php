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
	Packages\Data as Package_Data,
	Plugins\Data as Plugin_Data,
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
 * Composer metapackage endpoint for Troy Packages.
 *
 * Serves a virtual Composer metapackage that requires all plugins included
 * in a Troy Package plus Troy Client. The metapackage has no downloadable
 * artifact; Composer resolves each dependency from this server's per-plugin
 * endpoints and Troy Client from repo.deploytroy.org.
 *
 * Accessed via the standard metadata-url pattern:
 * `/composer/get/{vendor}-package/{slug}.json`
 *
 * @since 1.7.1184
 * @link https://getcomposer.org/doc/04-schema.md#type
 */
final class Package extends Base_Endpoint {

	/**
	 * Constructor.
	 *
	 * @since 1.7.1184
	 *
	 * @param string $vendor The vendor-type prefix from the URL (e.g., 'deploytroy-package').
	 * @param string $slug   The package slug extracted from the URL.
	 */
	public function __construct(
		public readonly string $vendor,
		public readonly string $slug,
	) {}

	/**
	 * Handle the metapackage Composer metadata request.
	 *
	 * Looks up the package by slug, verifies it is active, and returns
	 * a single metapackage version whose `require` lists every bundled
	 * plugin and Troy Client.
	 *
	 * @rest composer/get/{vendor}-package/{slug}.json GET
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
			$this->send_error( 'Package not found', 404 );

		$package_id = Package_Data::get_package_id_by_slug( $slug );

		if ( ! $package_id )
			$this->send_error( 'Package not found', 404 );

		$package_data = new Package_Data( $package_id );
		$package      = $package_data->get_packages_row();

		if ( ! $package || 'active' !== $package->status )
			$this->send_error( 'Package not found', 404 );

		$metas = $package_data->get_metas_row();

		if ( ! $metas || empty( $metas->plugins ) )
			$this->send_error( 'Package not found', 404 );

		$vendor       = API\Sanitize::slug( $this->vendor );
		$package_name = "$vendor/$slug";
		$require      = $this->build_require( $metas->plugins );

		if ( empty( $require ) )
			$this->send_error( 'Package not found', 404 );

		$this->send_json_response( [
			'packages' => [
				$package_name => [
					[
						'name'    => $package_name,
						'version' => $package->version ?? '1.0.0',
						'type'    => 'metapackage',
						'require' => $require,
					],
				],
			],
		] );
	}

	/**
	 * Builds the require map for the metapackage.
	 *
	 * Lists every public/unlisted plugin in the package using the latest
	 * released version as a minimum constraint, plus Troy Client.
	 *
	 * @since 1.7.1184
	 *
	 * @param array $plugin_entries The plugins array from package metas.
	 * @return array<string, string> Map of package names to version constraints.
	 */
	private function build_require( $plugin_entries ) {

		// The vendor prefix for plugins is the same as the package vendor but with "-plugin" instead of "-package".
		$vendor_base   = substr( $this->vendor, 0, strrpos( $this->vendor, '-' ) );
		$plugin_vendor = API\Sanitize::slug( "$vendor_base-plugin" );
		$require       = [ 'deploytroy-plugin/troy-client' => '>=1.0' ];

		foreach ( $plugin_entries as $entry ) {
			$plugin_id = $entry['id'] ?? null;

			if ( ! $plugin_id )
				continue;

			$plugin_data = new Plugin_Data( $plugin_id );
			$plugin_row  = $plugin_data->get_plugins_row();

			if ( ! $plugin_row )
				continue;

			switch ( $plugin_row->status ) {
				case 'public':
				case 'unlisted':
					break;
				default:
					continue 2;
			}

			$latest = $plugin_data->get_latest_version();

			if ( $latest ) {
				$parts = explode( '.', $latest );

				$require[ "$plugin_vendor/{$plugin_row->slug}" ] = ">={$parts[0]}." . ( $parts[1] ?? '0' );
			} else {
				$require[ "$plugin_vendor/{$plugin_row->slug}" ] = '*';
			}
		}

		// Only troy-client means no plugins resolved.
		if ( \count( $require ) <= 1 )
			return [];

		return $require;
	}
}
