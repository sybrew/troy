<?php
/**
 * @package Troy\Server\Packages
 * @access  public
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\API;

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
 * Class Troy\Server\Packages\Drop.
 *
 * This class provides a way for Troy packages to be dropped from the database.
 * See `Troy\Server\Upgrade\get_initial_db_schema_queries()` for the packages table.
 *
 * This class also deletes the package's storage directory, although recovery
 * is possible via the graveyard system.
 *
 * @since 0.0.1184
 */
final class Drop {

	/**
	 * @since 0.0.1184
	 * @var ?int $package_id The package ID.
	 *                       It will always be an integer, even though it is nullable.
	 */
	public readonly ?int $package_id;

	/**
	 * The constructor for the Drop class.
	 *
	 * @since 0.0.1184
	 *
	 * @param ?int $package_id The package ID. If unknown, use $post_id instead.
	 * @param ?int $post_id    Optional. The post ID of the package. Will be ignored if $package_id is set.
	 * @throws \Exception If both package_id and post_id are not set.
	 */
	public function __construct( $package_id = null, $post_id = null ) {

		if ( ! $package_id && ! $post_id )
			throw new \Exception( 'Either package_id or post_id must be set.' );

		$this->package_id = $package_id ?? API\Package::get_package_id_by_post_id( $post_id );
	}

	/**
	 * Removes a package from the server.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return bool Success status.
	 */
	public function commit() {

		$package_id = $this->package_id;

		// We can reach this if a post was created but no package slug was set.
		if ( ! $package_id )
			return false;

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			$tables = [
				'troy_package_metas',
				'troy_package_stats_totals',
				'troy_package_stats_totals_daily',
				'troy_package_stats_downloads',
				'troy_package_stats_downloads_live',
			];

			foreach ( $tables as $table )
				$wpdb->delete(
					"{$wpdb->prefix}{$table}",
					[ 'package_id' => $package_id ],
					[ '%d' ],
				);

			// Primary table uses a different key.
			$wpdb->delete(
				"{$wpdb->prefix}troy_packages",
				[ 'id' => $package_id ],
				[ '%d' ],
			);

			$wpdb->query( 'COMMIT' );

			Files::move_to_graveyard( $package_id );

			return true;
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
}
