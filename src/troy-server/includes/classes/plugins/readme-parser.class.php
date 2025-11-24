<?php
/**
 * @package Troy\Server\Plugins
 * @access  private
 */

namespace Troy\Server\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use Troy\Server\{
	API,
	Markdown,
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

// phpcs:disable TSF.Performance.Functions -- We require slow file operations for processing ZIP files.

// TEMP: PHPCS bugged with PHP 8.4 assymetric visibility and property hooks.
// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable, Squiz.Commenting.VariableComment.Missing
// phpcs:disable PSR2.Classes.PropertyDeclaration.ScopeMissing, PSR2.Classes.PropertyDeclaration.Multiple
// phpcs:disable PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Found, Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Squiz.Commenting.VariableComment.WrongStyle

/**
 * Class Troy\Server\Plugins\Readme_Parser.
 *
 * This class parses the plugin readme.txt file and extracts the plugin information.
 * See `Troy\Server\Upgrade\get_initial_db_schema_queries()` for the plugins table.
 *
 * @since 0.0.1184
 */
final class Readme_Parser {

	/**
	 * @since 0.0.1184
	 * @var int MAX_NAME_LENGTH The maximum length of the plugin name in the readme.txt file.
	 *                          This is a hard limit to prevent bad names.
	 */
	private const MAX_NAME_LENGTH = 60;

	/**
	 * @since 0.0.1184
	 * @var int MAX_HEADER_LENGTH The maximum length of a header line in the readme.txt file.
	 *                            This is used to prevent parsing too many bogus lines.
	 */
	private const MAX_HEADER_LENGTH = 30;

	/**
	 * @since 0.0.1184
	 * @var array PARSED The parsed states of the readme parser, bitmask style.
	 */
	private const PARSED = [
		'lines'        => 0b1,
		'headers_raw'  => 0b10,
		'headers'      => 0b100,
		'contents_raw' => 0b1000,
		'contents'     => 0b10000,
	];

	/**
	 * @since 0.0.1184
	 * @var int $parsed The parsed state of the readme parser.
	 *                 This is a bitmap of the states defined in self::STATES.
	 */
	private $parsed = 0b0;

	/**
	 * @since 0.0.1184
	 * @var array README_NAMES The names of the readme file to look for in order of preference.
	 *                         This is used to find the readme file in the plugin directory.
	 */
	private const README_NAMES = [
		'readme.txt',
		'README.txt',
		'readme.md',
		'README.md',
	];

	/**
	 * @since 0.0.1184
	 * @var array ERRORS The enumerated error codes for the readme parser.
	 */
	public const ERRORS = [
		'not_found' => 1,
		'empty'     => 2,
		'untitled'  => 3,
	];

	/**
	 * @since 0.0.1184
	 * @var array SECTIONS The sections to parse, in the format:
	 *                     'section name' => 'parsed key'.
	 */
	private const SECTIONS = [
		// Troy Server new and preferred section names.
		'details'                    => 'details',
		'usage'                      => 'usage',
		'api'                        => 'api', // Brand new in Troy.

		// WordPress.org default section names.
		'faq'                        => 'faq',
		'changelog'                  => 'changelog',
		// 'screenshots'                => 'screenshots', // TODO implement this?

		// WordPress.org preferred section names, now fallback aliases.
		'description'                => 'details',
		'installation'               => 'usage',

		// Fallback aliases
		'change log'                 => 'changelog',
		'frequently asked questions' => 'faq',
		// 'screenshot'                 => 'screenshots', // TODO implement this?

		// WordPress.org recognized section names, not implemented.
		// 'other notes'                => 'other_notes',
		// 'upgrade notice'             => 'upgrade_notice', // Implemented on a per-version basis
	];

