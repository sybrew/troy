<?php
/**
 * @package Troy\Server\Plugins
 * @access  public
 */

namespace Troy\Server\Plugins;

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

// TEMP: PHPCS bugged with PHP 8.4 assymetric visibility and property hooks.
// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable, Squiz.Commenting.VariableComment.Missing
// phpcs:disable PSR2.Classes.PropertyDeclaration.ScopeMissing, PSR2.Classes.PropertyDeclaration.Multiple
// phpcs:disable PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Found, Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Squiz.Commenting.VariableComment.WrongStyle

/**
 * Class Troy\Server\Plugins\Data.
 *
 * This class provides easy access to the plugin data. Getters only.
 * See `Troy\Server\Upgrade\get_initial_db_schema_queries()` for the plugins table.
 *
 * Use direct database calls for writing.
 *
 * The data will always consider the plugin version. If none is provided, the
 * latest version will be assumed.
 * To get all data, you must resort to custom queries.
 *
 * To get the plugin ID by slug, use `Troy\Server\API\Plugin::get_plugin_id_by_slug()`.
 *
 * @since 0.0.1184
 */
final class Data {

	/**
	 * @since 0.0.1184
	 * @var ?int $plugin_id The plugin ID.
	 *                      It will always be an integer, even though it is nullable.
	 */
	public readonly ?int $plugin_id;

	/**
	 * @since 0.0.1184
	 * @var ?string $plugin_version The plugin version.
	 *                              It will always be a string, even though it is nullable.
	 */
	public private(set) ?string $plugin_version {
		get => $this->plugin_version ??= $this->get_latest_version();
	}

	/**
	 * @since 0.0.1184
	 * @var ?string $date The date in "Y-m-d" format.
	 *                    It will always be a string, even though it is nullable.
	 */
	public private(set) ?string $date {
		get => $this->date ??= API\Sanitize::sql_date( 'now' );
	}

	/**
	 * @since 0.0.1184
	 * @var ?string $locale The locale.
	 */
	public private(set) ?string $locale {
		get => $this->locale ??= 'en_US';
	}

	/**
	 * Sets up the plugin data to work with.
	 *
	 * @since 0.0.1184
	 *
	 * @param ?int    $plugin_id      The plugin ID. If unknown, use $post_id instead.
	 * @param ?string $plugin_version Optional. The plugin version.
	 *                                If left empty, the latest version will be
	 *                                assumed when available. Will default to null.
	 * @param ?string $date           Optional. The date in ISO8601, preferably "Y-m-d".
	 *                                If left empty, the current UTC day will be assumed.
	 *                                For relative formats, see https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative.
	 * @param ?int    $post_id        Optional. The post ID of the plugin. Will be ignored if $plugin_id is set.
	 * @param string  $locale         Optional. The locale. If left empty, the default 'en_US' will be used.
	 * @throws \Exception If both $plugin_id and $post_id are not set.
	 */
	public function __construct( $plugin_id = null, $plugin_version = null, $date = null, $post_id = null, $locale = null ) {

		if ( ! $plugin_id && ! $post_id )
			throw new \Exception( 'Either plugin_id or post_id must be set.' );

		$this->plugin_id = $plugin_id ?? API\Plugin::get_plugin_id_by_post_id( $post_id );

		$this->plugin_version = $plugin_version;
		$this->date           = $date ? API\Sanitize::sql_date( $date ) : null;
		$this->locale         = $locale;
	}

	/**
	 * Returns the latest plugin version using the same logic as the JS editor.
	 * Prioritizes 'tag' type versions, then falls back to the highest version of any type.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?string The plugin version. Null if no versions are found.
	 */
	public function get_latest_version() {

		$zips = $this->get_zips();

		if ( empty( $zips ) )
			return null;

		// Convert to array format compatible with the standardized API function
		$versions = array_map(
			fn( $zip ) => [
				'version' => $zip->version,
				'type'    => $zip->type,
			],
			$zips,
		);

		return API\Utils::extract_latest_version( $versions );
	}

