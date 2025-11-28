<?php
/**
 * @package Troy\Server\Integrations\Plugins
 * @access  private
 */

namespace Troy\Server\Integrations\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Plugins, // A namesake import is valid; we're relative to \, not \Plugins.
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
 * Class Troy\Server\Integrations\Plugins\Store.
 *
 * Handles storage and retrieval of integration data.
 * Enforces single active integration per plugin.
 *
 * @since 0.0.1184
 */
final class Store {

	/**
	 * @since 0.0.1184
	 * @var int DEFER_RETRY_TIMEOUT Default defer timeout in seconds.
	 */
	public const DEFER_RETRY_TIMEOUT = 300;

	/**
	 * @since 0.0.1184
	 * @var string HISTORY_STATUS_SUCCESS Success status for history.
	 */
	public const HISTORY_STATUS_SUCCESS = 'SUCCESS';

	/**
	 * @since 0.0.1184
	 * @var string HISTORY_STATUS_TEMPORARY_FAILED Failed status for history.
	 */
	public const HISTORY_STATUS_TEMPORARY_FAILED = 'FAILED';

	/**
	 * @since 0.0.1184
	 * @var string HISTORY_STATUS_BLOCKED Permanent failure status for history.
	 */
	public const HISTORY_STATUS_BLOCKED = 'BLOCKED';

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

		$existing_integration = new Plugins\Data( $plugin_id )->get_integration();

		if ( $existing_integration ) {
			// Race condition / unsynced tabs, gracefully handle by updating existing record.
			$result = $wpdb->update(
				"{$wpdb->prefix}troy_plugin_integrations",
				[
					'mode'           => $mode,
					'settings'       => API\Sanitize::json_encode_db( $settings ),
					'auth'           => $auth ? API\Sanitize::json_encode_db( $auth ) : null,
					'tags'           => API\Sanitize::json_encode_db( [] ),
					'tags_refreshed' => null,
					'auto_process'   => $auto_process,
				],
				[ 'plugin_id' => $plugin_id ],
				[ '%s', '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ],
			);
		} else {
			$result = $wpdb->insert(
				"{$wpdb->prefix}troy_plugin_integrations",
				[
					'plugin_id'      => $plugin_id,
					'mode'           => $mode,
					'settings'       => API\Sanitize::json_encode_db( $settings ),
					'auth'           => $auth ? API\Sanitize::json_encode_db( $auth ) : null,
					'tags'           => API\Sanitize::json_encode_db( [] ),
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
			"{$wpdb->prefix}troy_plugin_integrations",
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

		$existing_integration = new Plugins\Data( $plugin_id )->get_integration();

		if ( ! $existing_integration )
			return false;

		global $wpdb;

		return false !== $wpdb->update(
			"{$wpdb->prefix}troy_plugin_integrations",
			[
				'tags'           => API\Sanitize::json_encode_db( $tags ),
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
				"SELECT * FROM {$wpdb->prefix}troy_plugin_integrations WHERE plugin_id = %d",
				$plugin_id,
			),
		);

		if ( ! $existing_integration )
			return false;

		$success = false !== $wpdb->update(
			"{$wpdb->prefix}troy_plugin_integrations",
			[ 'auto_process' => $auto_process ],
			[ 'plugin_id' => $plugin_id ],
			[ '%s' ],
			[ '%d' ],
		);

		// Clear the queue when changing to 'none'
		if ( $success && 'none' === $auto_process ) {
			$wpdb->delete(
				"{$wpdb->prefix}troy_plugin_integration_queue",
				[ 'plugin_id' => $plugin_id ],
				[ '%d' ],
			);
		}

		return $success;
	}

	/**
	 * Queue a tag for processing.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param array $data {
	 *     Queue data. Required keys: plugin_id, package_version, mode, download_url, type.
	 *
	 *     @type int    $plugin_id       Required. The plugin post ID.
	 *     @type string $package_version Required. The package version to queue.
	 *     @type string $mode            Required. The integration mode.
	 *     @type string $download_url    Required. The download URL.
	 *     @type string $type            Required. The tag type ('tag', 'beta', 'unreleased').
	 *     @type string $revision_id     Optional. The revision ID. Default ''.
	 * }
	 * @return int|false The number of rows affected, or false on error.
	 */
	public static function queue_tag( $data = [] ) {

		if (
			   empty( $data['plugin_id'] )
			|| empty( $data['package_version'] )
			|| empty( $data['mode'] )
			|| empty( $data['download_url'] )
			|| empty( $data['type'] )
		) return false;

		global $wpdb;

		// Use replace to avoid duplicate entries.
		return $wpdb->replace(
			"{$wpdb->prefix}troy_plugin_integration_queue",
			[
				'plugin_id'       => $data['plugin_id'],
				'package_version' => $data['package_version'],
				'mode'            => $data['mode'],
				'download_url'    => $data['download_url'],
				'revision_id'     => $data['revision_id'] ?? '',
				'type'            => $data['type'],
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ],
		);
	}

	/**
	 * Remove a tag from the processing queue.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param array $data {
	 *     Dequeue data. Required keys: plugin_id, package_version.
	 *
	 *     @type int    $plugin_id       Required. The plugin post ID.
	 *     @type string $package_version Required. The package version to dequeue.
	 * }
	 * @return Boolean True on success, false on failure.
	 */
	public static function dequeue_tag( $data = [] ) {

		if ( empty( $data['plugin_id'] ) || empty( $data['package_version'] ) )
			return false;

		global $wpdb;

		return false !== $wpdb->delete(
			"{$wpdb->prefix}troy_plugin_integration_queue",
			[
				'plugin_id'       => $data['plugin_id'],
				'package_version' => $data['package_version'],
			],
			[ '%d', '%s' ],
		);
	}

	/**
	 * Get queued tags for processing.
	 *
	 * Only returns tags where retry_after <= now, ordered by retry_after ascending
	 * so oldest/most ready items are processed first.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Optional. Maximum number of tags to retrieve. Default 2.
	 * @return ?object[] {
	 *     An array of queued tag objects, or null if none are found.
	 *
	 *     @type int    id              The queue ID.
	 *     @type int    plugin_id       The plugin post ID.
	 *     @type string package_version The package version.
	 *     @type string mode            The integration mode.
	 *     @type string download_url    The tag download URL.
	 *     @type string revision_id     The revision ID.
	 *     @type string type            The tag type.
	 *     @type string retry_after     The earliest time to process this tag.
	 *     @type string created_at      The row creation timestamp.
	 * }
	 */
	public static function get_queued_tags( $limit = 2 ) {

		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->prefix}troy_plugin_integration_queue
				WHERE retry_after < %s
				ORDER BY retry_after ASC
				LIMIT %d",
				\current_time( 'mysql' ),
				$limit,
			),
		);
	}

