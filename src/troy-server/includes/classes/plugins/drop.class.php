<?php
/**
 * @package Troy\Server\Plugins
 * @access  public
 */

namespace Troy\Server\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\{
	get_plugin_id_by_post_id,
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
 * Class Troy\Server\Plugins\Drop.
 *
 * This class provides a way for Troy plugins to be dropped from the database.
 * See `Troy\Server\Upgrade\get_initial_db_schema_queries()` for the plugins table.
 *
 * This class also deletes the plugin's ZIP storage directory, although recovery
 * is possible via the graveyard system.
 *
 * @since 0.0.1184
 */
final class Drop {

	/**
	 * @since 0.0.1184
	 * @var ?int $plugin_id The plugin ID.
	 *                      It will always be an integer, even though it is nullable.
	 */
	public readonly ?int $plugin_id;

	/**
	 * The constructor for the Drop class.
	 *
	 * @since 0.0.1184
	 *
	 * @param ?int $plugin_id The plugin ID. If unknown, use $post_id instead.
	 * @param ?int $post_id   Optional. The post ID of the plugin. Will be ignored if $plugin_id is set.
	 * @throws \Exception If both plugin_id and post_id are not set.
	 */
	public function __construct( $plugin_id = null, $post_id = null ) {

		if ( ! $plugin_id && ! $post_id )
			throw new \Exception( 'Either plugin_id or post_id must be set.' );

		$this->plugin_id = $plugin_id ?? get_plugin_id_by_post_id( $post_id );
	}

	/**
	 * Removes a plugin from the server.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return bool Success status.
	 */
	public function commit() {

		$plugin_id = $this->plugin_id;

		// We can reach this if a post was created but no Plugin Slug was set.
		if ( ! $plugin_id )
			return false;

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			$tables = [
				'troy_plugins_slug_transfers',
				'troy_plugins_metas',
				'troy_plugins_contributors',
				'troy_plugins_infos',
				'troy_plugins_settings',
				'troy_plugins_zips',
				'troy_plugins_translations',
				'troy_plugins_data_caches',
				'troy_plugins_ratings',
				'troy_plugins_stats_totals',
				'troy_plugins_stats_totals_to_date',
				'troy_plugins_stats',
				'troy_plugins_stats_to_date',
				'troy_plugins_view_stats',
				'troy_plugins_view_stats_live',
				'troy_plugins_download_stats',
				'troy_plugins_download_stats_live',
				'troy_plugins_update_request_stats',
				'troy_plugins_update_request_locales_stats',
				'troy_plugins_update_request_stats_live',
				'troy_plugins_integrations',
			];

			foreach ( $tables as $table )
				$wpdb->delete(
					"{$wpdb->prefix}{$table}",
					[ 'plugin_id' => $plugin_id ],
					[ '%d' ],
				);

			// Primary table uses a different key.
			$wpdb->delete(
				"{$wpdb->prefix}troy_plugins",
				[ 'id' => $plugin_id ],
				[ '%d' ],
			);

			$wpdb->query( 'COMMIT' );

			Files::move_to_graveyard( $plugin_id );

			return true;
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
}
