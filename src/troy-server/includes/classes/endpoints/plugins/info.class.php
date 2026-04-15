<?php
/**
 * @package Troy\Server\Endpoints\Plugins
 * @access  public
 */

namespace Troy\Server\Endpoints\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Endpoints\Base_Endpoint,
	Plugins\Data,
	Plugins\Files,
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
 * Plugin info endpoint for Troy Server.
 *
 * Handles requests for plugin information used in the WordPress plugin thickbox.
 *
 * @since 0.0.1184
 */
final class Info extends Base_Endpoint {

	/**
	 * Handle the plugin information request.
	 *
	 * @rest plugin/get/info/plugin-name POST
	 * @since 0.0.1184
	 */
	public function handle_request() {

		switch ( $_SERVER['REQUEST_METHOD'] ) {
			case 'POST':
				break;
			case 'OPTIONS':
				$this->send_preflight_response( 'POST, OPTIONS' );
				// No break. send_preflight_response() exits.
			default:
				$this->send_error( 'Method not allowed', 405 );
		}

		// phpcs:ignore TSF.Performance -- This read a stream, not a file.
		$input = json_decode( file_get_contents( 'php://input' ), true );

		if ( ! \is_array( $input ) )
			$this->send_error( 'Invalid JSON input', 400 );

		if ( empty( $input['slug'] ) )
			$this->send_error( 'Missing required parameter: slug', 400 );

		$slug   = API\Sanitize::slug( $input['slug'] );
		$fields = (array) ( $input['fields'] ?? [] );
		$locale = \sanitize_text_field( $input['locale'] ?? 'en_US' );
		$screen = \sanitize_text_field( $input['screen'] ?? 'unknown' );

		// Additional validation for slug format
		if ( ! $slug )
			$this->send_error( 'Invalid slug', 400 );

		$plugin_id = API\Plugin::get_plugin_id_by_slug( $slug );

		if ( ! $plugin_id )
			$this->send_error( 'Plugin not found', 404 );

		try {
			$data = new Data( $plugin_id, locale: $locale );

			$plugin_row = $data->get_plugins_row();
			$meta_row   = $data->get_metas_row();

			if ( ! $plugin_row || ! $meta_row )
				$this->send_error( 'Plugin data not available', 404 );

			// Check plugin status - only serve info for public/unlisted plugins
			switch ( $plugin_row->status ) {
				case 'public':
				case 'unlisted':
					// Allowed statuses. Break to continue processing.
					break;
				// TODO: Implement conditional listing.
				// case 'protected':
				// 	$this->send_error( 'Plugin is protected', 401 );
				// 	break;
				case 'pending':
				case 'disabled':
				default:
					$this->send_error( 'Plugin not available', 403 );
			}

			$info_row     = $data->get_infos_row();
			$latest_zip   = $data->get_zips_row();
			$data_cache   = $data->get_data_caches_row();
			$stats_totals = $data->get_stats_totals_row();
			$contributors = $data->get_contributors();

			$version = $latest_zip->version ?? '';

			$response = [
				'name'            => $meta_row->name,
				'slug'            => $plugin_row->slug,
				'version'         => $version,
				'author'          => $this->get_author_string( $meta_row->author_id ),
				'contributors'    => $this->get_contributors_array( $contributors ),
				'requires'        => $latest_zip->requires_wp ?? '',
				'tested'          => API\Utils::get_latest_public_wordpress_version(
					$latest_zip->tested_wp ?? $latest_zip->requires_wp ?? '',
				),
				'requires_php'    => $latest_zip->requires_php ?? '',
				'downloaded'      => (int) ( $stats_totals->downloads ?? 0 ),
				'active_installs' => (int) ( $data_cache->active_install_count ?? 0 ),
				'last_updated'    => $this->format_last_updated( $latest_zip->updated_at ?? '' ),
				'added'           => $this->format_date_added( $plugin_row->created_at ?? '' ),
				'homepage'        => $meta_row->permalink ?? '',
				'download_link'   => $this->get_download_link( $plugin_row->slug, $latest_zip ),
				'sections'        => $this->get_sections_array( $info_row->contents ?: [] ),
				'donate_link'     => $meta_row->donate_uri ?? '',
				'banners'         => $this->get_banners_array( $info_row ),
			];

			// Handle specific field inclusions/exclusions
			if ( $fields ) {

				$fields = array_merge(
					$fields,
					// Common fields that are always included
					[
						'name'    => true,
						'slug'    => true,
						'version' => true,
					],
				);

				$filtered = [];

				// Add requested fields. WordPress sends ['field' => bool], so iterate keys.
				foreach ( $fields as $field => $include )
					if ( $include && isset( $response[ $field ] ) )
						$filtered[ $field ] = $response[ $field ];

				$response = $filtered;
			}

			// Record plugin info request stats
			$this->record_info_request_stats( $plugin_id, $version, $locale, $screen );

			$this->send_json_response( $response );

		} catch ( \Exception $e ) {
			$this->send_error(
				\sprintf(
					/* translators: %s: Error message */
					\__( 'Failed to get plugin information: %s', 'troy-server' ),
					$e->getMessage(),
				),
				500,
			);
		}
	}

