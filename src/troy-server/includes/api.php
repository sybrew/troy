<?php
/**
 * @package Troy\Server
 * @access  public
 * var_dump() This is a mostly temporary dump for functions that are used in multiple places.
 *            Let's try not to use this in production code, but make a proper API instead.
 */

namespace Troy\Server;

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
 * Returns the database version.
 *
 * @since 0.0.1184
 *
 * @return int The database version.
 */
function get_db_version() {
	return (int) \get_option( 'troy_server_db_version' );
}

/**
 * Returns the epoch for the UUID.
 *
 * @since 0.0.1184
 *
 * @param int $offset The offset of the epoch.
 * @return int The epoch.
 */
function get_epoch( $offset = 0 ) {
	// This is the timeout for the UUID: 4 weeks. int-casting floors.
	return (int) ( time() / 604_800 ) + $offset;
}

/**
 * Returns this server's origin URL.
 * Forcing HTTPS.
 *
 * @since 0.0.1184
 *
 * @return string The origin URL.
 */
function get_origin_url() {
	static $memo;
	return $memo ??= Sanitize\make_fully_qualified_repo_url(
		\home_url( '', 'https' ),
	);
}

/**
 * Returns the latest version from an array of versions, following the same priority logic.
 * Prioritizes 'tag' type versions, then 'beta', and finally 'unreleased'.
 *
 * @since 0.0.1184
 *
 * @param array $versions Array of version objects with 'version' and optional 'type' keys.
 * @return ?string The latest version string, or null if no versions found.
 */
function extract_latest_version( $versions ) {

	if ( empty( $versions ) )
		return null;

	$filtered_versions = array_column(
		array_filter(
			$versions,
			fn ( $version ) => 'tag' === ( $version['type'] ?? '' ),
		)
			?: array_filter(
				$versions,
				fn ( $version ) => 'beta' === ( $version['type'] ?? '' ),
			)
			?: array_filter(
				$versions,
				fn ( $version ) => 'unreleased' === ( $version['type'] ?? '' ),
			),
		'version',
	);

	if ( empty( $filtered_versions ) )
		return null;

	usort( $filtered_versions, 'version_compare' );

	return end( $filtered_versions );
}

// var_dump() move the plugin ID functions below to a dedicated class.

/**
 * Returns the plugin ID by its slug.
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @param string $slug The plugin slug.
 * @return ?int The plugin ID. Null if not found.
 */
function get_plugin_id_by_slug( $slug ) {
	global $wpdb;

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT `id` FROM {$wpdb->prefix}troy_plugins WHERE `slug` = %s",
		$slug,
	) ) ?: null;
}

/**
 * Returns the plugin ID by its post ID.
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @param int $post_id The plugin post ID.
 * @return ?int The plugin ID. Null if not found.
 */
function get_plugin_id_by_post_id( $post_id ) {
	global $wpdb;

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT `id` FROM {$wpdb->prefix}troy_plugins WHERE `post_id` = %d",
		$post_id,
	) ) ?: null;
}

/**
 * Returns the post ID by its plugin ID.
 *
 * @since 0.0.1184
 * @global \wpdb $wpdb
 *
 * @param int $plugin_id The plugin ID.
 * @return ?int The plugin ID. Null if not found.
 */
function get_post_id_by_plugin_id( $plugin_id ) {
	global $wpdb;

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT `post_id` FROM {$wpdb->prefix}troy_plugins WHERE `id` = %d",
		$plugin_id,
	) ) ?: null;
}

/**
 * Returns the plugin settings array.
 *
 * @since 0.0.1184
 *
 * @return array The plugin settings.
 */