	/**
	 * @since 0.0.1184
	 * @var array HEADERS The headers to parse, in the format:
	 *                    'header name' => 'parsed key'.
	 */
	private const HEADERS = [
		// Troy Server new and preferred header names.
		// 'tested wp'         => 'tested_wp', // TODO reimplement?
		// 'requires wp'       => 'requires_wp', // TODO reimplement?
		// 'requires php'      => 'requires_php', // TODO reimplement?
		'support uri'       => 'support_uri',  // Brand new in Troy.
		'homepage_url'      => 'homepage_url', // Brand new in Troy.
		'locale'            => 'locale',       // Brand new in Troy.
		'short description' => 'short_description', // Brand new in Troy.

		// WordPress.org default header names, now fallback aliases.
		// 'tested'            => 'tested_wp', // TODO reimplement?
		// 'tested up to'      => 'tested_wp', // TODO reimplement?
		// 'requires'          => 'requires_wp', // TODO reimplement?
		// 'requires at least' => 'requires_wp', // TODO reimplement?
		'donate link'       => 'donate_uri',

		// WordPress.org recognized header names, not implemented.
		// 'contributors'      => 'contributors',
		// 'tags'              => 'tags',
		// 'donate link'       => 'donate_link',
		// 'stable tag'        => 'stable_tag',
		// 'license'           => 'license',
		// 'license uri'       => 'license_uri',
	];

	/**
	 * @since 0.0.1184
	 * @var string $raw_readme_contents The raw contents of the readme.txt file.
	 */
	private $raw_readme_contents;

	/**
	 * Sets up the readme parser.
	 *
	 * Note that we cannot accept a a plugin ID or version here, because we need
	 * to extract the readme.txt file before we can store a valid plugin version.
	 *
	 * Use `File_Utils::get_plugin_zip_file_path_latest()` or `File_Utils::get_plugin_zip_file_path()` to
	 * get the plugin ZIP file path, and then pass that directory to this constructor.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $plugin_dir The directory where the readme may be extracted.
	 *                           Make sure it's not the parent directory of the plugin.
	 * @throws \Exception If the readme file is not found or empty.
	 */
	public function __construct( $plugin_dir ) {

		$subdir = glob( "{$plugin_dir}*", GLOB_ONLYDIR )[0] ?? null;

		if ( ! $subdir )
			throw new \Exception( 'Plugin directory not found.', static::ERRORS['not_found'] );

		$subdir = "$subdir/";

		foreach ( static::README_NAMES as $file ) {
			if ( file_exists( "{$subdir}$file" ) ) {
				$readme_file = "{$subdir}$file";
				break;
			}
		}

		if ( empty( $readme_file ) )
			throw new \Exception( 'Readme file not found.', static::ERRORS['not_found'] );

		$this->raw_readme_contents = file_get_contents( $readme_file );

		if ( empty( $this->raw_readme_contents ) )
			throw new \Exception( 'Readme file is empty.', static::ERRORS['empty'] );
	}

	/**
	 * @since 0.0.1184
	 *
	 * Since WordPress.org also expects the readme.txt file to be UTF-8 encoded,
	 * we can safely assume that the readme.txt file must be UTF-8 encoded for
	 * WordPress Core.
	 *
	 * @var array $lines The parsed lines of the readme.txt file.
	 *                   This is the raw version of the parsed content.
	 * @return string[] The lines of the readme.txt file, normalized and UTF-8 encoded.
	 */
	private array $lines = [] {
		get {
			if ( ! ( $this->parsed & static::PARSED['lines'] ) ) {
				$contents = $this->raw_readme_contents;

				$boms = [
					"\xEF\xBB\xBF"     => 'UTF-8', // Most common first, no collision with UTF-16/32
					"\xFF\xFE\x00\x00" => 'UTF-32LE',
					"\x00\x00\xFE\xFF" => 'UTF-32BE',
					"\xFF\xFE"         => 'UTF-16LE',
					"\xFE\xFF"         => 'UTF-16BE',
				];

				// Expected encoding when BOM is not present.
				$encoding = 'UTF-8';

				foreach ( $boms as $bom => $enc ) {
					if ( str_starts_with( $contents, $bom ) ) {
						$contents = ltrim( $contents, $bom );
						$encoding = $enc;
						break;
					}
				}

				// Convert to UTF-8 and normalize line endings
				$this->lines = explode(
					"\n",
					str_replace(
						[ "\r\n", "\r" ],
						"\n",
						// Convert to UTF-8 and remove invalid UTF-8 characters
						mb_convert_encoding( $contents, 'UTF-8', $encoding ),
					),
				);

				$this->parsed |= static::PARSED['lines'];
			}

			return $this->lines;
		}
	}

