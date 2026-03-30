<?php
/**
 * @package Troy\Server\Packages
 * @access  private
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\REST_NS;

use Troy\Server\{
	API,
	Plugins\Data as Plugin_Data,
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
 * Class Troy\Server\Packages\REST.
 *
 * Handles REST API endpoints for packages.
 *
 * @since 0.0.1184
 */
final class REST {

	/**
	 * Register the REST API routes for the Troy Packages.
	 *
	 * @hook rest_api_init 10
	 * @since 0.0.1184
	 */
	public static function register_rest_routes() {

		$namespace = REST_NS['packages_manage']['namespace'];
		$base      = REST_NS['packages_manage']['base'];

		$permission_cb = fn() => \current_user_can( REST_NS['packages_manage']['access_cap'] );

		\register_rest_route(
			$namespace,
			"$base/validateSlug",
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'validate_slug' ],
				'permission_callback' => $permission_cb,
			],
		);

		\register_rest_route(
			$namespace,
			"$base/composerSnippet",
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'composer_snippet' ],
				'permission_callback' => $permission_cb,
			],
		);
	}

	/**
	 * Validates a package slug for conflicts.
	 *
	 * Checks if the slug is already taken by another plugin or package.
	 *
	 * @rest troy-server/v1/packages/manage/validateSlug GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function validate_slug( $request ) {

		$slug       = API\Sanitize::slug( $request->get_param( 'slug' ) ?? '' );
		$package_id = (int) ( $request->get_param( 'package_id' ) ?? 0 );

		if ( ! $slug )
			return new \WP_REST_Response(
				[
					'valid'   => false,
					'message' => \__( 'Invalid slug.', 'troy-server' ),
				],
				400,
			);

		$slug_checker  = new API\Slug( $slug, 'package', $package_id );
		$conflict_type = $slug_checker->conflict_type;

		if ( $conflict_type )
			return new \WP_REST_Response(
				[
					'valid'         => false,
					'conflict_type' => $conflict_type,
					'message'       => \sprintf(
						/* translators: %s: Conflict type (plugin or package) */
						\__( 'This slug is already taken by a %s.', 'troy-server' ),
						$conflict_type,
					),
				],
				200,
			);

		return new \WP_REST_Response(
			[
				'valid'   => true,
				'message' => \__( 'Slug is available.', 'troy-server' ),
			],
			200,
		);
	}

	/**
	 * Returns Composer setup data for a package.
	 *
	 * Builds the repository URLs, package name, and require map
	 * needed to generate user-facing Composer setup instructions.
	 *
	 * @rest troy-server/v1/packages/manage/composerSnippet GET
	 * @since 1.7.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function composer_snippet( $request ) {

		$package_id = (int) ( $request->get_param( 'package_id' ) ?? 0 );

		if ( ! $package_id )
			return new \WP_REST_Response(
				[ 'error' => \__( 'Missing package ID.', 'troy-server' ) ],
				400,
			);

		$package_data = new Data( $package_id );
		$package      = $package_data->get_packages_row();

		if ( ! $package || 'active' !== $package->status )
			return new \WP_REST_Response(
				[ 'error' => \__( 'Package not found or inactive.', 'troy-server' ) ],
				404,
			);

		$metas = $package_data->get_metas_row();

		if ( ! $metas || empty( $metas->plugins ) )
			return new \WP_REST_Response(
				[ 'error' => \__( 'Package has no plugins.', 'troy-server' ) ],
				404,
			);

		$vendor_base   = API\Server::get_composer_vendor();
		$vendor        = "$vendor_base-package";
		$plugin_vendor = "$vendor_base-plugin";
		$package_name  = "$vendor/{$package->slug}";
		$repo_url      = API\Server::get_full_repo_url() . 'composer/get';

		$require = [ 'deploytroy-plugin/troy-client' => '>=1.0' ];

		foreach ( $metas->plugins as $entry ) {
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

		if ( \count( $require ) <= 1 )
			return new \WP_REST_Response(
				[ 'error' => \__( 'No resolvable plugins in this package.', 'troy-server' ) ],
				404,
			);

		return new \WP_REST_Response(
			[
				'packageName' => $package_name,
				'repoUrl'     => $repo_url,
				'require'     => $require,
			],
			200,
		);
	}
}
