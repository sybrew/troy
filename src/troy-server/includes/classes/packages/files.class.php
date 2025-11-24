<?php
/**
 * @package Troy\Server\Packages
 * @access  public
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	File_Utils,
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

// phpcs:disable TSF.Performance.Functions -- We require slow file operations here.

/**
 * Class Troy\Server\Packages\Files.
 *
 * Handles package file storage for Troy Server.
 *
 * @since 0.0.1184
 */
final class Files {

	/**
	 * Returns the package storage directory.
	 * This is not for public use, but for internal lookups only.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $package_id The package ID. Optional.
	 *                        If not provided, the base directory will be returned.
	 * @return string The package zip directory.
	 */
	public static function get_package_storage_dir_path( $package_id = 0 ) {

		$package_id = (int) $package_id;
		$base       = \wp_upload_dir()['basedir'] . '/troy-packages/';

		return $package_id
			? "{$base}package-{$package_id}/"
			: "$base";
	}

	/**
	 * Returns the package graveyard directory.
	 * This is not for public use, but for internal lookups only.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $package_id The package ID. Optional.
	 *                        If not provided, the base directory will be returned.
	 * @return string The package's graveyard zip directory.
	 */
	public static function get_package_graveyard_dir_path( $package_id = 0 ) {

		$package_id = (int) $package_id;
		$base       = \wp_upload_dir()['basedir'] . '/troy-packages-graveyard/';

		return $package_id
			? "{$base}package-{$package_id}/"
			: "$base";
	}

	/**
	 * Returns the package zip file path.
	 * This is not for public use, but for internal lookups only.
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $package_id The package ID.
	 * @param string $slug       The package slug.
	 * @return string The package zip file path.
	 */
	public static function get_package_zip_file_path( $package_id, $slug ) {
		return static::get_package_storage_dir_path( $package_id ) . "{$slug}.zip";
	}

	/**
	 * Returns the package zip URL.
	 * This is for public use, so it can be used in the API.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $package_slug The package slug.
	 * @return string The package zip URL.
	 */
	public static function get_package_zip_url_by_slug( $package_slug ) {

		static $baseurl = null;

		$baseurl ??= \get_home_url() . '/package/get/zip/';

		return "{$baseurl}{$package_slug}";
	}

	/**
	 * Moves a package directory to the graveyard.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $package_id The package ID.
	 * @throws \Exception If the package ID is invalid.
	 */
	public static function move_to_graveyard( $package_id ) {

		$package_id = (int) $package_id;

		// Let's guard against invalid package IDs before something bad happens.
		if ( $package_id <= 0 )
			throw new \Exception( 'Invalid package ID.' );

		// 15 ought to be plenty to rename a few folder indexes.
		API\Utils::increase_time_limit_by( 15 );

		$source_dir = static::get_package_storage_dir_path( $package_id );

		// Not every package has files uploaded to it. Let's check if they have first.
		if ( \is_dir( $source_dir ) ) {
			File_Utils::init_wpfs();
			File_Utils::make_shielded_dir( static::get_package_graveyard_dir_path() );

			\move_dir(
				$source_dir,
				static::get_package_graveyard_dir_path( $package_id ),
				true,
			);
		}
	}
}