	/**
	 * Record a processing result to the integration history.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param array $data {
	 *     History data. Required keys: plugin_id, package_version, mode, status.
	 *
	 *     @type int    $plugin_id       Required. The plugin post ID.
	 *     @type string $package_version Required. The package version (tag name).
	 *     @type string $mode            Required. The integration mode.
	 *     @type string $status          Required. The result status (use HISTORY_STATUS_* constants).
	 *     @type string $revision_id     Optional. The revision ID. Default ''.
	 *     @type string $version         Optional. The actual plugin header version (for success). Default ''.
	 *     @type string $reason          Optional. The status reason. Default ''.
	 * }
	 * @return int|false The number of rows affected, or false on error.
	 */
	public static function record_history( $data = [] ) {

		if (
			   empty( $data['plugin_id'] )
			|| empty( $data['package_version'] )
			|| empty( $data['mode'] )
			|| empty( $data['status'] )
		) return false;

		$plugin_id       = $data['plugin_id'];
		$package_version = $data['package_version'];
		$mode            = $data['mode'];
		$status          = $data['status'];
		$revision_id     = $data['revision_id'] ?? '';
		$version         = $data['version'] ?? '';
		$reason          = $data['reason'] ?? '';

		global $wpdb;

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, attempts, status, revision_id
				FROM {$wpdb->prefix}troy_plugin_integration_history
				WHERE plugin_id = %d AND package_version = %s",
				$plugin_id,
				$package_version,
			),
		);

		if ( $existing ) {
			// Don't overwrite success with failure unless revision_id changed
			if (
				   self::HISTORY_STATUS_SUCCESS === $existing->status
				&& self::HISTORY_STATUS_SUCCESS !== $status
				&& $existing->revision_id === $revision_id
			) return 0;

			return $wpdb->update(
				"{$wpdb->prefix}troy_plugin_integration_history",
				[
					'mode'        => $mode,
					'status'      => $status,
					'revision_id' => $revision_id,
					'version'     => $version,
					'reason'      => $reason,
					'attempts'    => $existing->attempts + 1,
				],
				[
					'plugin_id'       => $plugin_id,
					'package_version' => $package_version,
				],
				[ '%s', '%s', '%s', '%s', '%s', '%d' ],
				[ '%d', '%s' ],
			);
		}

		return $wpdb->insert(
			"{$wpdb->prefix}troy_plugin_integration_history",
			[
				'plugin_id'       => $plugin_id,
				'package_version' => $package_version,
				'revision_id'     => $revision_id,
				'version'         => $version,
				'mode'            => $mode,
				'status'          => $status,
				'reason'          => $reason,
				'attempts'        => 1,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ],
		);
	}

	/**
	 * Defer a queued tag for later retry.
	 *
	 * Sets retry_after to a future time so the tag won't be processed until then.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param array $data {
	 *     Defer data. Required keys: plugin_id, package_version.
	 *
	 *     @type int    $plugin_id       Required. The plugin post ID.
	 *     @type string $package_version Required. The package version to defer.
	 *     @type int    $delay_minutes   Optional. Minutes to defer. Default 5.
	 * }
	 * @return int|false The number of rows affected, or false on error.
	 */
	public static function defer_queue_tag( $data = [] ) {

		if ( empty( $data['plugin_id'] ) || empty( $data['package_version'] ) )
			return false;

		global $wpdb;

		return $wpdb->update(
			"{$wpdb->prefix}troy_plugin_integration_queue",
			[
				'retry_after' => \gmdate(
					'Y-m-d H:i:s',
					\time() + self::DEFER_RETRY_TIMEOUT,
				),
			],
			[
				'plugin_id'       => $data['plugin_id'],
				'package_version' => $data['package_version'],
			],
			[ '%s' ],
			[ '%d', '%s' ],
		);
	}
}
