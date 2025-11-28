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
		'troy_server_cron_integrations_find_tags'         => [
			'callback' => [ self::class, 'find_all_tags' ],
			'schedule' => 'halfhourly',
			'interval' => \HOUR_IN_SECONDS / 2,
		],
		'troy_server_cron_integrations_process_tag_queue' => [
			'callback' => [ self::class, 'process_tag_queue' ],
			'schedule' => 'minutely',
			'interval' => \MINUTE_IN_SECONDS,
		],
	];

	/**
	 * Find tags from all integrations and queue them for processing.
	 *
	 * Compares fetched tags with existing processed versions and queues new/changed tags.
	 * Uses commit SHA for GitHub to detect tag updates.
	 *
	 * @hook troy_server_cron_integrations_find_tags 10
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 */
	public static function find_all_tags() {

		global $wpdb;

		$integrations = $wpdb->get_results(
			"SELECT plugin_id, mode, auto_process
			FROM {$wpdb->prefix}troy_plugin_integrations
			WHERE auto_process != 'none'",
		);

		if ( empty( $integrations ) )
			return;

		foreach ( $integrations as $integration ) {
			$plugin_id    = $integration->plugin_id;
			$mode         = $integration->mode;
			$auto_process = $integration->auto_process ?? 'all';

			$result = self::find_plugin_tags_by_mode( $plugin_id, $mode, $auto_process );

			if ( \is_wp_error( $result ) ) {
				self::integration_log(
					$plugin_id,
					'error',
					"Failed to find tags: {$result->get_error_message()}",
				);
				continue;
			}

			if ( ! ( $result['queued'] + $result['removed'] ) ) {
				self::integration_log(
					$plugin_id,
					'info',
					'Tag discovery complete: no changes found.',
				);
				continue;
			}

			self::integration_log(
				$plugin_id,
				'info',
				"Tag discovery complete: {$result['queued']} queued, {$result['removed']} removed from queue.",
			);
		}
	}

	/**
	 * Find tags for a plugin's integration based on its mode.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id    The plugin post ID.
	 * @param string $mode         The integration mode.
	 * @param string $auto_process The auto_process setting ('all', 'tag', 'beta', 'none').
	 * @return array|\WP_Error {
	 *     Result array on success, WP_Error on failure.
	 *
	 *     @type int $queued  Number of tags queued.
	 *     @type int $removed Number of tags removed from queue.
	 * }
	 */
	public static function find_plugin_tags_by_mode( $plugin_id, $mode, $auto_process = 'all' ) {

		$integration = new Plugins\Data( $plugin_id )->get_integration( [ 'get_auth' => true ] );

		if ( ! $integration )
			return new \WP_Error( 'no_integration', 'No integration found for this plugin.' );

		$tags = match ( $mode ) {
			'github' => Repos\GitHub::find_tags(
				$integration->settings->owner_repo,
				$integration->auth->token->value ?? '',
			),
			'wporg'  => Repos\WPOrg::find_tags( $integration->settings->slug ),
			default  => new \WP_Error( 'unsupported_mode', 'Unsupported integration mode.' ),
		};

		if ( \is_wp_error( $tags ) )
			return $tags;

		// Update the tags in the database
		if ( ! Store::update_tags( $plugin_id, $mode, $tags ) )
			return new \WP_Error( 'update_failed', 'Failed to update tags in database.' );

		global $wpdb;

		// Get successfully processed or blocked package versions and their revision IDs from history
		$processed_history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT package_version, revision_id
				FROM {$wpdb->prefix}troy_plugin_integration_history
				WHERE
					plugin_id = %d
					AND status IN (%s, %s)",
				$plugin_id,
				Store::HISTORY_STATUS_SUCCESS,
				Store::HISTORY_STATUS_BLOCKED,
			),
		);

		// Build a lookup map: package_version => revision_id
		$processed_revisions = array_column( $processed_history, 'revision_id', 'package_version' );

		$queued_tags = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT package_version, revision_id FROM {$wpdb->prefix}troy_plugin_integration_queue WHERE plugin_id = %d",
				$plugin_id,
			),
		);

		$queued_count  = 0;
		$removed_count = 0;

		// Queue new tags or update existing queue entries if revision ID changed
		foreach ( $tags as $package_version => $tag_data ) {
			// Skip if already successfully processed with the same revision ID
			if (
				   isset( $processed_revisions[ $package_version ] )
				&& ( $tag_data->revision_id ?? '' ) === $processed_revisions[ $package_version ]
			) continue;

			// Determine the tag type based on version pattern
			$version_type = API\Utils::get_version_type( $package_version );

			// Filter based on auto_process setting (ignore when 'all')
			switch ( $auto_process ) {
				case 'tag':
					if ( 'beta' === $version_type )
						continue 2;
					break;

				case 'beta':
					if ( 'tag' === $version_type )
						continue 2;
			}

			// Find existing queue tag by package_version
			foreach ( $queued_tags as $queued_tag ) {
				if ( $queued_tag->package_version === $package_version ) {
					$existing_queue_tag = $queued_tag;
					break;
				}
			}

			// Queue if new, or if revision ID changed
			if (
				   empty( $existing_queue_tag )
				|| $tag_data->revision_id !== $existing_queue_tag->revision_id
			) {
				Store::queue_tag( [
					'plugin_id'       => $plugin_id,
					'package_version' => $package_version,
					'mode'            => $mode,
					'download_url'    => $tag_data->download_url,
					'type'            => $tag_data->type,
					'revision_id'     => $tag_data->revision_id,
				] );

				++$queued_count;
			}
		}

		// Remove queued tags that no longer exist in the remote repository
		foreach ( $queued_tags as $queue_data ) {
			if ( ! isset( $tags->{$queue_data->package_version} ) ) {
				Store::dequeue_tag( [
					'plugin_id'       => $plugin_id,
					'package_version' => $queue_data->package_version,
				] );
				++$removed_count;
			}
		}

		return [
			'queued'  => $queued_count,
			'removed' => $removed_count,
		];
	}

	/**
	 * Process queued tags by downloading and validating them.
	 *
	 * Processes up to 2 tags at a time to avoid overloading the server.
	 * Skips tags that have failed 5+ times until manual intervention.
	 *
	 * @hook troy_server_cron_integrations_process_tag_queue 10
	 * @since 0.0.1184
	 */
	public static function process_tag_queue() {

		global $wpdb;

		$queued_tags = Store::get_queued_tags( 2 );

		if ( empty( $queued_tags ) )
			return;

		foreach ( $queued_tags as $tag ) {
			$plugin_id       = $tag->plugin_id;
			$package_version = $tag->package_version;
			$mode            = $tag->mode; // GitHub, WPOrg, etc.

			$integration = new Plugins\Data( $plugin_id )->get_integration( [ 'get_auth' => true ] );

			if ( ! $integration ) {
				self::integration_log( $plugin_id, 'error', 'No integration found for plugin during queue processing.' );
				continue;
			}

			$auto_process = $integration->auto_process ?? 'all';

			try {
				$uploader = new Plugins\Zip_Uploader(
					$plugin_id,
					$integration->settings->origin_url ?? null, // Future: distributed origins
				);

				$uploader->process_via_url(
					$tag->download_url,
					[
						'headers'     => (array) ( $integration->auth->download->headers ?? [] ),
						'queryParams' => (array) ( $integration->auth->download->queryParams ?? [] ),
					],
				);

				// Success: determine type based on repo match and version pattern
				$site_repo_url = API\Server::get_repo_url();
				$zip_repo_url  = $uploader->repo_uploaded;
				$version_type  = API\Utils::get_version_type( $uploader->version_uploaded );

				switch ( $auto_process ) {
					case 'tag':
						if ( 'beta' === $version_type ) {
							$version_type = 'unreleased';

							self::integration_log(
								$plugin_id,
								'warning',
								"Tag {$package_version} kept as 'unreleased' due to auto_process='tag' setting. We thought package version {$package_version} was a tag but the plugin header version {$uploader->version_uploaded} was a beta.",
							);
						}
						break;

					case 'beta':
						if ( 'tag' === $version_type ) {
							$version_type = 'unreleased';

							self::integration_log(
								$plugin_id,
								'warning',
								"Tag {$package_version} kept as 'unreleased' due to auto_process='beta' setting. We thought package version {$package_version} was a beta but the plugin header version {$uploader->version_uploaded} was a tag.",
							);
						}
						break;
				}

				if ( $zip_repo_url !== $site_repo_url ) {
					// Keep as unreleased if repo doesn't match
					$version_type = 'unreleased';

					self::integration_log(
						$plugin_id,
						'warning',
						"Tag {$package_version} kept as 'unreleased' due to repository mismatch (expected: {$site_repo_url}, got: {$zip_repo_url}).",
					);
				}

				$wpdb->update(
					"{$wpdb->prefix}troy_plugin_zips",
					[ 'type' => $version_type ],
					[
						'plugin_id' => $plugin_id,
						'version'   => $uploader->version_uploaded,
					],
					[ '%s' ],
					[ '%d', '%s' ],
				);

				// Record success and remove from queue
				Store::record_history( [
					'plugin_id'       => $plugin_id,
					'package_version' => $package_version,
					'mode'            => $mode,
					'status'          => Store::HISTORY_STATUS_SUCCESS,
					'revision_id'     => $tag->revision_id ?? '',
					'version'         => $uploader->version_uploaded,
					'reason'          => 'Processed successfully',
				] );
				Store::dequeue_tag( [
					'plugin_id'       => $plugin_id,
					'package_version' => $package_version,
				] );

				self::integration_log(
					$plugin_id,
					'info',
					"Successfully processed queued tag {$package_version} (type: {$version_type}, uploaded version: {$uploader->version_uploaded}).",
				);
			} catch ( \Exception $e ) {
				$error_message = $e->getMessage();

				// Determine failure type based on exception code or attempt count
				$is_permanent_error = $e->getCode() === Plugins\Zip_Uploader::EXCEPTION_PERMANENT;
				$block_reason       = 'Refer to integration logs';

				if ( ! $is_permanent_error ) {
					// Get current attempt count to decide on permanent vs temporary failure
					$attempts = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT attempts
							FROM {$wpdb->prefix}troy_plugin_integration_history
							WHERE plugin_id = %d AND package_version = %s",
							$plugin_id,
							$package_version,
						),
					);

					// Mark as blocked after 10 attempts (this will be attempt 11+)
					if ( $attempts >= 10 ) {
						$is_permanent_error = true;
						$block_reason       = 'Max retries exceeded';
					}
				}

				if ( $is_permanent_error ) {
					// Record block in history and remove from queue
					Store::record_history( [
						'plugin_id'       => $plugin_id,
						'package_version' => $package_version,
						'mode'            => $mode,
						'status'          => Store::HISTORY_STATUS_BLOCKED,
						'revision_id'     => $tag->revision_id ?? '',
						'reason'          => $block_reason,
					] );
					Store::dequeue_tag( [
						'plugin_id'       => $plugin_id,
						'package_version' => $package_version,
					] );

					self::integration_log(
						$plugin_id,
						'error',
						"Tag {$package_version} permanently failed and removed from queue: {$error_message}",
					);
				} else {
					// Record temporary failure in history and defer retry
					Store::record_history( [
						'plugin_id'       => $plugin_id,
						'package_version' => $package_version,
						'mode'            => $mode,
						'status'          => Store::HISTORY_STATUS_TEMPORARY_FAILED,
						'revision_id'     => $tag->revision_id ?? '',
						'reason'          => 'Processing failed, will retry',
					] );
					Store::defer_queue_tag( [
						'plugin_id'       => $plugin_id,
						'package_version' => $package_version,
					] );

					self::integration_log(
						$plugin_id,
						'error',
						"Failed to process queued tag {$package_version}, will retry in 5 minutes: {$error_message}",
					);
				}
			}
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
			"{$wpdb->prefix}troy_plugin_integration_logs",
			[
				'plugin_id' => $plugin_id,
				'type'      => $type,
				'message'   => \wp_strip_all_tags( $message ),
			],
			[ '%d', '%s', '%s' ],
		);
	}
}
