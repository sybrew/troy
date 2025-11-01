<?php
/**
 * @package Troy\Server\Plugins
 * @access  public
 */

namespace Troy\Server\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\increase_time_limit_by;

use function Troy\Server\Sanitize\{
	sanitize_semver,
	sanitize_tested_version,
	sanitize_version_type,
};

use Troy\Server\{
	File_Utils,
	Plugins\Data,
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
 * Class Troy\Server\Plugins\Files.
 *
 * Handles plugin files for Troy Server.
 *
 * @since 0.0.1184
 */
final class Files {

	/**
	 * Returns the plugin storage directory.
	 * This is not for public use, but for internal lookups only.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $plugin_id The plugin ID. Optional.
	 *                       If not provided, the base directory will be returned.
	 * @return string The plugin zip directory.
	 */
	public static function get_plugin_storage_dir_path( $plugin_id = 0 ) {

		// TODO make troy-zips an option that cannot be changed and randomize its name?
		// This makes snooping harder for unhardened servers.
		$plugin_id = (int) $plugin_id;
		$base      = \wp_upload_dir()['basedir'] . '/troy-zips/';

		return $plugin_id
			? "{$base}plugin-{$plugin_id}/"
			: "$base";
	}

	/**
	 * Returns the plugin graveyard directory.
	 * This is not for public use, but for internal lookups only.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $plugin_id The plugin ID. Optional.
	 *                       If not provided, the base directory will be returned.
	 * @return string The plugin's graveyard zip directory.
	 */
	public static function get_plugin_graveyard_dir_path( $plugin_id = 0 ) {

		$plugin_id = (int) $plugin_id;
		$base      = \wp_upload_dir()['basedir'] . '/troy-zips-graveyard/';

		return $plugin_id
			? "{$base}plugin-{$plugin_id}/"
			: "$base";
	}

	/**
	 * Returns the plugin zip file path.
	 * This is not for public use, but for internal lookups only.
	 *
	 * @since 0.0.1184
	 *
	 * @param int     $plugin_id The plugin ID.
	 * @param ?string $version   The plugin version. If not provided,
	 *                           the latest version will be used.
	 */
	public static function get_plugin_zip_file_path( $plugin_id, $version ) {

		$version = str_replace(
			[ ' ', '.', '_' ],
			'-',
			sanitize_semver( $version ),
		);

		return static::get_plugin_storage_dir_path( $plugin_id ) . "version-$version/plugin.zip";
	}

	/**
	 * Returns the plugin zip file for the latest version.
	 *
	 * @since 0.0.1184
	 *
	 * @param int   $plugin_id The plugin ID.
	 * @param array $args      {
	 *     Optional. Additional arguments.
	 *
	 *     @type ?string $wp_version  The minimum WordPress version to require.
	 *                                If not provided, the test will not be performed.
	 *     @type ?string $php_version The minimum PHP version to require.
	 *                                If not provided, the test will not be performed.
	 *     @type ?string $type        The type of the zip to return.
	 *                                Options are 'unreleased', 'beta', and 'tag'.
	 *                                Defaults to 'tag'.
	 * }
	 * @return string The plugin zip file path for the latest version.
	 */
	public static function get_plugin_zip_file_path_latest( $plugin_id, $args = [] ) {

		$zip = static::get_latest_plugin_zip( $plugin_id, $args );

		return $zip ? static::get_plugin_zip_file_path( $plugin_id, $zip->version ) : null;
	}

	/**
	 * Returns the plugin zip file for the latest version.
	 *
	 * @since 0.0.1184
	 *
	 * @param int   $plugin_id The plugin ID.
	 * @param array $args      {
	 *     Optional. Additional arguments.
	 *
	 *     @type ?string $wp_version  The minimum WordPress version to require.
	 *                                If not provided, the test will not be performed.
	 *     @type ?string $php_version The minimum PHP version to require.
	 *                                If not provided, the test will not be performed.
	 *     @type ?string $type        The type of the zip to return.
	 *                                Options are 'unreleased', 'beta', and 'tag'.
	 *                                Defaults to 'tag'.
	 * }
	 * @return ?object {
	 *     The zip file data, or null if nothing is found.
	 *
	 *     @type int    id               The zip ID.
	 *     @type int    plugin_id        The plugin ID.
	 *     @type string version          The plugin version.
	 *     @type string type             The zip type (unreleased, beta, tag).
	 *     @type int    file_size        The zip file size.
	 *     @type string tested_wp        The WordPress version the plugin is tested up to.
	 *     @type string requires_wp      The required WordPress version.
	 *     @type string requires_php     The required PHP version.
	 *     @type string repo             The repo identifier.
	 *     @type string dependencies     The plugin dependencies.
	 *     @type string upgrade_notice   The upgrade notice.
	 *     @type string origin_url       The origin URL.
	 *     @type string checksum         The zip checksum.
	 *     @type string checksum_version The checksum version.
	 *     @type string checksum_origin  The checksum origin.
	 *     @type string created_at       The row creation timestamp.
	 *     @type string updated_at       The row last updated timestamp.
	 * }
	 */
	public static function get_latest_plugin_zip( $plugin_id, $args = [] ) {

		$args = array_merge(
			[
				'wp_version'  => null,
				'php_version' => null,
				'type'        => 'tag',
			],
			$args,
		);

		// Unpack the args so we can use them directly, speeding up the loop below.
		$wp_version  = sanitize_tested_version( $args['wp_version'] );
		$php_version = sanitize_tested_version( $args['php_version'] );
		$type        = sanitize_version_type( $args['type'] );

		$zips = new Data( $plugin_id )->get_zips();

		usort( $zips, fn ( $a, $b ) => version_compare( $b->version, $a->version ) );

		foreach ( $zips as $zip ) {
			if ( $zip->type !== $type )
				continue;

			if ( $wp_version && version_compare( $zip->requires_wp, $wp_version, '>' ) )
				continue;

			if ( $php_version && version_compare( $zip->requires_php, $php_version, '>' ) )
				continue;

			// Since the versions are sorted in descending order,
			// the first match is the latest version that meets the criteria.
			return $zip;
		}

		return null;
	}

	/**
	 * Returns the plugin zip URL.
	 * This is for public use, so it can be used in the API.
	 *
	 * @since 0.0.1184
	 *
	 * @param string  $plugin_slug The plugin slug.
	 * @param ?string $version    Optional. The plugin version. If not provided,
	 *                            the latest version will be used.
	 * @return string The plugin zip URL.
	 */
	public static function get_plugin_zip_url_by_slug( $plugin_slug, $version = null ) {

		static $baseurl = null;

		$baseurl ??= \get_home_url() . '/plugin/get/zip/';

		return "$baseurl{$plugin_slug}/"
			. ( $version ? "$version/" : '' );
	}

	/**
	 * Moves a plugin directory to the graveyard.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $plugin_id The plugin ID.
	 * @throws \Exception If the plugin ID is invalid.
	 */
	public static function move_to_graveyard( $plugin_id ) {

		$plugin_id = (int) $plugin_id;

		// Let's guard against invalid plugin IDs before something bad happens.
		if ( $plugin_id <= 0 )
			throw new \Exception( 'Invalid plugin ID.' );

		// 15 ought to be plenty to rename a few folder indexes.
		increase_time_limit_by( 15 );

		$source_dir = static::get_plugin_storage_dir_path( $plugin_id );

		// Not every plugin has had filed uploaded to it. Let's check if they have first.
		if ( is_dir( $source_dir ) ) {
			File_Utils::init_wpfs();
			File_Utils::make_shielded_dir( static::get_plugin_graveyard_dir_path() );

			\move_dir(
				$source_dir,
				static::get_plugin_graveyard_dir_path( $plugin_id ),
				true,
			);
		}
	}
}
