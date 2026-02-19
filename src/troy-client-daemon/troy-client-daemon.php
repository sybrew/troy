<?php
/**
 * Troy Client Daemon
 *
 * @package   Troy\Client\Daemon
 * @author    Sybre Waaijer
 * @copyright 2025 Sybre Waaijer, CyberWire B.V. (https://cyberwire.nl/)
 * @license   MIT
 * @link      https://github.com/sybrew/troy/
 *
 * @troy-repo
 * Troy: disable-all-communications
 *
 * @wordpress-plugin
 * Plugin Name: Troy Client Daemon - Must Use only
 * Plugin URI: https://deploytroy.org/
 * Description: This daemon forces installation and activation of Troy Client. It blocks the WordPress update API if Troy Client is not active.
 * Version: 1.6.1184
 * Author: Sybre Waaijer
 * Author URI: https://deploytroy.org/
 * License: MIT
 * Requires at least: 6.7
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

namespace Troy\Client\Daemon;

\defined( 'ABSPATH' ) or die;

{ { { { { { { { { { { { { { { { { {
	'made' || 'with' || 'love';
	_by_:      'Sybre Waaijer';
	_for_:     'The community';
	_license_: 'MIT. No GPLv2';
	ExtendWordPressWith::class;
} } } } } } } } } } } } } } } } } }

/**
 * Troy Client Daemon
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

if ( \did_action( 'muplugins_loaded' ) ) {
	\deactivate_plugins( \plugin_basename( __FILE__ ) );
	\wp_die( '<p>Troy Client Daemon is a must-use plugin and cannot be activated normally.<br>This plugin is now deactivated.</p><p>Please install Troy Client Daemon as: <code>/wp-content/mu-plugins/troy-client-daemon.php</code></p>' );
}

/**
 * Whether the Troy Client Daemon is active and monitoring.
 *
 * @since 0.0.1184
 */
const ACTIVE = true;

\add_action( 'muplugins_loaded', 'Troy\Client\Daemon\check_troy_client' );
\add_action( 'admin_init', 'Troy\Client\Daemon\remove_deactivation_elements' );

/**
 * Force-activates the Troy Client plugin.
 * If the plugin is not installed, it will be installed from the DeployTroy repository.
 *
 * @hook muplugins_loaded 10
 * @since 0.0.1184
 */
function check_troy_client() {

	$plugin_file  = 'troy-client/troy-client.php';
	$is_multisite = \is_multisite();

	$is_troy_active = $is_multisite
		? isset( \get_site_option( 'active_sitewide_plugins' )[ $plugin_file ] )
		: \in_array( $plugin_file, (array) \get_option( 'active_plugins' ), true );

	if ( ! $is_troy_active ) {
		\add_filter( 'pre_http_request', 'Troy\Client\Daemon\block_wordpress_api', 10, 3 );
		\add_action( 'init', 'Troy\Client\Daemon\install_and_activate_troy_client' );
	} elseif ( ! $is_multisite ) {
		// Add a later sanity check for recovery mode where the plugin can still be deactivated.
		\add_action( 'plugins_loaded', 'Troy\Client\Daemon\check_client_paused' );
	}
}

/**
 * Checks if the Troy Client plugin is paused, and if so, blocks the WordPress API.
 *
 * @hook plugins_loaded 10
 * @since 0.0.1184
 */
function check_client_paused() {
	if ( \is_plugin_paused( 'troy-client/troy-client.php' ) )
		\add_filter( 'pre_http_request', 'Troy\Client\Daemon\block_wordpress_api', 10, 3 );
}

/**
 * Installs and activates the Troy Client plugin.
 *
 * If the plugin is not installed, it will be installed from the DeployTroy repository.
 *
 * @hook plugins_loaded 10
 * @since 0.0.1184
 */