	/**
	 * @since 0.0.1184
	 * @see $this->headers For the sanitized version.
	 *
	 * @var array $headers_raw The parsed plugin readme.txt headers.
	 * @return array {
	 *     The raw headers.
	 *
	 *     @type string $plugin_name       The name of the plugin.
	 *     @type string $tested_wp         The WordPress version the plugin is tested up to.
	 *     @type string $tested_php        The PHP version the plugin is tested up to.
	 *     @type string $requires_wp       The minimum WordPress version required by the plugin.
	 *     @type string $requires_php      The minimum PHP version required by the plugin.
	 *     @type string $license           The license URI of the plugin.
	 *     @type string $license_uri       The license URI of the plugin.
	 *     @type string $homepage_url      The homepage URL of the plugin.
	 *     @type string $locale            The locale of the plugin.
	 *     @type string $support_uri       The support URI of the plugin.
	 *     @type string $donate_uri        The donate URI of the plugin.
	 *     @type string $short_description The short description of the plugin.
	 * }
	 * @throws \Exception If the readme file is invalid or has too many bogus lines.
	 */
	public private(set) array $headers_raw = [
		'plugin_name'       => '',
		// 'tested_wp'    => '', // TODO reimplement?
		// 'tested_php'   => '', // TODO reimplement?
		// 'requires_wp'  => '', // TODO reimplement?
		// 'requires_php' => '', // TODO reimplement?
		// 'license'      => '', // TODO reimplement?
		// 'license_uri'  => '', // TODO reimplement?
		'homepage_url'      => '',
		'locale'            => '',
		'support_uri'       => '',
		'donate_uri'        => '',
		'short_description' => '',
	] {
		get {
			if ( ! ( $this->parsed & static::PARSED['headers_raw'] ) ) {
				// Find name first; it should be the first non-empty line.
				foreach ( $this->lines as $i => $line ) {
					$candidate = trim( $line, " \t#=" );

					// The candidate might be a header, like "Plugin Name: My Plugin".
					// We want to extract the plugin name from it.
					// If it's another header, we skip it.
					if ( str_contains( $candidate, ':' ) ) {
						[ $field, $value ] = explode( ':', $candidate, 2 );
						$field             = strtolower( trim( $field ) );

						// If the field is a valid header, skip and try next line.
						// We allow "plugin name" as a special case below.
						if ( isset( static::HEADERS[ $field ] ) )
							continue;

						// If the field is "plugin name", we take the value as the candidate.
						// Otherwise, we keep the candidate as-is, including the : colon.
						if ( 'plugin name' === $field )
							$candidate = trim( $value );
					}

					if ( empty( $candidate ) ) {
						// Let's not attempt to parse bogus readme.txt files. Bail.
						if ( $i > static::MAX_HEADER_LENGTH )
							throw new \Exception( 'Readme file is invalid.', static::ERRORS['untitled'] );

						continue;
					}

					// Let's discard it if it's too long. Enforce good and consistent practice.
					if ( mb_strlen( $candidate ) > static::MAX_NAME_LENGTH )
						break;

					$this->headers_raw['plugin_name'] = $candidate;
					break;
				}

				// Reset lines to start after the plugin name.
				$this->lines = \array_slice( $this->lines, $i + 1 );

				// Find and parse headers
				foreach ( $this->lines as $i => $line ) {
					$raw = trim( $line );

					// The header block ends with a blank line
					// The section headers start with ## or ==, so we can stop at the first one.
					if ( '' === $raw || preg_match( '/^(?:##+|==+)\s/', $raw ) )
						break;

					// Skip lines that don't look like headers. "Header: value"
					if ( ! str_contains( $raw, ':' ) )
						continue;

					[ $field, $value ] = explode( ':', $raw, 2 );

					// Trim extraneous whitespace and accidental new header line characters.
					$h_key = static::HEADERS[ strtolower( trim( $field, " \t*-" ) ) ] ?? null;

					// If valid header, store it in headers_raw. Discard otherwise.
					if ( isset( $h_key ) && \array_key_exists( $h_key, $this->headers_raw ) )
						$this->headers_raw[ $h_key ] = trim( $value );

					// Let's not attempt to parse bogus readme.txt headers.
					// Bail processing without throwing, we're working now with what we have.
					if ( $i > static::MAX_HEADER_LENGTH ) {
						// Move to the last header line quickly here without processing.
						foreach ( $this->lines as $line ) {
							++$i;

							$raw = trim( $line );

							if ( '' === $raw || preg_match( '/^(?:##+|==+)\s/', $raw ) )
								break;
						}
						break;
					}
				}

				// Reset lines to start after the plugin headers. Store these.
				$this->lines = \array_slice( $this->lines, $i + 1 );

				// Parse the first text line before sections as short description.
				// If we already have a short description header, we still need to consume this line
				// to prevent it from being included in the details section.
				foreach ( $this->lines as $j => $line ) {
					$raw = trim( $line );

					// Skip empty lines.
					if ( '' === $raw )
						continue;

					// The section headers start with ## or ==, so we can stop at the first one.
					if ( preg_match( '/^(?:##+|==+)\s/', $raw ) )
						break;

					// If we don't have a short description header yet, use this line.
					// Otherwise, discard it.
					if ( empty( $this->headers_raw['short_description'] ) ) {
						// We'll only allow a single line as the short description.
						$this->headers_raw['short_description'] = $raw;
					}

					$this->lines = \array_slice( $this->lines, $j + 1 );
					break;
				}

				$this->parsed |= static::PARSED['headers_raw'];
			}

			return $this->headers_raw;
		}
	}

