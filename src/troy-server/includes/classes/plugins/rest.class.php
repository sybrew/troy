<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\{
	get_db_version,
	get_origin_url,
	get_plugin_id_by_post_id,
};

use function Troy\Server\Sanitize\sanitize_slug;

use const Troy\Server\REST_NS;

use Troy\Server\{
	File_Utils,
	Plugins\CPT\Store,
	Zip_Extractor,
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
 * Class Troy\Server\Plugins\REST.
 *
 * @since 0.0.1184
 */
final class REST {

	/**
	 * Register the REST API routes for the Troy Plugins.
	 *
	 * @hook rest_api_init 10
	 * @since 0.0.1184
	 */
	public static function register_rest_routes() {

		$namespace = REST_NS['plugins_manage']['namespace'];
		$base      = REST_NS['plugins_manage']['base'];

		$permission_cb = fn() => \current_user_can( REST_NS['plugins_manage']['access_cap'] );

		// Remit FETCH_CLASS_NAME opcode, which performs a function call to check if it's valid.
		$class = static::class;

		// Register the REST API routes.
		foreach (
			[
				'getEditorStore'     => [ \WP_REST_Server::READABLE, 'get_editor_store' ],
				'registerSlug'       => [ \WP_REST_Server::CREATABLE, 'register_slug' ],
				'processZipFile'     => [ \WP_REST_Server::CREATABLE, 'process_zip_file' ],
				'processZipUrl'      => [ \WP_REST_Server::CREATABLE, 'process_zip_url' ],
				'removeVersion'      => [ \WP_REST_Server::CREATABLE, 'remove_version' ],
				'getReadmeData'      => [ \WP_REST_Server::READABLE, 'get_readme_data' ],
				'getSaveStatus'      => [ \WP_REST_Server::READABLE, 'get_save_status' ],
				'getPlaceholderLogo' => [ \WP_REST_Server::READABLE, 'get_placeholder_logo' ],
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
	 * Get plugin data for Editor Store.
	 *
	 * @rest troy-server/v1/plugins/manage/getEditorStore
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_editor_store( $request ) {

		$post_id = (int) $request->get_param( 'post_id' );

		if ( empty( $post_id ) )
			return new \WP_REST_Response(
				[ 'message' => 'Post ID is required' ],
				400,
			);

		// We simultanously should save the "short_description" as the post excerpt.
		// This is because the post excerpt is used in the Block Editor, enabling easy readouts via SEO plugins etc.
		// We should also store the content in the post content, but then nullify it as we extract it.

		$plugin_id = get_plugin_id_by_post_id( $post_id );

		if ( $plugin_id ) {
			// If no $plugin_id is assigned, assume it's a new post, and do not proceed getting the data.
			$getdata = new Data( $plugin_id );
			$post    = \get_post( $post_id );

			$data_plugins      = $getdata->get_plugins_row();
			$data_metas        = $getdata->get_metas_row();
			$data_infos        = $getdata->get_infos_row();
			$data_zips         = $getdata->get_zips();
			$data_contributors = $getdata->get_contributors();

			// Remit FETCH_OBJ_R opcode calls every time we'd otherwise use $data_plugins->slug hereinafter.
			$slug = $data_plugins->slug;

			$versions = $contributors = [];

			foreach ( $data_zips as $zip )
				$versions[] = [
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
					'download_uri'   => Files::get_plugin_zip_url_by_slug( $slug, $zip->version ),
					'remove'         => false,
				];

			foreach ( $data_contributors as $contributor ) {
				$contributors[] = [
					'user_id' => $contributor->user_id,
					'role'    => $contributor->role,
				];
			}

			// Null/undefined gets merged to defaults in JS reading Store::get_default_plugin_data.
			$data = [
				'plugin_id'         => $plugin_id,
				'name'              => $post->post_title,
				'slug'              => $data_plugins->slug,
				'status'            => $data_plugins->status,
				'author_id'         => $data_metas?->author_id,
				'builder_type'      => $data_metas?->builder_type,
				'versions'          => $versions,
				'permalink'         => $data_metas?->permalink,
				'support_uri'       => $data_metas?->support_uri,
				'short_description' => $data_metas?->short_description,
				'banner_uri'        => $data_infos?->banner_uri,
				'logo_uri'          => $data_metas?->logo_uri,
				'contributors'      => $contributors,
				'contents'          => json_decode( $data_infos?->contents, true ) ?? [],
			];
		} else {
			$data = Store::get_default_plugin_data();
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Register the plugin.
	 *
	 * @rest troy-server/v1/plugins/manage/registerSlug
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function register_slug( $request ) {
		global $wpdb;

		$params = $request->get_params();

		$params['post_id']     = (int) ( $params['post_id'] ?? 0 );
		$params['plugin_slug'] = sanitize_slug( $params['plugin_slug'] ?? '' );

		if ( ! $params['post_id'] || ! $params['plugin_slug'] )
			return new \WP_REST_Response(
				[ 'message' => 'Invalid parameters' ],
				400,
			);

		$existing_data = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, post_id, slug FROM {$wpdb->prefix}troy_plugins WHERE post_id = %d OR slug = %s",
			$params['post_id'],
			$params['plugin_slug'],
		) );

		if ( $existing_data ) {
			// This is no-op for now, cannot be reached since it's blocked in the JS.
			// TODO Create handler to update the existing plugin.
			return new \WP_REST_Response(
				[
					'message'     => $params['post_id'] === (int) $existing_data->post_id
						? \__( 'Plugin Post ID is already registered. Cannot change slug now.', 'troy-server' )
						: \__( 'Plugin slug is already registered. Try another one.', 'troy-server' ),
					'plugin_id'   => (int) $existing_data->id,
					'plugin_slug' => $existing_data->slug,
					'post_id'     => (int) $existing_data->post_id,
				],
				400,
			);
		}

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Insert new plugin
			$wpdb->insert(
				"{$wpdb->prefix}troy_plugins",
				[
					'post_id'          => $params['post_id'],
					'slug'             => $params['plugin_slug'],
					'status'           => 'pending',
					'origin_url'       => get_origin_url(),
					'database_version' => get_db_version(),
				],
				[
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
				],
			);

			$plugin_id = $wpdb->insert_id;

			if ( ! $plugin_id )
				return new \WP_REST_Response(
					[ 'message' => 'Failed to insert plugin record.' ],
					500,
				);

			// Check if data cache exists.
			$existing_cache = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}troy_plugins_data_caches WHERE plugin_id = %d",
				$plugin_id,
			) );
			if ( ! $existing_cache ) {
				// Insert initial cache entry
				// Fun fact: This is the only table that will always have id === plugin_id.
				$wpdb->insert(
					"{$wpdb->prefix}troy_plugins_data_caches",
					[
						'plugin_id'             => $plugin_id,
						'average_rating'        => 0,
						'rating_count'          => 0,
						'recent_average_rating' => 0,
						'recent_rating_count'   => 0,
						'active_install_count'  => 0,
					],
					[
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
					],
				);
			}

			$success = false !== $wpdb->query( 'COMMIT' );
			// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact

			if ( ! $success )
				return new \WP_REST_Response(
					[ 'message' => 'Failed to register plugin.' ],
					500,
				);

			return new \WP_REST_Response(
				[
					'message'     => \__( 'Plugin slug stored successfully.', 'troy-server' ),
					'plugin_id'   => $plugin_id,
					'plugin_slug' => $params['plugin_slug'], // TODO return the real slug accordingly.
					'post_id'     => $params['post_id'],
				],
				200,
			);
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new \WP_REST_Response(
				[ 'message' => 'Failed to register plugin: ' . $e->getMessage() ],
				500,
			);
		}
	}

	/**
	 * Process ZIP file from upload.
	 *
	 * @rest troy-server/v1/plugins/manage/processZipFile
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function process_zip_file( $request ) {

		$plugin_id = $request->get_param( 'plugin_id' );
		$file      = $request->get_file_params()['file'] ?? null;

		if ( ! $plugin_id || empty( $file ) )
			return new \WP_REST_Response(
				[ 'message' => 'Plugin ID and file are required' ],
				400,
			);

		if ( ! isset( $file['tmp_name'], $file['name'] ) )
			return new \WP_REST_Response(
				[ 'message' => 'Invalid file upload' ],
				400,
			);

		if ( ! is_uploaded_file( $file['tmp_name'] ) )
			return new \WP_REST_Response(
				[ 'message' => 'File is not a valid upload' ],
				400,
			);

		// Validate the file type.
		if ( ! \in_array(
			$file['type'],
			[ 'application/zip', 'application/x-zip-compressed', 'multipart/x-zip' ],
			true,
		) ) {
			return new \WP_REST_Response(
				[ 'message' => 'Invalid file type. Only ZIP files are allowed.' ],
				400,
			);
		}

		// Validate the file size.
		if ( isset( $file['size'] ) && $file['size'] > \wp_max_upload_size() )
			return new \WP_REST_Response(
				[ 'message' => 'File size exceeds the maximum limit.' ],
				400,
			);

		// Check for upload errors.
		if ( isset( $file['error'] ) && \UPLOAD_ERR_OK !== $file['error'] ) {
			$error_message = match ( $file['error'] ) {
				\UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
				\UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
				\UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
				\UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
				\UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
				\UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
				\UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
				default                => 'Unknown upload error.',
			};

			return new \WP_REST_Response( [ 'message' => $error_message ], 400 );
		}

		try {
			$uploader = new Zip_Uploader( $plugin_id );
			$uploader->process_via_file( $file['tmp_name'] );
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[ 'message' => 'Failed to process ZIP file: ' . $e->getMessage() ],
				500,
			);
		}

		$data = new Data( $plugin_id, $uploader->version_uploaded );
		$zip  = $data->get_zips_row();

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
					'download_uri'   => Files::get_plugin_zip_url_by_slug(
						$data->get_plugins_row()->slug,
						$zip->version
					),
					'remove'         => false,
				],
			],
			200,
		);
	}

	/**
	 * Process ZIP file from URL.
	 *
	 * @rest troy-server/v1/plugins/manage/processZipUrl
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function process_zip_url( $request ) {

		$plugin_id = $request->get_param( 'plugin_id' );
		$zip_url   = $request->get_param( 'zip_url' );

		if ( ! $plugin_id || ! $zip_url )
			return new \WP_REST_Response(
				[ 'message' => 'Plugin ID and ZIP URL are required' ],
				400,
			);

		try {
			$uploader = new Zip_Uploader( $plugin_id );
			$uploader->process_via_url( $zip_url );
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[ 'message' => 'Failed to parse ZIP file: ' . $e->getMessage() ],
				500,
			);
		}

		$data = new Data( $plugin_id, $uploader->version_uploaded );
		$zip  = $data->get_zips_row();

		return new \WP_REST_Response(
			[
				'message' => 'ZIP file processed successfully',
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
					'download_uri'   => Files::get_plugin_zip_url_by_slug(
						$data->get_plugins_row()->slug,
						$zip->version,
					),
					'remove'         => false,
				],
			],
			200,
		);
	}

	/**
	 * Get readme data.
	 *
	 * @rest troy-server/v1/plugins/manage/getReadmeData
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_readme_data( $request ) {

		$plugin_id = $request->get_param( 'plugin_id' );
		$version   = $request->get_param( 'version' );

		if ( ! $plugin_id || ! $version )
			return new \WP_REST_Response(
				[ 'message' => 'Plugin ID and version are required' ],
				400,
			);

		if ( 'latest' === $version ) {
			$zip_file_path = Files::get_plugin_zip_file_path_latest( $plugin_id );
		} else {
			$zip_file_path = Files::get_plugin_zip_file_path( $plugin_id, $version );
		}

		try {
			$temp_zip_extraction_dir = new Zip_Extractor( $zip_file_path )->temp_zip_extraction_dir;
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[ 'message' => 'Failed to extract ZIP file: ' . $e->getMessage() ],
				500,
			);
		}

		try {
			$parser   = new Readme_Parser( $temp_zip_extraction_dir );
			$headers  = $parser->headers;
			$contents = $parser->contents;
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[ 'message' => 'Failed to parse readme: ' . $e->getMessage() ],
				500,
			);
		}

		return new \WP_REST_Response(
			[
				'headers'  => $headers,
				'contents' => $contents,
			],
			200,
		);
	}

	/**
	 * Get plugin save status.
	 *
	 * @rest troy-server/v1/plugins/manage/getSaveStatus
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_save_status( $request ) {

		$post_id = $request->get_param( 'post_id' );

		if ( ! $post_id )
			return new \WP_REST_Response(
				[ 'message' => 'Post ID is required' ],
				400,
			);

		$status = \get_post_meta( $post_id, 'troy_server_plugin_update_status', true ) ?: [];

		switch ( $status['type'] ?? '' ) {
			case 'updated':
			case 'error':
				$response = [
					'data'   => $status,
					'status' => 200,
				];
				break;
			default:
				$response = [
					'data'   => [
						'message' => \__( 'No status found.', 'troy-server' ),
					],
					'status' => 409,
				];
		}

		// We no longer need the status, so we can delete it.
		\delete_post_meta( $post_id, 'troy_server_plugin_update_status' );

		return new \WP_REST_Response( $response['data'], $response['status'] );
	}

	/**
	 * Remove a plugin version.
	 *
	 * @rest troy-server/v1/plugins/manage/removeVersion
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 * @throws \Exception If the version cannot be removed.
	 */
	public static function remove_version( $request ) {
		global $wpdb;

		$plugin_id = $request->get_param( 'plugin_id' );
		$version   = $request->get_param( 'version' );

		if ( ! $plugin_id || ! $version )
			return new \WP_REST_Response(
				[ 'message' => 'Plugin ID and version are required' ],
				400,
			);

		// Get the ZIP record first to get the file path
		$zip_record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}troy_plugins_zips WHERE plugin_id = %d AND version = %s",
				$plugin_id,
				$version,
			),
		);

		if ( ! $zip_record )
			return new \WP_REST_Response(
				[ 'message' => 'Version not found' ],
				400,
			);

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Delete from database
			$wpdb->delete(
				"{$wpdb->prefix}troy_plugins_zips",
				[
					'plugin_id' => $plugin_id,
					'version'   => $version,
				],
				[
					'%d',
					'%s',
				],
			);

			$success = false !== $wpdb->query( 'COMMIT' );

			if ( ! $success )
				return new \WP_REST_Response(
					[ 'message' => 'Version not not deleted' ],
					400,
				);

			File_Utils::clean_dir_recursively(
				\dirname( Files::get_plugin_zip_file_path( $plugin_id, $version ) ),
			);

			return new \WP_REST_Response(
				[
					'message' => 'Version removed successfully',
					'version' => $version,
				],
				200,
			);
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new \WP_REST_Response(
				[ 'message' => 'Failed to remove version: ' . $e->getMessage() ],
				500,
			);
		}
	}

	/**
	 * Get placeholder logo for the plugin.
	 *
	 * @rest troy-server/v1/plugins/manage/getPlaceholderLogo
	 * @since 0.0.1184
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public static function get_placeholder_logo( $request ) {

		$width  = (int) $request->get_param( 'width' ) ?: 512;
		$height = (int) $request->get_param( 'height' ) ?: 512;

		// Ensure we have GD extension
		if ( ! \extension_loaded( 'gd' ) )
			return new \WP_REST_Response(
				[ 'message' => 'GD extension is not enabled.' ],
				500,
			);

		// Create image
		$image = \imagecreatetruecolor( $width, $height );

		if ( ! $image )
			return new \WP_REST_Response(
				[ 'message' => 'Failed to create image resource.' ],
				500,
			);

		// Enable alpha blending
		\imagealphablending( $image, true );
		\imagesavealpha( $image, true );

		// Calculate scale factors from base SVG of 512x512
		$scale_x = $width / 512;
		$scale_y = $height / 512;

		// Helper function to scale point arrays
		$scale_points = fn( $points ) => \array_map(
			fn( $value, $index ) => (int) ( $index & 1
				? $value * $scale_y
				: $value * $scale_x ),
			$points,
			\array_keys( $points ),
		);

		// Helper function to add random variation to points
		$randomize_points = fn( $points, $variation = 5 ) => \array_map(
			fn( $value ) => $value + mt_rand( -$variation, $variation ),
			$points,
			\array_keys( $points ),
		);

		// Fill background
		\imagefill(
			$image,
			0,
			0,
			\imagecolorallocate(
				$image,
				mt_rand( 175, 225 ), // 255-80, 255-30
				mt_rand( 165, 215 ), // 255-90, 255-40
				mt_rand( 170, 220 ), // 255-85, 255-35
			),
		);

		$body_color   = \imagecolorallocatealpha(
			$image,
			mt_rand( 0, 75 ),    // 255-255, 255-180
			mt_rand( 75, 135 ),  // 255-180, 255-120
			mt_rand( 35, 105 ),  // 255-220, 255-150
			mt_rand( 20, 50 ),
		);
		$accent_color = \imagecolorallocatealpha(
			$image,
			mt_rand( 0, 55 ),    // 255-255, 255-200
			mt_rand( 105, 155 ), // 255-150, 255-100
			mt_rand( 25, 95 ),   // 255-230, 255-160
			mt_rand( 30, 70 ),
		);

		// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine -- let's not.
		// Body and head
		\imagefilledpolygon(
			$image,
			$scale_points( $randomize_points(
				[
					471.82, 171.1, 459.8, 157.49, 443.37, 121.01, 441.78, 110.92,
					435.07, 106.86, 418.28, 89.36, 418.28, 89.36, 427.45, 81.78,
					424.82, 72.04, 424.82, 72.04, 423.72, 78.74, 406.44, 80.34,
					395.34, 81.37, 391.57, 85.11, 391.57, 85.11, 391.52, 85.15,
					391.47, 85.15, 387.03, 81.87, 376.94, 81.96, 365.95, 85.34,
					349.77, 88.08, 334.7, 95.77, 322.96, 107.54, 308.09, 122.42,
					299.72, 142.56, 299.67, 163.59, 299.62, 185.57, 281.87, 203.55,
					259.89, 203.55, 141.65, 203.55, 119.68, 203.55, 101.87, 221.36,
					101.87, 241.33, 101.87, 440.22, 141.65, 440.22, 141.65, 340.77, // left leg start
					// 152.2, 340.77, 162.32, 336.57, 169.77, 329.12, 177.23, 321.66, // hip start -- commented because it tends to create a penis
					181.42, 311.54, 181.42, 301.01, 181.42, 290.48, 203.29, 309.06, // belly start
					231.61, 320.9, 261.17, 320.9, 274.73, 320.82, 288.29, 318.41, // right leg start
					301.05, 313.81, 301.05, 440.24, 340.83, 440.24, 340.83, 291.11,
					366.06, 268.56, 380.51, 236.34, 380.61, 202.49, 380.61, 202.47,
					380.61, 162.71, 381.06, 162.71, 381.48, 162.67, 381.9, 162.61, // chin start
					383.93, 167.7, 390.23, 175.96, 409.74, 179.84, 439.07, 185.67,
					437.35, 194.39, 438.9, 200.51, 441.29, 209.96, 459.19, 209.66,
					// 463.02, 202.63, 464.61, 199.71, 470.71, 202.53, 473.19, 199.18, // mouth start
					// 474.41, 197.46, 473.8, 193.96, 476.37, 191.95, 483.46, 186.48, // -- commented for it creates too much noise
					481.28, 181.81, 471.82, 171.1,
				],
				mt_rand( 0, 15 ),
			) ),
			$body_color,
		);
		// Foot
		\imagefilledpolygon(
			$image,
			$scale_points( $randomize_points(
				[
					368.39, 313.98, 368.39, 313.98, 400.68, 313.98, 400.68, 322.44,
					397.31, 339.02, 391.32, 345.01, 385.33, 350.99, 377.21, 354.36,
					368.75, 354.36, 368.75, 386.28, 385.67, 386.28, 401.91, 380.04,
					413.88, 368.07, 425.85, 356.1, 432.59, 339.86, 432.59, 322.94,
					432.59, 291.02, 400.68, 291.02, 400.31, 291.02, 382.68, 282.06,
					368.39, 296.35, 368.39, 313.98,
				],
				mt_rand( 0, 5 ),
			) ),
			$accent_color,
		);
		// Tail
		\imagefilledpolygon(
			$image,
			$scale_points( $randomize_points(
				[
					33.93, 242.28, 33.93, 361.62, 73.71, 361.62, 73.71, 202.5,
					73.71, 202.5, 51.74, 202.5, 33.93, 220.31, 33.93, 242.28,
				],
				mt_rand( 0, 15 ),
			) ),
			$accent_color,
		);
		// phpcs:enable WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine

		// Make random rectangle coordinates, opposing the sides
		$flip_rect_bias_x = mt_rand( 0, 1 );
		$flip_rect_bias_y = mt_rand( 0, 1 );
		for ( $i = mt_rand( 1, 2 ); $i--; ) {
			$biased_x = $flip_rect_bias_x
				? mt_rand( 275, 450 ) // bias right
				: mt_rand( 50, 175 ); // bias left

			$biased_y = $flip_rect_bias_y
				? mt_rand( 275, 450 ) // bias bottom
				: mt_rand( 50, 175 ); // bias top

			// Toggle bias for next rectangle
			$flip_rect_bias_x = ! $flip_rect_bias_x;
			$flip_rect_bias_y = ! $flip_rect_bias_y;

			// Generate dimensions with rectangular bias
			$_width  = mt_rand( 60, 180 );
			$_height = mt_rand( 20, 80 );

			// Randomly swap width/height to create both horizontal and vertical rectangles
			if ( mt_rand( 0, 1 ) ) {
				$__width = $_width; // Store original width
				$_width  = $_height;
				$_height = $__width;
			}

			$random_rect = [
				$biased_x,
				$biased_y,
				$_width,
				$_height,
			];

			$scaled_rect = \array_map(
				fn( $value, $index ) => (int) ( $value * (
					$index & 1 ? $scale_y : $scale_x
				) ),
				$random_rect,
				\array_keys( $random_rect )
			);

			$rect_color = \imagecolorallocatealpha(
				$image,
				mt_rand( 100, 255 ),
				mt_rand( 100, 255 ),
				mt_rand( 100, 255 ),
				mt_rand( 40, 80 ),
			);

			\imagefilledrectangle(
				$image,
				$scaled_rect[0],
				$scaled_rect[1],
				$scaled_rect[0] + $scaled_rect[2],
				$scaled_rect[1] + $scaled_rect[3],
				$rect_color,
			);
		}

		// Add random burning rectangle
		$burn_color = \imagecolorallocatealpha(
			$image,
			mt_rand( 0, 100 ),
			mt_rand( 0, 100 ),
			mt_rand( 0, 100 ),
			mt_rand( 70, 120 ),
		);

		$burn_rect = \array_map(
			fn( $val ) => (int) ( $val * ( $scale_x + $scale_y ) / 2 ),
			[
				mt_rand( 50, 300 ),
				mt_rand( 50, 300 ),
				mt_rand( 60, 120 ),
				mt_rand( 60, 120 ),
			]
		);

		\imagefilledrectangle(
			$image,
			$burn_rect[0],
			$burn_rect[1],
			$burn_rect[0] + $burn_rect[2],
			$burn_rect[1] + $burn_rect[3],
			$burn_color,
		);

		// Add 2-4 random color circles biased toward top half
		for ( $i = mt_rand( 2, 4 ); $i--; ) {
			$circle_color = \imagecolorallocatealpha(
				$image,
				mt_rand( 150, 255 ),
				mt_rand( 150, 255 ),
				mt_rand( 50, 150 ),
				mt_rand( 50, 90 ),
			);

			// Bias Y position toward top half with weighted random
			$y_bias = mt_rand( 1, 100 );
			$y_pos  = $y_bias <= 70
				? mt_rand( 50, 200 )   // 70% chance in upper area
				: mt_rand( 150, 300 ); // 30% chance in middle area

			$circle_data = \array_map(
				fn( $val ) => (int) ( $val * ( $scale_x + $scale_y ) / 2 ),
				[
					mt_rand( 80, 350 ), // x position
					$y_pos,             // biased y position
					mt_rand( 25, 60 ),  // radius
				]
			);

			\imagefilledellipse(
				$image,
				$circle_data[0],
				$circle_data[1],
				$circle_data[2] * 2,
				$circle_data[2] * 2,
				$circle_color,
			);
		}

		// Add texture effects
		\imagesetthickness(
			$image,
			(int) max( 1, 3 * ( $scale_x + $scale_y ) / 2 ),
		);

		// Add 2-4 random arcs
		for ( $i = mt_rand( 2, 4 ); $i--; ) {
			$start_angle = mt_rand( 0, 360 );
			$arc_length  = mt_rand( 30, 180 ); // Control arc length to avoid full circles
			$end_angle   = $start_angle + $arc_length;

			$curve = [
				mt_rand( 50, 450 ),  // x center
				mt_rand( 50, 450 ),  // y center
				mt_rand( 120, 300 ), // width (increased for more elliptical)
				mt_rand( 20, 60 ),   // height (decreased for more linear)
				$start_angle,        // start angle
				$end_angle,          // end angle
			];

			$randomized_curve = $randomize_points( $curve, 15 );
			$scaled_curve     = $scale_points( $randomized_curve );
			\imagearc(
				$image,
				$scaled_curve[0],
				$scaled_curve[1],
				$scaled_curve[2],
				$scaled_curve[3],
				$scaled_curve[4],
				$scaled_curve[5],
				\imagecolorallocatealpha(
					$image,
					mt_rand( 180, 255 ), // higher reds for gold
					mt_rand( 140, 200 ), // moderate greens for gold
					mt_rand( 50, 120 ),  // lower blues for gold
					mt_rand( 60, 100 ),
				),
			);
		}

		// Add random sun
		\imagefilledrectangle(
			$image,
			(int) ( $width * 0.85 ),
			0,
			$width - 1,
			(int) ( $height * 0.15 ),
			\imagecolorallocatealpha(
				$image,
				mt_rand( 200, 255 ), // bias toward yellows/oranges for sun
				mt_rand( 150, 220 ), // moderate yellows for sun warmth
				mt_rand( 50, 120 ),  // lower blues for sun colors
				mt_rand( 20, 60 ),
			),
		);

		// Add random noise
		for ( $i = 0; $i < 1000; $i++ )
			\imagesetpixel(
				$image,
				mt_rand( 0, $width - 1 ),
				mt_rand( 0, $height - 1 ),
				\imagecolorallocatealpha(
					$image,
					mt_rand( 0, 255 ),
					mt_rand( 0, 255 ),
					mt_rand( 0, 255 ),
					mt_rand( 50, 100 ),
				)
			);

		// Add 2-4 random boxed lines
		for ( $i = mt_rand( 2, 4 ); $i--; ) {
			// Randomly choose border width
			$border_width = mt_rand( 10, 25 );

			// Randomly choose border color
			$border_color = \imagecolorallocatealpha(
				$image,
				mt_rand( 0, 255 ),
				mt_rand( 0, 255 ),
				mt_rand( 0, 255 ),
				mt_rand( 50, 100 ),
			);

			// Draw rectangle with max 1/3rd of the image size
			$rect_width  = mt_rand( 10, $width / 3 );
			$rect_height = mt_rand( 10, $height / 3 );
			$rect_x      = mt_rand( 0, $width - $rect_width );
			$rect_y      = mt_rand( 0, $height - $rect_height );

			\imagerectangle(
				$image,
				$rect_x,
				$rect_y,
				$rect_x + $rect_width,
				$rect_y + $rect_height,
				$border_color,
			);
		}

		// Add 2 random canvas borders around the image, consider scaling
		for ( $i = mt_rand( 1, 4 ); $i--; ) {
			// Randomly choose border width (scaled)
			$border_width = mt_rand( 5, 15 ) * ( $scale_x + $scale_y ) / 2;

			// Randomly choose border color
			$border_color = \imagecolorallocatealpha(
				$image,
				...(
					60 >= mt_rand( 1, 100 )
						? [ // 60% bronze
							mt_rand( 139, 205 ),
							mt_rand( 69, 115 ),
							mt_rand( 19, 69 ),
						]
						: (
							70 >= mt_rand( 1, 100 )
								? [  // 28% wood
									mt_rand( 101, 160 ),
									mt_rand( 67, 101 ),
									mt_rand( 33, 67 ),
								]
								: [ // 12% white
									mt_rand( 220, 255 ),
									mt_rand( 220, 255 ),
									mt_rand( 220, 255 ),
								]
						)
				),
				// Workaround the positional argument unpacking issue
				...[ mt_rand( 50, 100 ) ],
			);

			// Draw border rectangle that touches image edges
			for ( $thickness = 0; $thickness < $border_width; $thickness++ ) {
				\imagerectangle(
					$image,
					$thickness,
					$thickness,
					$width - 1 - $thickness,
					$height - 1 - $thickness,
					$border_color,
				);
			}
		}

		// Start output buffering to capture the image data
		\ob_start();
		\imagepng( $image, null, 6 );
		$image_data = \ob_get_clean();

		// Destroy the image resource
		\imagedestroy( $image );

		// Return raw image data for JS to handle as blob
		return new \WP_REST_Response(
			[
				// phpcs:ignore, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WP_REST breaks the encoding.
				'image_data' => base64_encode( $image_data ),
				'mime_type'  => 'image/png',
			],
			200,
		);
	}
}
