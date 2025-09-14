<?php
/**
 * @package Troy\Server
 * @access  private
 */

namespace Troy\Server\Plugins\CPT;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	VERSION,
	MAIN_FILE,
	PLUGINS_CPT,
	REST_NS,
};

use function Troy\Server\get_origin_url;

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
 * Class Troy\Server\Plugins\CPT\Block_Editor
 *
 * @since 0.0.1184
 */
final class Block_Editor {

	/**
	 * Register the Troy Plugins post meta.
	 *
	 * @TODO Transform these fields to a structure that can be interpreted by the
	 *       JS file agnostically. Then, we can mimic the sidebar like Post, and
	 *       allow filtering hereof without the need for Gutenberg's broken block
	 *       API being constantly updated by everyone. This ought to be much like
	 *       blocks.json, but then bespoke. What we have now is a hacked together
	 *       mess that is not extensible, but we needed a proof of concept and V1
	 *       product to ship. Hint for later: VStack + HStack.
	 *       Any missing fields here will cause the Block Editor to not save the
	 *       post data, so, this makes the above TODO even more important to allow
	 *       for extensibility.
	 * @hook rest_api_init 10
	 * @since 0.0.1184
	 */
	public static function register_post_meta() {

		\register_post_meta(
			PLUGINS_CPT,
			'troy_server_plugin_data',
			[
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'plugin_id'         => [ 'type' => 'integer' ],
							'name'              => [ 'type' => 'string' ],
							'slug'              => [ 'type' => 'string' ],
							'status'            => [ 'type' => 'string' ],
							'author_id'         => [ 'type' => 'integer' ],
							'builder_type'      => [ 'type' => 'string' ],
							'versions'          => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'version'        => [ 'type' => 'string' ],
										'type'           => [ 'type' => 'string' ],
										'file_size'      => [ 'type' => 'integer' ],
										'tested_wp'      => [ 'type' => 'string' ],
										'requires_wp'    => [ 'type' => 'string' ],
										'requires_php'   => [ 'type' => 'string' ],
										'repo'           => [ 'type' => 'string' ],
										'dependencies'   => [ 'type' => 'string' ],
										'upgrade_notice' => [ 'type' => 'string' ],
										'origin_url'     => [ 'type' => 'string' ],
										'created_at'     => [ 'type' => 'string' ],
										'updated_at'     => [ 'type' => 'string' ],
										'download_uri'   => [ 'type' => 'string' ],  // not stored in db
										'remove'         => [ 'type' => 'boolean' ], // not stored in db
									],
								],
							],
							'permalink'         => [ 'type' => 'string' ],
							'support_uri'       => [ 'type' => 'string' ],
							'short_description' => [ 'type' => 'string' ],
							'banner_uri'        => [ 'type' => 'string' ],
							'logo_uri'          => [ 'type' => 'string' ],
							'contributors'      => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'user_id' => [ 'type' => 'integer' ],
										'role'    => [ 'type' => 'string' ],
									],
								],
							],
							'contents'          => [
								'type'       => 'object',
								'properties' => [
									'details'     => [ 'type' => 'string' ],
									'usage'       => [ 'type' => 'string' ],
									'faq'         => [ 'type' => 'string' ],
									'api'         => [ 'type' => 'string' ],
									'changelog'   => [ 'type' => 'string' ],
									'screenshots' => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
				'default'           => Store::get_default_plugin_data(),
				'auth_callback'     => fn() => \current_user_can( 'edit_posts' ),
				'sanitize_callback' => [ Store::class, 'sanitize_editor_plugin_data' ],
			],
		);
	}

	/**
	 * Register the Troy Plugins blocks.
	 *
	 * @hook init 10
	 * @since 0.0.1184
	 */
	public static function register_blocks() {

		// Header group.
		\register_block_type(
			'troy-server/plugin-headergroup',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Header Group', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'index-card',
				'attributes'  => [
					'align' => [
						'type'    => 'string',
						'default' => 'wide',
					],
				],
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
					'align'    => true,
				],
				// 'editor_script' and 'editor_style' are handled by the main enqueue function
			],
		);

		// Heading group (logo, title, author, download).
		\register_block_type(
			'troy-server/plugin-heading',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Heading', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'index-card',
				'attributes'  => [
					'align' => [
						'type'    => 'string',
						'default' => 'wide',
					],
				],
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
					'align'    => true,
				],
				// 'editor_script' and 'editor_style' are handled by the main enqueue function
			],
		);

		// Title-Author wrapper group.
		\register_block_type(
			'troy-server/plugin-title-author-wrap',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Title & Author Wrapper', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'index-card',
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
				],
				// 'editor_script' and 'editor_style' are handled by the main enqueue function
			],
		);

		// Banner
		\register_block_type(
			'troy-server/plugin-banner',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Banner', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'index-card',
				'attributes'  => [
					'sizeSlug' => [
						'type'    => 'string',
						'default' => 'large',
					],
					'align'    => [
						'type'    => 'string',
						'default' => 'wide',
					],
				],
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
					'align'    => true,
					'width'    => true,
					'height'   => true,
				],
				// 'editor_script' and 'editor_style' are handled by the main enqueue function
			],
		);

		// Logo
		\register_block_type(
			'troy-server/plugin-logo',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Logo', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'index-card',
				'attributes'  => [
					'sizeSlug' => [
						'type'    => 'string',
						'default' => 'thumbnail',
					],
					'align'    => [
						'type'    => 'string',
						'default' => 'left',
					],
				],
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
					'width'    => true,
					'height'   => true,
				],
				// 'editor_script' and 'editor_style' are handled by the main enqueue function
			],
		);

		\register_block_type(
			'troy-server/plugin-title',
			[
				'api_version'                  => 3,
				'title'                        => \__( 'Plugin Title', 'troy-server' ),
				'category'                     => 'widgets',
				'icon'                         => 'index-card',
				'tagname'                      => 'h1',
				'attributes'                   => [
					'content' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'supports'                     => [
					'inserter' => false,
					'reusable' => false,
					// 'align'    => true,
					'lock'     => true,
				],
				'withoutInteractiveFormatting' => true,
				'allowedFormats'               => [],
				// 'editor_script' and 'editor_style' are handled by the main enqueue function
			],
		);

		\register_block_type(
			'troy-server/plugin-author',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Author', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'admin-users',
				'attributes'  => [
					'authorId' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
				],
			],
		);

		\register_block_type(
			'troy-server/plugin-download',
			[
				'api_version' => 3,
				'title'       => \__( 'Plugin Download', 'troy-server' ),
				'category'    => 'widgets',
				'icon'        => 'download',
				'supports'    => [
					'inserter' => false,
					'reusable' => false,
					'lock'     => true,
				],
			],
		);

		// Tabs wrapper.
		\register_block_type(
			'troy-server/plugin-tabs',
			[
				'api_version'      => 3,
				'title'            => \__( 'Plugin Content Tabs', 'troy-server' ),
				'category'         => 'widgets',
				'icon'             => 'index-card',
				'attributes'       => [
					'activeTab' => [
						'type'    => 'number',
						'default' => 0,
					],
					'align'     => [
						'type'    => 'string',
						'default' => 'wide',
					],
				],
				'provides_context' => [
					'troy-server/plugin-tabs/activeTab' => 'activeTab',
				],
				'supports'         => [
					'html'     => false,
					'inserter' => true,
					'reusable' => false,
					'align'    => true,
					'lock'     => true, // Lock the block itself
				],
				'textdomain'       => 'troy-server',
			]
		);

		// Tabs content.
		\register_block_type(
			'troy-server/plugin-tab-content',
			[
				'api_version'  => 3,
				'title'        => \__( 'Plugin Tab Panel', 'troy-server' ),
				'category'     => 'widgets',
				'parent'       => [ 'troy-server/plugin-tabs' ],
				'icon'         => 'text-page',
				'attributes'   => [
					'title'           => [
						'type'    => 'string',
						'default' => \__( 'Tab', 'troy-server' ), // Not editable in the editor.
					],
					'align'           => [
						'type'    => 'string',
						'default' => 'wide',
					],
					'troyServerTabId' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'uses_context' => [ 'troy-server/plugin-tabs/activeTab' ],
				'supports'     => [
					'html'     => true,
					'reusable' => false,
					'inserter' => false, // Only use default tabs.
					'lock'     => false, // Allow content inside to be edited
				],
				'textdomain'   => 'troy-server',
			],
		);
	}

	/**
	 * Register the Block Editor assets for the Troy Plugins post type.
	 *
	 * @hook block_editor_settings_all 10
	 * @since 0.0.1184
	 *
	 * @param array                   $editor_settings      Default editor settings.
	 * @param WP_Block_Editor_Context $block_editor_context The current block editor context.
	 */
	public static function register_block_editor_template( $editor_settings, $block_editor_context ) {

		if (
			   'core/edit-post' !== $block_editor_context->name
			|| PLUGINS_CPT !== $block_editor_context->post?->post_type
		)
			return $editor_settings;

		$editor_settings['template'] = [
			[
				'troy-server/plugin-headergroup',
				[
					'align'     => 'wide',
					'className' => 'troy-server-block-plugin-headergroup',
					'lock'      => 'all',
					'style'     => [
						'marginTop' => '4rem',
					],
				],
				[
					[
						'troy-server/plugin-banner',
						[
							'align'     => 'wide',
							'className' => 'troy-server-block-plugin-banner',
							'lock'      => 'all',
							'width'     => 772,
							'height'    => 250,
							'style'     => [
								'width'     => '100%',
								'height'    => 'auto',
								'maxWidth'  => '772px',
								'maxHeight' => '250px',
							],
						],
					],
					[
						'troy-server/plugin-heading',
						[
							'align'     => 'wide',
							'className' => 'troy-server-block-plugin-heading',
							'lock'      => 'all',
						],
						[
							[
								'troy-server/plugin-logo',
								[
									'className' => 'troy-server-block-plugin-logo',
									'lock'      => 'all',
									'width'     => 96,
									'height'    => 96,
								],
							],
							[
								'troy-server/plugin-title-author-wrap',
								[
									'className' => 'troy-server-block-plugin-title-author-wrap',
									'lock'      => 'all',
								],
								[
									[
										'troy-server/plugin-title',
										[
											'className' => 'troy-server-block-plugin-title',
											'lock'      => 'all',
										],
									],
									[
										'troy-server/plugin-author',
										[
											'className' => 'troy-server-block-plugin-author',
											'lock'      => 'all',
										],
									],
								],
							],
							[
								'troy-server/plugin-download',
								[
									'className' => 'troy-server-block-plugin-download',
									'lock'      => 'all',
								],
							],
						],
					],
				],
			],
			[
				'troy-server/plugin-tabs',
				[
					// Set max width to 100% of the container.
					'align' => 'wide',
					'lock'  => 'all',
				],
			],
		];
		$editor_settings['templateLock']  = 'all';
		$editor_settings['canLockBlocks'] = false; // Actually lock the entire template.

		return $editor_settings;
	}

	/**
	 * Enqueue the Block Editor assets for the Troy Plugins post type.
	 * This is only used with the Block Editor, not on the frontend.
	 *
	 * TODO we need to separate the "troy-server-editor" from
	 * "troy-server-plugin-editor" to allow for Theme support later.
	 *
	 * @hook enqueue_block_editor_assets 10
	 * @since 0.0.1184
	 */
	public static function enqueue_editor_assets() {

		if ( PLUGINS_CPT !== $GLOBALS['current_screen']?->post_type )
			return;

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_style(
			'troy-server-editor-components',
			"{$dir_url}library/css/editor-components{$min}.css",
			[],
			VERSION,
		);

		\wp_enqueue_style(
			'troy-server-plugin-editor-components',
			"{$dir_url}library/css/plugin-editor-components{$min}.css",
			[ 'troy-server-editor-components' ],
			VERSION,
		);

		\wp_enqueue_script(
			'troy-server-editor-utils',
			"{$dir_url}library/js/editor-utils{$min}.js",
			[],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-editor-components',
			"{$dir_url}library/js/editor-components{$min}.js",
			[
				'wp-element',
				'wp-i18n',
				'wp-data',
				'wp-components',
				'wp-block-editor',
			],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-constants',
			"{$dir_url}library/js/constants{$min}.js",
			[],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-plugin-editor-components',
			"{$dir_url}library/js/plugin-editor-components{$min}.js",
			[
				'wp-element',
				'wp-i18n',
				'wp-data',
				'wp-components',
				'wp-block-editor',
				'troy-server-editor-utils',
				'troy-server-editor-components',
				'troy-server-constants',
			],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-plugin-editor-store',
			"{$dir_url}library/js/plugin-editor-store{$min}.js",
			[
				'troy-server-editor-utils',
				'wp-data',
			],
			VERSION,
			true, // Load in footer.
		);

		\wp_localize_script(
			'troy-server-plugin-editor-store',
			'troyPluginEditorStoreData',
			[
				'defaultData' => Store::get_default_plugin_data(),
			],
		);

		\wp_enqueue_script(
			'troy-server-plugin-editor-blocks',
			"{$dir_url}library/js/plugin-editor-blocks{$min}.js",
			[
				'wp-blocks',
				'wp-element',
				'wp-i18n',
				'wp-data',
				'wp-components',
				'wp-block-editor',
				'troy-server-plugin-editor-store',
				'troy-server-constants',
			],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-plugin-editor-content',
			"{$dir_url}library/js/plugin-editor-content{$min}.js",
			[
				'wp-plugins',
				'wp-element',
				'wp-i18n',
				'wp-data',
				'wp-block-editor',
				'wp-api-fetch',
				'troy-server-plugin-editor-store',
			],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-plugin-editor-notices',
			"{$dir_url}library/js/plugin-editor-notices{$min}.js",
			[
				'wp-plugins',
				'wp-element',
				'wp-i18n',
				'wp-data',
				'troy-server-plugin-editor-store',
			],
			VERSION,
			true, // Load in footer.
		);

		\wp_enqueue_script(
			'troy-server-plugin-editor',
			"{$dir_url}library/js/plugin-editor{$min}.js",
			[
				'troy-server-editor-components',
				'troy-server-plugin-editor-components',
				'troy-server-plugin-editor-store',
				'troy-server-plugin-editor-content',
				'troy-server-plugin-editor-notices',
				'wp-i18n',
				'wp-element',
				'wp-data',
				'wp-components',
				'wp-block-editor',
				'wp-plugins',
				'wp-editor',
				'wp-api-fetch',
			],
			VERSION,
			true, // Load in footer.
		);

		$rest_plugins_manage = REST_NS['plugins_manage']['namespace'] . '/' . REST_NS['plugins_manage']['base'];

		\wp_localize_script(
			'troy-server-plugin-editor',
			'troyPluginEditorData',
			[
				'postType'       => PLUGINS_CPT,
				'maxFileSize'    => \wp_max_upload_size(),
				'maxFileSizeStr' => \size_format( \wp_max_upload_size() ),
				'originUrl'      => get_origin_url(),
				'restUrls'       => [
					'getEditorStore'     => \rest_url( "$rest_plugins_manage/getEditorStore" ),
					'registerSlug'       => \rest_url( "$rest_plugins_manage/registerSlug" ),
					'processZipFile'     => \rest_url( "$rest_plugins_manage/processZipFile" ),
					'processZipUrl'      => \rest_url( "$rest_plugins_manage/processZipUrl" ),
					'removeVersion'      => \rest_url( "$rest_plugins_manage/removeVersion" ),
					'getReadmeData'      => \rest_url( "$rest_plugins_manage/getReadmeData" ),
					'getSaveStatus'      => \rest_url( "$rest_plugins_manage/getSaveStatus" ),
					'getPlaceholderLogo' => \rest_url( "$rest_plugins_manage/getPlaceholderLogo" ),
				],
				// TODO, look at WP packages\editor\src\components\post-status\index.js
				'pluginStatuses' => [
					[
						'value'       => 'public',
						'label'       => \__( 'Public', 'troy-server' ),
						'description' => \__( 'Available for download and listing.', 'troy-server' ),
					],
					[
						'value'       => 'unlisted',
						'label'       => \__( 'Unlisted', 'troy-server' ),
						'description' => \__( 'Available for download only.', 'troy-server' ),
					],
					// TODO: Implement conditional listing.
					// [
					// 	'value'       => 'protected',
					// 	'label'       => \__( 'Protected', 'troy-server' ),
					// 	'description' => \__( 'Available for download and listing conditionally.', 'troy-server' ),
					// ],
					// TODO: var_dump() Implement automated publishing (i.e., add a check on save and test if the plugin is draft or public)
					[
						'value'       => 'pending',
						'label'       => \__( 'Pending', 'troy-server' ),
						'description' => \__( 'Automatically convert to public when published.', 'troy-server' ),
					],
					[
						'value'       => 'disabled',
						'label'       => \__( 'Disabled', 'troy-server' ),
						'description' => \__( 'Not available for download or listing.', 'troy-server' ),
					],
				],
				'builderTypes'   => [
					[
						'value'       => 'readme',
						'label'       => \__( 'ZIP readme', 'troy-server' ),
						'description' => \__( 'Reads the readme.txt from the latest public plugin ZIP.', 'troy-server' ),
					],
					[
						'value'       => 'post',
						'label'       => \__( 'Block Editor', 'troy-server' ),
						'description' => \__( 'Unlocks the Block Editor to edit the plugin.', 'troy-server' ),
					],
				],
				'versionTypes'   => [
					[
						'value'       => 'tag',
						'label'       => \__( 'Tag', 'troy-server' ),
						'description' => \__( 'Serve to everyone.', 'troy-server' ),
					],
					[
						'value'       => 'beta',
						'label'       => \__( 'Beta', 'troy-server' ),
						'description' => \__( 'Serve to beta testers.', 'troy-server' ),
					],
					[
						'value'       => 'unreleased',
						'label'       => \__( 'Unreleased', 'troy-server' ),
						'description' => \__( 'Not publicly available.', 'troy-server' ),
					],
				],
				'contentTabs'    => [
					'details'   => [
						'title' => \__( 'Details', 'troy-server' ),
						'id'    => 'details',
					],
					'usage'     => [
						'title' => \__( 'Usage', 'troy-server' ),
						'id'    => 'usage',
					],
					'faq'       => [
						'title' => \__( 'FAQ', 'troy-server' ),
						'id'    => 'faq',
					],
					'api'       => [
						'title' => \__( 'API', 'troy-server' ),
						'id'    => 'api',
					],
					'changelog' => [
						'title' => \__( 'Changelog', 'troy-server' ),
						'id'    => 'changelog',
					],
					// TODO
					// 'screenshots' => [
					// 	'title' => \__( 'Screenshots', 'troy-server' ),
					// 	'id'    => 'screenshots',
					// ],
				],
			],
		);
	}

	/**
	 * Enqueue the Block assets for the Troy Plugins post type.
	 *
	 * @hook enqueue_block_assets 10
	 * @since 0.0.1184
	 */
	public static function enqueue_block_assets() {

		$post_type = \is_admin()
			? $GLOBALS['current_screen']?->post_type
			: \get_post_type( \get_queried_object_id() );

		if ( PLUGINS_CPT !== $post_type )
			return;

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_style(
			'troy-server-plugin-blocks',
			"{$dir_url}library/css/plugin-blocks{$min}.css",
			[],
			VERSION,
		);
	}

	/**
	 * Adjust the theme.json settings for the Block Editor, but only for
	 * the Troy Plugins post type in the admin area.
	 *
	 * @hook wp_theme_json_data_theme 10
	 * @since 0.0.1184
	 *
	 * @param WP_Theme_JSON_Data $theme_json The theme.json settings.
	 * @return WP_Theme_JSON_Data The adjusted theme.json settings.
	 */
	public static function adjust_theme_json( $theme_json ) {

		$post_type = \is_admin()
			? $GLOBALS['current_screen']?->post_type
			: false;

		if ( PLUGINS_CPT !== $post_type )
			return $theme_json;

		return $theme_json->update_with(
			[
				'version'  => 3,
				'settings' => [
					'layout' => [
						'wideSize' => '772px',
					],
				],
			],
		);
	}
}
