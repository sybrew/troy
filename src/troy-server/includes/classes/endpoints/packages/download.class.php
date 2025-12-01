<?php
/**
 * @package Troy\Server\Endpoints\Packages
 * @access  public
 */

namespace Troy\Server\Endpoints\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Endpoints\Base_Endpoint,
	Packages\Data,
	Packages\Files,
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
 * Package download endpoint for Troy Server.
 *
 * Handles package installer ZIP file downloads with proper file streaming
 * and download statistics recording.
 *
 * @since 0.0.1184
 */
final class Download extends Base_Endpoint {

	/**
	 * Handle a package download request with slug provided directly.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug The package slug.
	 */
	public function __construct(
		public readonly string $slug,
	) {}

	/**
	 * Handle the core download request logic.
	 *
	 * @since 0.0.1184
	 */
	public function handle_request() {

		if ( 'GET' !== $_SERVER['REQUEST_METHOD'] )
			$this->send_error( 'Method not allowed', 405 );

		$slug = $this->slug;

		// Validate required parameter
		if ( ! $slug )
			$this->send_error( 'Missing required parameter: slug', 400 );

		// Sanitize slug parameter
		$slug = API\Sanitize::slug( $slug );

		if ( ! $slug )
			$this->send_error( 'Invalid slug', 400 );

		$package_id = Data::get_package_id_by_slug( $slug );

		if ( ! $package_id )
			$this->send_error( 'Package not found', 404 );

		$package = new Data( $package_id )->get_packages_row();

		if ( ! $package )
			$this->send_error( 'Package not found', 404 );

		// Check package status
		if ( 'active' !== $package->status )
			$this->send_error( 'Package not available', 403 );

		// Get ZIP file path
		$zip_file = Files::get_package_zip_file_path( $package_id, $slug );

		// phpcs:ignore TSF.Performance -- file must be loaded from disk to stream
		if ( ! \file_exists( $zip_file ) )
			$this->send_error( 'Package file not found', 404 );

		// Record download stats
		$this->record_download_stats(
			$package_id,
			new Data( $package_id )->get_metas_row()->version ?? '0.0.0',
			$package->origin_url,
		);

		// Stream the file
		$this->stream_file( $zip_file, "{$slug}.zip" );
	}

	/**
	 * Records download statistics.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $package_id The package ID.
	 * @param string $version    The package version.
	 * @param string $origin_url The origin URL.
	 */
	private function record_download_stats( $package_id, $version, $origin_url ) {

		global $wpdb;

		// Record to live stats table (for later aggregation)
		$wpdb->insert(
			"{$wpdb->prefix}troy_package_stats_downloads_live",
			[
				'package_id' => $package_id,
				'version'    => $version,
				'type'       => 'download',
				'origin_url' => $origin_url,
			],
			[ '%d', '%s', '%s', '%s' ],
		);
	}

	/**
	 * Streams a file to the client with proper headers.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $file_path The file path to stream.
	 * @param string $filename  The filename to send to the client.
	 */
	private function stream_file( $file_path, $filename ) {

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- file streaming requires native functions
		$file_size = \filesize( $file_path );

		// Clear any existing output buffers
		while ( \ob_get_level() )
			\ob_end_clean();

		// Set headers
		\header( 'Content-Type: application/zip' );
		\header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		\header( 'Content-Length: ' . $file_size );
		\header( 'Content-Transfer-Encoding: binary' );
		\header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		\header( 'Pragma: public' );
		\header( 'Expires: 0' );

		// Stream the file
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- file streaming requires native functions
		\readfile( $file_path );

		exit;
	}
}
