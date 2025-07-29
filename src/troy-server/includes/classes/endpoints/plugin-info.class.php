<?php
/**
 * @package Troy\Server
 * @access  public
 */

namespace Troy\Server\Endpoints;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\{
	get_origin_url,
	get_plugin_id_by_slug,
	get_latest_public_wordpress_version,
};

use function Troy\Server\Sanitize\sanitize_slug;

use Troy\Server\Plugins\{
	Data,
	Files,
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
 * Class Troy\Server\Endpoints\Plugin_Info.
 *
 * Handles requests for plugin information used in the WordPress plugin thickbox.
 *
 * @since 0.0.1184
 */
final class Plugin_Info extends Base_Endpoint {

	/**
	 * Handle the plugin information request.
	 *
	 * @since 0.0.1184
	 */
	public function handle_request() {

		// Validate that this is a POST request
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			$this->send_error( 'Method not allowed', 405 );

		// phpcs:disable WordPress.Security.NonceVerification -- Public API endpoints don't use nonces
		if ( empty( $_POST['slug'] ) )
			$this->send_error( 'Missing required parameter: slug', 400 );

		$slug   = sanitize_slug( $_POST['slug'] );
		$fields = (array) ( $_POST['fields'] ?? [] );
		$locale = \sanitize_text_field( $_POST['locale'] ?? 'en_US' );

		// phpcs:enable WordPress.Security.NonceVerification

		// Additional validation for slug format
		if ( ! $slug )
			$this->send_error( 'Invalid slug', 400 );

		$plugin_id = get_plugin_id_by_slug( $slug );

		if ( ! $plugin_id )
			$this->send_error( 'Plugin not found', 404 );

		try {
			$data = new Data( $plugin_id );

			$plugin_row   = $data->get_plugins_row();
			$meta_row     = $data->get_metas_row();
			$info_row     = $data->get_infos_row();
			$latest_zip   = $data->get_zips_row();
			$translations = $data->get_translations();
			$data_cache   = $data->get_data_caches_row();
			$contributors = $data->get_contributors();

			if ( ! $plugin_row || ! $meta_row )
				$this->send_error( 'Plugin data not available', 404 );

			// Decode info contents once for performance
			$info_contents = $info_row->contents ? json_decode( $info_row->contents, true ) : [];

			// var_dump() We need to see how this works in practice, hence some stuff is commented out.
			$response = [
				'name'                     => $meta_row->name,
				'slug'                     => $plugin_row->slug,
				'version'                  => $latest_zip->version ?? '',
				'author'                   => $this->get_author_string( $meta_row->author_id ),
				// 'author_profile'           => \get_author_posts_url( $meta_row->author_id ),
				'contributors'             => $this->get_contributors_array( $contributors ),
				'requires'                 => $latest_zip->requires_wp ?? '',
				'tested'                   => $latest_zip->tested_wp ?? \Troy\Server\get_latest_public_wordpress_version( $latest_zip->requires_wp ),
				'requires_php'             => $latest_zip->requires_php ?? '',
				// 'compatibility'            => [], // What is this?
				'rating'                   => $this->calculate_rating_percentage( $data_cache ),
				'ratings'                  => $this->get_ratings_breakdown( $data_cache ),
				'num_ratings'              => (int) ( $data_cache->rating_count ?? 0 ),
				// 'support_threads'          => 0,
				// 'support_threads_resolved' => 0,
				'downloaded'               => (int) ( $data_cache->active_install_count ?? 0 ),
				'last_updated'             => $this->format_last_updated( $latest_zip->updated_at ?? '' ),
				// 'added'                    => $this->format_date_added( $plugin_row->created_at ?? '' ),
				'homepage'                 => $meta_row->permalink ?? '',
				// 'short_description'        => $meta_row->short_description ?? '',
				// 'description'              => $this->get_description_content( $info_contents ),
				'download_link'            => $this->get_download_link( $plugin_row->slug, $latest_zip ),
				// 'changelog'                => $this->get_changelog_content( $info_contents ),
				// 'installation'             => $this->get_installation_content( $info_contents ),
				// 'faq'                      => $this->get_faq_content( $info_contents ),
				// 'screenshots'              => $this->get_screenshots_content( $info_contents ),
				// 'tags'                     => [],
				// 'versions'                 => $this->get_versions_list( $data ),
				'sections'                 => $this->get_sections_array( $info_contents ),
				'donate_link'              => '',
				'banners'                  => $this->get_banners_array( $info_row ),
				// 'icons'                    => $this->get_icons_array( $meta_row ),
				// 'blocks'                   => [],
				// 'block_assets'             => [],
				// 'author_block_count'       => 0,
				// 'author_block_rating'      => 0,
				// 'blueprints'               => [],
				// 'preview_link'             => '',
			];

			// Filter response based on requested fields
			if ( ! empty( $fields ) )
				$response = $this->filter_response_by_fields( $response, $fields );

			// Record plugin info request stats
			$this->record_info_request_stats( $plugin_id, $locale );

			$this->send_json_response( $response );

		} catch ( \Exception $e ) {
			$this->send_error( 'Failed to get plugin information: ' . $e->getMessage(), 500 );
		}
	}

	/**
	 * Get author string for the plugin.
	 *
	 * @since 0.0.1184
	 *
	 * @param int $author_id Main author ID.
	 * @return string Author string.
	 */
	private function get_author_string( $author_id ) {

		if ( ! $author_id )
			return 'Unknown Author';

		$user = \get_user_by( 'id', $author_id );

		return $user ? $user->user_nicename : 'Unknown Author';
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
			'5' => 69, // var_dump() TODO remove me, testing
			'4' => 4,
			'3' => 3,
			'2' => 2,
			'1' => 42,
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
	 * @param array $info_contents Decoded contents from troy_plugins_infos table.
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
	 * @param object $latest_zip Latest zip row from troy_plugins_zips table.
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
	 * @param array $info_contents Decoded contents from troy_plugins_infos table.
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
	 * @param array $info_contents Decoded contents from troy_plugins_infos table.
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
	 * @param array $info_contents Decoded contents from troy_plugins_infos table.
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
	 * @param array $info_contents Decoded contents from troy_plugins_infos table.
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
		uksort( $versions, fn( $a, $b ) => version_compare( $b, $a ) );

		return $versions;
	}

	/**
	 * Get sections array from decoded info contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $info_contents Decoded contents from troy_plugins_infos table.
	 * @return array Sections array.
	 */
	private function get_sections_array( $info_contents ) {

		$sections = [];

		if ( ! empty( $info_contents['details'] ) )
			$sections['details'] = $info_contents['details'];

		if ( ! empty( $info_contents['usage'] ) )
			$sections['usage'] = $info_contents['usage'];

		if ( ! empty( $info_contents['faq'] ) )
			$sections['faq'] = $info_contents['faq'];

		if ( ! empty( $info_contents['changelog'] ) )
			$sections['changelog'] = $info_contents['changelog'];

		if ( ! empty( $info_contents['api'] ) )
			$sections['api'] = $info_contents['api'];

		return $sections;
	}

	/**
	 * Get banners array from info row.
	 *
	 * @since 0.0.1184
	 *
	 * @param object $info_row Info row from troy_plugins_infos table.
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
	 * @param object $meta_row Meta row from troy_plugins_metas table.
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
	 * Filter response based on requested fields.
	 *
	 * @since 0.0.1184
	 *
	 * @param array $response Response array.
	 * @param array $fields   Optional. Requested fields. If empty, all fields are returned.
	 * @return array Filtered response.
	 */
	private function filter_response_by_fields( $response, $fields = [] ) {

		// If fields is empty, return all
		if ( empty( $fields ) )
			return $response;

		// Handle specific field inclusions/exclusions
		$filtered = [];

		// Common fields that are always included
		$always_include = [ 'name', 'slug', 'version' ];

		foreach ( $always_include as $field )
			if ( isset( $response[ $field ] ) )
				$filtered[ $field ] = $response[ $field ];

		// Add requested fields
		foreach ( $fields as $field )
			if ( isset( $response[ $field ] ) )
				$filtered[ $field ] = $response[ $field ];

		return $filtered;
	}

	/**
	 * Record plugin info request statistics to the live view stats table.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param int    $plugin_id The plugin ID.
	 * @param string $locale    The requested locale.
	 */
	private function record_info_request_stats( $plugin_id, $locale ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		global $wpdb;

		$referer = $_SERVER['HTTP_REFERER'] ?? '';

		$type = 'direct_api';

		// Determine view type based on referer URL (20 chars max)
		if ( $referer ) {
			if ( str_contains( $referer, 'update-core.php' ) ) {
				$type = 'updates_page';
			} elseif ( str_contains( $referer, 'plugins.php' ) ) {
				$type = 'plugins_page';
			} elseif ( str_contains( $referer, 'plugin-install.php' ) ) {
				$type = 'search';
			} elseif ( str_contains( $referer, 'wp-admin' ) ) {
				$type = 'dashboard';
			} elseif ( str_contains( $referer, '.php' ) ) {
				$type = 'unknown_script';
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Public API endpoints don't use nonces
		// Check for plugin thickbox context
		if ( isset( $_POST['fields'] ) && \is_array( $_POST['fields'] ) ) {
			$fields = $_POST['fields'];
			// TODO validate this
			if ( \in_array( 'screenshots', $fields, true ) || \in_array( 'sections', $fields, true ) )
				$type = 'thickbox';
		}
		// phpcs:enable WordPress.Security.NonceVerification

		// Record live view stat
		$wpdb->insert(
			"{$wpdb->prefix}troy_plugins_view_stats_live",
			[
				'plugin_id'  => $plugin_id,
				'version'    => '',
				'type'       => $type,
				'origin_url' => get_origin_url(),
			],
			[
				'%d',
				'%s',
				'%s',
				'%s',
			],
		);
	}
}
