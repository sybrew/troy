<?php
/**
 * @package Troy\Client\Bootstrap
 * @access  private
 */

namespace Troy\Client\Bootstrap\Hook;

\defined( 'Troy\Client\ABSPATH' ) or die;

use const Troy\Client\PLUGIN_BASENAME;

use Troy\Client\{
	Dependencies,
	Headers,
	HTTP,
	PluginsAPI,
	ProtectClient,
	SiteHealth,
};

use function Troy\Client\API\recheck_dependencies;

/**
 * Troy Client
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

// Conditionally hide Troy Client from every plugins list.
\add_filter( 'plugins_list', [ ProtectClient::class, 'hide_plugin' ], \PHP_INT_MAX );

// Conditionally hide Troy Client plugin's bulk action checkbox.
\add_action( 'after_plugin_row_' . PLUGIN_BASENAME, [ ProtectClient::class, 'hide_action_checkbox' ] );
\add_action( 'after_plugin_row_meta', [ ProtectClient::class, 'list_troy_dependents' ] );

// Conditionally block Troy Client deactivation.
\add_action( 'pre_update_option_active_plugins', [ ProtectClient::class, 'block_plugin_deactivation' ], \PHP_INT_MIN, 2 );
\add_filter( 'plugin_action_links_' . PLUGIN_BASENAME, [ ProtectClient::class, 'remove_deactivate_link' ] );

// Conditionally block Troy Client network deactivation.
\add_action( 'pre_update_site_option_active_sitewide_plugins', [ ProtectClient::class, 'block_plugin_deactivation' ], \PHP_INT_MIN, 2 );
\add_filter( 'network_admin_plugin_action_links_' . PLUGIN_BASENAME, [ ProtectClient::class, 'remove_deactivate_link' ] );

// Add Troy connection status to Site Health Status tab.
\add_filter( 'site_status_tests', [ SiteHealth::class, 'register_site_status_tests' ] );
\add_action( 'rest_api_init', [ SiteHealth::class, 'register_async_rest_routes' ], 100 );

// Add Troy connection information to Site Health Info tab.
\add_filter( 'debug_information', [ SiteHealth::class, 'add_debug_info' ] );

// Register extra plugin and theme headers as late as possible.
\add_filter( 'extra_plugin_headers', [ Headers::class, 'register_plugin_headers' ], \PHP_INT_MAX );

// Enable the Troy plugins API.
\add_filter( 'pre_set_site_transient_update_plugins', [ PluginsAPI::class, 'filter_update_plugins_transient' ], \PHP_INT_MAX );
\add_filter( 'plugins_api', [ PluginsAPI::class, 'filter_plugins_api' ], \PHP_INT_MAX, 3 );

// Prevent Troy plugins from reaching WordPress.org. This primarily affects plugin search and info.
\add_filter( 'plugins_api_args', [ PluginsAPI::class, 'filter_plugins_api_args' ], \PHP_INT_MAX );
\add_filter( 'http_request_args', [ HTTP::class, 'filter_request_args' ], \PHP_INT_MAX, 2 );

// Clear the API request cache after an update, for cached information may now be incompatible.
\add_filter( 'upgrader_process_complete', [ PluginsAPI::class, 'cleanup_after_upgrade' ] );

// Look for Troy Dependencies and install them on plugin activation or upgrade.
\add_action( 'activated_plugin', [ Dependencies::class, 'set_recheck_flag' ] );
\add_action( 'upgrader_process_complete', [ Dependencies::class, 'set_recheck_flag' ] );

// Only loads the file when we can recheck dependencies.
if ( recheck_dependencies() )
	\add_action( 'admin_init', [ Dependencies::class, 'install_troy_dependencies' ] );

// Themes (soon™):
// \add_filter( 'extra_theme_headers', [ Headers::class, 'register_theme_headers' ], \PHP_INT_MAX );
// \add_filter( 'themes_api_args', [ ThemesAPI::class, 'filter_themes_api_args' ], \PHP_INT_MAX );
// \add_filter( 'themes_api', [ ThemesAPI::class, 'filter_themes_api' ], \PHP_INT_MAX, 3 );
// \add_action( 'switch_theme', [ Dependencies::class, 'set_recheck_flag' ] );
