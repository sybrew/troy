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
 * Plugin download endpoint for Troy Server.
 *
 * Handles plugin ZIP file downloads with proper file streaming,
 * version selection, and download statistics recording.
 *
 * @since 0.0.1184
 */
final class Download extends Base_Endpoint {

	/**
	 * Handle a plugin download request with slug provided directly.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug    The plugin slug.
	 * @param string $version The plugin version (default: 'latest').
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $version = 'latest',
	) {}

	/**
	 * Handle a plugin download request with slug provided directly.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug    The plugin slug.
	 * @param string $version The plugin version (default: 'latest').
	 */
	public function handle_slug_request( $slug, $version = 'latest' ) {
		$this->handle_download_request( $slug, $version );
	}

	/**
	 * Handle the core download request logic.
	 *
	 * @since 0.0.1184
	 */
	public function handle_request() {

		if ( 'GET' !== $_SERVER['REQUEST_METHOD'] )
			$this->send_error( 'Method not allowed', 405 );

		$slug    = $this->slug;
		$version = $this->version;

		// Validate required parameter (consistent with other endpoints)
		if ( ! $slug )
			$this->send_error( 'Missing required parameter: slug', 400 );

		// Sanitize slug parameter
		$slug = API\Sanitize::slug( $slug );

		if ( ! $slug )
			$this->send_error( 'Invalid slug', 400 );

		$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

		if ( ! $plugin_id )
			$this->send_error( 'Plugin not found', 404 );

		try {
			$data = new Data(
				$plugin_id,
				'latest' === $version
					? null
					: API\Sanitize::semver( $version ),
			);

			// Check plugin status - only serve downloads for public/unlisted plugins
			switch ( $data->get_plugins_row()->status ) {
				case 'public':
				case 'unlisted':
					// Allowed statuses. Break to continue processing.
					break;
				case 'protected':
				case 'pending':
				case 'disabled':
				default:
					$this->send_error( 'Plugin not available for download', 403 );
			}

			// This is sanitized with the database; overwrite if needed.
			$version = $data->plugin_version;

			if ( ! $version )
				$this->send_error( 'No compatible version found', 404 );

			$zip_data = $data->get_zips_row();

			if ( ! $zip_data )
				$this->send_error( 'Plugin data not found', 404 );

			$zip_file_path = Files::get_plugin_zip_file_path( $plugin_id, $version );

			// phpcs:ignore TSF.Performance.Functions.PHP -- Required for file validation
			if ( ! file_exists( $zip_file_path ) )
				$this->send_error( 'Plugin file not found', 404 );

			$this->record_download_stats( $plugin_id, $version );

			$this->clean_response_header();

			$filename = \sanitize_file_name( "{$slug}-{$version}.zip" );

			http_response_code( 200 );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/zip' );
			header( "Content-Disposition: attachment; filename=\"{$filename}\"" );
			header( "Content-Length: {$zip_data->file_size}" );
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );

			// phpcs:ignore WordPress.WP.AlternativeFunctions, TSF.Performance.Functions.PHP -- Required for file streaming
			readfile( $zip_file_path );
			exit;
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- acceptable in this context
			error_log( \sprintf(
				'Error downloading plugin %s (ID: %d): %s',
				$slug,
				$plugin_id,
				$e->getMessage(),
			) );

			$this->send_error( 'Internal server error', 500 );
		}
	}

	/**
	 * Record download statistics to the live stats table.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id The plugin ID.
	 * @param string $version   The version being downloaded.
	 */
	private function record_download_stats( $plugin_id, $version ) {

		global $wpdb;

		// Determine download type based on context (20 chars max)
		$type = 'api'; // Default for API downloads

		// Check if this is an update download
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = $_SERVER['HTTP_USER_AGENT'];
			// WordPress uses download_url(), which results using their standard User Agent.
			if ( str_contains( $user_agent, 'WordPress' ) ) {
				$type = 'update';
			} elseif ( str_contains( $user_agent, 'WP-CLI' ) ) {
				$type = 'cli';
			}
		}

		// TODO, check for referers?

		// Record live download stat
		$wpdb->insert(
			"{$wpdb->prefix}troy_plugin_stats_downloads_live",
			[
				'plugin_id'  => $plugin_id,
				'epoch'      => API\Utils::get_epoch(),
				'version'    => $version,
				'type'       => $type,
				'origin_url' => API\Server::get_repo_url(),
			],
			[
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			],
		);
	}
}
