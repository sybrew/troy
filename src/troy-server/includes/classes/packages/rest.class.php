<?php
/**
 * @package Troy\Server\Packages
 * @access  private
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\REST_NS;

use Troy\Server\API;

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
}
