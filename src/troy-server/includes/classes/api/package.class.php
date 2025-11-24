<?php
/**
 * @package Troy\Server\API
 * @api
 */

namespace Troy\Server\API;

\defined( 'Troy\Server\ABSPATH' ) or die;

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
 * Holds package-related API methods.
 *
 * @since 0.0.1184
 */
final class Package {

	/**
	 * Returns the package ID by its slug.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param string $slug The package slug.
	 * @return ?int The package ID. Null if not found.
	 */
	public static function get_package_id_by_slug( $slug ) {

		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT `id` FROM {$wpdb->prefix}troy_packages WHERE `slug` = %s",
			$slug,
		) ) ?: null;
	}

	/**
	 * Returns the package ID by its post ID.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $post_id The package post ID.
	 * @return ?int The package ID. Null if not found.
	 */
	public static function get_package_id_by_post_id( $post_id ) {

		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT `id` FROM {$wpdb->prefix}troy_packages WHERE `post_id` = %d",
			$post_id,
		) ) ?: null;
	}

	/**
	 * Returns the post ID by its package ID.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int $package_id The package ID.
	 * @return ?int The post ID. Null if not found.
	 */
	public static function get_post_id_by_package_id( $package_id ) {

		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT `post_id` FROM {$wpdb->prefix}troy_packages WHERE `id` = %d",
			$package_id,
		) ) ?: null;
	}
}
