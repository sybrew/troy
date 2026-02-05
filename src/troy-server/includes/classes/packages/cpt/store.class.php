<?php
/**
 * @package Troy\Server\Packages\CPT
 * @access  private
 */

namespace Troy\Server\Packages\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	DB_VERSION,
	PACKAGES_CPT,
};

use Troy\Server\API;
use Troy\Server\Packages\{
	Zip_Builder,
	Data,
	Drop,
	Files,
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
 * Class Troy\Server\Packages\CPT\Store.
 *
 * Handles the storage of package data in the PACKAGES_CPT custom post type.
 *
 * @since 0.0.1184
 */
final class Store {

	/**
	 * The package save nonce.
	 *
	 * @since 0.0.1184
	 */
	public const SAVE_NONCE = [
		'name'   => '_troy_server_package_save_nonce',
		'action' => '_troy_server_package_save',
	];

	/**
	 * Outputs the save nonce field for package forms.
	 *
	 * @hook edit_form_top 10
	 * @since 0.0.1184
	 *
	 * @param \WP_Post $post The post object.
	 * @return void
	 */
	public static function output_save_nonce( $post ) {

		if ( PACKAGES_CPT !== $post->post_type )
			return;

		\wp_nonce_field( self::SAVE_NONCE['action'], self::SAVE_NONCE['name'], false );
	}

	/**
	 * Displays admin notices for package save operations.
	 *
	 * @hook admin_notices 10
	 * @since 0.0.1184
	 *
	 * @return void
	 */
	public static function display_admin_notices() {

		$screen = \get_current_screen();

		if ( 'post' !== $screen->base || PACKAGES_CPT !== $screen->post_type )
			return;

		$post_id = \get_the_ID();

		if ( ! $post_id )
			return;

		$notices = \get_post_meta( $post_id, '_troy_server_package_update_status', true ) ?: [];

		if ( empty( $notices ) )
			return;

		$persistent_notices = [];

		foreach ( $notices as $notice ) {
			if ( empty( $notice['message'] ) )
				continue;

			$type    = $notice['type'] ?? 'error';
			$message = $notice['message'];

			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				\esc_attr( $type ),
				\esc_html( $message ),
			);

			// Preserve error and warning notices for re-display
			if ( 'error' === $type || 'warning' === $type )
				$persistent_notices[] = $notice;
		}

		// Only keep persistent notices (errors/warnings), clear everything else
		if ( empty( $persistent_notices ) ) {
			\delete_post_meta( $post_id, '_troy_server_package_update_status' );
		} elseif ( \count( $persistent_notices ) !== \count( $notices ) ) {
			\update_post_meta( $post_id, '_troy_server_package_update_status', $persistent_notices );
		}
	}

	/**
	 * Filters out default post update messages when we have custom notices.
	 *
	 * @hook post_updated_messages 10
	 * @since 0.0.1184
	 *
	 * @param array $messages Post update messages array.
	 * @return array Modified messages array.
	 */
	public static function filter_post_updated_messages( $messages ) {

		$post_id = \get_the_ID();

		if ( ! $post_id || PACKAGES_CPT !== \get_post_type( $post_id ) )
			return $messages;

		$notices = \get_post_meta( $post_id, '_troy_server_package_update_status', true ) ?: [];

		if ( ! empty( $notices ) )
			$messages[ PACKAGES_CPT ] = array_fill( 1, 10, '' );

		return $messages;
	}

	/**
	 * Prevents the post from being marked as empty.
	 * We do this so other actions can run for the post.
	 *
	 * This ought not to be necessary, since the post doesn't support excerpts
	 * or even the editor, but this is for future-proofing when the post store
	 * becomes more sane.
	 *
	 * @hook wp_insert_post_empty_content 10
	 * @since 0.0.1184
	 *
	 * @param bool  $maybe_empty Whether the post is empty.
	 * @param array $postarr     The post data.
	 * @return bool Whether the post is empty.
	 */
	public static function unset_empty_post( $maybe_empty, $postarr ) {

		if ( PACKAGES_CPT === $postarr['post_type'] )
			return false;

		return $maybe_empty;
	}

	/**
	 * Gets the default package data.
	 *
	 * @since 0.0.1184
	 *
	 * @return array The default package data.
	 */
	public static function get_default_package_data() {
		return [
			'package_id'               => 0,
			'plugin_uri'               => API\Server::get_full_repo_url(),
			'description'              => 'This package installs vendor plugins and Troy Client (update handler). Troy Client is required while the others are active.',
			'version'                  => '1.0.0',
			'author'                   => '',
			'author_uri'               => '',
			'requires_wp'              => '6.7',
			'requires_php'             => '7.4',
			'network'                  => 0,
			'install_timeout'          => 30,
			'deactivate_on_completion' => 1,
			'delete_on_completion'     => 0,
			'notice_severity'          => 'detailed',
			'plugins'                  => [],
			'themes'                   => [],
		];
	}

	/**
	 * Sanitizes package data from the editor.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $data The raw package data from the editor.
	 * @return array The sanitized package data.
	 */
	public static function sanitize_editor_package_data( $data ) {
		return [
			'package_id'               => \absint( $data['package_id'] ?? 0 ),
			'plugin_uri'               => API\Sanitize::url_qualified( $data['plugin_uri'] ?? '' ),
			'name'                     => \sanitize_text_field( ( $data['name'] ?? '' ) ?: 'Troy Installer' ),
			'slug'                     => API\Sanitize::slug( $data['slug'] ?? '' ),
			'description'              => \sanitize_text_field( $data['description'] ?? '' ),
			'version'                  => API\Sanitize::semver(
				( $data['version'] ?? '1.0.0' )
					?: '',
			),
			'author'                   => \sanitize_text_field( $data['author'] ?? '' ),
			'author_uri'               => API\Sanitize::url_qualified( $data['author_uri'] ?? '' ),
			'requires_wp'              => API\Sanitize::tested_version( $data['requires_wp'] ?? '' ),
			'requires_php'             => API\Sanitize::tested_version( $data['requires_php'] ?? '' ),
			'network'                  => ! empty( $data['network'] ) ? 1 : 0,
			'install_timeout'          => min(
				60,
				max(
					\absint( $data['install_timeout'] ?? 30 ),
					7,
				),
			),
			'deactivate_on_completion' => empty( $data['deactivate_on_completion'] ) ? 0 : 1,
			'delete_on_completion'     => empty( $data['delete_on_completion'] ) ? 0 : 1,
			'notice_severity'          => \in_array(
				$data['notice_severity'] ?? '',
				[ 'detailed', 'verbose', 'silent' ],
				true,
			)
				? $data['notice_severity']
				: 'detailed',
			'plugins'                  => array_filter( array_map(
				fn( $plugin_id, $plugin_options ) => $plugin_id && ! empty( $plugin_options['selected'] )
					? [
						'id'             => \absint( $plugin_id ),
						'network'        => ! empty( $plugin_options['network'] ),
						'activate'       => ! empty( $plugin_options['activate'] ),
						'overwrite'      => ! empty( $plugin_options['overwrite'] ),
						'overwrite_troy' => ! empty( $plugin_options['overwrite_troy'] ),
					]
					: false,
				array_keys( (array) ( $data['plugins'] ?? [] ) ),
				(array) ( $data['plugins'] ?? [] ),
			) ),
			'themes'                   => array_filter( array_map(
				fn( $theme_id, $theme_options ) => $theme_id && ! empty( $theme_options['selected'] )
					? [
						'id'             => \absint( $theme_id ),
						'activate'       => ! empty( $theme_options['activate'] ),
						'overwrite'      => ! empty( $theme_options['overwrite'] ),
						'overwrite_troy' => ! empty( $theme_options['overwrite_troy'] ),
					]
					: false,
				array_keys( (array) ( $data['themes'] ?? [] ) ),
				(array) ( $data['themes'] ?? [] ),
			) ),
		];
	}

	/**
	 * Handles package save.
	 *
	 * @hook save_post_{PACKAGES_CPT} 10
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public static function handle_save_post( $post_id ) {

		if (
			   \wp_is_post_autosave( $post_id )
			|| \wp_is_post_revision( $post_id )
			|| ! \current_user_can( 'edit_post', $post_id )
			|| empty( $_POST['troy-server-package'] )
			|| ! isset( $_POST[ self::SAVE_NONCE['name'] ] )
			|| ! \wp_verify_nonce( $_POST[ self::SAVE_NONCE['name'] ], self::SAVE_NONCE['action'] )
		)
			return;

		// Clear previous notices
		\delete_post_meta( $post_id, '_troy_server_package_update_status' );

		// Placeholder; this will be overwritten later.
		\update_post_meta(
			$post_id,
			'_troy_server_package_update_status',
			[
				'type'    => 'info',
				'message' => \__( 'Package is being saved...', 'troy-server' ),
			],
		);

		$notices = [];

		$input = \wp_unslash( $_POST['troy-server-package'] ); // phpcs:ignore WordPress.Security.NonceVerification -- WordPress does this.

		$post_title = \get_post( $post_id )->post_title;

		if ( \strlen( $post_title ) > 191 ) {
			$post_title = substr( $post_title, 0, 191 );
			\wp_update_post( [
				'ID'         => $post_id,
				'post_title' => $post_title,
			] );
			$notices[] = [
				'type'    => 'warning',
				'message' => \__( 'Post title was too long and has been truncated to 191 characters.', 'troy-server' ),
			];
		}

		// Proactively fill package_id and slug
		$input['package_id'] = API\Package::get_package_id_by_post_id( $post_id );
		$input['name']       = $post_title;
		$input['slug']       = \strlen( $input['slug'] ) ? $input['slug'] : $input['name'];

		$data = array_merge(
			self::get_default_package_data(),
			self::sanitize_editor_package_data( $input ),
		);

		switch ( true ) {
			case empty( $data['slug'] ):
				\update_post_meta(
					$post_id,
					'_troy_server_package_update_status',
					[
						...$notices,
						[
							'type'    => 'error',
							'message' => \__( 'No valid slug found! Please set a package slug.', 'troy-server' ),
						],
					],
				);
				return;
		}

		$package_id = $data['package_id'];
		$slug       = $data['slug'];

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- no love for goto.
			find_unique_slug: {
				$slug_checker  = new API\Slug( $slug, 'package', $package_id );
				$conflict_type = $slug_checker->conflict_type;

				if ( $conflict_type ) {
					$original_slug = $slug;
					$data['slug']  = $slug = $slug_checker->unique_slug;

					$notices[] = [
						'type'    => 'warning',
						'message' => \sprintf(
							/* translators: 1: Original slug, 2: Conflict type (plugin or package), 3: New slug */
							\__( 'Slug "%1$s" was already taken by a %2$s. Package slug changed to "%3$s".', 'troy-server' ),
							$original_slug,
							$conflict_type,
							$slug,
						),
					];
				}
			}

			set_package: {
				$existing_package_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_packages WHERE post_id = %d",
					$post_id,
				) );

				if ( $existing_package_id ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_packages",
						[
							'slug'             => $slug,
							'status'           => 'pending',
							'origin_url'       => API\Server::get_repo_url(),
							'database_version' => DB_VERSION,
						],
						[ 'id' => $existing_package_id ],
						[ '%s', '%s', '%s', '%d' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_packages",
						[
							'post_id'          => $post_id,
							'slug'             => $slug,
							'status'           => 'pending',
							'origin_url'       => API\Server::get_repo_url(),
							'database_version' => DB_VERSION,
						],
						[ '%d', '%s', '%s', '%s', '%d' ],
					);

					$data['package_id'] = $package_id = \absint( $wpdb->insert_id );
				}

				if ( ! $package_id ) {
					\update_post_meta(
						$post_id,
						'_troy_server_package_update_status',
						[
							...$notices,
							[
								'type'    => 'error',
								'message' => \__( 'Failed to create package. Please try again.', 'troy-server' ),
							],
						],
					);
					return;
				}
			}

			save_meta_row: {
				$existing_meta_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_package_metas WHERE package_id = %d",
					$package_id,
				) );

				if ( $existing_meta_id ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_package_metas",
						[
							'plugin_uri'               => $data['plugin_uri'],
							'name'                     => $data['name'],
							'description'              => $data['description'],
							'version'                  => $data['version'],
							'author'                   => $data['author'],
							'author_uri'               => $data['author_uri'],
							'requires_wp'              => $data['requires_wp'],
							'requires_php'             => $data['requires_php'],
							'network'                  => $data['network'],
							'install_timeout'          => $data['install_timeout'],
							'deactivate_on_completion' => $data['deactivate_on_completion'],
							'delete_on_completion'     => $data['delete_on_completion'],
							'notice_severity'          => $data['notice_severity'],
							'plugins'                  => API\Sanitize::json_encode_db( $data['plugins'] ),
							'themes'                   => API\Sanitize::json_encode_db( $data['themes'] ),
						],
						[ 'package_id' => $package_id ],
						[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_package_metas",
						[
							'package_id'               => $package_id,
							'plugin_uri'               => $data['plugin_uri'],
							'name'                     => $data['name'],
							'description'              => $data['description'],
							'version'                  => $data['version'],
							'author'                   => $data['author'],
							'author_uri'               => $data['author_uri'],
							'requires_wp'              => $data['requires_wp'],
							'requires_php'             => $data['requires_php'],
							'network'                  => $data['network'],
							'install_timeout'          => $data['install_timeout'],
							'deactivate_on_completion' => $data['deactivate_on_completion'],
							'delete_on_completion'     => $data['delete_on_completion'],
							'notice_severity'          => $data['notice_severity'],
							'plugins'                  => API\Sanitize::json_encode_db( $data['plugins'] ),
							'themes'                   => API\Sanitize::json_encode_db( $data['themes'] ),
						],
						[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ],
					);
				}
			}

			// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact

			$wpdb->query( 'COMMIT' );

			\update_post_meta(
				$post_id,
				'_troy_server_package_update_status',
				[
					...$notices,
					[
						'type'    => 'success',
						'message' => \__( 'Package saved successfully.', 'troy-server' ),
					],
				],
			);
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			\update_post_meta(
				$post_id,
				'_troy_server_package_update_status',
				[
					...$notices,
					[
						'type'    => 'error',
						'message' => \sprintf(
							/* translators: %s: Error message */
							\__( 'Failed to save package: %s. ', 'troy-server' ),
							$e->getMessage(),
						),
					],
				],
			);
			return;
		}

		build_package_zip: try {
			$builder = new Zip_Builder( $package_id );

			$builder->build();

			if ( $builder->zip_existed ) {
				$notices[] = [
					'type'    => 'info',
					'message' => \__( 'Package ZIP already existed and has been rebuilt.', 'troy-server' ),
				];
			} else {
				$notices[] = [
					'type'    => 'success',
					'message' => \__( 'Package ZIP built successfully.', 'troy-server' ),
				];
			}
		} catch ( \Exception $e ) {
			$notices[] = [
				'type'    => 'error',
				'message' => \sprintf(
					/* translators: %s: Error message */
					\__( 'Failed to build package: %s', 'troy-server' ),
					$e->getMessage(),
				),
			];
			$notices[] = [
				'type'    => 'error',
				'message' => \__( 'Save to try building again.', 'troy-server' ),
			];
		}

		\update_post_meta( $post_id, '_troy_server_package_update_status', $notices );
	}

	/**
	 * Handles package trashing.
	 *
	 * @hook trash_{PACKAGES_CPT} 10
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public static function handle_trash_post( $post_id ) {

		global $wpdb;

		\update_post_meta(
			$post_id,
			'troy_server_package_trashed_previous_status',
			new Data( post_id: $post_id )->get_packages_row()?->status,
		);

		$wpdb->update(
			"{$wpdb->prefix}troy_packages",
			[ 'status' => 'disabled' ],
			[ 'post_id' => $post_id ],
			[ '%s' ],
			[ '%d' ],
		);
	}

	/**
	 * Handles package untrashing.
	 *
	 * @hook untrashed_post 10
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public static function handle_untrash_post( $post_id ) {

		if ( PACKAGES_CPT !== \get_post_type( $post_id ) )
			return;

		// $previous_status is an optional parameter of the untrash_post action.
		$previous_package_status = \get_post_meta(
			$post_id,
			'troy_server_package_trashed_previous_status',
			true
		);

		if ( $previous_package_status ) {
			global $wpdb;

			$wpdb->update(
				"{$wpdb->prefix}troy_packages",
				[ 'status' => $previous_package_status ],
				[ 'post_id' => $post_id ],
				[ '%s' ],
				[ '%d' ],
			);
		}

		// The previous status can be null if it's a new package that was never saved before.
		\delete_post_meta( $post_id, 'troy_server_package_trashed_previous_status' );
	}

	/**
	 * Handles package deletion.
	 *
	 * @hook delete_post_{PACKAGES_CPT} 10
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public static function handle_delete_post( $post_id ) {
		new Drop( post_id: $post_id )->commit();
	}
}
