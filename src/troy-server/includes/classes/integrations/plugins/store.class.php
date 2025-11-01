<?php
/**
 * @package Troy\Server\Integrations\Plugins
 * @access  private
 */

namespace Troy\Server\Integrations\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\Sanitize\{
	json_encode_db,
	sanitize_tags,
};

use Troy\Server\Plugins; // A namesake import is valid; we're relative to \, not \Plugins.

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
 * Class Troy\Server\Integrations\Plugins\Store.
 *
 * Handles storage and retrieval of integration data.
 * Enforces single active integration per plugin.
 *
 * @since 0.0.1184
 */
final class Store {

	/**
	 * Connects an integration for a plugin.
	 * Enforces single active integration per plugin.
	 * Tags must be updated separately using update_tags().
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id    The plugin post ID.
	 * @param string $mode         The integration mode.
	 * @param array  $settings     Integration settings to store, varies by mode.
	 * @param ?array $auth         Optional. Authentication data structure. {
	 *     @type array $token {
	 *         The token data.
	 *
	 *         @type string $type  Token type (e.g., 'bearer', 'oauth2').
	 *         @type mixed  $value Token value. e.g., string for PAT, array for OAuth2.
	 *     }
	 *     @type array $download {
	 *         The download configuration.
	 *
	 *         @type array $headers     HTTP headers for downloads (e.g., ['Authorization' => 'Bearer $token']).
	 *         @type array $queryParams Query parameters for downloads.
	 *     }
	 * }
	 * @param string $auto_process Optional. Auto-process during cron. Default 'all'.
	 *                             Accepts 'all', 'tag', 'beta', and 'none'.
	 * @return array {
	 *    The result of the connection attempt.
	 *
	 *    @type bool   $success Whether the connection was successful.
	 *    @type string $error   An error message if the connection failed.
	 * }
	 */
	public static function connect( $plugin_id, $mode, $settings, $auth = null, $auto_process = 'all' ) {

		if ( ! $plugin_id )
			return [
				'success' => false,
				'error'   => \__( 'Missing plugin ID.', 'troy-server' ),
			];

		global $wpdb;

		$settings['has_auth'] = (bool) $auth;

		$existing_integration = ( new Plugins\Data( $plugin_id ) )->get_integration();

		if ( $existing_integration ) {
			// Race condition / unsynced tabs, gracefully handle by updating existing record.
			$result = $wpdb->update(
				"{$wpdb->prefix}troy_plugins_integrations",
				[
					'mode'           => $mode,
					'settings'       => json_encode_db( $settings ),
					'auth'           => $auth ? json_encode_db( $auth ) : null,
					'tags'           => json_encode_db( [] ),
					'tags_refreshed' => null,
					'auto_process'   => $auto_process,
				],
				[ 'plugin_id' => $plugin_id ],
				[ '%s', '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ],
			);
		} else {
			$result = $wpdb->insert(
				"{$wpdb->prefix}troy_plugins_integrations",
				[
					'plugin_id'      => $plugin_id,
					'mode'           => $mode,
					'settings'       => json_encode_db( $settings ),
					'auth'           => $auth ? json_encode_db( $auth ) : null,
					'tags'           => json_encode_db( [] ),
					'tags_refreshed' => null,
					'auto_process'   => $auto_process,
				],
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ],
			);
		}

		return [
			'success' => $result,
			'error'   => $result
				? ''
				: \__( 'Failed to save integration settings.', 'troy-server' ),
		];
	}

	/**
	 * Disconnects (deletes) integration data for a plugin.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $plugin_id The plugin post ID.
	 * @return Boolean True on success, false on failure.
	 */
	public static function disconnect( $plugin_id ) {

		global $wpdb;

		return false !== $wpdb->delete(
			"{$wpdb->prefix}troy_plugins_integrations",
			[ 'plugin_id' => $plugin_id ],
			[ '%d' ],
		);
	}

	/**
	 * Updates tags for an integration.
	 * Always updates tags_refreshed to current time.
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id The plugin post ID.
	 * @param string $mode      The integration mode.
	 * @param array  $tags      {
	 *     An array of tags to store, indexed by tag name (aka version).
	 *     Tags are automatically typed during fetch based on version naming patterns.
	 *
	 *     @type string $download_url The tag download URL.
	 *     @type string $type         The tag type ('tag' or 'beta'); may be determined by version pattern.
	 * }
	 * @return Boolean True on success, false on failure.
	 */
	public static function update_tags( $plugin_id, $mode, $tags ) {

		if ( ! $plugin_id )
			return false;

		$existing_integration = ( new Plugins\Data( $plugin_id ) )->get_integration();

		if ( ! $existing_integration )
			return false;

		global $wpdb;

		return false !== $wpdb->update(
			"{$wpdb->prefix}troy_plugins_integrations",
			[
				'tags'           => json_encode_db( $tags ),
				'tags_refreshed' => \current_time( 'mysql' ), // Use local time via wp_timezone().
			],
			[
				'plugin_id' => $plugin_id,
				'mode'      => $mode,
			],
			[ '%s', '%s' ],
			[ '%d', '%s' ],
		);
	}

	/**
	 * Update auto_process setting for an integration.
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id    The plugin post ID.
	 * @param string $auto_process The auto_process value ('all', 'tag', 'beta', 'none').
	 * @return Boolean True on success, false on failure.
	 */
	public static function update_auto_process( $plugin_id, $auto_process ) {

		if ( ! $plugin_id || ! \in_array( $auto_process, [ 'all', 'tag', 'beta', 'none' ], true ) )
			return false;

		global $wpdb;

		$existing_integration = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}troy_plugins_integrations WHERE plugin_id = %d",
				$plugin_id,
			),
		);

		if ( ! $existing_integration )
			return false;

		return false !== $wpdb->update(
			"{$wpdb->prefix}troy_plugins_integrations",
			[ 'auto_process' => $auto_process ],
			[ 'plugin_id' => $plugin_id ],
			[ '%s' ],
			[ '%d' ],
		);
	}
}
