<?php
/**
 * @package Troy\Server\Packages
 * @access  private
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	ABSPATH,
	MAIN_FILE,
	PACKAGES_CPT,
	REST_NS,
	VERSION,
};

use Troy\Server\{
	API,
	Template,
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
 * Class Troy\Server\Packages\Meta_Boxes.
 *
 * Manages meta boxes for the Packages CPT.
 *
 * @since 0.0.1184
 */
final class Meta_Boxes {

	/**
	 * Enqueues editor assets for the packages CPT.
	 *
	 * @hook admin_enqueue_scripts 10
	 * @since 0.0.1184
	 */
	public static function enqueue_editor_assets() {

		$screen = \get_current_screen();

		if ( 'post' !== $screen->base || PACKAGES_CPT !== $screen->post_type )
			return;

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_style(
			'troy-server-editor-package-meta-boxes',
			"{$dir_url}library/css/editor/packages/meta-boxes{$min}.css",
			[],
			VERSION,
		);

		\wp_enqueue_script(
			'troy-server-editor-package-meta-boxes',
			"{$dir_url}library/js/editor/packages/meta-boxes{$min}.js",
			[ 'wp-api-fetch', 'wp-url', 'troy-server-timing' ],
			VERSION,
			true,
		);

		$post_id              = \get_the_ID();
		$rest_packages_manage = REST_NS['packages_manage']['namespace'] . '/' . REST_NS['packages_manage']['base'];

		\wp_localize_script(
			'troy-server-editor-package-meta-boxes',
			'troyPackageEditorData',
			[
				'packageId' => $post_id ? API\Package::get_package_id_by_post_id( $post_id ) : 0,
				'restUrls'  => [ 'validateSlug' => \rest_url( "$rest_packages_manage/validateSlug" ) ],
			],
		);
	}

	/**
	 * Registers meta boxes for the packages CPT.
	 *
	 * @hook add_meta_boxes_{PACKAGES_CPT} 10
	 * @since 0.0.1184
	 */
	public static function register() {

		\add_meta_box(
			'troy_server_package_plugins',
			\__( 'Included Plugins', 'troy-server' ),
			[ self::class, 'render_plugins_metabox' ],
			PACKAGES_CPT,
			'normal',
			'default',
		);

		\add_meta_box(
			'troy_server_package_themes',
			\__( 'Included Themes', 'troy-server' ),
			[ self::class, 'render_themes_metabox' ],
			PACKAGES_CPT,
			'normal',
			'default',
		);

		\add_meta_box(
			'troy_server_package_settings',
			\__( 'Package Settings', 'troy-server' ),
			[ self::class, 'render_settings_metabox' ],
			PACKAGES_CPT,
			'normal',
			'high',
		);

		\add_meta_box(
			'troy_server_package_advanced',
			\__( 'Advanced Package Settings', 'troy-server' ),
			[ self::class, 'render_advanced_metabox' ],
			PACKAGES_CPT,
			'normal',
			'default',
		);

		\add_meta_box(
			'troy_server_package_download',
			\__( 'Package Download', 'troy-server' ),
			[ self::class, 'render_download_metabox' ],
			PACKAGES_CPT,
			'side',
			'high',
		);
	}

	/**
	 * Renders the plugins meta box.
	 *
	 * @since 0.0.1184
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function render_plugins_metabox( $post ) {
		Template::output_view( 'editor/packages/plugins-meta-box', $post );
	}

	/**
	 * Renders the themes meta box.
	 *
	 * @since 0.0.1184
	 */
	public static function render_themes_metabox() {
		Template::output_view( 'editor/packages/themes-meta-box' );
	}

	/**
	 * Renders the package settings meta box.
	 *
	 * @since 0.0.1184
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function render_settings_metabox( $post ) {
		Template::output_view( 'editor/packages/settings-meta-box', $post );
	}

	/**
	 * Renders the advanced package settings meta box.
	 *
	 * @since 0.0.1184
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function render_advanced_metabox( $post ) {
		Template::output_view( 'editor/packages/advanced-meta-box', $post );
	}

	/**
	 * Renders the download link meta box.
	 *
	 * @since 0.0.1184
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function render_download_metabox( $post ) {
		Template::output_view( 'editor/packages/download-meta-box', $post );
	}
}
