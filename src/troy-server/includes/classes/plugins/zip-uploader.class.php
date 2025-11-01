<?php
/**
 * @package Troy\Server\Plugins
 * @access  public
 */

namespace Troy\Server\Plugins;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\TROY_PLUGIN_HEADERS;

use function Troy\Server\{
	get_origin_url,
	get_latest_public_wordpress_version,
	increase_time_limit_by,
};

use function Troy\Server\Sanitize\{
	sanitize_tested_version,
	sanitize_semver,
	sanitize_slug,
	make_fully_qualified_repo_url,
};

use Troy\Server\{
	File_Utils,
	Zip_Extractor,
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

/**
 * Class Troy\Server\Plugins\Zip_Uploader.
 *
 * Handles plugin ZIP file uploading.
 *
 * @since 0.0.1184
 */
final class Zip_Uploader {

	/**
	 * @since 0.0.1184
	 * @var int ZIP_DOWNLOAD_TIMEOUT Timeout for downloading ZIP files.
	 *                               It takes 5 seconds to download 30MB at 50Mbps.
	 *                               We double that to be safe.
	 */
	private const ZIP_DOWNLOAD_TIMEOUT = 10;

	/**
	 * @since 0.0.1184
	 * @var int MAX_ZIP_DOWNLOAD_SIZE Maximum file size for ZIP file downloads (30MB).
	 */
	private const MAX_ZIP_DOWNLOAD_SIZE = 30 * \MB_IN_BYTES;

	/**
	 * @since 0.0.1184
	 * @var int MAX_DOWNLOAD_RETRIES Maximum number of download retry attempts.
	 */
	private const MAX_DOWNLOAD_RETRIES = 3;

	/**
	 * @since 0.0.1184
	 * @var int ZIP_PROCESS_TIMEOUT Timeout for processing ZIP files.
	 *                              We benchmarked 30k files, 1kb each, this is a worst-case.
	 *                              Creation took 2.1 seconds on Mac Mini (M4, internal SSD).
	 *                              Gemini assumes, based on that, 45s on SATA hosting.
	 */
	private const ZIP_PROCESS_TIMEOUT = 45;

	/**
	 * @since 0.0.1184
	 * @var bool $wpfs_initialized Whether the WordPress Filesystem has been initialized.
	 */
	private static $wpfs_initialized = false;

	/**
	 * @since 0.0.1184
	 * @var bool $lock Whether the ZIP handler is locked.
	 *                 This prevents processing multiple files in the same instance.
	 */
	private $lock = false;

	/**
	 * @since 0.0.1184
	 * @var ?string $origin_url The plugin's Origin URL.
	 *                          This is set during class construction.
	 */
	public readonly string $origin_url;

	/**
	 * @since 0.0.1184
	 * @var ?string $version_uploaded The version of the plugin that was uploaded.
	 *                                This is set after processing the ZIP file.
	 */
	public readonly string $version_uploaded;

	/**
	 * @since 0.0.1184
	 * @var bool $zip_existed Whether the ZIP file already existed in the database.
	 *                        This is set after processing the ZIP file.
	 */
	public readonly bool $zip_existed;

	/**
	 * Sets up the ZIP handler data to work with.
	 *
	 * @since 0.0.1184
	 *
	 * @param int    $plugin_id  The plugin's ID to process.
	 * @param string $origin_url Optional. The plugin's Origin URL.
	 *                           If left empty, this server's Origin URL will be used.
	 * @throws \Exception Via `File_Utils::init_wpfs()` if the WordPress Filesystem can't be
	 *                    initialized or if we cannot create plugin storage directories.
	 */
	public function __construct(
		public readonly int $plugin_id,
		$origin_url = null,
	) {
		ignore_user_abort( true );

		increase_time_limit_by( static::ZIP_DOWNLOAD_TIMEOUT + static::ZIP_PROCESS_TIMEOUT );

		File_Utils::init_wpfs();

		$this->origin_url = $origin_url ?? get_origin_url();

		// Make plugin storage directories if they do not exist.
		// We want a shield in every plugin storage directory, hence the double call.
		File_Utils::make_shielded_dir( Files::get_plugin_storage_dir_path() );
		File_Utils::make_shielded_dir( Files::get_plugin_storage_dir_path( $this->plugin_id ) );
	}

	/**
	 * Gets plugin data with Troy headers included.
	 *
	 * Temporarily adds Troy headers to WordPress's extra_plugin_headers filter
	 * to ensure Troy-specific headers are parsed by get_plugin_data().
	 *
	 * @since 0.0.1184
	 *
	 * @param string $plugin_file Path to the plugin file.
	 * @return array Plugin data with Troy headers included.
	 */
	private function get_plugin_data_with_troy_headers( $plugin_file ) {

		$filter_callback = fn( $headers ) => array_merge(
			$headers,
			TROY_PLUGIN_HEADERS['repo'],
			TROY_PLUGIN_HEADERS['dependencies'],
			TROY_PLUGIN_HEADERS['tested_wp'],
		);

		\add_filter( 'extra_plugin_headers', $filter_callback );

		$plugin_data = \get_plugin_data( $plugin_file, false, false );

		\remove_filter( 'extra_plugin_headers', $filter_callback );

		return $plugin_data;
	}

	/**
	 * Processes a plugin ZIP file from a file upload.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $temp_zip_file_path Path to the temporary ZIP file.
	 * @throws \Exception If the ZIP file is invalid or processing fails.
	 */
	public function process_via_file( $temp_zip_file_path ) {

		if ( ! is_readable( $temp_zip_file_path ) || ! is_file( $temp_zip_file_path ) )
			throw new \Exception( 'Invalid ZIP file provided.' );

		$this->process( $temp_zip_file_path );
	}

	/**
	 * Processes a plugin ZIP file from a URL.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $download_url The URL to download the ZIP file from.
	 * @param array  $args         Optional. {
	 *        Arguments for the download request.
	 *
	 *        @type array $headers     Custom HTTP headers.
	 *        @type array $queryParams Query parameters to append to the URL.
	 *    }
	 * @throws \Exception If the URL is invalid, download fails, or processing fails.
	 */
	public function process_via_url( $download_url, $args = [] ) {

		// Force HTTPS for now. Maybe we can add other protocols later.
		$download_url = \sanitize_url( $download_url, [ 'https' ] );

		if ( ! filter_var( $download_url, \FILTER_VALIDATE_URL ) )
			throw new \Exception( 'Invalid URL provided.' );

		// Download the ZIP file using our custom method that supports auth headers.
		$temp_zip_file_path = static::download_url( $download_url, $args );

		if ( \is_wp_error( $temp_zip_file_path ) ) {
			throw new \Exception(
				\sprintf(
					/* translators: %s: Error message */
					\__( 'Failed to download ZIP file with message: %s', 'troy-server' ),
					$temp_zip_file_path->get_error_message(),
				),
			);
		}

		if ( filesize( $temp_zip_file_path ) > static::MAX_ZIP_DOWNLOAD_SIZE ) {
			throw new \Exception( \sprintf(
				/* translators: %d: Maximum file size in MB */
				\__( 'The ZIP file exceeds the maximum allowed size of %dMB.', 'troy-server' ),
				static::MAX_ZIP_DOWNLOAD_SIZE / \MB_IN_BYTES,
			) );
		}

		// Ensure temporary file is cleaned up after processing.
		// phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- WP functions are irrelevant here.
		register_shutdown_function( static fn() => @unlink( $temp_zip_file_path ) );

		$this->process( $temp_zip_file_path );
	}

	/**
	 * Processes a plugin ZIP file from a URL.
	 *
	 * The uploaded ZIP file should resemble a standard WordPress plugin structure.
	 * This means that the main plugin file is usually located in the first subdirectory
	 * of the extracted ZIP file, and its name typically matches the plugin slug.
	 *
	 * The slug will be overridden by the plugin's slug settings, however.
	 *
	 * @since 0.0.1184
	 * @global \wpdb $wpdb
	 *
	 * @param string $temp_zip_file_path The path to the temporary ZIP file.
	 * @throws \Exception If the ZIP file is invalid, processing fails, or if no valid plugin file is found.
	 */
	private function process( $temp_zip_file_path ) {
		// phpcs:disable Generic.WhiteSpace.ScopeIndent -- no love for goto.

		if ( $this->lock )
			throw new \Exception( 'Do not process two files in the same ZIP Uploader instance.' );

		$this->lock = true;

		extract_zip_file: try {
			$temp_zip_extraction_dir = new Zip_Extractor( $temp_zip_file_path )->temp_zip_extraction_dir;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to extract ZIP file: ' . $e->getMessage() );
		}

		find_temp_zip_main_plugin_file: {
			// Extract the first subdirectory name, this is likely the plugin folder.
			$subdirs            = glob( "{$temp_zip_extraction_dir}*", GLOB_ONLYDIR );
			$plugin_subdir_name = $subdirs ? basename( $subdirs[0] ) : '';

			$temp_plugin_path = \trailingslashit(
				"{$temp_zip_extraction_dir}{$plugin_subdir_name}"
			);

			if ( $plugin_subdir_name ) {
				// The main plugin file is usually named after the folder. Let's try that first.
				$likely_file = "{$temp_plugin_path}{$plugin_subdir_name}.php";

				if ( file_exists( $likely_file ) )
					$temp_plugin_file_path = $likely_file;
			}

			if ( empty( $temp_plugin_file_path ) ) {
				// If not found, look for any PHP file with plugin headers inside that folder only.
				foreach ( new \DirectoryIterator( $temp_plugin_path ) as $file ) {
					if ( $file->isFile() && 'php' === $file->getExtension() ) {
						$pathname = $file->getPathname();

						if ( ! empty( \get_plugin_data( $pathname, false, false )['Name'] ) ) {
							$temp_plugin_file_path = $pathname;
							break;
						}
					}
				}
			}

			if ( empty( $temp_plugin_file_path ) )
				throw new \Exception( 'No valid plugin file found in ZIP.' );
		}

		process_plugin_headers: {
			// Grab headers early; we need this to determine the plugin version.
			$plugin_headers = $this->get_plugin_data_with_troy_headers( $temp_plugin_file_path );

			if ( empty( $plugin_headers['Name'] ) )
				throw new \Exception( 'Failed to parse plugin headers.' );

			$version = sanitize_semver( $plugin_headers['Version'] ?? '' );

			if ( ! $version )
				throw new \Exception( 'No valid version found in plugin headers.' );

			foreach ( TROY_PLUGIN_HEADERS['tested_wp'] as $header ) {
				if ( ! empty( $plugin_headers[ $header ] ) ) {
					$tested_wp = sanitize_tested_version( $plugin_headers[ $header ] );
					break; // The first supported tested WP header found is used
				}
			}

			// This shouldn't exceed 20 chars, but we don't expect mistakes here.
			// User will get a generic error. No need to throw an exception.
			$tested_wp = trim( $tested_wp ?? '' );

			// Extract Troy-specific headers using same logic as Troy Client API
			$repo = $dependencies = null;

			foreach ( TROY_PLUGIN_HEADERS['repo'] as $header ) {
				if ( ! empty( $plugin_headers[ $header ] ) ) {
					$repo = $plugin_headers[ $header ];
					break; // The first supported repo header found is used
				}
			}

			$repo = trim( $repo ?? '' );

			// We won't store the sanitized version because this will need to be revalidated later.
			if ( ! $repo || ! make_fully_qualified_repo_url( $repo ) )
				throw new \Exception( 'The main plugin file does not have a valid repo header.' );

			if ( \strlen( $repo ) > 191 )
				throw new \Exception( 'Repo header cannot exceed 191 characters.' );

			foreach ( TROY_PLUGIN_HEADERS['dependencies'] as $header ) {
				if ( ! empty( $plugin_headers[ $header ] ) ) {
					$dependencies = $plugin_headers[ $header ];
					break; // The first supported dependency header found is used
				}
			}

			$dependencies = trim( $dependencies ?? '' );
			if ( \strlen( $dependencies ) > 191 )
				throw new \Exception( 'Repo Dependencies header cannot exceed 191 characters.' );

			// Validate dependencies format and count (max 5)
			if ( ! empty( $dependencies ) ) {
				$dependency_list = \array_slice( explode( ',', $dependencies ), 0, 5 ); // Limit to 5 dependencies per plugin.

				if ( \count( $dependency_list ) > 5 )
					throw new \Exception( 'Repo Dependencies header cannot exceed 5 dependencies.' );

				// Validate each dependency format; this isn't stored.
				foreach ( $dependency_list as $dependency ) {
					$dependency = trim( $dependency );

					if ( empty( $dependency ) )
						throw new \Exception( 'Repo Dependencies header cannot contain empty dependency entries.' );

					// Parse dependency: "plugin-slug" or "plugin-slug <repo-uri>"
					[ $dep_slug, $dep_repo ] = array_pad( explode( '<', $dependency ), 2, null );
					$dep_slug                = trim( $dep_slug );

					// A sanitized slug doesn't mean it was valid, so test additional conditions before
					if ( empty( $dep_slug ) || str_contains( $dep_slug, ' ' ) || ! sanitize_slug( $dep_slug ) )
						throw new \Exception( 'Repo Dependencies header must specify a valid plugin slug for each dependency.' );

					// If repo part exists, validate the closing bracket
					if (
						   null !== $dep_repo
						&& (
							   ! str_ends_with( trim( $dep_repo ), '>' )
							|| ! trim( $dep_repo, ' >' ) // empty repo part is not allowed
						)
					) {
						throw new \Exception( 'Repo Dependencies header with repository URIs must use format: "plugin-slug" or "plugin-slug <repo-uri>".' );
					}

					// If repo part exists, validate the URL
					if ( $dep_repo && ! make_fully_qualified_repo_url( trim( $dep_repo, ' >' ) ) )
						throw new \Exception( 'Repo Dependencies header must use a valid repository URL for each dependency.' );
				}
			}
		}

		parse_readme_contents: try {
			$readme_parser = new Readme_Parser( $temp_zip_extraction_dir );

			// Although these headers may contain fallback requires headers, we don't use them.
			// If we reintroduce them, also write them in the catch block.
			// $readme_headers  = $readme_parser->headers;

			$readme_contents = $readme_parser->contents;
		} catch ( \Exception $e ) {
			switch ( $e->getCode() ) {
				case Readme_Parser::ERRORS['not_found']:
				case Readme_Parser::ERRORS['empty']:
				case Readme_Parser::ERRORS['untitled']:
					$error           = $e->getMessage();
					$readme_contents = [];
					break;
				default:
					$error           = 'Unexpected exception in readme.txt file: ' . $e->getMessage();
					$readme_contents = [];
			}

			// Fail silently if Readme parsing fails, but log the error, for now.
			// In the editor, we can automatically show there's no (valid) readme data.
			// Still, we may want to use the error message in the future.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- your mom is an error log.
			error_log(
				\sprintf(
					'Failed to parse readme.txt file for plugin ID %d: %s.',
					$this->plugin_id,
					$error,
				),
			);
		}

		write_zip: {
			$plugin_slug = sanitize_slug( new Data( $this->plugin_id )->get_plugins_row()?->slug );

			if ( ! $plugin_slug )
				throw new \Exception( 'Failed to sanitize plugin slug.' );

			$plugin_zip_file_path = Files::get_plugin_zip_file_path( $this->plugin_id, $version );

			File_Utils::make_shielded_dir( \dirname( $plugin_zip_file_path ) );

			$temp_plugin_dir_path = \dirname( $temp_plugin_file_path );

			$zip = new \ZipArchive();
			$res = $zip->open(
				$plugin_zip_file_path,
				\ZipArchive::CREATE | \ZipArchive::OVERWRITE,
			);

			$error = match ( $res ) {
				\ZipArchive::ER_MEMORY   => 'Not enough memory to open the new ZIP file.',
				true, \ZipArchive::ER_OK => null, // No error.
				default                  => 'An unknown error occurred while creating the new ZIP file.',
			};

			if ( isset( $error ) )
				throw new \Exception( $error );

			// Add the plugin directory slug to the ZIP file.
			$zip->addEmptyDir( $plugin_slug );

			// Add all files from the temporary extraction directory to the new ZIP file.
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
					$temp_plugin_dir_path,
					\RecursiveDirectoryIterator::SKIP_DOTS,
				),
				\RecursiveIteratorIterator::SELF_FIRST,
			);

			foreach ( $iterator as $file ) {
				$source_path = $file->getPathname();
				// Create a path relative to the directory being zipped.
				$relative_path = str_replace(
					\trailingslashit( $temp_plugin_dir_path ),
					'',
					$source_path,
				);

				$zip_path = "{$plugin_slug}/{$relative_path}";

				if ( $file->isDir() ) {
					$zip->addEmptyDir( $zip_path );
				} else {
					$zip->addFile( $source_path, $zip_path );
				}
			}

			// Close the ZIP file to finalize it.
			if ( ! $zip->close() )
				throw new \Exception( 'Failed to close the ZIP file after repackaging.' );

			// Additional validation: ensure the ZIP file exists and is readable.
			if ( ! is_readable( $plugin_zip_file_path ) || ! is_file( $plugin_zip_file_path ) )
				throw new \Exception( 'ZIP file was not created or is not readable after repackaging.' );
		}

		write_db: {
			$zip_db_data = [
				'plugin_id'        => $this->plugin_id,
				'version'          => $version, // Already sanitized.
				'type'             => 'unreleased',
				'file_size'        => filesize( $plugin_zip_file_path ),
				// We're storing the latest version we know about when the plugin misses the header.
				// This is a safe assumption for the author shouldn't release an unstable version.
				'tested_wp'        => sanitize_tested_version( $tested_wp ?: get_latest_public_wordpress_version() ),
				'requires_wp'      => sanitize_tested_version( $plugin_headers['RequiresWP'] ?? '' ),
				'requires_php'     => sanitize_tested_version( $plugin_headers['RequiresPHP'] ?? '' ),
				'repo'             => $repo,
				'dependencies'     => $dependencies,
				'upgrade_notice'   => $readme_contents['upgrade_notice'] ?? '', // Already sanitized in Readme_Parser.
				'origin_url'       => $this->origin_url,
				'checksum'         => hash_file( 'sha256', $plugin_zip_file_path ),
				'checksum_version' => 'sha256',
				'checksum_origin'  => $this->origin_url,
			];

			global $wpdb;

			$wpdb->query( 'START TRANSACTION' );

			$success = false;

			try {
				$existing_zip = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}troy_plugins_zips WHERE plugin_id = %d AND version = %s",
					$this->plugin_id,
					$zip_db_data['version'],
				) );

				$this->zip_existed = (bool) $existing_zip;

				if ( $existing_zip ) {
					// Update existing plugin zip.
					$wpdb->update(
						"{$wpdb->prefix}troy_plugins_zips",
						[
							'type'             => $zip_db_data['type'],
							'file_size'        => $zip_db_data['file_size'],
							'tested_wp'        => $zip_db_data['tested_wp'],
							'requires_wp'      => $zip_db_data['requires_wp'],
							'requires_php'     => $zip_db_data['requires_php'],
							'repo'             => $zip_db_data['repo'],
							'dependencies'     => $zip_db_data['dependencies'],
							'upgrade_notice'   => $zip_db_data['upgrade_notice'],
							'origin_url'       => $zip_db_data['origin_url'],
							'checksum'         => $zip_db_data['checksum'],
							'checksum_version' => $zip_db_data['checksum_version'],
							'checksum_origin'  => $zip_db_data['checksum_origin'],
						],
						[
							'id' => $existing_zip,
						],
						[
							'%s',
							'%d',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
						],
						[
							'%d',
						],
					);
				} else {
					// Insert new plugin zip.
					$wpdb->insert(
						"{$wpdb->prefix}troy_plugins_zips",
						[
							'plugin_id'        => $this->plugin_id,
							'version'          => $zip_db_data['version'],
							'type'             => $zip_db_data['type'],
							'file_size'        => $zip_db_data['file_size'],
							'tested_wp'        => $zip_db_data['tested_wp'],
							'requires_wp'      => $zip_db_data['requires_wp'],
							'requires_php'     => $zip_db_data['requires_php'],
							'repo'             => $zip_db_data['repo'],
							'dependencies'     => $zip_db_data['dependencies'],
							'upgrade_notice'   => $zip_db_data['upgrade_notice'],
							'origin_url'       => $zip_db_data['origin_url'],
							'checksum'         => $zip_db_data['checksum'],
							'checksum_version' => $zip_db_data['checksum_version'],
							'checksum_origin'  => $zip_db_data['checksum_origin'],
						],
						[
							'%d',
							'%s',
							'%s',
							'%d',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
						],
					);
				}

				// COMMIT will tell 0 rows affected. We could check the insert ID.
				$success = false !== $wpdb->query( 'COMMIT' );
			} catch ( \Exception $e ) {
				$wpdb->query( 'ROLLBACK' );

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions -- acceptable in this context.
				error_log(
					\wp_strip_all_tags(
						\sprintf(
							'Troy ZIP processing error (Plugin ID: %1$d): %2$s',
							$this->plugin_id,
							$e->getMessage(),
						),
					),
				);

				$success = false;
			}

			if ( ! $success ) {
				// If the database write failed, we should remove the ZIP file.
				// This is a safeguard; we do not want to leave orphaned ZIP files.
				// Only do this if the ZIP file was not already existing.
				if ( empty( $existing_zip ) && is_file( $plugin_zip_file_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions -- WP functions are irrelevant here.
					unlink( $plugin_zip_file_path );
				}

				throw new \Exception( 'Failed to write ZIP data to the database.' );
			}
		}

		$this->version_uploaded = $version;
		// phpcs:enable Generic.WhiteSpace.ScopeIndent
	}

	/**
	 * Downloads a file from a URL with optional custom headers.
	 *
	 * Simplified version of WordPress core's download_url() without signature verification.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $url  The URL to download from.
	 * @param array  $args Optional {
	 *        Arguments for the download request.
	 *
	 *        @type array $headers     Custom HTTP headers.
	 *        @type array $queryParams Query parameters to append to the URL.
	 *    }
	 * @return string|\WP_Error Path to the downloaded temporary file on success, WP_Error on failure.
	 */
	private static function download_url( $url, $args = [] ) {

		if ( ! $url )
			return new \WP_Error( 'http_no_url', \__( 'No URL Provided.', 'troy-server' ) );

		if ( ! empty( $args['queryParams'] ) )
			$url = \add_query_arg( $args['queryParams'], $url );

		$url_path     = parse_url( $url, \PHP_URL_PATH );
		$url_filename = '';

		if ( \is_string( $url_path ) && '' !== $url_path )
			$url_filename = basename( $url_path );

		$tmpfname = \wp_tempnam( $url_filename );

		if ( ! $tmpfname )
			return new \WP_Error( 'http_no_file', \__( 'Could not create temporary file.', 'troy-server' ) );

		$response = \wp_safe_remote_get(
			$url,
			[
				'timeout'  => static::ZIP_DOWNLOAD_TIMEOUT,
				'stream'   => true,
				'filename' => $tmpfname,
				'headers'  => $args['headers'] ?? [],
			],
		);

		if ( \is_wp_error( $response ) ) {
			unlink( $tmpfname );

			return $response;
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			$data = [ 'code' => $response_code ];

			// Retrieve a sample of the response body for debugging purposes.
			$tmpf = fopen( $tmpfname, 'rb' );

			if ( $tmpf ) {
				$response_size = \apply_filters( 'download_url_error_max_body_size', \KB_IN_BYTES );
				$data['body']  = fread( $tmpf, $response_size );
				fclose( $tmpf );
			}

			unlink( $tmpfname );

			return new \WP_Error(
				'http_404',
				trim( \wp_remote_retrieve_response_message( $response ) ),
				$data,
			);
		}

		// Handle Content-Disposition header for proper filename.
		$content_disposition = \wp_remote_retrieve_header( $response, 'Content-Disposition' );

		if ( $content_disposition ) {
			$content_disposition = strtolower( $content_disposition );

			if ( str_starts_with( $content_disposition, 'attachment; filename=' ) ) {
				$tmpfname_disposition = \sanitize_file_name( substr( $content_disposition, 21 ) );
			} else {
				$tmpfname_disposition = '';
			}

			if (
				$tmpfname_disposition
				&& \is_string( $tmpfname_disposition )
				&& ( 0 === \validate_file( $tmpfname_disposition ) )
			) {
				$tmpfname_disposition = \dirname( $tmpfname ) . '/' . $tmpfname_disposition;

				if ( rename( $tmpfname, $tmpfname_disposition ) ) {
					$tmpfname = $tmpfname_disposition;
				}

				if ( ( $tmpfname !== $tmpfname_disposition ) && file_exists( $tmpfname_disposition ) )
					unlink( $tmpfname_disposition );
			}
		}

		// Correct file extension based on MIME type.
		$mime_type = \wp_remote_retrieve_header( $response, 'content-type' );

		if ( $mime_type && 'tmp' === pathinfo( $tmpfname, \PATHINFO_EXTENSION ) ) {
			// We only care about ZIP files for this context.
			if ( 'application/zip' === $mime_type || 'application/x-zip-compressed' === $mime_type ) {
				$new_zip_name = substr( $tmpfname, 0, -4 ) . '.zip';

				if ( 0 === \validate_file( $new_zip_name ) ) {
					if ( rename( $tmpfname, $new_zip_name ) )
						$tmpfname = $new_zip_name;

					if ( ( $tmpfname !== $new_zip_name ) && file_exists( $new_zip_name ) )
						unlink( $new_zip_name );
				}
			}
		}

		// Verify Content-MD5 if provided.
		$content_md5 = \wp_remote_retrieve_header( $response, 'Content-MD5' );

		if ( $content_md5 ) {
			$md5_check = \verify_file_md5( $tmpfname, $content_md5 );

			if ( \is_wp_error( $md5_check ) ) {
				unlink( $tmpfname );

				return $md5_check;
			}
		}

		return $tmpfname;
	}
}