function install_and_activate_troy_client() {

	if ( ! \function_exists( 'get_plugins' ) )
		require_once \ABSPATH . 'wp-admin/includes/plugin.php';

	$plugin_file = 'troy-client/troy-client.php';
	$troy_plugin = \get_plugins()[ $plugin_file ] ?? '';

	if ( ! $troy_plugin ) {
		\wp_raise_memory_limit( 'troy-client-daemon-init-fs' );

		// Let's not fully rely on globals to check if the filesystem is initialized.
		if ( empty( $GLOBALS['wp_filesystem'] ) || ! \function_exists( 'WP_Filesystem' ) ) {
			// Ensure WP_Filesystem() is declared
			require_once \ABSPATH . 'wp-admin/includes/file.php';

			if ( ! \WP_Filesystem() )
				return;
		}

		require_once \ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$client_url = 'https://repo.deploytroy.org/plugin/get/zip/troy-client/';

		\add_filter(
			'http_headers_useragent',
			fn( $user_agent, $url ) => $url === $client_url ? 'Troy Daemon' : $user_agent,
			10,
			2,
		);

		$result = ( new \Plugin_Upgrader(
			new class extends \Automatic_Upgrader_Skin {
				/**
				 * Footer output suppression.
				 */
				public function footer() {
					ob_end_clean();
				}
			}
		) )->install(
			$client_url,
			[ 'overwrite_package' => true ],
		);

		if ( true !== $result )
			return;

		\wp_clean_plugins_cache();

		$troy_plugin = \get_plugins()[ $plugin_file ] ?? '';
	}

	if ( $troy_plugin )
		\wp_installing() or \activate_plugin( $plugin_file, '', \is_multisite(), true );
}

/**
 * Blocks requests to the WordPress themes and plugins API.
 *
 * This is to prevent leaking data to WordPress.org when the Troy Client plugin
 * is not installed.
 * This is a security measure, for it prevents WordPress from hijacking
 * Troy-dependent plugins.
 *
 * It is also a privacy measure, for it prevents WordPress.org from tracking Troy
 * plugins and themes installed on the site.
 *
 * @hook pre_http_request 10
 * @since 0.0.1184
 *
 * @param false|array|WP_Error $response    A preemptive return value of an HTTP
 *                                          request. Default false.
 * @param array                $parsed_args HTTP request arguments.
 * @param string               $url         The request URL.
 * @return array|false
 */
function block_wordpress_api( $response, $parsed_args, $url ) {

	if ( array_filter(
		[
			'api.wordpress.org/plugins',
			'api.wordpress.org/themes',
		],
		fn( $blocked_uri ) => false !== stripos( $url, $blocked_uri ),
	) ) {
		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code' => 403, // Forbidden
			],
		];
	}

	return $response;
}

/**
 * Removes deactivation elements from the Troy Client plugin.
 *
 * This also happens in Troy Client itself, however, that is done conditionally
 * based on Troy dependencies.
 * Therefore, this process is duplicated here.
 *
 * @hook admin_init 10
 * @since 0.0.1184
 */
function remove_deactivation_elements() {

	$basename = \defined( 'Troy\Client\PLUGIN_BASENAME' )
		? \Troy\Client\PLUGIN_BASENAME
		: 'troy-client/troy-client.php'; // Default

	\add_filter( "plugin_action_links_$basename", 'Troy\Client\Daemon\hide_deactivate_link' );
	\add_filter( "network_admin_plugin_action_links_$basename", 'Troy\Client\Daemon\hide_deactivate_link', 10, 2 );
	\add_action( "after_plugin_row_$basename", 'Troy\Client\Daemon\hide_action_checkbox' );
}

/**
 * Removes the deactivate link from the plugin row for the Troy Client plugin.
 *
 * @hook plugin_action_links_troy-client/troy-client.php 10
 * @hook network_admin_plugin_action_links_troy-client/troy-client.php 10
 * @since 0.0.1184
 *
 * @param array $actions The plugin action links.
 * @return array
 */
function hide_deactivate_link( $actions ) {
	unset( $actions['deactivate'] );
	return $actions;
}

/**
 * Hides the checkbox for the Troy Client plugin for no-JS, and deletes it on JS
 * so it cannot be bulk-deactivated.
 * The plugin is forced active, so the checkbox is redundant, and will only cause
 * a loop of uselessness.
 *
 * @hook after_plugin_row_troy-client/troy-client.php 10
 * @since 0.0.1184
 */
function hide_action_checkbox() {
	echo <<<'HTML'
	<style>
		#the-list [data-slug="troy-client"] .check-column :where(label,input) {
			display: none;
		}
	</style>
	<script>
		document.querySelectorAll( '#the-list [data-slug="troy-client"] .check-column :where(label,input)' )
			.forEach( e => e.remove() );
	</script>
	HTML;
}
