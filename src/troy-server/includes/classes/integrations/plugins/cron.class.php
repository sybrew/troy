<?php
/**
 * @package Troy\Server\Integrations\Plugins
 * @access  private
 */

namespace Troy\Server\Integrations\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

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
 * Class Troy\Server\Integrations\Cron.
 *
 * Handles scheduling and removal of integration cron jobs for all integration types.
 *
 * @since 0.0.1184
 */
final class Cron extends \Troy\Server\Cron {

	/**
	 * @since 0.0.1184
	 * @var array $cron_jobs {
	 *     An array of cron jobs with their schedules, indexed by the hook name.
	 *
	 *     @type array {
	 *         @type callable $callback The callback function to execute.
	 *         @type string   $schedule The schedule type.
	 *         @type int      $interval Optional. The interval in seconds for a custom schedule.
	 *     }
	 * }
	 */
	protected const CRON_JOBS = [
		'troy_integrations_sync_all' => [
			'callback' => [ self::class, 'sync_all_integrations' ],
			'schedule' => 'hourly',
		],
	];

	/**
	 * Sync all integrations.
	 *
	 * Dynamically handles different integration types based on their mode.
	 *
	 * TODO: When repositories grow large, implement batch processing with an option
	 * to control batch size and add multiple cron jobs to handle batches.
	 *
	 * @since 0.0.1184
	 */
	public static function sync_all_integrations() {

		global $wpdb;

		// Get all plugins with integrations
		$integrations = $wpdb->get_results(
			"SELECT plugin_id, mode FROM {$wpdb->prefix}troy_plugins_integrations",
			\ARRAY_A,
		);

		if ( empty( $integrations ) )
			return;

		foreach ( $integrations as $integration ) {
			$plugin_id = $integration['plugin_id'];
			$mode      = $integration['mode'];

			// Route to appropriate handler based on mode
			$result = self::sync_plugin_integration_by_mode( $plugin_id, $mode );

			// Continue with other integrations on error
			if ( \is_wp_error( $result ) ) {
				static::integration_log(
					$plugin_id,
					'error',
					"Failed to sync integration: {$result->get_error_message()}",
				);
				continue;
			}

			// Auto-process new tags if enabled.
			// TODO offload to separate cron job? We can do this via settings; add "queued" column?
			self::process_new_tags( $plugin_id );
		}
	}

	/**
	 * Sync a plugin's integration based on its mode.
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id The plugin post ID.
	 * @param string $mode      The integration mode.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function sync_plugin_integration_by_mode( $plugin_id, $mode ) {

		$integration = ( new Plugins\Data( $plugin_id ) )->get_integration( [ 'get_auth' => true ] );

		if ( ! $integration )
			return new \WP_Error( 'no_integration', 'No integration found for this plugin.' );

		// TODO make this agnostic? "process_new_tags" based on plugin_id?
		$tags = match ( $mode ) {
			'github' => Repos\GitHub::fetch_tags(
				$integration->settings->owner_repo,
				$integration->auth->token->value ?? '',
			),
			'wporg'  => Repos\WPOrg::fetch_tags( $integration->settings->slug ),
			default  => new \WP_Error( 'unsupported_mode', 'Unsupported integration mode' ),
		};

		if ( \is_wp_error( $tags ) )
			return $tags;

		// Update the tags in the database
		return Store::update_tags( $plugin_id, $mode, $tags )
			? true
			: new \WP_Error( 'update_failed', 'Failed to update tags in database' );
	}

	/**
	 * Process new tags for a plugin based on auto-process settings.
	 *
	 * Compares fetched tags with existing versions and processes new ones automatically
	 * if auto-processing is enabled.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $plugin_id The plugin post ID.
	 */
	public static function process_new_tags( $plugin_id ) {

		$integration = ( new Plugins\Data( $plugin_id ) )->get_integration( [ 'get_auth' => true ] );

		if ( ! $integration ) {
			static::integration_log( $plugin_id, 'error', 'No integration found for plugin during auto-processing.' );
			return;
		}

		// Check if auto-processing is enabled
		if ( 'none' === $integration->auto_process )
			return;

		// Get existing versions from database
		global $wpdb;

		$existing_versions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT version FROM {$wpdb->prefix}troy_plugins_zips WHERE plugin_id = %d",
				$plugin_id,
			),
		);

		$existing_versions = array_flip( $existing_versions );
		$processed_count   = 0;
		$error_count       = 0;

		// Process limit to avoid long cron runs and spamming APIs obtaining incompatible plugin packages
		$limit = 2;

		// Process each tag that doesn't exist in the database
		foreach ( $integration->tags as $version_name => $tag_data ) {
			// Skip if version already exists
			if ( isset( $existing_versions[ $version_name ] ) )
				continue;

			// Check if we should process this tag based on auto_process setting
			$should_process = match ( $integration->auto_process ) {
				'all'   => true,
				'tag'   => ( $tag_data->type ?? 'tag' ) === 'tag',
				'beta'  => ( $tag_data->type ?? 'tag' ) === 'beta',
				default => false,
			};

			if ( ! $should_process )
				continue;

			if ( ( $processed_count + $error_count ) >= $limit ) {
				static::integration_log(
					$plugin_id,
					'info',
					'Auto-processing limit reached for this run; remaining tags will be processed in future runs.',
				);
				break;
			}

			$download_url = $tag_data->download_url ?? null;

			if ( ! $download_url ) {
				static::integration_log(
					$plugin_id,
					'warning',
					"Tag {$version_name} has no download URL, skipping.",
				);
				continue;
			}

			try {
				$uploader = new Plugins\Zip_Uploader(
					$plugin_id,
					$integration->settings->origin_url ?? null,
				);

				$uploader->process_via_url(
					$download_url,
					[
						'headers'     => (array) ( $integration->auth->download->headers ?? [] ), // headers is likely an object.
						'queryParams' => (array) ( $integration->auth->download->queryParams ?? [] ), // queryParams is likely an object.
					],
				);

				++$processed_count;

				static::integration_log(
					$plugin_id,
					'info',
					"Successfully auto-processed tag {$version_name} (uploaded version: {$uploader->version_uploaded}).",
				);
			} catch ( \Exception $e ) {
				++$error_count;

				static::integration_log(
					$plugin_id,
					'error',
					"Failed to auto-process tag {$version_name}: {$e->getMessage()}",
				);
			}
		}

		if ( $processed_count || $error_count ) {
			static::integration_log(
				$plugin_id,
				'info',
				"Auto-processing complete: {$processed_count} successful, {$error_count} failed.",
			);
		}
	}

	/**
	 * Log integration activity to the database.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id The plugin post ID.
	 * @param string $type      Log type: 'error', 'warning', or 'info'.
	 * @param string $message   The log message.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	private static function integration_log( $plugin_id, $type, $message ) {

		global $wpdb;

		return $wpdb->insert(
			"{$wpdb->prefix}troy_plugins_integration_logs",
			[
				'plugin_id' => $plugin_id,
				'type'      => $type,
				'message'   => $message,
			],
			[ '%d', '%s', '%s' ],
		);
	}
}