	/**
	 * Gets the primary plugin row.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The plugins' row.
	 *
	 *     @type int    id               The plugin meta ID.
	 *     @type int    post_id          The plugin's post ID.
	 *     @type string slug             The plugin's slug.
	 *     @type string status           The plugin's status.
	 *     @type string origin_url       The origin URL.
	 *     @type int    database_version The database version.
	 *     @type string created_at       The row creation timestamp.
	 *     @type string updated_at       The row last updated timestamp.
	 * }
	 */
	public function get_plugins_row() {

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}troy_plugins WHERE id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the plugin slug transfers row.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin slugs, or null if none are found.
	 *
	 *     @type int    id         The plugin meta ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string old_slug   The old slug.
	 *     @type string new_slug   The new slug.
	 *     @type string created_at The row creation timestamp.
	 *     @type string updated_at The row last updated timestamp.
	 * }
	 */
	public function get_slug_transfers() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_slug_transfers
			WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the plugin meta row.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The plugin meta row.
	 *
	 *     @type int    id                The plugin meta ID.
	 *     @type int    plugin_id         The plugin ID.
	 *     @type string name              The plugin name.
	 *     @type int    author_id         The plugin author ID.
	 *     @type string short_description The short description.
	 *     @type string permalink         The permalink.
	 *     @type string support_uri       The support URI.
	 *     @type string donate_uri        The donate URI.
	 *     @type string logo_uri          The logo image URI.
	 *     @type string builder_type      The builder type.
	 *     @type string created_at        The row creation timestamp.
	 *     @type string updated_at        The row last updated timestamp.
	 * }
	 */
	public function get_metas_row() {

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}troy_plugin_metas WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the plugin contributors.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin contributors, or null if none are found.
	 *
	 *     @type int    id         The contributor ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type int    user_id    The user ID.
	 *     @type string role       The role.
	 *     @type string created_at The row creation timestamp.
	 *     @type string updated_at The row last updated timestamp.
	 * }
	 */
	public function get_contributors() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}troy_plugin_contributors WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the plugin info.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The plugin info.
	 *
	 *     @type int    id             The info ID.
	 *     @type int    plugin_id      The plugin ID.
	 *     @type string locale         The info locale.
	 *     @type string latest_version The latest version.
	 *     @type string banner_uri     The banner image URI.
	 *     @type array  contents       {
	 *         The HTML contents.
	 *
	 *         @type string $details     The main plugin details page.
	 *         @type string $usage       The installation/usage instructions.
	 *         @type string $faq         The FAQ content.
	 *         @type string $changelog   The changelog content.
	 *         @type string $screenshots The screenshots content.
	 *     }
	 *     @type string created_at     The row creation timestamp.
	 *     @type string updated_at     The row last updated timestamp.
	 * }
	 */
	public function get_infos_row() {

		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_infos
			WHERE plugin_id = %d
				AND locale IN (%s, 'en_US')",
			$this->plugin_id,
			$this->locale,
		) );

		if ( ! $rows )
			return null;

		$infos = null;

		foreach ( $rows as $row ) {
			if ( $row->locale === $this->locale ) {
				$infos = $row;
				break;
			}
			// Default to en_US if no exact match found.
			if ( 'en_US' === $row->locale )
				$infos = $row; // Don't break, keep looking for exact match
		}

		if ( $infos?->contents )
			$infos->contents = json_decode( $infos->contents, true );

		return $infos;
	}

	/**
	 * Gets the plugin snapshot.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The plugin snapshot.
	 *
	 *     @type int    id         The snapshot ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string version    The plugin version.
	 *     @type string values     The snapshot values.
	 *     @type string created_at The row creation timestamp.
	 *     @type string updated_at The row last updated timestamp.
	 * }
	 */
	public function get_snapshots_row() {

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_snapshots
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin zip row data by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin zips, or null if none are found.
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
	public function get_zips() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}troy_plugin_zips WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the plugin zip row data by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The plugin zip row data by version.
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
	public function get_zips_row() {

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_zips
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin translations by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin translations by version, or null if none are found.
	 *
	 *     @type int    id               The translation ID.
	 *     @type int    plugin_id        The plugin ID.
	 *     @type string version          The plugin version.
	 *     @type string locale           The translation's locale.
	 *     @type int    file_size        The translation file size.
	 *     @type string origin_url       The origin URL that collected this information.
	 *     @type string checksum         The checksum.
	 *     @type string checksum_version The checksum version.
	 *     @type string checksum_origin  The checksum origin.
	 *     @type string created_at       The row creation timestamp.
	 *     @type string updated_at       The row last updated timestamp.
	 * }
	 */
	public function get_translations() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_translations
			WHERE plugin_id = %d
				AND `version` = %s
				AND `locale` = %s",
			$this->plugin_id,
			$this->plugin_version,
			$this->locale,
		) );
	}

	/**
	 * Gets the plugin data cache.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object {
	 *     The plugin data cache.
	 *
	 *     @type int    id                    The cache ID.
	 *     @type int    plugin_id             The plugin ID.
	 *     @type int    average_rating        The average rating.
	 *     @type int    rating_count          The rating count.
	 *     @type int    recent_average_rating The recent average rating.
	 *     @type int    recent_rating_count   The recent rating count.
	 *     @type int    active_install_count  The active install count.
	 *     @type string created_at            The row creation timestamp.
	 *     @type string updated_at            The row last updated timestamp.
	 * }
	 */
	public function get_data_caches_row() {

		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_data_caches
			WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the plugin ratings.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin ratings, or null if none are found.
	 *
	 *     @type int    id         The rating ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string version    The plugin version.
	 *     @type int    user_id    The user ID.
	 *     @type int    rating     The rating (0~100).
	 *     @type string created_at The row creation timestamp.
	 *     @type string updated_at The row last updated timestamp.
	 * }
	 */
	public function get_ratings() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}troy_plugin_ratings WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the total plugin stats (accumulated total of all versions).
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin stats, or null if none are found.
	 *
	 *     @type int    id                           The stats ID.
	 *     @type int    plugin_id                    The plugin ID.
	 *     @type string origin_url                   The origin URL that collected this information.
	 *     @type int    downloads                    The number of downloads.
	 *     @type int    views                        The number of views.
	 *     @type int    installations_current_epoch  The current epoch installations.
	 *     @type int    installations_previous_epoch The previous epoch installations.
	 *     @type string created_at                   The row creation timestamp.
	 *     @type string updated_at                   The row last updated timestamp.
	 * }
	 */
	public function get_stats_totals() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}troy_plugin_stats_totals WHERE plugin_id = %d",
			$this->plugin_id,
		) );
	}

	/**
	 * Gets the total plugin stats to date (historical, accumulated total of all versions).
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin stats to date, or null if none are found.
	 *
	 *     @type int    id                           The stats ID.
	 *     @type int    plugin_id                    The plugin ID.
	 *     @type string date                         The date of the stats.
	 *     @type string origin_url                   The origin URL that collected this information.
	 *     @type int    downloads                    The number of downloads.
	 *     @type int    views                        The number of views.
	 *     @type int    installations_current_epoch  The current epoch installations.
	 *     @type int    installations_previous_epoch The previous epoch installations.
	 *     @type string created_at                   The row creation timestamp.
	 *     @type string updated_at                   The row last updated timestamp.
	 * }
	 */
	public function get_stats_totals_to_date() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_totals_daily
			WHERE plugin_id = %d
				AND `date` = %s",
			$this->plugin_id,
			$this->date,
		) );
	}

	/**
	 * Gets the plugin stats by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin stats by version, or null if none are found.
	 *
	 *     @type int    id                           The stats ID.
	 *     @type int    plugin_id                    The plugin ID.
	 *     @type string version                      The plugin version.
	 *     @type string origin_url                   The origin URL that collected this information.
	 *     @type int    downloads                    The number of downloads.
	 *     @type int    views                        The number of views.
	 *     @type int    installations_current_epoch  The current epoch installations.
	 *     @type int    installations_previous_epoch The previous epoch installations.
	 *     @type string created_at                   The row creation timestamp.
	 *     @type string updated_at                   The row last updated timestamp.
	 * }
	 */
	public function get_stats() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_views
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin stats by version to date (historical, accumulated total by version).
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin stats by version to date, or null if none are found.
	 *
	 *     @type int    id                           The stats ID.
	 *     @type int    plugin_id                    The plugin ID.
	 *     @type string version                      The plugin version.
	 *     @type string date                         The date of the stats.
	 *     @type string origin_url                   The origin URL that collected this information.
	 *     @type int    downloads                    The number of downloads.
	 *     @type int    views                        The number of views.
	 *     @type int    installations_current_epoch  The current epoch installations.
	 *     @type int    installations_previous_epoch The previous epoch installations.
	 *     @type string created_at                   The row creation timestamp.
	 *     @type string updated_at                   The row last updated timestamp.
	 * }
	 */
	public function get_stats_to_date() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_versions_daily
			WHERE plugin_id = %d
				AND `version` = %s
				AND `date` = %s",
			$this->plugin_id,
			$this->plugin_version,
			$this->date,
		) );
	}

	/**
	 * Gets the plugin view stats by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin view stats by version, or null if none are found.
	 *
	 *     @type int    id         The view ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string version    The plugin version.
	 *     @type int    views      The number of views.
	 *     @type string screen     The screen type: 'thickbox', 'search', etc.
	 *     @type string locale     The locale of the view.
	 *     @type string origin_url The origin URL that collected this information.
	 *     @type string created_at The row creation timestamp.
	 *     @type string updated_at The row last updated timestamp.
	 * }
	 */
	public function get_view_stats() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_views
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin view stats by version meant for scheduled processing.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin view stats by version meant for scheduled processing, or null if none are found.
	 *
	 *     @type int    id         The view ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string version    The plugin version.
	 *     @type string screen     The screen type: 'thickbox', 'search', etc.
	 *     @type string locale     The locale of the view.
	 *     @type string origin_url The origin URL that collected this information.
	 *     @type string created_at The row creation timestamp.
	 * }
	 */
	public function get_view_stats_live() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_views_live
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin download stats.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     An array of plugin download stats by version, or null if none are found.
	 *
	 *     @type int    id         The download ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string version    The plugin version.
	 *     @type int    downloads  The number of downloads.
	 *     @type string type       The type of download: 'update', 'direct-version', 'direct-latest'.
	 *     @type string origin_url The origin URL that collected this information.
	 *     @type string created_at The row creation timestamp.
	 *     @type string updated_at The row last updated timestamp.
	 * }
	 */
	public function get_download_stats() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_downloads
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin download stats by version meant for scheduled processing.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     The plugin download stats by version meant for scheduled processing, or null if none are found.
	 *
	 *     @type int    id         The download ID.
	 *     @type int    plugin_id  The plugin ID.
	 *     @type string version    The plugin version.
	 *     @type string type       The type of download: 'update', 'version', 'latest'.
	 *     @type string origin_url The origin URL that collected this information.
	 *     @type string created_at The row creation timestamp.
	 * }
	 */
	public function get_download_stats_live() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_downloads_live
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin update request stats by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     The plugin update request stats by version, or null if none are found.
	 *
	 *     @type int    id            The request ID.
	 *     @type int    plugin_id     The plugin ID.
	 *     @type int    epoch         The update epoch.
	 *     @type string version       The plugin version.
	 *     @type int    is_active     Whether the plugin is active on the client site. (1 = active, 0 = inactive)
	 *     @type int    request_count The request count.
	 *     @type string created_at    The row creation timestamp.
	 *     @type string updated_at    The row last updated timestamp.
	 * }
	 */
	public function get_update_request_stats() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_requests
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin update request locale stats by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     The plugin update request locale stats by version, or null if none are found.
	 *
	 *     @type int    id            The request ID.
	 *     @type int    plugin_id     The plugin ID.
	 *     @type string version       The plugin version.
	 *     @type int    epoch         The update epoch.
	 *     @type string locale        The locale (e.g., 'en_US').
	 *     @type int    install_count The number of unique sites using this locale.
	 *     @type string created_at    The row creation timestamp.
	 *     @type string updated_at    The row last updated timestamp.
	 * }
	 */
	public function get_update_request_locales_stats() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_locales
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the live plugin update request stats by version.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @return ?object[] {
	 *     The live plugin update request stats by version, or null if none are found.
	 *
	 *     @type int    id             The request ID.
	 *     @type int    plugin_id      The plugin ID.
	 *     @type int    epoch          The update epoch extracted from the UUID.
	 *     @type int    is_active      Whether the plugin is active on the client site. (1 = active, 0 = inactive)
	 *     @type string version        The plugin version.
	 *     @type string uuid           The UUID of the client.
	 *     @type int    request_count  The request count.
	 *     @type string locales        The locales supported by the client, JSON-encoded array.
	 *     @type string php_version    The PHP version.
	 *     @type string wp_version     The WordPress version.
	 *     @type string client_version The Troy client version.
	 *     @type string created_at     The row creation timestamp.
	 *     @type string updated_at     The row last updated timestamp.
	 * }
	 */
	public function get_update_request_stats_live() {

		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_stats_requests_live
			WHERE plugin_id = %d
				AND `version` = %s",
			$this->plugin_id,
			$this->plugin_version,
		) );
	}

	/**
	 * Gets the plugin integration data.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $args {
	 *     Getter arguments.
	 *
	 *     @type ?bool $args['get_auth'] Whether to include authentication data.
	 *                                   Leave null or false to exclude.
	 * }
	 * @return ?object {
	 *     The plugin integration row.
	 *
	 *     @type int     id              The integration ID.
	 *     @type int     plugin_id       The plugin post ID.
	 *     @type string  $mode           The integration mode. Currently, either 'github', 'wporg'.
	 *     @type object  $settings       Structure varies by mode:
	 *         GitHub: {
	 *             Decoded settings object.
	 *
	 *             @type string $owner_repo Repository in owner/repo format.
	 *             @type bool   $has_auth   Whether authentication is configured.
	 *         }
	 *         WordPress.org: {
	 *             Decoded settings object.
	 *
	 *             @type string $slug     Plugin slug.
	 *             @type bool   $has_auth Whether authentication is configured (always false for WPOrg).
	 *         }
	 *     @type ?object $auth           Null if not configured or when get_auth is false.
	 *                                   Object otherwise, Structure varies by mode: {
	 *         GitHub: {
	 *             Authentication data
	 *
	 *             @type object $token {
	 *                 @type string $type  Token type (e.g., 'bearer').
	 *                 @type mixed  $value Token value. String for PAT, array for OAuth2 (client_id, client_secret, etc.).
	 *             }
	 *             @type object $download {
	 *                 @type array $headers     HTTP headers for authenticated downloads (e.g., ['Authorization' => 'Bearer token']).
	 *                 @type array $queryParams Query parameters for authenticated downloads (reserved for future use).
	 *             }
	 *         }
	 *     }
	 *     @type object  $tags           Decoded tags object, indexed by tag name (version). {
	 *         @type string $download_url The tag download URL.
	 *         @type string $type         The tag type ('tag' or 'beta').
	 *     }
	 *     @type ?string $tags_refreshed The tags last refreshed timestamp. Null if never refreshed.
	 *     @type string  $auto_process   Auto-process setting ('all', 'tag', 'beta', 'none').
	 *     @type string  $created_at     The row creation timestamp.
	 *     @type string  $updated_at     The row last updated timestamp.
	 * }
	 */
	public function get_integration( $args = [] ) {

		global $wpdb;

		$integration = $wpdb->get_row( $wpdb->prepare(
			"SELECT *
			FROM {$wpdb->prefix}troy_plugin_integrations
			WHERE plugin_id = %d",
			$this->plugin_id,
		) );

		if ( ! $integration )
			return null;

		// Decode JSON fields
		$integration->settings = json_decode( $integration->settings );
		$integration->tags     = json_decode( $integration->tags );

		if ( $args['get_auth'] ?? false ) {
			$integration->auth = $integration->auth ? json_decode( $integration->auth ) : null;
		} else {
			unset( $integration->auth );
		}

		return $integration;
	}
}