	/**
	 * @since 0.0.1184
	 * @see $this->headers_raw For the defaults.
	 *
	 * @var array $headers The parsed plugin readme.txt headers.
	 *                     This is the sanitized version of the parsed content.
	 * @return array {
	 *     The sanitized headers.
	 *
	 *     @type string $plugin_name       The name of the plugin.
	 *     @type string $tested_wp         The WordPress version the plugin is tested up to.
	 *     @type string $tested_php        The PHP version the plugin is tested up to.
	 *     @type string $requires_wp       The minimum WordPress version required by the plugin.
	 *     @type string $requires_php      The minimum PHP version required by the plugin.
	 *     @type string $license           The license URI of the plugin.
	 *     @type string $license_uri       The license URI of the plugin.
	 *     @type string $homepage_url      The homepage URL of the plugin.
	 *     @type string $locale            The locale of the plugin.
	 *     @type string $support_uri       The support URI of the plugin.
	 *     @type string $donate_uri        The donate URI of the plugin.
	 *     @type string $short_description The short description of the plugin.
	 * }
	 * @throws \Exception (via headers_raw::get) If the readme file is invalid or has too many bogus lines.
	 */
	public private(set) array $headers = [] {
		get {
			if ( ! ( $this->parsed & static::PARSED['headers'] ) ) {
				$this->headers['plugin_name']       = \sanitize_text_field( trim( $this->headers_raw['plugin_name'] ) );
				// $this->headers['tested_wp']      = API\Sanitize::tested_version( $this->headers_raw['tested_wp'] );
				// $this->headers['tested_php']     = API\Sanitize::tested_version( $this->headers_raw['tested_php'] );
				// $this->headers['requires_wp']    = API\Sanitize::tested_version( $this->headers_raw['requires_wp'] );
				// $this->headers['requires_php']   = API\Sanitize::tested_version( $this->headers_raw['requires_php'] );
				// $this->headers['license']        = \sanitize_text_field( $this->headers_raw['license_uri'] );
				// $this->headers['license_uri']    = \sanitize_url( $this->headers_raw['license_uri'] );
				$this->headers['homepage_url']      = \sanitize_url( $this->headers_raw['homepage_url'] );
				$this->headers['locale']            = API\Sanitize::wp_locale( $this->headers_raw['locale'] );
				$this->headers['support_uri']       = \sanitize_url( $this->headers_raw['support_uri'] );
				$this->headers['donate_uri']        = \sanitize_url( $this->headers_raw['donate_uri'] );
				$this->headers['short_description'] = \sanitize_text_field( trim( $this->headers_raw['short_description'] ) );

				$this->parsed |= static::PARSED['headers'];
			}

			return $this->headers;
		}
	}

