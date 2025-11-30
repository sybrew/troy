<?php
/**
 * @package Troy\Server\Integrations\Plugins\Repos
 * @access  private
 */

namespace Troy\Server\Integrations\Plugins\Repos;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\VERSION;

use Troy\Server\{
	API,
	Integrations\Plugins\Store,
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
 * Class Troy\Server\Integrations\Plugins\Repos\WPOrg.
 *
 * Handles WordPress.org plugin integration for fetching plugin tags and creating versions.
 *
 * @since 0.0.1184
 */
final class WPOrg {

	/**
	 * Connects WordPress.org integration for a plugin.
	 *
	 * Tags must be updated separately using Store::update_tags().
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id    The plugin post ID.
	 * @param array  $data         {
	 *     The connection data to use and store.
	 *
	 *     @type string $slug The plugin slug.
	 * }
	 * @param string $auto_process Optional. Auto-process during cron. Default 'all'.
	 *                             Acceptes 'all', 'tag', 'beta', and 'none'.
	 * @return array {
	 *    The result of the connection attempt.
	 *
	 *    @type bool   $success Whether the connection was successful.
	 *    @type string $error   An error message if the connection failed.
	 * }
	 */
	public static function connect( $plugin_id, $data, $auto_process = 'all' ) {

		$settings = [ 'slug' => API\Sanitize::slug( $data['slug'] ?? '' ) ];

		if ( empty( $settings['slug'] ) )
			return [
				'success' => false,
				'error'   => \__( 'Invalid plugin slug.', 'troy-server' ),
			];

		$response = self::get_plugin_info( $settings['slug'] );

		if ( \is_wp_error( $response ) )
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];

		if ( ! empty( $response['error'] ) )
			return [
				'success' => false,
				'error'   => $response['error'],
			];

		return Store::connect( $plugin_id, 'wporg', $settings, null, $auto_process );
	}

	/**
	 * Find plugin tags from WordPress.org.
	 *
	 * Tag types are automatically determined based on version naming patterns.
	 * WordPress.org tags never require authentication.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug Plugin slug.
	 * @return object|\WP_Error Object of tags on success, WP_Error on failure. {
	 *     An object of tags, indexed by tag name (aka version).
	 *
	 *     @type string $download_url The tag download URL.
	 *     @type string $type         The tag type ('tag' or 'beta'); determined by version pattern.
	 * }
	 */
	public static function find_tags( $slug ) {

		$slug = API\Sanitize::slug( $slug );

		if ( empty( $slug ) )
			return new \WP_Error(
				'missing_slug',
				\__( 'Plugin slug is required.', 'troy-server' ),
			);

		$response = self::get_plugin_info( $slug );

		if ( \is_wp_error( $response ) )
			return $response;

		$versions = $response['versions'] ?? [];

		if ( empty( $versions ) )
			return new \WP_Error(
				'no_versions_found',
				\__( 'No versions found for this plugin on WordPress.org.', 'troy-server' ),
			);

		// $versions is in format [ 'version' => 'download_url' ].
		// Unset 'trunk' if present, as it's not a valid version.
		unset( $versions['trunk'] );

		// WordPress doesn't provide commit IDs; use last updated timestamp.
		$revision_id = sha1( $response['last_updated'] ?? '' );

		$tags = [];

		foreach ( $versions as $version => $url )
			$tags[ $version ] = [
				'download_url' => $url,
				'revision_id'  => $revision_id,
			];

		return API\Sanitize::tags( $tags );
	}

	/**
	 * Fetch plugin information from WordPress.org API.
	 *
	 * This is a low-level method that doesn't validate the input or response;
	 * use find_tags() for sanitized tags.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug The plugin slug.
	 * @return array|\WP_Error Plugin data or error.
	 */
	public static function get_plugin_info( $slug ) {

		$response = \wp_remote_get(
			\sprintf(
				'https://api.wordpress.org/plugins/info/1.0/%s.json',
				API\Sanitize::slug( $slug ),
			),
			[
				'timeout'    => 3,
				'headers'    => [
					'Accept'     => 'application/json',
					'User-Agent' => 'Troy Server/' . VERSION, // See WP_Http_Curl::request()
				],
				'user-agent' => 'Troy Server/' . VERSION, // See WP_Http::request()
			],
		);

		if ( \is_wp_error( $response ) )
			return $response;

		$response_code = \wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			if ( 404 === $response_code )
				return new \WP_Error(
					'wporg_plugin_not_found',
					\__( 'Plugin not found on WordPress.org.', 'troy-server' ),
				);

			return new \WP_Error(
				'wporg_api_error',
				\sprintf(
					/* translators: %d is the HTTP status code. */
					\__( 'WordPress.org API request failed with status %d.', 'troy-server' ),
					$response_code,
				)
			);
		}

		$data = json_decode( \wp_remote_retrieve_body( $response ), true );

		if ( \JSON_ERROR_NONE !== \json_last_error() )
			return new \WP_Error(
				'json_decode_error',
				\__( 'Failed to decode WordPress.org API response.', 'troy-server' ),
			);

		return $data;
	}
}
