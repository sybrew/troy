<?php
/**
 * @package Troy\Server\Integrations\Plugins
 * @access  private
 */

namespace Troy\Server\Integrations\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	PLUGINS_CPT,
	REST_NS,
};

use Troy\Server\{
	API,
	Plugins, // A namesake import is valid; we're relative to \, not \Plugins.
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
 * Class Troy\Server\Integrations\Plugins\REST.
 *
 * Handles all plugin integration REST API endpoints (GitHub, WordPress.org, etc.).
 *
 * @since 0.0.1184
 */
final class REST {

	/**
	 * Register REST routes for all plugin integrations.
	 *
	 * @hook rest_api_init 10
	 * @since 0.0.1184
	 */
	public static function register_rest_routes() {

		$namespace = REST_NS['plugins_integrations']['namespace'];
		$base      = REST_NS['plugins_integrations']['base'];

		$permission_cb = fn() => \current_user_can( REST_NS['plugins_integrations']['access_cap'] );

		$class = self::class;

		foreach (
			[
				// Core integration management
				'connect'      => [ \WP_REST_Server::CREATABLE, 'connect' ],
				'disconnect'   => [ \WP_REST_Server::DELETABLE, 'disconnect' ],
				'reveal-token' => [ \WP_REST_Server::READABLE, 'reveal_token' ],

				// Universal tag endpoints
				'tags/get'     => [ \WP_REST_Server::READABLE, 'get_tags' ],
				'tags/refresh' => [ \WP_REST_Server::CREATABLE, 'refresh_tags' ],
				'tags/process' => [ \WP_REST_Server::CREATABLE, 'process_tag' ],
			]
			as $type => [ $methods, $cb ]
		) {
			\register_rest_route(
				$namespace,
				"$base/$type",
				[
					'methods'             => $methods,
					'callback'            => [ $class, $cb ],
					'permission_callback' => $permission_cb,
				],
			);
		}
	}

	/**
	 * Reveal GitHub PAT (personal access token) for a plugin.
	 *
	 * @rest troy-server/v1/plugins/integrations/reveal-token GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function reveal_token( $request ) {

		$plugin_id = (int) $request->get_param( 'plugin_id' );

		if ( ! $plugin_id )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Plugin ID is required.', 'troy-server' ) ],
				400,
			);

		$integration = new Plugins\Data( $plugin_id )->get_integration( [ 'get_auth' => true ] );

		if ( ! $integration || 'github' !== $integration->mode )
			return new \WP_REST_Response(
				[ 'message' => \__( 'No GitHub integration found.', 'troy-server' ) ],
				400,
			);

		return new \WP_REST_Response(
			[ 'token' => $integration->auth->token->value ?? '' ],
			200,
		);
	}

	/**
	 * Connect integration.
	 *
	 * @rest troy-server/v1/plugins/integrations/connect POST
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function connect( $request ) {

		$plugin_id = (int) $request->get_param( 'plugin_id' );

		if ( ! $plugin_id )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Plugin ID is required.', 'troy-server' ) ],
				400,
			);

		$mode     = \sanitize_key( $request->get_param( 'mode' ) );
		$settings = $request->get_param( 'settings' );

		switch ( $mode ) {
			case 'github':
				if ( empty( $settings['owner_repo'] ) )
					return new \WP_REST_Response(
						[ 'message' => \__( 'Repository URL is required.', 'troy-server' ) ],
						400,
					);

				[
					'owner' => $owner,
					'repo'  => $repo,
				] = Repos\GitHub::parse_repo_url( $settings['owner_repo'] );

				if ( empty( $owner ) || empty( $repo ) )
					return new \WP_REST_Response(
						[ 'message' => \__( 'Invalid repository URL.', 'troy-server' ) ],
						400,
					);

				$connect_results = Repos\GitHub::connect(
					$plugin_id,
					[
						'owner_repo' => "$owner/$repo",
						'pat'        => $settings['pat'] ?? '',
					],
				);
				break;

			case 'wporg':
				if ( empty( $settings['slug'] ) )
					return new \WP_REST_Response(
						[ 'message' => \__( 'Plugin slug is required for WordPress.org integration.', 'troy-server' ) ],
						400,
					);

				// Our requirements are more stringent than WordPress.org's; TODO: create Repos\WPOrg::parse_slug()?
				$slug = API\Sanitize::slug( $settings['slug'] );

				if ( empty( $slug ) )
					return new \WP_REST_Response(
						[ 'message' => \__( 'Invalid plugin slug.', 'troy-server' ) ],
						400,
					);

				$connect_results = Repos\WPOrg::connect(
					$plugin_id,
					[ 'slug' => $slug ],
				);
				break;

			default:
				return new \WP_REST_Response(
					[ 'message' => \__( 'Unsupported integration mode.', 'troy-server' ) ],
					400,
				);
		}

		if ( ! $connect_results['success'] )
			return new \WP_REST_Response(
				[
					'message' => ( $connect_results['error'] ?? '' )
						?: \__( 'Failed to save integration settings.', 'troy-server' ),
				],
				500,
			);

		$integration = new Plugins\Data( $plugin_id )->get_integration();

		return new \WP_REST_Response(
			[
				'mode'           => $integration->mode,
				'settings'       => $integration->settings,
				'auto_process'   => $integration->auto_process ?? 'all',
				'tags'           => $integration->tags,
				'tags_refreshed' => $integration->tags_refreshed,
			],
			200,
		);
	}

	/**
	 * Disconnect integration.
	 *
	 * @rest troy-server/v1/plugins/integrations/disconnect DELETE
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function disconnect( $request ) {

		$plugin_id = (int) $request->get_param( 'plugin_id' );

		if ( ! $plugin_id )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Plugin ID is required.', 'troy-server' ) ],
				400,
			);

		$deleted = Store::disconnect( $plugin_id );

		if ( ! $deleted ) {
			$integration = new Plugins\Data( $plugin_id )->get_integration();

			if ( $integration )
				return new \WP_REST_Response(
					[ 'message' => \__( 'Failed to disconnect integration.', 'troy-server' ) ],
					500,
				);
		}

		return new \WP_REST_Response(
			[ 'disconnected' => true ],
			200,
		);
	}

	/**
	 * Get cached tags for any integration type.
	 *
	 * @rest troy-server/v1/plugins/integrations/tags/get GET
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_tags( $request ) {

		$plugin_id = (int) $request->get_param( 'plugin_id' );

		if ( ! $plugin_id )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Plugin ID is required.', 'troy-server' ) ],
				400,
			);

		$integration = new Plugins\Data( $plugin_id )->get_integration();

		if ( ! $integration )
			return new \WP_REST_Response(
				[ 'message' => \__( 'No integration configured.', 'troy-server' ) ],
				400,
			);

		return new \WP_REST_Response(
			[
				'tags'           => $integration->tags,
				'tags_refreshed' => $integration->tags_refreshed
					? strtotime( $integration->tags_refreshed )
					: null,
			],
			200,
		);
	}

	/**
	 * Refresh tags from external source.
	 *
	 * @rest troy-server/v1/plugins/integrations/tags/refresh POST
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function refresh_tags( $request ) {

		$plugin_id = (int) $request->get_param( 'plugin_id' );

		if ( ! $plugin_id )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Plugin ID is required.', 'troy-server' ) ],
				400,
			);

		$integration = new Plugins\Data( $plugin_id )->get_integration( [ 'get_auth' => true ] );

		if ( ! $integration )
			return new \WP_REST_Response(
				[ 'message' => \__( 'No integration configured.', 'troy-server' ) ],
				400,
			);

		$tags = null;

		switch ( $integration->mode ) {
			case 'github':
				$tags = Repos\GitHub::find_tags(
					$integration->settings->owner_repo,
					$integration->auth->token->value ?? '',
				);

				if ( \is_wp_error( $tags ) )
					return new \WP_REST_Response(
						[
							'message' => $tags->get_error_message(),
							'code'    => $tags->get_error_code(),
						],
						500,
					);

				break;

			case 'wporg':
				$tags = Repos\WPOrg::find_tags( $integration->settings->slug );

				if ( \is_wp_error( $tags ) )
					return new \WP_REST_Response(
						[
							'message' => $tags->get_error_message(),
							'code'    => $tags->get_error_code(),
						],
						500,
					);

				break;

			default:
				return new \WP_REST_Response(
					[ 'message' => \__( 'Unsupported integration mode.', 'troy-server' ) ],
					400,
				);
		}

		if ( ! Store::update_tags( $plugin_id, $integration->mode, $tags ) )
			return new \WP_REST_Response(
				[ 'message' => \__( 'Failed to update tags.', 'troy-server' ) ],
				500,
			);

		// $tags are already sanitized in find_tags().
		// Because the Store succeeded, we can safely assume "current_time" here. UTC datetime.
		return new \WP_REST_Response(
			[
				'tags'           => $tags,
				'tags_refreshed' => \current_time( 'mysql' ), // Use local time via wp_timezone().
			],
			200,
		);
	}

	/**
	 * Process tag from integration.
	 *
	 * @rest troy-server/v1/plugins/integrations/tags/process POST
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object. {
	 *     @type int    $plugin_id       The plugin post ID.
	 *     @type string $package_version The package version name from the tag (not the plugin header version).
	 * }
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function process_tag( $request ) {

		$plugin_id    = (int) $request->get_param( 'plugin_id' );
		$package_version = \sanitize_text_field( $request->get_param( 'package_version' ) );

		if ( ! $plugin_id )
			return new \WP_Error(
				'missing_plugin_id',
				\__( 'Plugin ID is required.', 'troy-server' ),
				[ 'status' => 400 ],
			);

		if ( ! $package_version )
			return new \WP_Error(
				'missing_package_version',
				\__( 'Version name is required.', 'troy-server' ),
				[ 'status' => 400 ],
			);

		$integration = new Plugins\Data( $plugin_id )->get_integration( [ 'get_auth' => true ] );

		if ( ! $integration )
			return new \WP_Error(
				'no_integration',
				\__( 'No integration configured for this plugin.', 'troy-server' ),
				[ 'status' => 400 ],
			);

		if ( empty( $integration->tags->$package_version ) )
			return new \WP_Error(
				'package_version_not_found',
				\__( 'The specified package version was not found in stored tags.', 'troy-server' ),
				[ 'status' => 400 ],
			);

		$download_url = $integration->tags->$package_version->download_url ?? null;

		if ( ! $download_url )
			return new \WP_Error(
				'missing_download_url',
				\__( 'Download URL not found for this package version.', 'troy-server' ),
				[ 'status' => 500 ],
			);

		try {
			try {
				$uploader = new Plugins\Zip_Uploader( $plugin_id );
				$uploader->process_via_url(
					$download_url,
					[
						'headers'     => (array) ( $integration->auth->download->headers ?? [] ), // headers is likely an object.
						'queryParams' => (array) ( $integration->auth->download->queryParams ?? [] ), // queryParams is likely an object.
					],
				);
			} catch ( \Exception $e ) {
				return new \WP_REST_Response(
					[ 'message' => "Failed to parse ZIP file: {$e->getMessage()}" ],
					500,
				);
			}

			$data = new Plugins\Data( $plugin_id, $uploader->version_uploaded );
			$zip  = $data->get_zips_row();

			if ( ! $zip )
				return new \WP_REST_Response(
					[ 'message' => \__( 'ZIP file was processed but failed to store in the database.', 'troy-server' ) ],
					500,
				);

			$zip_existed = $uploader->zip_existed;

			return new \WP_REST_Response(
				[
					'message' => $zip_existed
						? \sprintf(
							/* translators: %s is the version number of the plugin ZIP file. */
							\esc_html__( 'ZIP file for version %s was already present and has been updated.', 'troy-server' ),
							\esc_html( $zip->version ),
						)
						: \sprintf(
							/* translators: %s is the version number of the plugin ZIP file. */
							\esc_html__( 'ZIP file for version %s has been processed successfully.', 'troy-server' ),
							\esc_html( $zip->version ),
						),
					'version' => [
						'version'        => $zip->version,
						'type'           => $zip->type ?? 'unreleased',
						'file_size'      => $zip->file_size,
						'tested_wp'      => $zip->tested_wp,
						'requires_wp'    => $zip->requires_wp,
						'requires_php'   => $zip->requires_php,
						'repo'           => $zip->repo,
						'dependencies'   => $zip->dependencies,
						'upgrade_notice' => $zip->upgrade_notice,
						'origin_url'     => $zip->origin_url,
						'created_at'     => $zip->created_at,
						'updated_at'     => $zip->updated_at,
						'download_uri'   => Plugins\Files::get_plugin_zip_url_by_slug(
							$data->get_plugins_row()->slug,
							$zip->version,
						),
						'remove'         => false,
					],
				],
				200,
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'process_failed',
				\sprintf(
					/* translators: %s: Error message */
					\__( 'Failed to process tag: %s', 'troy-server' ),
					$e->getMessage(),
				),
				[ 'status' => 500 ],
			);
		}
	}
}
