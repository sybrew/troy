<?php
/**
 * @package Troy\Client
 */

namespace Troy\Client;

\defined( 'Troy\Client\ABSPATH' ) or die;

/**
 * Troy Client
 *
 * Copyright (c) 2025 - 2026 Sybre Waaijer, CyberWire B.V.
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
 * Handles plugin table modifications.
 *
 * @since 1.6.1184
 */
final class Plugin_Table {

	/**
	 * Adds a privacy policy link to the plugin row meta.
	 *
	 * @hook plugin_row_meta 10
	 * @since 1.6.1184
	 *
	 * @param string[] $plugin_meta An array of the plugin's metadata.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return string[] The modified plugin metadata.
	 */
	public static function add_row_meta( $plugin_meta, $plugin_file ) {

		if ( PLUGIN_BASENAME !== $plugin_file )
			return $plugin_meta;

		$plugin_meta['troy-client-privacy'] = \sprintf(
			'<a href="https://deploytroy.org/privacy/" target="_blank" rel="noopener">%s</a>',
			\esc_html__( 'Privacy', 'troy-client' ),
		);

		return $plugin_meta;
	}

	/**
	 * Adds a link explaining why Troy Client cannot be deactivated.
	 * Hidden when the daemon is visible, the user lacks the deactivate_plugins capability,
	 * or the user is not a network admin in a multisite environment.
	 *
	 * @hook plugin_action_links_{\Troy\Client\PLUGIN_BASENAME} 10
	 * @hook network_admin_plugin_action_links_{\Troy\Client\PLUGIN_BASENAME} 10
	 * @since 1.7.1184
	 *
	 * @param string[] $actions An array of plugin action links.
	 * @return string[] The modified plugin action links.
	 */
	public static function show_deactivation_explanation( $actions ) {

		if (
			   \defined( 'Troy\Client\Daemon\ACTIVE' )
			|| ! \current_user_can( 'deactivate_plugins' )
			|| ( \is_multisite() && ! \is_network_admin() )
			|| ! has_troy_plugins()
		)
			return $actions;

		$actions['troy-deactivation-info'] = \sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			'https://deploytroy.org/docs/troy-client/deactivation/',
			\esc_html__( 'Deactivation protected', 'troy-client' ),
		);

		return $actions;
	}
}