	/**
	 * Get author string for the plugin.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $author_id Plugin main author ID.
	 * @return string Author string.
	 */
	private function get_author_string( $author_id ) {
		return ( $author_id
			// false on failure, so no null-safe operator here.
			? \get_user_by( 'id', $author_id )->display_name ?? false
			: false
		)
			?: \__( 'Unknown author', 'troy-server' );
	}

	/**
	 * Get contributors array.
	 *
	 * @since 0.0.1184
	 *
	 * @param object[] $contributors Contributors array.
	 * @return array Contributors array in WordPress format.
	 */
	private function get_contributors_array( $contributors ) {

		$result = [];

		foreach ( $contributors as $contributor ) {
			// User should always exit.
			$userdata = \get_userdata( $contributor->user_id );

			if ( $userdata ) {
				$result[ $contributor->user_id ] = [
					'profile'      => \get_author_posts_url( $contributor->user_id ),
					'avatar'       => \get_avatar_url( $contributor->user_id, [ 'size' => 96 ] ),
					'display_name' => $userdata->display_name,
				];
			}
		}

		return $result;
	}

	/**
	 * Calculate rating percentage from data cache.
	 *
	 * @since 0.0.1184
	 *
	 * @param object $data_cache Data cache row.
	 * @return int Rating percentage (0-100).
	 */
	private function calculate_rating_percentage( $data_cache ) {

		if ( ! $data_cache->rating_count )
			return 0;

		// Convert from 0-100 scale to percentage
		return (int) $data_cache->average_rating;
	}

	/**
	 * Get ratings breakdown array.
	 *
	 * @since 0.0.1184
	 *
	 * @param object $data_cache Data cache row.
	 * @return array Ratings breakdown (5-star to 1-star counts).
	 */
	private function get_ratings_breakdown( $data_cache ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// For now, return empty breakdown
		// In the future, this could be enhanced with actual rating distribution
		return [
			'5' => 0,
			'4' => 0,
			'3' => 0,
			'2' => 0,
			'1' => 0,
		];
	}

