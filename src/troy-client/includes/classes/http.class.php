<?php
/**
 * @package Troy\Client
 * @access  private
 */

namespace Troy\Client;

\defined( 'Troy\Client\ABSPATH' ) or die;

use function Troy\Client\{
	has_troy_plugins,
	get_troy_plugins,
};

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

/**
 * Class Troy\Client\HTTP.
 *
 * @since 0.0.1184
 */
final class HTTP {

	/**
	 * Filters the arguments used in an HTTP request.
	 *
	 * This is used to filter out Troy plugins from the update check and subsequent
	 * translation checks. It also filters out Troy plugin headers, so that Troy's
	 * presence isn't leaked.
	 *
	 * We do not filter out Troy Dependencies here. Plugins installed via a Troy
	 * Dependency should come with their own Troy headers, with which they can be
	 * filtered out.
	 *
	 * However, we do hijack the update check to redirect Troy Dependencies. This
	 * happens in PluginsAPI::modify_plugins_transient().
	 *
	 * @hook http_request_args PHP_INT_MAX 2
	 * @since 0.0.1184
	 *
	 * @param array  $parsed_args An array of HTTP request arguments.
	 * @param string $url         The request URL.
	 * @return array The filtered arguments.
	 */
	public static function filter_request_args( $parsed_args, $url ) {

		if (
			   false !== stripos( $url, 'api.wordpress.org/plugins/update-check' )
			&& isset( $parsed_args['body'] )
		) {
			$troy_plugins = get_troy_plugins();

			if ( ! empty( $parsed_args['body']['plugins'] ) ) {
				$troy_plugin_files = array_keys( $troy_plugins );

				// This list is obtained via \get_plugins().
				$plugins = json_decode( $parsed_args['body']['plugins'], true );

				if ( isset( $plugins['plugins'] ) ) {
					$plugins['plugins'] = array_diff_key( $plugins['plugins'], array_flip( $troy_plugin_files ) );

					$troy_headers_keyed = array_flip( array_merge(
						TROY_PLUGIN_HEADERS['repo'],
						TROY_PLUGIN_HEADERS['dependencies'],
					) );

					foreach ( $plugins['plugins'] as &$header )
						$header = array_diff_key( $header, $troy_headers_keyed );
				}

				if ( isset( $plugins['active'] ) )
					$plugins['active'] = array_diff( $plugins['active'], $troy_plugin_files );

				// Let's not relegate to wp_json_encode().
				$parsed_args['body']['plugins'] = json_encode( $plugins );
			}

			if ( ! empty( $parsed_args['body']['translations'] ) ) {
				// This list is obtained via \wp_get_installed_translations(), which globbed a directory.
				// Those files are only populated via the plugin update check, relying only on text-domains.
				$translations = json_decode( $parsed_args['body']['translations'], true );

				$translations = array_diff_key(
					$translations,
					array_flip( array_column( $troy_plugins, 'textdomain' ) ),
				);

				// Let's not relegate to wp_json_encode().
				$parsed_args['body']['plugins'] = json_encode( $translations );
			}
		}

		return $parsed_args;
	}
}
