<?php
/**
 * @package Troy\Server\Bootstrap
 * @access  private
 */

namespace Troy\Server\Bootstrap\Hook;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\PLUGINS_CPT;

use Troy\Server\{
	Cron,
	Endpoints,
	Plugins,
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

// Register global cron schedules globally.
\add_filter( 'cron_schedules', [ Cron::class, 'register_schedules' ] );

plugins: {
	// Register the plugin's post meta fields, but only for REST API requests (i.e. saving).
	\add_action( 'rest_api_init', [ Plugins\CPT\Block_Editor::class, 'register_post_meta' ] );

	// Handle plugin storage. These hooks can exist on non-admin endpoints (e.g., REST API).
	\add_action( 'rest_after_insert_' . PLUGINS_CPT, [ Plugins\CPT\Store::class, 'handle_rest_after_insert_post' ] );
	\add_action( 'trash_' . PLUGINS_CPT, [ Plugins\CPT\Store::class, 'handle_trash_post' ] );
	\add_action( 'untrashed_post', [ Plugins\CPT\Store::class, 'handle_untrash_post' ] );
	\add_action( 'delete_post_' . PLUGINS_CPT, [ Plugins\CPT\Store::class, 'handle_delete_post' ] );
	\add_filter( 'wp_insert_post_empty_content', [ Plugins\CPT\Store::class, 'unset_empty_post' ], 10, 2 );

	// Register plugin cron schedules globally.
	\add_filter( 'cron_schedules', [ Plugins\Cron::class, 'register_schedules' ] );

	// Register the plugins Custom Post Type.
	\add_action( 'init', [ Plugins\CPT\Init::class, 'register_post_types' ] );
	\add_action( 'init', [ Plugins\CPT\Init::class, 'register_taxonomies' ] );

	// Register the plugin's rest routes.
	\add_action( 'rest_api_init', [ Plugins\REST::class, 'register_rest_routes' ] );
}

endpoints: {
	\add_action( 'init', [ Endpoints\Router::class, 'handle_api_requests' ] );
}
