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
	 * @var string QUEUE_STATUS_PENDING Pending status for queue.
	 */
	public const QUEUE_STATUS_PENDING = 'pending';

	/**
	 * @since 0.0.1184
	 * @var string QUEUE_STATUS_TEMPORARY_FAILURE Temporary failure status for queue.
	 */
	public const QUEUE_STATUS_TEMPORARY_FAILURE = 'temporary-failure';

	/**
	 * @since 0.0.1184
	 * @var string QUEUE_STATUS_PERMANENT_FAILURE Permanent failure status for queue.
	 */
	public const QUEUE_STATUS_PERMANENT_FAILURE = 'permanent-failure';

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
	 * @param int     $plugin_id        The plugin post ID.
	 * @param string  $package_version  The package version to queue.
	 * @param string  $mode             The integration mode.
	 * @param string  $download_url     The download URL.
	 * @param string  $type             The tag type ('tag', 'beta', 'unreleased').
	 * @param ?string $revision_id      Optional. The revision ID.
	 * @param string  $status           Optional. The queue status (use class constants). Default 'pending'.
	 * @return int|false The number of rows affected, or false on error.
	 */
	public static function queue_tag(
		$plugin_id,
		$package_version,
		$mode,
		$download_url,
		$type,
		$revision_id = null,
		$status = 'pending',
	) {

		global $wpdb;

		// Use replace to avoid duplicate entries.
		return $wpdb->replace(
			"{$wpdb->prefix}troy_plugin_integration_queue",
			[
				'plugin_id'       => $plugin_id,
				'package_version' => $package_version,
				'mode'            => $mode,
				'download_url'    => $download_url,
				'revision_id'     => $revision_id ?? '',
				'type'            => $type,
				'status'          => $status,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ],
		);
	}

	/**
	 * Remove a tag from the processing queue.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id       The plugin post ID.
	 * @param string $package_version The package version to dequeue.
	 * @return Boolean True on success, false on failure.
	 */
	public static function dequeue_tag( $plugin_id, $package_version ) {

		global $wpdb;

		return false !== $wpdb->delete(
			"{$wpdb->prefix}troy_plugin_integration_queue",
			[
				'plugin_id'       => $plugin_id,
				'package_version' => $package_version,
			],
			[ '%d', '%s' ],
		);
	}

	/**
	 * Get queued tags for processing.
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
	 *     @type string status          The queue status.
	 *     @type string created_at      The row creation timestamp.
	 * }
	 */
	public static function get_queued_tags( $limit = 2 ) {

		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				 FROM {$wpdb->prefix}troy_plugin_integration_queue
				 WHERE status IN (%s, %s)
				 ORDER BY created_at ASC
				 LIMIT %d",
				self::QUEUE_STATUS_PENDING,
				self::QUEUE_STATUS_TEMPORARY_FAILURE,
				$limit,
			),
		);
	}

	/**
	 * Record a processing failure for a tag.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id       The plugin post ID.
	 * @param string $package_version The package version that failed.
	 * @param string $mode            The integration mode.
	 * @param string $reason          The failure reason.
	 * @param string $details         Optional. Additional failure details.
	 * @return int|false The number of rows affected, or false on error.
	 */
	public static function record_failure( $plugin_id, $package_version, $mode, $reason, $details = '' ) {

		global $wpdb;

		$existing_attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attempts FROM {$wpdb->prefix}troy_plugin_integration_failures
				 WHERE plugin_id = %d AND package_version = %s",
				$plugin_id,
				$package_version,
			),
		);

		if ( $existing_attempts ) {
			return $wpdb->update(
				"{$wpdb->prefix}troy_plugin_integration_failures",
				[
					'mode'     => $mode,
					'reason'   => $reason,
					'details'  => $details,
					'attempts' => $existing_attempts + 1,
				],
				[
					'plugin_id'       => $plugin_id,
					'package_version' => $package_version,
				],
				[ '%s', '%s', '%s', '%d' ],
				[ '%d', '%s' ],
			);
		}

		return $wpdb->insert(
			"{$wpdb->prefix}troy_plugin_integration_failures",
			[
				'plugin_id'       => $plugin_id,
				'package_version' => $package_version,
				'mode'            => $mode,
				'reason'          => $reason,
				'details'         => $details,
				'attempts'        => 1,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d' ],
		);
	}

	/**
	 * Clear a processing failure for a tag.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id       The plugin post ID.
	 * @param string $package_version The package version to clear.
	 * @return Boolean True on success, false on failure.
	 */
	public static function clear_failure( $plugin_id, $package_version ) {

		global $wpdb;

		return false !== $wpdb->delete(
			"{$wpdb->prefix}troy_plugin_integration_failures",
			[
				'plugin_id'       => $plugin_id,
				'package_version' => $package_version,
			],
			[ '%d', '%s' ],
		);
	}

	/**
	 * Mark a queued tag with a status.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int     $plugin_id       The plugin post ID.
	 * @param string  $package_version The package version to mark.
	 * @param ?string $status          The status to set (use class constants or null to clear).
	 * @return int|false The number of rows affected, or false on error.
	 */
	public static function mark_queue_status( $plugin_id, $package_version, $status ) {

		global $wpdb;

		return $wpdb->update(
			"{$wpdb->prefix}troy_plugin_integration_queue",
			[ 'status' => $status ],
			[
				'plugin_id'       => $plugin_id,
				'package_version' => $package_version,
			],
			[ '%s' ],
			[ '%d', '%s' ],
		);
	}
}
