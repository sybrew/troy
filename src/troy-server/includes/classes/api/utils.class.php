<?php
/**
 * @package Troy\Server\API
 * @api
 */

namespace Troy\Server\API;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\VERSION;

/**
 * Troy Server
 *
 * Copyright (c) 2025 - 2026 Sybre Waaijer, CyberWire B.V.
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
 * Holds utility API methods.
 *
 * @since 0.0.1184
 */
final class Utils {

	/**
	 * Returns the epoch for the UUID.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $week 'this' for this epoch, 'last' for the last epoch.
	 * @return int The epoch.
	 */
	public static function get_epoch( $week = 'this' ) {
		// This is the timeout for the UUID: 1 week. int-casting floors.
		return (int) ( time() / \WEEK_IN_SECONDS ) + ( 'this' === $week ? 0 : -1 );
	}

	/**
	 * Returns the latest version from an array of versions, following the same priority logic.
	 * Prioritizes 'tag' type versions, then 'beta', and finally 'unreleased'.
	 *
	 * @since 0.0.1184
	 * @since 1.7.1184 Added $types parameter.
	 *
	 * @param array    $versions Array of version objects with 'version' and optional 'type' keys.
	 * @param string[] $types    Optional. Version types to consider, in priority order.
	 *                           Default [ 'tag', 'beta', 'unreleased' ].
	 * @return ?string The latest version string, or null if no versions found.
	 */
	public static function extract_latest_version( $versions, $types = [ 'tag', 'beta', 'unreleased' ] ) {

		if ( empty( $versions ) )
			return null;

		// Force canonical priority order regardless of input order.
		$types = array_intersect( [ 'tag', 'beta', 'unreleased' ], $types );

		$filtered_versions = null;

		foreach ( $types as $type ) {
			$filtered_versions = array_column(
				array_filter(
					$versions,
					fn( $version ) => ( $version['type'] ?? null ) === $type,
				),
				'version',
			);

			if ( $filtered_versions )
				break;
		}

		if ( empty( $filtered_versions ) )
			return null;

		usort( $filtered_versions, 'version_compare' );

		return end( $filtered_versions );
	}

	/**
	 * Returns the latest patch version for the input WordPress version.
	 *
	 * This function checks the WordPress API for the latest stable versions
	 * and returns the highest patch version for the given major.major version.
	 *
	 * WordPress doesn't use SemVer, so it groups versions by
	 * major.major (e.g., 6.3) and returns the highest patch version for that.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $from_version The current WordPress version. Optional.
	 *                             This will be used as the base major version to find the latest patch version.
	 *                             If not provided, the latest version will be returned.
	 * @return string The latest public WordPress version for the given input version.
	 */
	public static function get_latest_public_wordpress_version( $from_version = '' ) {

		$cache = \get_option( 'troy_server_latest_public_wp_version_cache' ) ?: [];

		if ( time() < ( $cache['expire'] ?? 0 ) ) {
			$api_versions = $cache['versions'];
		} else {
			$expire = \HOUR_IN_SECONDS * 6;

			// Limit this to 100 versions to avoid performance issues.
			// At the moment of writing, this went back to 1617 days of releases.
			$body = \wp_remote_retrieve_body( \wp_remote_get( // No safe: hardcoded github.com URL
				'https://api.github.com/repos/WordPress/WordPress/tags?per_page=100',
				[
					'timeout'    => 3,
					'headers'    => [
						'Accept'     => 'application/json',
						'User-Agent' => 'Troy Server/' . VERSION, // See WP_Http_Curl::request()
					],
					'user-agent' => 'Troy Server/' . VERSION, // See WP_Http::request()
				],
			) );

			// Body becomes empty on error via wp_remote_retrieve_body().
			if ( empty( $body ) ) {
				// Fallback to previous cache if available.
				$api_versions = $cache['versions'] ?? [];
				$expire       = \MINUTE_IN_SECONDS;
			} else {
				// Decode the JSON response and get only the version numbers.

				// This is for https://api.wordpress.org/core/stable-check/1.0/
				// $versions_array = array_keys( json_decode( $body, true ) ?? [] );

				// This is for https://api.github.com/repos/WordPress/WordPress/tags
				$versions_array = array_map(
					fn( $tag ) => $tag['name'] ?? '',
					json_decode( $body, true ) ?: [],
				);

				// Group by major.major and set the highest patch for each.
				// e.g., with 6.8, 6.8.1, and 6.8.2, we only set "6.8.2" for key "6.8".
				$api_versions = [];
				foreach ( $versions_array as $ver ) {
					if ( preg_match( '/^(\d+\.\d+)/', $ver, $matches ) ) {
						$major_major = $matches[1];
						if (
							   ! isset( $api_versions[ $major_major ] )
							|| version_compare( $ver, $api_versions[ $major_major ], '>' )
						) {
							$api_versions[ $major_major ] = $ver;
						}
					}
				}
			}

			if ( ! $api_versions ) {
				// Fallback to the current WordPress version
				$blog_version = \get_bloginfo( 'version' );
				$api_versions = [
					preg_replace( '/(\d+\.\d+).*/', '$1', $blog_version )
						=> preg_replace( '/(\d+\.\d+(?:\.\d+)?).*/', '$1', $blog_version ),
				];
			}

			\update_option(
				'troy_server_latest_public_wp_version_cache',
				[
					'versions' => $api_versions,
					'expire'   => time() + $expire,
				],
				true,
			);
		}

		// If no current version is provided, return the latest version (the highest number).
		if ( empty( $from_version ) )
			return reset( $api_versions );

		return $api_versions[ preg_replace( '/(\d+\.\d+).*/', '$1', $from_version ) ]
			?? $from_version;
	}

	/**
	 * Determines the version type based on its naming pattern.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $version The version string to evaluate.
	 * @return string 'beta' if the version is a beta/pre-release, 'tag' otherwise.
	 */
	public static function get_version_type( $version ) {
		return preg_match( '/(dev|alpha|a|beta|b|rc|#|pl|p)([^a-z]|\Z)/i', $version )
			? 'beta'
			: 'tag';
	}

	/**
	 * Increments the PHP time limit by the given number of seconds.
	 * It starts with the default of max_execution_time (30 seconds).
	 *
	 * @since 0.0.1184
	 *
	 * @param int $seconds The number of seconds to increment the time limit by.
	 */
	public static function increase_time_limit_by( $seconds ) {

		static $total_seconds = 30;

		set_time_limit( $total_seconds += $seconds );
	}
}
