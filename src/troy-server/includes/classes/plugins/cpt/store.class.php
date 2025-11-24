<?php
/**
 * @package Troy\Server\Plugins\CPT
 * @access  private
 */

namespace Troy\Server\Plugins\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\PLUGINS_CPT;


use Troy\Server\{
	API,
	Integrations,
	Zip_Extractor,
};

use Troy\Server\Plugins\{
	Data,
	Drop,
	Files,
	Readme_Parser,
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
 * Class Troy\Server\Plugins\CPT\Store.
 *
 * Handles the storage of plugin data in the PLUGINS_CPT custom post type.
 *
 * How the store works:
 * 1. The metadata is registered via `Troy\Server\Plugins\CPT\Block_Editor::register_post_meta()`.
 * 2. The post is saved (REST or classic) via WordPress Core, it automatically saves the post meta.
 * 3. The post meta is sanizied via the `sanitize_callback` of `register_post_meta()`, which is `sanitize_editor_plugin_data()` below.
 * 4. Once the post is done saving, `handle_after_insert_post()` is called via `wp_after_insert_post`.
 * 5. `handle_save_post()` extracts the data from the post meta and block content and saves it to custom Troy Server tables.
 * 6. The function then deletes any redundant data from the post and post meta, such as the content.
 *
 * @since 0.0.1184
 */
final class Store {

	/**
	 * Prevents the post from being marked as empty.
	 * We do this so other actions can run for the post.
	 *
	 * This ought not to be necessary, since the post doesn't support excerpts,
	 * but this is for future-proofing when the post store becomes more sane.
	 *
	 * @hook wp_insert_post_empty_content 10
	 * @since 0.0.1184
	 *
	 * @param bool  $maybe_empty Whether the post is empty.
	 * @param array $postarr     The post data.
	 * @return bool Whether the post is empty.
	 */
	public static function unset_empty_post( $maybe_empty, $postarr ) {

		if ( PLUGINS_CPT === $postarr['post_type'] )
			return false;

		return $maybe_empty;
	}

	/**
	 * Returns the default plugin data.
	 *
	 * @since 0.0.1184
	 * @return array The default plugin data.
	 */
	public static function get_default_plugin_data() {
		return [
			'plugin_id'         => 0,
			'name'              => '',
			'slug'              => '',
			'status'            => 'pending',
			'author_id'         => 0,
			'builder_type'      => 'readme',
			'versions'          => [],
			'permalink'         => '',
			'support_uri'       => '',
			'donate_uri'        => '',
			'short_description' => '',
			'banner_uri'        => '',
			'logo_uri'          => '',
			'contributors'      => [],
			'contents'          => [
				'details'     => '',
				'usage'       => '',
				'faq'         => '',
				'api'         => '',
				'changelog'   => '',
				'screenshots' => '',
			],
			'integrations'      => null,
		];
	}

	/**
	 * Sanitizes the Editor plugin data.
	 * This data is not complete; some of it is left to the database or other handlers.
	 *
	 * @hook sanitize_post_meta_{Troy\Server\PLUGINS_CPT} 10
	 *       This hook is registered via register_post_meta() in the CPT class.
	 * @since 0.0.1184
	 *
	 * @param array $data The plugin data to sanitize.
	 * @return array The sanitized plugin data.
	 */
	public static function sanitize_editor_plugin_data( $data ) {
		return [
			'plugin_id'         => \absint( $data['plugin_id'] ?? 0 ),
			'name'              => \sanitize_text_field( $data['name'] ?? '' ),
			'slug'              => API\Sanitize::slug( $data['slug'] ?? '' ),
			'status'            => array_intersect(
				[ $data['status'] ?? '' ], // Don't flip this, we want index "0"
				[ 'public', 'unlisted', 'protected', 'pending', 'disabled' ],
			)[0] ?? 'pending',
			'author_id'         => API\Sanitize::user_id( $data['author_id'] ?? 0 ),
			'builder_type'      => array_intersect(
				[ $data['builder_type'] ?? '' ], // Don't flip this, we want index "0"
				[ 'readme', 'post' ],
			)[0] ?? 'readme',
			'versions'          => array_filter(
				array_map(
					// Any item not listed was processed during the ZIP file upload.
					fn( $item ) => [
						// Ref https://semver.org/. Slightly adjusted to trim leading/trailing whitespace and group the first version found in $1.
						'version'        => API\Sanitize::semver( $item['version'] ?? '' ),
						'type'           => API\Sanitize::version_type( $item['type'] ?? '' ),
						'upgrade_notice' => API\Sanitize::upgrade_notice( $item['upgrade_notice'] ?? '' ),
					],
					$data['versions'] ?? [],
				),
				fn( $item ) => $item['version'], // "0" is not a valid version.
			),
			'permalink'         => API\Sanitize::url_qualified( $data['permalink'] ?? '' ),
			'support_uri'       => API\Sanitize::url_qualified( $data['support_uri'] ?? '' ),
			'donate_uri'        => API\Sanitize::url_qualified( $data['donate_uri'] ?? '' ),
			'short_description' => \sanitize_text_field( $data['short_description'] ?? '' ), // not textarea
			'banner_uri'        => API\Sanitize::static_image_url( $data['banner_uri'] ?? '' ),
			'logo_uri'          => API\Sanitize::static_image_url( $data['logo_uri'] ?? '' ),
			'contributors'      => API\Sanitize::contributors( $data['contributors'] ?? [] ),
			'screenshots'       => array_filter(
				array_map(
					fn( $item ) => [
						'id'      => \absint( $item['id'] ?? 0 ),
						'url'     => API\Sanitize::url_qualified( $item['url'] ?? '' ),
						'caption' => \sanitize_text_field( $item['caption'] ?? '' ),
					],
					$data['screenshots'] ?? [],
				),
				fn( $item ) => $item['url'],
			),
			'contents'          => array_map(
				fn( $type ) => trim( \wp_kses_post( $data['contents'][ $type ] ?? '' ), " \r\n\v\t" ),
				[ 'details', 'usage', 'faq', 'api', 'changelog', 'screenshots' ],
			),
			'integrations'      => \is_array( $data['integrations'] ?? null )
				? [
					// All other data is handled by their respective integration handlers.
					'auto_process' => array_intersect(
						[ $data['integrations']['auto_process'] ?? '' ], // Don't flip this, we want index "0"
						[ 'all', 'tag', 'beta', 'none' ],
					)[0] ?? 'all',
				]
				: null, // Must be null if not an array.
		];
	}

	/**
	 * Handles post insertion/update after all meta is processed via REST.
	 *
	 * Extracts data from post meta and block content, then saves it to custom tables.
	 * Finally, it deletes redundant data from the post and post meta.
	 *
	 * @hook rest_after_insert_{Troy\Server\PLUGINS_CPT} 10
	 * @since 0.0.1184
	 *
	 * @param WP_Post $post The post object.
	 */
	public static function handle_rest_after_insert_post( $post ) {

		$post_id = $post?->ID;

		if (
			   empty( $post_id ) // It's technically possible to save a post without an ID. Let's forgo those.
			|| \wp_is_post_autosave( $post ) // Let's assume that the autosave contains unintentional edits. Note that an autosave is a revision.
			|| \wp_is_post_revision( $post ) // Revisions are not supported. Use version control at Hub/Lab/Bucket instead.
			|| 'auto-draft' === $post->post_status // We cannot do much if the post is still an auto-draft (i.e., new post).
			|| ! \current_user_can( 'edit_post', $post_id ) // Redundant sanity check.
		) return;

		\update_post_meta(
			$post_id,
			'_troy_server_plugin_update_status',
			[
				'type'    => 'processing',
				'message' => \__( 'Started plugin save handler.', 'troy-server' ),
			],
		);

		// Sanitization is registered via `register_post_meta()` and done before this callback is run.
		$data = array_merge(
			self::get_default_plugin_data(),
			// This data has already been sanitized by the callback of register_post_meta.
			\get_post_meta( $post_id, 'troy_server_plugin_data', true ) ?: [],
		);

		$plugin_id_by_post_id = API\Plugin::get_plugin_id_by_post_id( $post_id );

		// We don't actually need the plugin ID, for we can fetch it via the post ID.
		if ( empty( $data['plugin_id'] ) ) {
			$data['plugin_id'] = $plugin_id_by_post_id;
		} elseif ( $data['plugin_id'] !== $plugin_id_by_post_id ) {
			// The data has been tampered with. We should not allow this for future proofing (plugin author as editor).
			\update_post_meta(
				$post_id,
				'_troy_server_plugin_update_status',
				[
					'type'    => 'error',
					'message' => \__( 'The plugin ID is not for this post ID! No changes were stored.', 'troy-server' ),
				],
			);
			return;
		}

		switch ( true ) {
			case empty( $data['plugin_id'] ):
			case empty( $data['slug'] ):
				\update_post_meta(
					$post_id,
					'_troy_server_plugin_update_status',
					[
						'type'    => 'error',
						'message' => \__( 'No valid plugin ID or slug found! Please set a plugin slug.', 'troy-server' ),
					],
				);
				return;
		}

		get_latest_working_version: {
			$working_version = API\Utils::extract_latest_version( $data['versions'] ?? [] );
		}

		$builder_type       = $data['builder_type'];
		$empty_post_content = false; // We do it in an opt-in way, so custom builders won't be affected.

		switch ( $builder_type ) {
			case 'post':
				$empty_post_content = true;

				$parsed_blocks = \parse_blocks( $post->post_content );
				$contents      = [];

				// Find the plugin-tabs block
				foreach ( $parsed_blocks as $block ) {
					if ( 'troy-server/plugin-tabs' !== $block['blockName'] )
						continue;

					// Iterate each tab
					foreach ( $block['innerBlocks'] as $tab ) {
						$tab_id = $tab['attrs']['troyServerTabId'] ?? null;

						if ( ! $tab_id )
							continue;

						// Concatenate all innerHTML of child blocks
						$html = '';

						foreach ( $tab['innerBlocks'] as $child )
							$html .= $child['innerHTML'] ?? '';

						// Map to plugin_data['contents'] - only store if there's actual text content
						// Test if there's any contents inside the HTML. We still want to send HTML, hence the double trim.
						$contents[ $tab_id ] = trim( \wp_strip_all_tags( $html ), " \r\n\v\t" )
							? trim( $html, " \r\n\v\t" )
							: '';
					}
				}

				// Merge with defaults to ensure all keys exist
				$data['contents'] = array_merge(
					self::get_default_plugin_data()['contents'],
					$contents,
				);
				break;

			case 'readme':
				if ( $working_version ) {
					try {
						// Merge with defaults to ensure all keys exist
						$data['contents'] = array_merge(
							self::get_default_plugin_data()['contents'],
							new Readme_Parser(
								new Zip_Extractor(
									Files::get_plugin_zip_file_path( $data['plugin_id'], $working_version ),
								)->temp_zip_extraction_dir,
							)->contents,
						);
					} catch ( \Exception $e ) {
						// Fail if the ZIP extraction fails.
						\update_post_meta(
							$post_id,
							'_troy_server_plugin_update_status',
							[
								'type'    => 'error',
								'message' => \sprintf(
									/* translators: %s is the error message from the exception. */
									\__( 'Your changes are not saved! ZIP extraction failed before saving: %s', 'troy-server' ),
									$e->getMessage(),
								),
							],
						);
						return;
					}
				}

				$empty_post_content = true;
				break;
			default:
				// We don't do anything here. The readme.txt is parsed by the server.
				// This is a fallback for when the plugin author doesn't use the block editor.
				// TODO add support for other builders? e.g., apply_filters..

				\update_post_meta(
					$post_id,
					'_troy_server_plugin_update_status',
					[
						'type'    => 'error',
						'message' => \__( 'Your changes are not saved! No valid builder type found. Please set a valid builder type.', 'troy-server' ),
					],
				);
				return;
		}

		// This MUST be done here. We do not want scripts and on-* attributes sent to the Troy Client.
		$data['contents'] = \wp_kses_post_deep( $data['contents'] );

		// Auto-convert pending → public when WordPress post is published
		if (
			   'pending' === $data['status']
			&& 'publish' === $post->post_status
		) {
			$data['status'] = 'public';
		}

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- no love for goto.
			update_plugins: {
				// This always exist, so we can safely update it.
				$wpdb->update(
					"{$wpdb->prefix}troy_plugins",
					[
						'slug'   => $data['slug'],
						'status' => $data['status'],
					],
					[
						'id'      => $data['plugin_id'],
						'post_id' => $post_id,
					],
					[ '%s', '%s' ],
					[ '%d', '%d' ],
				);
			}

			update_metas: {
				$existing_meta_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_plugins_metas WHERE plugin_id = %d",
					$data['plugin_id'],
				) );

				if ( $existing_meta_id ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_plugins_metas",
						[
							'plugin_id'         => $data['plugin_id'],
							'name'              => $data['name'],
							'author_id'         => $data['author_id'],
							'short_description' => $data['short_description'],
							'permalink'         => $data['permalink'] ?? \get_permalink( $post_id ),
							'support_uri'       => $data['support_uri'],
							'donate_uri'        => $data['donate_uri'],
							'logo_uri'          => $data['logo_uri'],
							'builder_type'      => $data['builder_type'],
						],
						[ 'id' => $existing_meta_id ],
						[ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_plugins_metas",
						[
							'plugin_id'         => $data['plugin_id'],
							'name'              => $data['name'],
							'author_id'         => $data['author_id'],
							'short_description' => $data['short_description'],
							'permalink'         => $data['permalink'],
							'support_uri'       => $data['support_uri'],
							'donate_uri'        => $data['donate_uri'],
							'logo_uri'          => $data['logo_uri'],
							'builder_type'      => $data['builder_type'],
						],
						[ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ],
					);
				}
			}

			update_contributors: {
				// It's easier to delete and reinsert a list than to update.
				// However, we must keep track of update timestamps.
				$contributors_keyed = array_column(
					$wpdb->get_results( $wpdb->prepare(
						"SELECT id, user_id FROM {$wpdb->prefix}troy_plugins_contributors WHERE plugin_id = %d",
						$data['plugin_id'],
					) ), // ARRAY_A is not needed here. array_column() can unpack objects.
					'id',
					'user_id',
				);

				foreach ( $data['contributors'] as $contributor ) {
					if ( isset( $contributors_keyed[ $contributor['user_id'] ] ) ) {
						$wpdb->update(
							"{$wpdb->prefix}troy_plugins_contributors",
							[ 'role' => $contributor['role'] ],
							[ 'id' => $contributors_keyed[ $contributor['user_id'] ] ],
							[ '%s' ],
							[ '%d' ],
						);
					} else {
						$wpdb->insert(
							"{$wpdb->prefix}troy_plugins_contributors",
							[
								'plugin_id' => $data['plugin_id'],
								'user_id'   => $contributor['user_id'],
								'role'      => $contributor['role'],
							],
							[ '%d', '%d', '%s' ],
						);
					}

					unset( $contributors_keyed[ $contributor['user_id'] ] );
				}

				// Delete any remaining contributors.
				foreach ( $contributors_keyed as $contributor_id ) {
					$wpdb->delete(
						"{$wpdb->prefix}troy_plugins_contributors",
						[ 'id' => $contributor_id ],
						[ '%d' ],
					);
				}
			}

			update_versions: {
				// Process versions data for troy_plugins_zips table
				foreach ( $data['versions'] as $version_data ) {
					// Skip the 'remove' field as it's editor-only data
					if ( isset( $version_data['remove'] ) && $version_data['remove'] )
						continue;

					// Check if this version already exists
					$existing_zip_id = $wpdb->get_var( $wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}troy_plugins_zips WHERE plugin_id = %d AND version = %s",
						$data['plugin_id'],
						$version_data['version'],
					) );

					if ( $existing_zip_id ) {
						// Update only upgrade_notice and type for existing versions
						$wpdb->update(
							"{$wpdb->prefix}troy_plugins_zips",
							[
								'upgrade_notice' => $version_data['upgrade_notice'] ?? '',
								'type'           => $version_data['type'] ?? '',
							],
							[ 'id' => $existing_zip_id ],
							[ '%s', '%s' ],
							[ '%d' ],
						);
					}
					// We don't insert new versions here as other data like file_size,
					// requires_wp, etc. are handled when uploading the ZIP file.
				}
			}

			update_infos: {
				// TODO... locale is a big endeavor. We must add a toggle to the interface, or add a new interface
				$locale       = \get_locale() ?: 'en_US';
				$info_version = $working_version ?: 'ò.ó'; // 'ò.ó' is a placeholder for "no version" (it may not be empty).

				// JSON decode is significantly inefficient, but we can cache the outputs later.
				// It takes about 0.0028s to decode TSF's readme.txt, allowing 357 info requests per second.
				// TSFEM gets about 1620 info requests per week for 20k users, so we can assume 0.081 requests/user/week.
				// 357 requests/second * 604 800/second/week = 21.5M requests/week
				// Dividing by per-user weekly requests:
				// 21.5M requests/week / 0.081 requests/user/week = 267M users served at current usage.
				$encoded_content = API\Sanitize::json_encode_db( [
					'details'     => $data['contents']['details'] ?? '', // aka description
					'usage'       => $data['contents']['usage'] ?? '',
					'faq'         => $data['contents']['faq'] ?? '',
					'api'         => $data['contents']['api'] ?? '',
					'changelog'   => $data['contents']['changelog'] ?? '',
					'screenshots' => $data['contents']['screenshots'] ?? '',
				] );

				// Check if a revision already exists for this plugin+version
				$existing_revision = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_plugins_infos WHERE plugin_id = %d AND locale = %s",
					$data['plugin_id'],
					$locale,
				) );

				if ( $existing_revision ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_plugins_infos",
						[
							'locale'         => $locale,
							'latest_version' => $info_version,
							'banner_uri'     => $data['banner_uri'] ?? '',
							'contents'       => $encoded_content,
						],
						[ 'id' => $existing_revision ],
						[ '%s', '%s', '%s', '%s' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_plugins_infos",
						[
							'plugin_id'      => $data['plugin_id'],
							'locale'         => $locale,
							'latest_version' => $info_version,
							'banner_uri'     => $data['banner_uri'] ?? '',
							'contents'       => $encoded_content,
						],
						[ '%d', '%s', '%s', '%s', '%s' ],
					);
				}
			}

			update_integrations: {
				// Integrations are handled almost entirely via a bespoke REST API.
				// However, we still need to store the auto_process setting here.
				$existing_data = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_plugins_integrations WHERE plugin_id = %d",
					$data['plugin_id'],
				) );

				if ( $existing_data ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_plugins_integrations",
						[
							'auto_process' => $data['integrations']['auto_process'] ?? 'all',
						],
						[ 'id' => $existing_data ],
						[ '%s' ],
						[ '%d' ],
					);
				}
				// We do not insert new integration settings here, as they are handled via the bespoke REST API.
			}

			update_snapshots: {
				// Store a snapshot of the plugin data for this version.
				// This allows restoring plugin settings in the future (future feature).
				$snapshot_version = $working_version ?: '0.0.0';

				$existing_snapshot = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_plugins_snapshots WHERE plugin_id = %d AND version = %s",
					$data['plugin_id'],
					$snapshot_version,
				) );

				if ( $existing_snapshot ) {
					$wpdb->update(
						"{$wpdb->prefix}troy_plugins_snapshots",
						[
							'plugin_id' => $data['plugin_id'],
							'version'   => $snapshot_version,
							'values'    => API\Sanitize::json_encode_db( $data ),
						],
						[ 'id' => $existing_snapshot ],
						[ '%d', '%s', '%s' ],
						[ '%d' ],
					);
				} else {
					$wpdb->insert(
						"{$wpdb->prefix}troy_plugins_snapshots",
						[
							'plugin_id' => $data['plugin_id'],
							'version'   => $snapshot_version,
							'values'    => API\Sanitize::json_encode_db( $data ),
						],
						[ '%d', '%s', '%s' ],
					);
				}
			}
			// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact

			if ( $empty_post_content )
				$wpdb->update(
					$wpdb->posts,
					[ 'post_content' => '' ],
					[ 'ID' => $post_id ],
					[ '%s' ],
					[ '%d' ],
				);

			$wpdb->query( 'COMMIT' );

			// We no longer need this. Bye, you old hack!
			\delete_post_meta( $post_id, 'troy_server_plugin_data' );

			\update_post_meta(
				$post_id,
				'_troy_server_plugin_update_status',
				[ 'type' => 'updated' ],
			);
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			\update_post_meta(
				$post_id,
				'_troy_server_plugin_update_status',
				[
					'type'    => 'error',
					'message' => \sprintf(
						/* translators: %s is the error message from the exception. */
						\__( 'Your changes are not saved! An error occurred while saving the plugin: %s', 'troy-server' ),
						$e->getMessage(),
					),
				],
			);
			return; // Redundant here at the end. Defensive: we really need to quit parsing on exceptions.
		}
	}

	/**
	 * Handles the trash action for the PLUGINS_CPT CPT.
	 *
	 * Because we do not expect plugins to be "trashed" via APIs, we use the post
	 * meta to store the previous status of the plugin. This is later used when
	 * the plugin is untrashed, or deleted automatically by WordPress if the trash
	 * is emptied.
	 *
	 * @hook trash_{Troy\Server\PLUGINS_CPT} 10
	 *       This hook is documented in wp_transition_post_status()
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID being trashed.
	 */
	public static function handle_trash_post( $post_id ) {

		global $wpdb;

		\update_post_meta(
			$post_id,
			'troy_server_plugin_trashed_previous_status',
			new Data( post_id: $post_id )->get_plugins_row()?->status,
		);

		$wpdb->update(
			"{$wpdb->prefix}troy_plugins",
			[ 'status' => 'disabled' ],
			[ 'post_id' => $post_id ],
			[ '%s' ],
			[ '%d' ],
		);
	}

	/**
	 * Handles the untrash action for the PLUGINS_CPT CPT.
	 *
	 * @hook untrashed_post 10
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID being untrashed.
	 */
	public static function handle_untrash_post( $post_id ) {

		if ( PLUGINS_CPT !== \get_post_type( $post_id ) )
			return;

		// $previous_status is an optional parameter of the untrash_post action.
		$previous_plugin_status = \get_post_meta(
			$post_id,
			'troy_server_plugin_trashed_previous_status',
			true,
		);

		if ( $previous_plugin_status ) {
			global $wpdb;

			$wpdb->update(
				"{$wpdb->prefix}troy_plugins",
				[ 'status' => $previous_plugin_status ],
				[ 'post_id' => $post_id ],
				[ '%s' ],
				[ '%d' ],
			);
		}

		// The previous status can be null if it's a new plugin that was never saved before.
		\delete_post_meta( $post_id, 'troy_server_plugin_trashed_previous_status' );
	}

	/**
	 * Handles the delete_post action for the PLUGINS_CPT CPT.
	 *
	 * @hook delete_post_{Troy\Server\PLUGINS_CPT} 10
	 * @since 0.0.1184
	 *
	 * @param int $post_id The post ID being trashed.
	 */
	public static function handle_delete_post( $post_id ) {
		new Drop( post_id: $post_id )->commit();
	}

	/**
	 * Handles user deletion by cleaning up references in Troy plugin tables.
	 *
	 * @hook delete_user 10
	 * @since 0.0.1184
	 *
	 * @param int  $user_id The ID of the user being deleted.
	 * @param ?int $reassign The ID of the user to reassign posts and links to, if any.
	 */
	public static function handle_user_deletion( $user_id, $reassign ) {

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			if ( $reassign ) {
				// Reassign user from contributors table
				$wpdb->update(
					"{$wpdb->prefix}troy_plugins_contributors",
					[ 'user_id' => $reassign ],
					[ 'user_id' => $user_id ],
					[ '%d' ],
					[ '%d' ],
				);

				// Reassign user ratings
				$wpdb->update(
					"{$wpdb->prefix}troy_plugins_ratings",
					[ 'user_id' => $reassign ],
					[ 'user_id' => $user_id ],
					[ '%d' ],
					[ '%d' ],
				);
			} else {
				// Remove user from contributors table
				$wpdb->delete(
					"{$wpdb->prefix}troy_plugins_contributors",
					[ 'user_id' => $user_id ],
					[ '%d' ],
				);

				// Remove user ratings
				$wpdb->delete(
					"{$wpdb->prefix}troy_plugins_ratings",
					[ 'user_id' => $user_id ],
					[ '%d' ],
				);
			}

			// Set author_id to 0 in metas table for deleted users
			$wpdb->update(
				"{$wpdb->prefix}troy_plugins_metas",
				[ 'author_id' => $reassign ?? 0 ],
				[ 'author_id' => $user_id ],
				[ '%d' ],
				[ '%d' ],
			);

			$wpdb->query( 'COMMIT' );
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			\wp_die(
				\sprintf(
					/* translators: %s is the error message from the exception. */
					\esc_html__( 'An error occurred while deleting the user: %s', 'troy-server' ),
					\esc_html( $e->getMessage() ),
				),
			);
		}
	}
}
