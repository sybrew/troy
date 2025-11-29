<?php
/**
 * @package Troy\Server\Bootstrap
 * @access  private
 */

namespace Troy\Server\Bootstrap\Hook;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	PLUGINS_CPT,
	PACKAGES_CPT,
};

use Troy\Server\{
	Cron,
	Endpoints,
	Integrations,
	Plugins,
	Packages,
	Settings,
	Stats,
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

// Register general cron tasks.
\add_action( 'init', [ Cron::class, 'register' ] );

stats: {
	// Register stats aggregation cron tasks.
	\add_action( 'init', [ Stats\Cron::class, 'register' ] );

	// Register stats dashboard REST routes.
	\add_action( 'rest_api_init', [ Settings\REST::class, 'register_rest_routes' ] );
}

plugins: {
	// Register the plugins Custom Post Type.
	\add_action( 'init', [ Plugins\CPT\Init::class, 'register_post_types' ] );
	\add_action( 'init', [ Plugins\CPT\Init::class, 'register_taxonomies' ] );

	// Handle plugin storage. These hooks can exist on non-admin endpoints (e.g., REST API).
	\add_action( 'rest_after_insert_' . PLUGINS_CPT, [ Plugins\CPT\Store::class, 'handle_rest_after_insert_post' ] );
	\add_action( 'trash_' . PLUGINS_CPT, [ Plugins\CPT\Store::class, 'handle_trash_post' ] );
	\add_action( 'untrashed_post', [ Plugins\CPT\Store::class, 'handle_untrash_post' ] );
	\add_action( 'delete_post_' . PLUGINS_CPT, [ Plugins\CPT\Store::class, 'handle_delete_post' ] );
	\add_filter( 'wp_insert_post_empty_content', [ Plugins\CPT\Store::class, 'unset_empty_post' ], 10, 2 );

	// Register the plugin's rest routes.
	\add_action( 'rest_api_init', [ Plugins\REST::class, 'register_rest_routes' ] );
	\add_action( 'rest_api_init', [ Plugins\CPT\Block_Editor::class, 'register_post_meta' ] );
}

integrations: {
	// Register the plugin integrations' rest routes.
	\add_action( 'rest_api_init', [ Integrations\Plugins\REST::class, 'register_rest_routes' ] );

	// Register plugin cron tasks.
	\add_action( 'init', [ Integrations\Plugins\Cron::class, 'register' ] );
}

packages: {
	// Register the packages CPT.
	\add_action( 'init', [ Packages\CPT\Init::class, 'register_post_types' ] );

	// Register package meta boxes.
	\add_action( 'add_meta_boxes_' . PACKAGES_CPT, [ Packages\Meta_Boxes::class, 'register' ] );

	// Handle package storage.
	\add_action( 'save_post_' . PACKAGES_CPT, [ Packages\CPT\Store::class, 'handle_save_post' ] );
	\add_action( 'trash_' . PACKAGES_CPT, [ Packages\CPT\Store::class, 'handle_trash_post' ] );
	\add_action( 'untrashed_post', [ Packages\CPT\Store::class, 'handle_untrash_post' ] );
	\add_action( 'delete_post_' . PACKAGES_CPT, [ Packages\CPT\Store::class, 'handle_delete_post' ] );
	\add_filter( 'wp_insert_post_empty_content', [ Packages\CPT\Store::class, 'unset_empty_post' ], 10, 2 );

	// Register the package's REST routes.
	\add_action( 'rest_api_init', [ Packages\REST::class, 'register_rest_routes' ] );

	// Package list table customizations.
	\add_action( 'load-edit.php', [ Packages\CPT\List_View::class, 'register_list_edit_hooks' ] );
	\add_filter( 'manage_' . PACKAGES_CPT . '_posts_columns', [ Packages\CPT\List_View::class, 'register_columns' ] );
	\add_filter( 'manage_edit-' . PACKAGES_CPT . '_sortable_columns', [ Packages\CPT\List_View::class, 'register_sortable_columns' ] );
	\add_action( 'manage_' . PACKAGES_CPT . '_posts_custom_column', [ Packages\CPT\List_View::class, 'render_columns' ], 10, 2 );
}

endpoints: {
	// Handle repo API requests.
	\add_action( 'init', [ Endpoints\Router::class, 'handle_api_requests' ] );
}