function get_settings() {
	static $memo;
	return $memo ??= \get_option( 'troy_server_settings' );
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
function get_latest_public_wordpress_version( $from_version = '' ) {

	$cache = \get_option( 'troy_server_latest_public_wp_version_cache' ) ?: [];

	if ( time() < $cache['expire'] ?? 0 ) {
		$api_versions = $cache['versions'];
	} else {
		$expire = \HOUR_IN_SECONDS * 6;

		// Limit this to 100 versions to avoid performance issues.
		// At the moment of writing, this went back to 1617 days of releases.
		$body = \wp_remote_retrieve_body( \wp_safe_remote_get(
			'https://api.github.com/repos/WordPress/wordpress-develop/tags?per_page=100',
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

			// This is for https://api.github.com/repos/WordPress/wordpress-develop/tags
			$versions_array = array_map(
				fn ( $tag ) => $tag['name'] ?? '',
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
function get_version_type( $version ) {
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
function increase_time_limit_by( $seconds ) {

	static $total_seconds = 30;

	set_time_limit( $total_seconds += $seconds );
}

/**
 * Returns the all the available locales.
 *
 * This function returns an associative array of available locales,
 * where each key is the locale code and the value is an array
 * containing the English name and native name of the locale.
 *
 * @since 0.0.1184
 *
 * @return array {
 *     An associative array of available locales, keyed by locale code.
 *
 *     @type string $name   The English name of the locale.
 *     @type string $native The native name of the locale.
 * }
 */
function get_available_locales() {
	// This function sucks.
	// $locales = \get_available_languages();

	// Let's use something more comprehensive.
	return [
		'af'             => [
			'name'   => 'Afrikaans',
			'native' => 'Afrikaans',
		],
		'am'             => [
			'name'   => 'Amharic',
			'native' => 'አማርኛ',
		],
		'ar'             => [
			'name'   => 'Arabic',
			'native' => 'العربية',
		],
		'arg'            => [
			'name'   => 'Aragonese',
			'native' => 'Aragonés',
		],
		'art_xemoji'     => [
			'name'   => 'Emoji',
			'native' => '🌍🌎🌏 (Emoji)',
		],
		'art_xpirate'    => [
			'name'   => 'English (Pirate)',
			'native' => 'English (Pirate)',
		],
		'arq'            => [
			'name'   => 'Algerian Arabic',
			'native' => 'الدارجة الجزايرية',
		],
		'ary'            => [
			'name'   => 'Moroccan Arabic',
			'native' => 'العربية المغربية',
		],
		'as'             => [
			'name'   => 'Assamese',
			'native' => 'অসমীয়া',
		],
		'ast'            => [
			'name'   => 'Asturian',
			'native' => 'Asturianu',
		],
		'az'             => [
			'name'   => 'Azerbaijani',
			'native' => 'Azərbaycan dili',
		],
		'az_TR'          => [
			'name'   => 'Azerbaijani (Turkey)',
			'native' => 'Azərbaycan Türkcəsi',
		],
		'azb'            => [
			'name'   => 'South Azerbaijani',
			'native' => 'تۆرکجه‌ (آذربایجان تۆرکجه‌سی)',
		],
		'ba'             => [
			'name'   => 'Bashkir',
			'native' => 'башҡорт теле',
		],
		'bal'            => [
			'name'   => 'Catalan (Balear)',
			'native' => 'Català (Balear)',
		],
		'bcc'            => [
			'name'   => 'Balochi Southern',
			'native' => 'بلوچی مکرانی',
		],
		'bel'            => [
			'name'   => 'Belarusian',
			'native' => 'Беларуская мова',
		],
		'bg_BG'          => [
			'name'   => 'Bulgarian',
			'native' => 'Български',
		],
		'bgn'            => [
			'name'   => 'Western Balochi',
			'native' => 'بلۏچی',
		],
		'bho'            => [
			'name'   => 'Bhojpuri',
			'native' => 'भोजपुरी',
		],
		'bn_BD'          => [
			'name'   => 'Bengali (Bangladesh)',
			'native' => 'বাংলা',
		],
		'bn_IN'          => [
			'name'   => 'Bengali (India)',
			'native' => 'বাংলা (ভারত)',
		],
		'bo'             => [
			'name'   => 'Tibetan',
			'native' => 'བོད་ཡིག',
		],
		'bre'            => [
			'name'   => 'Breton',
			'native' => 'Brezhoneg',
		],
		'brx'            => [
			'name'   => 'Bodo',
			'native' => 'बरʼ',
		],
		'bs_BA'          => [
			'name'   => 'Bosnian',
			'native' => 'Bosanski',
		],
		'ca'             => [
			'name'   => 'Catalan',
			'native' => 'Català',
		],
		'ca_valencia'    => [
			'name'   => 'Catalan (Valencian)',
			'native' => 'Català (Valencià)',
		],
		'ceb'            => [
			'name'   => 'Cebuano',
			'native' => 'Cebuano',
		],
		'ckb'            => [
			'name'   => 'Kurdish (Sorani)',
			'native' => 'سۆرانی',
		],
		'co'             => [
			'name'   => 'Corsican',
			'native' => 'Corsu',
		],
		'cor'            => [
			'name'   => 'Cornish',
			'native' => 'Kernewek',
		],
		'cs_CZ'          => [
			'name'   => 'Czech',
			'native' => 'Čeština',
		],
		'cy'             => [
			'name'   => 'Welsh',
			'native' => 'Cymraeg',
		],
		'da_DK'          => [
			'name'   => 'Danish',
			'native' => 'Dansk',
		],
		'de_AT'          => [
			'name'   => 'German (Austria)',
			'native' => 'Deutsch (Österreich)',
		],
		'de_CH'          => [
			'name'   => 'German (Switzerland)',
			'native' => 'Deutsch (Schweiz)',
		],
		'de_CH_informal' => [
			'name'   => 'German (Switzerland, Informal)',
			'native' => 'Deutsch (Schweiz, Du)',
		],
		'de_DE'          => [
			'name'   => 'German',
			'native' => 'Deutsch',
		],
		'de_DE_formal'   => [
			'name'   => 'German (Formal)',
			'native' => 'Deutsch (Sie)',
		],
		'dsb'            => [
			'name'   => 'Lower Sorbian',
			'native' => 'Dolnoserbšćina',
		],
		'dv'             => [
			'name'   => 'Dhivehi',
			'native' => 'ދިވެހި',
		],
		'dzo'            => [
			'name'   => 'Dzongkha',
			'native' => 'རྫོང་ཁ',
		],
		'el'             => [
			'name'   => 'Greek',
			'native' => 'Ελληνικά',
		],
		'en_AU'          => [
			'name'   => 'English (Australia)',
			'native' => 'English (Australia)',
		],
		'en_CA'          => [
			'name'   => 'English (Canada)',
			'native' => 'English (Canada)',
		],
		'en_GB'          => [
			'name'   => 'English (UK)',
			'native' => 'English (UK)',
		],
		'en_NZ'          => [
			'name'   => 'English (New Zealand)',
			'native' => 'English (New Zealand)',
		],
		'en_US'          => [
			'name'   => 'English',
			'native' => 'English',
		],
		'en_ZA'          => [
			'name'   => 'English (South Africa)',
			'native' => 'English (South Africa)',
		],
		'eo'             => [
			'name'   => 'Esperanto',
			'native' => 'Esperanto',
		],
		'es_AR'          => [
			'name'   => 'Spanish (Argentina)',
			'native' => 'Español de Argentina',
		],
		'es_CL'          => [
			'name'   => 'Spanish (Chile)',
			'native' => 'Español de Chile',
		],
		'es_CO'          => [
			'name'   => 'Spanish (Colombia)',
			'native' => 'Español de Colombia',
		],
		'es_CR'          => [
			'name'   => 'Spanish (Costa Rica)',
			'native' => 'Español de Costa Rica',
		],
		'es_DO'          => [
			'name'   => 'Spanish (Dominican Republic)',
			'native' => 'Español de República Dominicana',
		],
		'es_EC'          => [
			'name'   => 'Spanish (Ecuador)',
			'native' => 'Español de Ecuador',
		],
		'es_ES'          => [
			'name'   => 'Spanish (Spain)',
			'native' => 'Español',
		],
		'es_GT'          => [
			'name'   => 'Spanish (Guatemala)',
			'native' => 'Español de Guatemala',
		],
		'es_HN'          => [
			'name'   => 'Spanish (Honduras)',
			'native' => 'Español de Honduras',
		],
		'es_MX'          => [
			'name'   => 'Spanish (Mexico)',
			'native' => 'Español de México',
		],
		'es_PE'          => [
			'name'   => 'Spanish (Peru)',
			'native' => 'Español de Perú',
		],
		'es_PR'          => [
			'name'   => 'Spanish (Puerto Rico)',
			'native' => 'Español de Puerto Rico',
		],
		'es_UY'          => [
			'name'   => 'Spanish (Uruguay)',
			'native' => 'Español de Uruguay',
		],
		'es_VE'          => [
			'name'   => 'Spanish (Venezuela)',
			'native' => 'Español de Venezuela',
		],
		'et'             => [
			'name'   => 'Estonian',
			'native' => 'Eesti',
		],
		'eu'             => [
			'name'   => 'Basque',
			'native' => 'Euskara',
		],
		'ewe'            => [
			'name'   => 'Ewe',
			'native' => 'Eʋegbe',
		],
		'fa_AF'          => [
			'name'   => 'Persian (Afghanistan)',
			'native' => '(فارسی (افغانستان',
		],
		'fa_IR'          => [
			'name'   => 'Persian',
			'native' => 'فارسی',
		],
		'fi'             => [
			'name'   => 'Finnish',
			'native' => 'Suomi',
		],
		'fo'             => [
			'name'   => 'Faroese',
			'native' => 'Føroyskt',
		],
		'fon'            => [
			'name'   => 'Fon',
			'native' => 'fɔ̀ngbè',
		],
		'fr_BE'          => [
			'name'   => 'French (Belgium)',
			'native' => 'Français de Belgique',
		],
		'fr_CA'          => [
			'name'   => 'French (Canada)',
			'native' => 'Français du Canada',
		],
		'fr_FR'          => [
			'name'   => 'French (France)',
			'native' => 'Français',
		],
		'frp'            => [
			'name'   => 'Arpitan',
			'native' => 'Arpitan',
		],
		'fuc'            => [
			'name'   => 'Fulah',
			'native' => 'Pulaar',
		],
		'fur'            => [
			'name'   => 'Friulian',
			'native' => 'Friulian',
		],
		'fy'             => [
			'name'   => 'Frisian',
			'native' => 'Frysk',
		],
		'ga'             => [
			'name'   => 'Irish',
			'native' => 'Gaelige',
		],
		'gax'            => [
			'name'   => 'Borana-Arsi-Guji Oromo',
			'native' => 'Afaan Oromoo',
		],
		'gd'             => [
			'name'   => 'Scottish Gaelic',
			'native' => 'Gàidhlig',
		],
		'gl_ES'          => [
			'name'   => 'Galician',
			'native' => 'Galego',
		],
		'gu'             => [
			'name'   => 'Gujarati',
			'native' => 'ગુજરાતી',
		],
		'hat'            => [
			'name'   => 'Haitian Creole',
			'native' => 'Kreyol ayisyen',
		],
		'hau'            => [
			'name'   => 'Hausa',
			'native' => 'Harshen Hausa',
		],
		'haw_US'         => [
			'name'   => 'Hawaiian',
			'native' => 'Ōlelo Hawaiʻi',
		],
		'haz'            => [
			'name'   => 'Hazaragi',
			'native' => 'هزاره گی',
		],
		'he_IL'          => [
			'name'   => 'Hebrew',
			'native' => 'עִבְרִית',
		],
		'hi_IN'          => [
			'name'   => 'Hindi',
			'native' => 'हिन्दी',
		],
		'hr'             => [
			'name'   => 'Croatian',
			'native' => 'Hrvatski',
		],
		'hsb'            => [
			'name'   => 'Upper Sorbian',
			'native' => 'Hornjoserbšćina',
		],
		'hu_HU'          => [
			'name'   => 'Hungarian',
			'native' => 'Magyar',
		],
		'hy'             => [
			'name'   => 'Armenian',
			'native' => 'Հայերեն',
		],
		'ibo'            => [
			'name'   => 'Igbo',
			'native' => 'Asụsụ Igbo',
		],
		'id_ID'          => [
			'name'   => 'Indonesian',
			'native' => 'Bahasa Indonesia',
		],
		'ido'            => [
			'name'   => 'Ido',
			'native' => 'Ido',
		],
		'is_IS'          => [
			'name'   => 'Icelandic',
			'native' => 'Íslenska',
		],
		'it_IT'          => [
			'name'   => 'Italian',
			'native' => 'Italiano',
		],
		'ja'             => [
			'name'   => 'Japanese',
			'native' => '日本語',
		],
		'jv_ID'          => [
			'name'   => 'Javanese',
			'native' => 'Basa Jawa',
		],
		'ka_GE'          => [
			'name'   => 'Georgian',
			'native' => 'ქართული',
		],
		'kaa'            => [
			'name'   => 'Karakalpak',
			'native' => 'Qaraqalpaq tili',
		],
		'kab'            => [
			'name'   => 'Kabyle',
			'native' => 'Taqbaylit',
		],
		'kal'            => [
			'name'   => 'Greenlandic',
			'native' => 'Kalaallisut',
		],
		'kin'            => [
			'name'   => 'Kinyarwanda',
			'native' => 'Ikinyarwanda',
		],
		'kir'            => [
			'name'   => 'Kyrgyz',
			'native' => 'Кыргызча',
		],
		'kk'             => [
			'name'   => 'Kazakh',
			'native' => 'Қазақ тілі',
		],
		'km'             => [
			'name'   => 'Khmer',
			'native' => 'ភាសាខ្មែរ',
		],
		'kmr'            => [
			'name'   => 'Kurdish (Kurmanji)',
			'native' => 'Kurdî',
		],
		'kn'             => [
			'name'   => 'Kannada',
			'native' => 'ಕನ್ನಡ',
		],
		'ko_KR'          => [
			'name'   => 'Korean',
			'native' => '한국어',
		],
		'lb_LU'          => [
			'name'   => 'Luxembourgish',
			'native' => 'Lëtzebuergesch',
		],
		'li'             => [
			'name'   => 'Limburgish',
			'native' => 'Limburgs',
		],
		'lij'            => [
			'name'   => 'Ligurian',
			'native' => 'Lìgure',
		],
		'lin'            => [
			'name'   => 'Lingala',
			'native' => 'Ngala',
		],
		'lmo'            => [
			'name'   => 'Lombard',
			'native' => 'Lombardo',
		],
		'lo'             => [
			'name'   => 'Lao',
			'native' => 'ພາສາລາວ',
		],
		'lt_LT'          => [
			'name'   => 'Lithuanian',
			'native' => 'Lietuvių kalba',
		],
		'lug'            => [
			'name'   => 'Luganda',
			'native' => 'Oluganda',
		],
		'lv'             => [
			'name'   => 'Latvian',
			'native' => 'Latviešu valoda',
		],
		'mai'            => [
			'name'   => 'Maithili',
			'native' => 'मैथिली',
		],
		'me_ME'          => [
			'name'   => 'Montenegrin',
			'native' => 'Crnogorski jezik',
		],
		'mfe'            => [
			'name'   => 'Mauritian Creole',
			'native' => 'Kreol Morisien',
		],
		'mg_MG'          => [
			'name'   => 'Malagasy',
			'native' => 'Malagasy',
		],
		'mk_MK'          => [
			'name'   => 'Macedonian',
			'native' => 'Македонски јазик',
		],
		'ml_IN'          => [
			'name'   => 'Malayalam',
			'native' => 'മലയാളം',
		],
		'mlt'            => [
			'name'   => 'Maltese',
			'native' => 'Malti',
		],
		'mn'             => [
			'name'   => 'Mongolian',
			'native' => 'Монгол',
		],
		'mr'             => [
			'name'   => 'Marathi',
			'native' => 'मराठी',
		],
		'mri'            => [
			'name'   => 'Maori',
			'native' => 'Te Reo Māori',
		],
		'ms_MY'          => [
			'name'   => 'Malay',
			'native' => 'Bahasa Melayu',
		],
		'my_MM'          => [
			'name'   => 'Myanmar (Burmese)',
			'native' => 'ဗမာစာ',
		],
		'nb_NO'          => [
			'name'   => 'Norwegian (Bokmål)',
			'native' => 'Norsk bokmål',
		],
		'ne_NP'          => [
			'name'   => 'Nepali',
			'native' => 'नेपाली',
		],
		'nl_BE'          => [
			'name'   => 'Dutch (Belgium)',
			'native' => 'Nederlands (België)',
		],
		'nl_NL'          => [
			'name'   => 'Dutch',
			'native' => 'Nederlands',
		],
		'nl_NL_formal'   => [
			'name'   => 'Dutch (Formal)',
			'native' => 'Nederlands (Formeel)',
		],
		'nn_NO'          => [
			'name'   => 'Norwegian (Nynorsk)',
			'native' => 'Norsk nynorsk',
		],
		'nqo'            => [
			'name'   => 'N’ko',
			'native' => 'ߒߞߏ',
		],
		'oci'            => [
			'name'   => 'Occitan',
			'native' => 'Occitan',
		],
		'ory'            => [
			'name'   => 'Oriya',
			'native' => 'ଓଡ଼ିଆ',
		],
		'os'             => [
			'name'   => 'Ossetic',
			'native' => 'Ирон',
		],
		'pa_IN'          => [
			'name'   => 'Panjabi (India)',
			'native' => 'ਪੰਜਾਬੀ',
		],
		'pa_PK'          => [
			'name'   => 'Punjabi (Pakistan)',
			'native' => 'پنجابی',
		],
		'pap_AW'         => [
			'name'   => 'Papiamento (Aruba)',
			'native' => 'Papiamento',
		],
		'pap_CW'         => [
			'name'   => 'Papiamento (Curaçao and Bonaire)',
			'native' => 'Papiamentu',
		],
		'pcd'            => [
			'name'   => 'Picard',
			'native' => 'Ch’ti',
		],
		'pcm'            => [
			'name'   => 'Nigerian Pidgin',
			'native' => 'Nigerian Pidgin',
		],
		'pl_PL'          => [
			'name'   => 'Polish',
			'native' => 'Polski',
		],
		'ps'             => [
			'name'   => 'Pashto',
			'native' => 'پښتو',
		],
		'pt_AO'          => [
			'name'   => 'Portuguese (Angola)',
			'native' => 'Português de Angola',
		],
		'pt_BR'          => [
			'name'   => 'Portuguese (Brazil)',
			'native' => 'Português do Brasil',
		],
		'pt_PT'          => [
			'name'   => 'Portuguese (Portugal)',
			'native' => 'Português',
		],
		'pt_PT_ao90'     => [
			'name'   => 'Portuguese (Portugal, AO90)',
			'native' => 'Português (AO90)',
		],
		'rhg'            => [
			'name'   => 'Rohingya',
			'native' => 'Ruáinga',
		],
		'ro_RO'          => [
			'name'   => 'Romanian',
			'native' => 'Română',
		],
		'roh'            => [
			'name'   => 'Romansh',
			'native' => 'Rumantsch',
		],
		'ru_RU'          => [
			'name'   => 'Russian',
			'native' => 'Русский',
		],
		'sa_IN'          => [
			'name'   => 'Sanskrit',
			'native' => 'भारतम्',
		],
		'sah'            => [
			'name'   => 'Sakha',
			'native' => 'Сахалыы',
		],
		'scn'            => [
			'name'   => 'Sicilian',
			'native' => 'Sicilianu',
		],
		'si_LK'          => [
			'name'   => 'Sinhala',
			'native' => 'සිංහල',
		],
		'sk_SK'          => [
			'name'   => 'Slovak',
			'native' => 'Slovenčina',
		],
		'skr'            => [
			'name'   => 'Saraiki',
			'native' => 'سرائیکی',
		],
		'sl_SI'          => [
			'name'   => 'Slovenian',
			'native' => 'Slovenščina',
		],
		'sna'            => [
			'name'   => 'Shona',
			'native' => 'ChiShona',
		],
		'snd'            => [
			'name'   => 'Sindhi',
			'native' => 'سنڌي',
		],
		'so_SO'          => [
			'name'   => 'Somali',
			'native' => 'Afsoomaali',
		],
		'sq'             => [
			'name'   => 'Albanian',
			'native' => 'Shqip',
		],
		'sq_XK'          => [
			'name'   => 'Shqip (Kosovo)',
			'native' => 'Për Kosovën Shqip',
		],
		'sr_RS'          => [
			'name'   => 'Serbian',
			'native' => 'Српски језик',
		],
		'sr_RS_latin'    => [
			'name'   => 'Serbian (Latin)',
			'native' => 'Srpski jezik',
		],
		'srd'            => [
			'name'   => 'Sardinian',
			'native' => 'Sardu',
		],
		'ssw'            => [
			'name'   => 'Swati',
			'native' => 'SiSwati',
		],
		'su_ID'          => [
			'name'   => 'Sundanese',
			'native' => 'Basa Sunda',
		],
		'sv_SE'          => [
			'name'   => 'Swedish',
			'native' => 'Svenska',
		],
		'sw'             => [
			'name'   => 'Swahili',
			'native' => 'Kiswahili',
		],
		'syr'            => [
			'name'   => 'Syriac',
			'native' => 'Syriac',
		],
		'szl'            => [
			'name'   => 'Silesian',
			'native' => 'Ślōnskŏ gŏdka',
		],
		'ta_IN'          => [
			'name'   => 'Tamil',
			'native' => 'தமிழ்',
		],
		'ta_LK'          => [
			'name'   => 'Tamil (Sri Lanka)',
			'native' => 'தமிழ்',
		],
		'tah'            => [
			'name'   => 'Tahitian',
			'native' => 'Reo Tahiti',
		],
		'te'             => [
			'name'   => 'Telugu',
			'native' => 'తెలుగు',
		],
		'tg'             => [
			'name'   => 'Tajik',
			'native' => 'Тоҷикӣ',
		],
		'th'             => [
			'name'   => 'Thai',
			'native' => 'ไทย',
		],
		'tir'            => [
			'name'   => 'Tigrinya',
			'native' => 'ትግርኛ',
		],
		'tl'             => [
			'name'   => 'Tagalog',
			'native' => 'Tagalog',
		],
		'tr_TR'          => [
			'name'   => 'Turkish',
			'native' => 'Türkçe',
		],
		'tt_RU'          => [
			'name'   => 'Tatar',
			'native' => 'Татар теле',
		],
		'tuk'            => [
			'name'   => 'Turkmen',
			'native' => 'Türkmençe',
		],
		'twd'            => [
			'name'   => 'Tweants',
			'native' => 'Twents',
		],
		'tzm'            => [
			'name'   => 'Tamazight (Central Atlas)',
			'native' => 'ⵜⴰⵎⴰⵣⵉⵖⵜ',
		],
		'ug_CN'          => [
			'name'   => 'Uighur',
			'native' => 'ئۇيغۇرچە',
		],
		'uk'             => [
			'name'   => 'Ukrainian',
			'native' => 'Українська',
		],
		'ur'             => [
			'name'   => 'Urdu',
			'native' => 'اردو',
		],
		'uz_UZ'          => [
			'name'   => 'Uzbek',
			'native' => 'O‘zbekcha',
		],
		'vec'            => [
			'name'   => 'Venetian',
			'native' => 'Vèneto',
		],
		'vi'             => [
			'name'   => 'Vietnamese',
			'native' => 'Tiếng Việt',
		],
		'wol'            => [
			'name'   => 'Wolof',
			'native' => 'Wolof',
		],
		'xho'            => [
			'name'   => 'Xhosa',
			'native' => 'isiXhosa',
		],
		'yor'            => [
			'name'   => 'Yoruba',
			'native' => 'Yorùbá',
		],
		'zgh'            => [
			'name'   => 'Tamazight',
			'native' => 'ⵜⴰⵎⴰⵣⵉⵖⵜ',
		],
		'zh_CN'          => [
			'name'   => 'Chinese (China)',
			'native' => '简体中文',
		],
		'zh_HK'          => [
			'name'   => 'Chinese (Hong Kong)',
			'native' => '香港中文',
		],
		'zh_SG'          => [
			'name'   => 'Chinese (Singapore)',
			'native' => '中文',
		],
		'zh_TW'          => [
			'name'   => 'Chinese (Taiwan)',
			'native' => '繁體中文',
		],
		'zul'            => [
			'name'   => 'Zulu',
			'native' => 'isiZulu',
		],
	];
}