	/**
	 * Format last updated date.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $date Updated date string.
	 * @return string Formatted date.
	 */
	private function format_last_updated( $date ) {

		if ( ! $date )
			return '';

		try {
			$datetime = new \DateTime( $date );
			return $datetime->format( 'Y-m-d g:ia \G\M\T' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Format date added.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $date Created date string.
	 * @return string Formatted date.
	 */
	private function format_date_added( $date ) {

		if ( ! $date )
			return '';

		try {
			$datetime = new \DateTime( $date );
			return $datetime->format( 'Y-m-d' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get description content from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugin_infos table.
	 * @return string Description content.
	 */
	private function get_description_content( $info_contents ) {
		return $info_contents['details'] ?? '';
	}

	/**
	 * Get download link for the plugin.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $slug       Plugin slug.
	 * @param object $latest_zip Latest zip row from troy_plugin_zips table.
	 * @return string Download link.
	 */
	private function get_download_link( $slug, $latest_zip ) {

		if ( ! $latest_zip?->version )
			return '';

		return Files::get_plugin_zip_url_by_slug( $slug, $latest_zip->version );
	}

	/**
	 * Get changelog content from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugin_infos table.
	 * @return string Changelog content.
	 */
	private function get_changelog_content( $info_contents ) {
		return $info_contents['changelog'] ?? '';
	}

	/**
	 * Get installation content from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugin_infos table.
	 * @return string Installation content.
	 */
	private function get_installation_content( $info_contents ) {
		return $info_contents['usage'] ?? '';
	}

	/**
	 * Get FAQ content from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugin_infos table.
	 * @return string FAQ content.
	 */
	private function get_faq_content( $info_contents ) {
		return $info_contents['faq'] ?? '';
	}

	/**
	 * Get screenshots content from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugin_infos table.
	 * @return array Screenshots array.
	 */
	private function get_screenshots_content( $info_contents ) {
		return $info_contents['screenshots'] ?? [];
	}

	/**
	 * Get versions list for the plugin.
	 *
	 * @since 0.0.1184
	 *
	 * @param Data $data Plugin data object.
	 * @return array Versions list in WordPress format (version => download_url).
	 */
	private function get_versions_list( $data ) {

		$zips = $data->get_zips();
		$slug = $data->get_plugins_row()->slug ?? '';

		if ( ! $zips || ! $slug )
			return [];

		$versions = [];

		foreach ( $zips as $zip )
			if ( $zip->version )
				$versions[ $zip->version ] = Files::get_plugin_zip_url_by_slug( $slug, $zip->version );

		// Sort versions in descending order
		uksort(
			$versions,
			fn( $a, $b ) => version_compare( $b, $a ),
		);

		return $versions;
	}

	/**
	 * Get sections array from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugin_infos table.
	 * @return array Sections array.
	 */
	private function get_sections_array( $info_contents ) {

		$sections = [];

		foreach ( [ 'details', 'usage', 'faq', 'changelog', 'api' ] as $key )
			if ( ! empty( $info_contents[ $key ] ) )
				$sections[ $key ] = $info_contents[ $key ];

		return $sections;
	}

	/**
	 * Get banners array from info row.
	 *
	 * @since 0.0.1184
	 *
	 * @param object $info_row Info row from troy_plugin_infos table.
	 * @return array Banners array.
	 */
	private function get_banners_array( $info_row ) {
		return $info_row?->banner_uri
			? [
				// WordPress expects (in order) 'high', 'low'.
				'low' => $info_row->banner_uri,
			]
			: [];
	}

	/**
	 * Get icons array from meta row.
	 *
	 * @since 0.0.1184
	 *
	 * @param object $meta_row Meta row from troy_plugin_metas table.
	 * @return array Icons array.
	 */
	private function get_icons_array( $meta_row ) {
		return $meta_row?->logo_uri
			? [
				// WordPress expects (in order) svg, 2x, 1x, and default.
				'1x' => $meta_row->logo_uri,
			]
			: [];
	}

	/**
	 * Record plugin info request statistics to the live view stats table.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id The plugin ID.
	 * @param string $version   The plugin version.
	 * @param string $locale    The requested locale.
	 * @param string $screen    The screen name.
	 */
	private function record_info_request_stats( $plugin_id, $version, $locale, $screen ) {

		global $wpdb;

		// Record live view stat
		$wpdb->insert(
			"{$wpdb->prefix}troy_plugin_stats_views_live",
			[
				'plugin_id'  => $plugin_id,
				'epoch'      => API\Utils::get_epoch(),
				'version'    => $version,
				'screen'     => $screen,
				'locale'     => $locale,
				'origin_url' => API\Server::get_repo_url(),
			],
			[
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			],
		);
	}
}
