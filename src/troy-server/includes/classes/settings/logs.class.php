<?php
/**
 * @package Troy\Server\Settings
 * @access  private
 */

namespace Troy\Server\Settings;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\{
	REST_NS,
	VERSION,
	MAIN_FILE,
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
 * Class Troy\Server\Settings\Logs.
 *
 * Provides dashboard logs data for the Logs settings tab.
 *
 * @since 0.0.1184
 */
final class Logs {

	/**
	 * Enqueues logs page assets.
	 *
	 * @since 0.0.1184
	 */
	public static function enqueue_assets() {

		$dir_url = \plugin_dir_url( MAIN_FILE );
		$min     = \SCRIPT_DEBUG ? '' : '.min';

		\wp_enqueue_style(
			'troy-server-settings-logs-css',
			"{$dir_url}library/css/settings/logs{$min}.css",
			[ 'troy-server-settings-css' ],
			VERSION,
		);

		\wp_enqueue_script(
			'troy-server-settings-logs-js',
			"{$dir_url}library/js/settings/logs{$min}.js",
			[
				'troy-server-settings-js',
				'wp-api-fetch',
				'troy-server-escape',
				'troy-server-sanitize',
			],
			VERSION,
			true,
		);

		\wp_localize_script(
			'troy-server-settings-logs-js',
			'troyServerLogs',
			[
				'restBase' => \rest_url( REST_NS['logs_dashboard']['namespace'] . '/' . REST_NS['logs_dashboard']['base'] ),
				'nonce'    => \wp_create_nonce( 'wp_rest' ),
				'i18n'     => [
					'loading'        => \__( 'Loading...', 'troy-server' ),
					'error'          => \__( 'Failed to load logs.', 'troy-server' ),
					'noData'         => \__( 'No log entries found.', 'troy-server' ),
					'cleared'        => \__( 'Logs cleared.', 'troy-server' ),
					'clearFailed'    => \__( 'Failed to clear logs.', 'troy-server' ),
					'confirmClear'   => \__( 'Are you sure you want to clear these logs?', 'troy-server' ),
					'autoRefreshOn'  => \__( 'Auto-refresh enabled (20s)', 'troy-server' ),
					'autoRefreshOff' => \__( 'Auto-refresh disabled', 'troy-server' ),
				],
			],
		);
	}

	/**
	 * Gets integration history.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of entries to return.
	 * @return array[] {
	 *     Array of integration history entries.
	 *
	 *     @type int    $id              The history ID.
	 *     @type int    $plugin_id       The plugin ID.
	 *     @type string $plugin_slug     The plugin slug.
	 *     @type string $package_version The package version.
	 *     @type string $mode            The integration mode (github, wporg).
	 *     @type string $status          The history status (SUCCESS, BLOCKED).
	 *     @type string $reason          The status reason.
	 *     @type int    $attempts        Number of processing attempts.
	 *     @type string $created_at      When the entry was first recorded.
	 *     @type string $updated_at      When the entry was last updated.
	 * }
	 */
	public static function get_integration_history( $limit = 100 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				h.id,
				h.plugin_id,
				p.slug as plugin_slug,
				h.package_version,
				h.mode,
				h.status,
				h.reason,
				h.attempts,
				h.created_at,
				h.updated_at
			FROM {$wpdb->prefix}troy_plugin_integration_history h
			LEFT JOIN {$wpdb->prefix}troy_plugins p
				ON h.plugin_id = p.id
			ORDER BY h.updated_at DESC
			LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'id'              => (int) $row->id,
				'plugin_id'       => (int) $row->plugin_id,
				'plugin_slug'     => $row->plugin_slug ?: '[deleted]',
				'package_version' => $row->package_version,
				'mode'            => $row->mode,
				'status'          => $row->status,
				'reason'          => $row->reason,
				'attempts'        => (int) $row->attempts,
				'created_at'      => $row->created_at,
				'updated_at'      => $row->updated_at,
			],
			$results,
		);
	}

	/**
	 * Gets integration logs.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $limit Number of entries to return.
	 * @return array[] {
	 *     Array of integration log entries.
	 *
	 *     @type int    $id          The log ID.
	 *     @type int    $plugin_id   The plugin ID.
	 *     @type string $plugin_slug The plugin slug.
	 *     @type string $type        The log type (error, warning, info).
	 *     @type string $message     The log message.
	 *     @type string $created_at  When the log was created.
	 * }
	 */
	public static function get_integration_logs( $limit = 100 ) {

		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
				l.id,
				l.plugin_id,
				p.slug as plugin_slug,
				l.type,
				l.message,
				l.created_at
			FROM {$wpdb->prefix}troy_plugin_integration_logs l
			LEFT JOIN {$wpdb->prefix}troy_plugins p
				ON l.plugin_id = p.id
			ORDER BY l.created_at DESC
			LIMIT %d",
			$limit,
		) );

		if ( ! $results )
			return [];

		return array_map(
			fn( $row ) => [
				'id'          => (int) $row->id,
				'plugin_id'   => (int) $row->plugin_id,
				'plugin_slug' => $row->plugin_slug ?: '[deleted]',
				'type'        => $row->type,
				'message'     => $row->message,
				'created_at'  => $row->created_at,
			],
			$results,
		);
	}

	/**
	 * Clears integration history.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return int Number of rows deleted.
	 */
	public static function clear_integration_history() {

		global $wpdb;

		// Try TRUNCATE first (faster), fallback to DELETE if blocked by server rules.
		$result = $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}troy_plugin_integration_history" );

		if ( false === $result )
			$result = $wpdb->query( "DELETE FROM {$wpdb->prefix}troy_plugin_integration_history" );

		return (int) $result;
	}

	/**
	 * Clears integration logs.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return int Number of rows deleted.
	 */
	public static function clear_integration_logs() {

		global $wpdb;

		// Try TRUNCATE first (faster), fallback to DELETE if blocked by server rules.
		$result = $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}troy_plugin_integration_logs" );

		if ( false === $result )
			$result = $wpdb->query( "DELETE FROM {$wpdb->prefix}troy_plugin_integration_logs" );

		return (int) $result;
	}
}
