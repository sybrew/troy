<?php
/**
 * @package Troy\Server\Endpoints\Plugins
 * @access  public
 */

namespace Troy\Server\Endpoints\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Endpoints\Base_Endpoint,
	Plugins\Data,
	Plugins\Files,
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
final class Updates extends Base_Endpoint {

	/**
	 * Handle the plugin updates request.
	 *
	 * @rest plugin/get/updates/plugin-name POST
	 * @since 0.0.1184
	 */
	public function handle_request() {

		// Validate that this is a POST request
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			$this->send_error( 'Method not allowed', 405 );

		// phpcs:ignore TSF.Performance -- This read a stream, not a file.
		$input = json_decode( file_get_contents( 'php://input' ), true );

		if ( ! \is_array( $input ) )
			$this->send_error( 'Invalid JSON input', 400 );

		$active_plugins   = (array) ( $input['active_plugins'] ?? [] );
		$inactive_plugins = (array) ( $input['inactive_plugins'] ?? [] );

		$locales      = (array) ( $input['locales'] ?? [] );
		$origin_url   = API\Server::get_repo_url(); // Already sanitized
		$php_version  = API\Sanitize::tested_version( $input['php_version'] ?? '' );
		$wp_version   = API\Sanitize::tested_version( $input['wp_version'] ?? '' );
		$troy_version = API\Sanitize::tested_version( $input['troy_version'] ?? '' );
		$channel      = API\Sanitize::channel( $input['channel'] ?? 'tag' );

		[
			'epoch' => $epoch,
			'uuid' => $uuid,
		] = $this->get_client_uuid();

		// TODO: implement translation updates
		// $translations = (array) ( $input['translations'] ?? [] );

		$response = [
			'no_update'    => [],
			'translations' => [],
			'update'       => [],
		];

		// Process active plugins
		foreach ( array_merge( $active_plugins, $inactive_plugins ) as $slug => $cur_version ) {

			// We need an unmodified index key to test if the plugin is active. Do not sanitize yet.
			$is_active = isset( $active_plugins[ $slug ] );

			$slug = API\Sanitize::slug( $slug );

			if ( ! $slug )
				continue;

			// TODO: Once we support transporting slugs, we should resolve the new slug from this plugin ID.
			$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

			if ( ! $plugin_id )
				continue;

			try {
				$data = new Data( $plugin_id );

				// Check plugin status - only serve updates for public/unlisted plugins
				switch ( $data->get_plugins_row()->status ) {
					case 'public':
					case 'unlisted':
						// Allowed statuses. Break to continue processing.
						break;
					case 'protected':
					case 'pending':
					case 'disabled':
					default:
						// Skip other statuses. Continue to next plugin.
						continue 2;
				}

				$metas = $data->get_metas_row();
				// Get latest COMPATIBLE plugin zip
				$zip = Files::get_latest_plugin_zip(
					$plugin_id,
					[
						'wp_version'  => $wp_version,
						'php_version' => $php_version,
						'channel'     => $channel,
					]
				);

				// Write default plugin info for update and no_update responses.
				$plugin_info = [
					// ID should actually be equal to the "Update URI", but that header never considered real plugin authors.
					'id'               => $origin_url,
					'slug'             => $slug,
					// The 'plugin' field is expected by WordPress, but overwritten immediately by the original filename.
					// Then, it remains unused. Ehh...? TODO reintroduce for future-proofing? We'd have to write the filename.
					// 'plugin'           => $slug,
					'new_version'      => null,
					'url'              => $metas?->permalink ?: '',
					'package'          => '', // Plugin update package URL. Filled below.
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
					'autoupdate'       => false, // We can FORCE an update via this. Let's block it via Troy Client.
				];

				// Feed if the latest compatible version is newer than the current version.
				if ( $zip && version_compare( $zip->version, $cur_version, '>' ) ) {
					$response['update'][ $slug ] = array_merge(
						$plugin_info,
						[
							'new_version'    => $zip->version,
							'package'        => Files::get_plugin_zip_url_by_slug( $slug, $zip->version ),
							'tested'         => API\Utils::get_latest_public_wordpress_version( $zip->tested_wp ),
							'requires'       => $zip->requires_wp ?: '',
							'requires_php'   => $zip->requires_php ?: '',
							'upgrade_notice' => $zip->upgrade_notice ?: '',
							'autoupdate'     => false, // We can FORCE an update via this. Let's block it via Troy Client.
						],
					);
				} else {
					// We must send a no_update response to support auto updates.
					$response['no_update'][ $slug ] = $plugin_info;
				}

				// Record update request stats
				$this->record_update_request_stats(
					$plugin_id,
					$epoch,
					$cur_version,
					$is_active,
					$uuid,
					$locales,
					$origin_url,
					$php_version,
					$wp_version,
					$troy_version,
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
	 * Upserts a row per unique (plugin_id, epoch, uuid) combination. On duplicate,
	 * updates to the latest version/is_active state and increments request_count.
	 *
	 * Uses MySQL 8.0.19+ syntax for upsert with multiple updates.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id      The plugin ID.
	 * @param int    $epoch          The epoch extracted from the client UUID.
	 * @param string $version        The client's current plugin version.
	 * @param bool   $is_active      Whether the plugin is active.
	 * @param string $client_uuid    The client UUID.
	 * @param array  $locales        The requested locales.
	 * @param string $origin_url     The origin URL.
	 * @param string $php_version    The PHP version.
	 * @param string $wp_version     The WordPress version.
	 * @param string $client_version The Troy Client version.
	 */
	private function record_update_request_stats(
		$plugin_id,
		$epoch,
		$version,
		$is_active,
		$client_uuid,
		$locales,
		$origin_url,
		$php_version,
		$wp_version,
		$client_version,
	) {

		global $wpdb;

		$locales = API\Sanitize::json_encode_db( $locales );

		// Upsert live update request stat; on duplicate, update to latest state and increment request_count.
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}troy_plugin_stats_requests_live
			SET
				plugin_id      = %d,
				epoch          = %d,
				version        = %s,
				is_active      = %d,
				uuid           = %s,
				request_count  = 1,
				locales        = %s,
				origin_url     = %s,
				php_version    = %s,
				wp_version     = %s,
				client_version = %s
			ON DUPLICATE KEY UPDATE
				version        = %s,
				is_active      = %d,
				request_count  = {$wpdb->prefix}troy_plugin_stats_requests_live.request_count + 1,
				locales        = %s,
				origin_url     = %s,
				php_version    = %s,
				wp_version     = %s,
				client_version = %s",
			// INSERT
			$plugin_id,
			$epoch,
			$version,
			$is_active,
			$client_uuid ?: 'unknown',
			$locales,
			$origin_url,
			$php_version,
			$wp_version,
			$client_version,
			// UPDATE
			$version,
			$is_active,
			$locales,
			$origin_url,
			$php_version,
			$wp_version,
			$client_version,
		) );
	}
}
