<?php
/**
 * @package Troy\Server\Endpoints
 * @access  public
 */

namespace Troy\Server\Endpoints;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\{
	get_origin_url,
	get_plugin_id_by_slug,
	get_latest_public_wordpress_version,
};

use function Troy\Server\Sanitize\{
	sanitize_slug,
	sanitize_tested_version,
};

use Troy\Server\Plugins\{
	Data,
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
 * Plugin updates endpoint for Troy Server.
 *
 * Handles plugin update checks, returning WordPress-compatible update
 * information for Troy Client plugin update requests.
 *
 * @since 0.0.1184
 */
final class Plugin_Updates extends Base_Endpoint {

	/**
	 * Handle the plugin updates request.
	 *
	 * @since 0.0.1184
	 */
	public function handle_request() {

		// Validate that this is a POST request
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			$this->send_error( 'Method not allowed', 405 );

		// phpcs:disable WordPress.Security.NonceVerification -- Public API endpoints don't use nonces
		$active_plugins   = (array) ( $_POST['active_plugins'] ?? [] );
		$inactive_plugins = (array) ( $_POST['inactive_plugins'] ?? [] );

		$locales     = (array) ( $_POST['locales'] ?? [] );
		$origin_url  = get_origin_url();
		$php_version = sanitize_tested_version( $_POST['php_version'] ?? '' );
		$wp_version  = sanitize_tested_version( $_POST['wp_version'] ?? '' );
		// $troy_version = sanitize_tested_version( $_POST['troy_version'] ?? [] );

		$client_uuid = $this->get_client_uuid();
		// phpcs:enable WordPress.Security.NonceVerification

		// $translations        = (array) ( $_POST['translations'] ?? [] );

		$response = [
			'response'     => [],
			'no_update'    => [],
			'translations' => [],
		];

		// Process active plugins
		foreach ( array_merge( $active_plugins, $inactive_plugins ) as $slug => $cur_version ) {
			$slug = sanitize_slug( $slug );

			if ( ! $slug )
				continue;

			// TODO: Once we support transporting slugs, we should resolve the new slug from this plugin ID.
			$plugin_id = get_plugin_id_by_slug( $slug );

			if ( ! $plugin_id )
				continue;

			try {
				$metas = new Data( $plugin_id )->get_metas_row();
				// Get latest COMPATIBLE plugin zip
				$zip = Files::get_latest_plugin_zip(
					$plugin_id,
					[
						'wp_version'  => $wp_version,
						'php_version' => $php_version,
						'type'        => 'tag', // TODO implement beta channel support
					]
				);

				$response = [
					// ID should actually be equal to the "Update URI", but that header never considered real plugin authors.
					'id'               => $slug,
					'slug'             => $slug,
					// The 'plugin' field is expected by WordPress, but overwritten immediately by the original filename.
					// Then, it remains unused. Ehh...?
					// 'plugin'           => $slug,
					'new_version'      => null,
					'url'              => $metas?->permalink ?: '',
					'package'          => '', // Plugin update pakcage URL.
					// WordPress expects (in order) svg, 2x, 1x, and default.
					// There's a bug where it only uses 1x instead of default, so we always fill 1x.
					'icons'            => [
						'1x' => $metas?->logo_uri ?: '',
					],
					// The banners are expected in the response, but unused by WordPress. Let's leave them empty.
					'banners'          => [],
					'banners_rtl'      => [],
					'requires'         => '', // WordPress version
					'tested'           => '', // WordPress version
					'requires_php'     => '',
					// We use a different format, managed locally by the Client. We'll leave this empty.
					'requires_plugins' => [],
					// Compatibility is expected but immediately filtered out by WordPress -- odd.
					'compatibility'    => [],
					'upgrade_notice'   => '',
				];

				// Feed if the latest compatible version is newer than the current version.
				if ( $zip && version_compare( $zip->version, $cur_version, '>' ) ) {
					$response['response'][ $slug ] = array_merge(
						$response,
						[
							'new_version'    => $zip->version,
							'package'        => Files::get_plugin_zip_url_by_slug( $slug, $zip->version ),
							'requires'       => $zip->requires_wp ?: '',
							'requires_php'   => $zip->requires_php ?: '',
							'tested'         => \Troy\Server\get_latest_public_wordpress_version( $zip->tested_wp ),
							'upgrade_notice' => $zip->upgrade_notice ?: '',
						],
					);
				} else {
					// We must send a no_update response to support auto updates.
					$response['no_update'][ $slug ] = $response;
				}

				// Record update request stats
				$this->record_update_request_stats(
					$plugin_id,
					// TODO add inactive/active version check
					$cur_version,
					$client_uuid,
					$locales,
					$origin_url,
					$php_version,
					$wp_version,
				);

			} catch ( \Exception $e ) {
				// Skip plugin on error
				continue;
			}
		}

		$this->send_json_response( $response );
	}

	/**
	 * Record update request statistics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id   The plugin ID.
	 * @param string $version     The client's current plugin version.
	 * @param string $client_uuid The client UUID.
	 * @param array  $locales     The requested locales.
	 * @param string $origin_url  The origin URL.
	 * @param string $php_version The PHP version.
	 * @param string $wp_version  The WordPress version.
	 */
	private function record_update_request_stats( $plugin_id, $version, $client_uuid, $locales, $origin_url, $php_version, $wp_version ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		global $wpdb;

		// Record live update request stat
		$wpdb->insert(
			"{$wpdb->prefix}troy_plugins_update_request_stats_live",
			[
				'plugin_id'     => $plugin_id,
				'version'       => $version,
				'uuid'          => $client_uuid ?: 'unknown',
				'request_count' => 1,
				'locales'       => json_encode( $locales ),
				'php_version'   => $php_version,
				'wp_version'    => $wp_version,
			],
			[
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
			],
		);
	}
}
