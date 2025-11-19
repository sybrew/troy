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
 * Class Troy\Server\Integrations\Plugins\Repos\GitHub.
 *
 * GitHub API adapter for fetching repository tags.
 * Standalone class - does not extend Store.
 *
 * @since 0.0.1184
 */
final class GitHub {

	/**
	 * Connects GitHub integration for a plugin.
	 *
	 * PAT is optional - only required for private repositories.
	 * Tags must be updated separately using Store::update_tags().
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id    The plugin post ID.
	 * @param array  $data         {
	 *     The connection data to store.
	 *
	 *     @type string $owner_repo Optional. Repository in owner/repo format.
	 *     @type string $pat        Optional PAT for private repos.
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

		$pat = $data['pat'] ?? '';

		$settings = [
			'owner_repo' => $data['owner_repo'] ?? '',
		];
		$auth     = null;

		if ( $pat ) {
			$auth = [
				'token'    => [
					'type'  => 'bearer',
					'value' => $pat,
				],
				'download' => [
					'headers'     => [ 'Authorization' => "Bearer $pat" ],
					'queryParams' => [],
				],
			];
		}

		if ( ! preg_match( '/^([\w\.\-]+)\/([\w\.\-]+)$/', $settings['owner_repo'] ) )
			return [
				'success' => false,
				'error'   => \__( 'Invalid repository format.', 'troy-server' ),
			];

		$response = static::get_repo_tags( $settings['owner_repo'], $pat );

		if ( \is_wp_error( $response ) )
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];

		if ( ! empty( $response['message'] ) )
			return [
				'success' => false,
				'error'   => $response['message'],
			];

		return Store::connect( $plugin_id, 'github', $settings, $auth, $auto_process );
	}

	/**
	 * Find repository tags from GitHub and sanitize them.
	 *
	 * Tag types are automatically determined based on version naming patterns:
	 * - 'beta' if version contains pre-release identifiers (e.g., -beta, -rc)
	 * - 'tag' otherwise
	 *
	 * @since 0.0.1184
	 *
	 * @param string $owner_repo Repository in owner/repo format.
	 * @param string $pat        Optional GitHub PAT (personal access token) for private repos.
	 * @return object|\WP_Error Object of tags on success, WP_Error on failure. {
	 *     An object of tags, indexed by tag name (aka version).
	 *
	 *     @type string $download_url The tag download URL.
	 *     @type string $type         The tag type ('tag' or 'beta'); determined by version pattern.
	 *     @type string $revision_id  The revision ID for the tag.
	 * }
	 */
	public static function find_tags( $owner_repo, $pat = '' ) {

		if ( ! preg_match( '/^([\w\.\-]+)\/([\w\.\-]+)$/', $owner_repo ) )
			return new \WP_Error(
				'invalid_repo',
				\__( 'Invalid repository format', 'troy-server' ),
			);

		$response = static::get_repo_tags( $owner_repo, $pat );

		if ( \is_wp_error( $response ) )
			return $response;

		if ( empty( $response ) )
			return new \WP_Error(
				'no_tags_found',
				\__( 'No tags found for this repository on GitHub.', 'troy-server' ),
			);

		// Preformat tags for API\Sanitize::tags().
		$tags = [];

		foreach ( $response as $tag )
			$tags[ $tag['name'] ] = [
				'download_url' => $tag['zipball_url'],
				'revision_id'  => $tag['commit']['sha'] ?? '',
			];

		return API\Sanitize::tags( $tags );
	}

	/**
	 * Fetch repository tags from GitHub API.
	 *
	 * This is a low-level method that doesn't validate the input or response;
	 * use find_tags() for sanitized tags.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $owner_repo Repository in owner/repo format.
	 * @param string $pat        Optional GitHub PAT (personal access token) for private repos.
	 * @return array|\WP_Error Response data or error.
	 */
	public static function get_repo_tags( $owner_repo, $pat = '' ) {

		$response = \wp_remote_get(
			"https://api.github.com/repos/$owner_repo/tags?per_page=30&page=1",
			[
				'timeout'    => 3,
				'headers'    => [
					'Accept'               => 'application/vnd.github.v3+json',
					'User-Agent'           => 'Troy Server/' . VERSION, // See WP_Http_Curl::request()
					'X-GitHub-Api-Version' => '2022-11-28',
				]
				+ (
					$pat ? [ 'Authorization' => "Bearer $pat" ] : []
				),
				'user-agent' => 'Troy Server/' . VERSION, // See WP_Http::request()
			],
		);

		if ( \is_wp_error( $response ) )
			return $response;

		$response_code = \wp_remote_retrieve_response_code( $response );

		switch ( $response_code ) {
			case 200:
				$data = json_decode( \wp_remote_retrieve_body( $response ), true );

				if ( \JSON_ERROR_NONE !== json_last_error() )
					return new \WP_Error(
						'json_decode_error',
						\__( 'Failed to decode GitHub.com API response', 'troy-server' ),
					);

				return $data;

			// common error cases
			case 401:
				return new \WP_Error(
					'github_unauthorized',
					\__( 'Unauthorized access to GitHub.com repo (invalid PAT?).', 'troy-server' ),
				);
			case 404:
				if ( $pat )
					return new \WP_Error(
						'github_repo_not_found',
						\__( 'Repo not found or not granted via PAT.', 'troy-server' ),
					);

				return new \WP_Error(
					'github_repo_not_found',
					\__( 'Repo not found on GitHub.com. Use PAT if private.', 'troy-server' ),
				);

			default:
				return new \WP_Error(
					'github_api_error',
					\sprintf(
						/* translators: %d is the HTTP status code. */
						\__( 'GitHub.com API request failed with status %d', 'troy-server' ),
						$response_code,
					),
				);
		}
	}

	/**
	 * Parse GitHub repository URL and returns only owner and repo.
	 *
	 * This accepts:
	 * - https://github.com/owner/repo
	 * - http://github.com/owner/repo
	 * - //github.com/owner/repo
	 * - owner/repo
	 * - Any combination of above followed by extra path segments or query strings.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $url Repository URL.
	 * @return array {
	 *     Parsed repository info.
	 *
	 *     @type string $owner Repository owner.
	 *     @type string $repo  Repository name.
	 * }
	 */
	public static function parse_repo_url( $url ) {

		preg_match(
			'/^(?:(?:\w+:)?\/\/)?(?:[^.]+\.[^\/]+)?\/?([^\/?#]+)\/([^\/?#]+).*?/',
			trim( $url ),
			$matches,
		);

		return [
			'owner' => $matches[1] ?? '',
			'repo'  => $matches[2] ?? '',
		];
	}
}
