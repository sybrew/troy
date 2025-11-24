<?php
/**
 * @package Troy\Server\Packages
 * @access  public
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\DB_VERSION;

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

// TEMP: PHPCS bugged with PHP 8.4 assymetric visibility and property hooks.
// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable, Squiz.Commenting.VariableComment.Missing
// phpcs:disable PSR2.Classes.PropertyDeclaration.ScopeMissing, PSR2.Classes.PropertyDeclaration.Multiple
// phpcs:disable PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Found, Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Squiz.Commenting.VariableComment.WrongStyle

/**
 * Class Troy\Server\Packages\Data.
 *
 * This class provides easy access to the package data. Getters only.
 * See `Troy\Server\Upgrade\get_initial_db_schema_queries()` for the packages tables.
 *
 * Use direct database calls for writing.
 *
 * @since 0.0.1184
 */
final class Data {

	/**
	 * @since 0.0.1184
	 * @var ?int $package_id The package ID.
	 *                       It will always be an integer, even though it is nullable.
	 */
	public readonly ?int $package_id;

	/**
	 * Sets up the package data to work with.
	 *
	 * @since 0.0.1184
	 *
	 * @param ?int $package_id The package ID. If unknown, use $post_id instead.
	 * @param ?int $post_id    Optional. The post ID of the package. Will be ignored if $package_id is set.
	 * @throws \Exception If both $package_id and $post_id are not set.
	 */
	public function __construct( $package_id = null, $post_id = null ) {

		if ( ! $package_id && ! $post_id )
			throw new \Exception( 'Either package_id or post_id must be set.' );

		$this->package_id = $package_id ?? API\Package::get_package_id_by_post_id( $post_id );
	}

	/**
	 * Gets the package ID by slug.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param string $slug The package slug.
	 * @return ?int The package ID. Null if not found.
	 */
	public static function get_package_id_by_slug( $slug ) {

		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$wpdb->prefix}troy_packages` WHERE slug = %s",
				$slug,
			),
		) ?: null;
	}

	/**
	 * Gets the primary package row.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The package data, or null if not found.
	 *
	 *     @type int    id               The package ID.
	 *     @type int    post_id          The post ID.
	 *     @type string slug             The package slug.
	 *     @type string status           The package status.
	 *     @type string origin_url       The origin URL.
	 *     @type int    database_version The database version.
	 *     @type string created_at       The row creation timestamp.
	 *     @type string updated_at       The row last updated timestamp.
	 * }
	 */
	public function get_packages_row() {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}troy_packages` WHERE id = %d",
				$this->package_id,
			),
		);
	}

	/**
	 * Gets the package meta row.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The package meta data, or null if not found.
	 *
	 *     @type int    id                        The meta ID.
	 *     @type int    package_id                The package ID.
	 *     @type string plugin_uri                The plugin URI.
	 *     @type string name                      The name.
	 *     @type string description               The description.
	 *     @type string version                   The version.
	 *     @type string author                    The author.
	 *     @type string author_uri                The author URI.
	 *     @type string requires_wp               The required WordPress version.
	 *     @type string requires_php              The required PHP version.
	 *     @type int    network                   Whether network-wide activation.
	 *     @type int    install_timeout           The installer timeout in seconds.
	 *     @type int    deactivate_on_completion  Whether to deactivate installer after completion.
	 *     @type int    delete_on_completion      Whether to delete installer after completion.
	 *     @type string notice_severity           The notice severity level.
	 *     @type string plugins                   The plugins JSON.
	 *     @type string themes                    The themes JSON.
	 *     @type string created_at                The row creation timestamp.
	 *     @type string updated_at                The row last updated timestamp.
	 * }
	 */
	public function get_metas_row() {

		global $wpdb;

		$meta = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}troy_packages_metas` WHERE package_id = %d",
				$this->package_id,
			),
		);

		if ( ! $meta )
			return null;

		// Decode JSON fields
		$meta->plugins = json_decode( $meta->plugins, true );
		$meta->themes  = json_decode( $meta->themes, true );

		return $meta;
	}
}