	/**
	 * @since 0.0.1184
	 * @see $this->contents For the sanitized version.
	 *
	 * @var array $contents_raw The parsed plugin readme.txt contents.
	 *                          This is the raw version of the parsed content.
	 * @return array {
	 *     The raw contents.
	 *
	 *     @type string $details     The details of the plugin.
	 *     @type string $usage       The usage instructions for the plugin.
	 *     @type string $faq         The frequently asked questions for the plugin.
	 *     @type string $api         The API documentation for the plugin.
	 *     @type string $changelog   The changelog of the plugin.
	 *     @type string $screenshots The screenshots of the plugin.
	 * }
	 * @throws \Exception (via headers_raw::get) If the readme file is invalid or has too many bogus lines.
	 */
	public private(set) array $contents_raw = [
		'details'     => '',
		'usage'       => '',
		'faq'         => '',
		'api'         => '',
		'changelog'   => '',
		'screenshots' => '',
	] {
		get {
			if ( ! ( $this->parsed & static::PARSED['contents_raw'] ) ) {
				// We need to skip to the content sections. To do that, we first parse the raw headers.
				$this->headers_raw;

				// Let's put back the lines and run through the sections in one go.
				// Match 1 is == or ##, match 2 is the title, match 3 is the content. We named the matches for clarity.
				preg_match_all(
					'/^(==|##)\s+(?<title>.+?)(?:\s+\1\s*)?\s*\n(?<content>.+?)(?=^(?:==|##)\s|\z)/ms',
					implode( "\n", $this->lines ),
					$matches,
					PREG_SET_ORDER,
				);

				// Parse the sections and store them in contents_raw.
				foreach ( $matches as $match ) {
					// Trim extraneous whitespace and accidental header characters.
					$s_key = static::SECTIONS[ strtolower( trim( $match['title'], ' =#' ) ) ] ?? null;

					// Check against default sections, we do not want to accidentally insert rogue sections.
					if ( \array_key_exists( $s_key, $this->contents_raw ) )
						$this->contents_raw[ $s_key ] = trim( $match['content'], " \r\n\v\t" );
				}

				// If there's no short description header, attempt to extract it from the details.
				if ( $this->contents_raw['details'] && empty( $this->headers_raw['short_description'] ) ) {
					foreach ( explode( "\n", $this->contents_raw['details'] ) as $line ) {
						$line = trim( $line );
						if ( $line ) {
							$this->headers_raw['short_description'] = trim( $line, " \r\n\v\t" );
							break;
						}
					}
				}

				// We're done with the lines now, so we can clear them.
				$this->lines = [];

				$this->parsed |= static::PARSED['contents_raw'];
			}

			return $this->contents_raw;
		}
	}

	/**
	 * @since 0.0.1184
	 * @see $this->contents_raw For the defaults.
	 *
	 * @var array $contents The parsed plugin readme.txt contents.
	 *                      This is the sanitized version of the parsed content.
	 * @return array {
	 *     The sanitized contents.
	 *
	 *     @type string $details     The details of the plugin.
	 *     @type string $usage       The usage instructions for the plugin.
	 *     @type string $faq         The frequently asked questions for the plugin.
	 *     @type string $api         The API documentation for the plugin.
	 *     @type string $changelog   The changelog of the plugin.
	 *     @type string $screenshots The screenshots of the plugin.
	 * }
	 * @throws \Exception (via headers_raw::get) If the readme file is invalid or has too many bogus lines.
	 */
	public private(set) array $contents = [] {
		get {
			if ( ! ( $this->parsed & static::PARSED['contents'] ) ) {
				// Convert the contents_raw to sanitized HTML.
				foreach ( $this->contents_raw as $key => $content ) { // No ref: we cannot assume others don't mangle it.
					$this->contents[ $key ] = $content
						? trim(
							\wp_kses_post( Markdown::process(
								$content,
								'common',
								[
									'html_input'         => 'allow',
									'allow_unsafe_links' => true,
									'convert_wordpress'  => true,
								],
							) ),
							" \r\n\v\t",
						)
						: '';
				}

				$this->parsed |= static::PARSED['contents'];
			}

			return $this->contents;
		}
	}
}
