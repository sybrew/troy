<?php
/**
 * @package Troy\Server\Packages
 * @access  public
 */

namespace Troy\Server\Packages;

\defined( 'Troy\Server\ABSPATH' ) or die;

use const Troy\Server\ABSPATH;

use Troy\Server\{
	API,
	File_Utils,
	Packages\CPT\Store,
	Plugins,
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
// phpcs:disable WordPress.WP.AlternativeFunctions -- WP functions are irrelevant here.

/**
 * Class Troy\Server\Packages\Zip_Builder.
 *
 * Generates installer packages from the troy-installer template files.
 *
 * @since 0.0.1184
 */
final class Zip_Builder {

	/**
	 * @since 0.0.1184
	 * @var int ZIP_PROCESS_TIMEOUT Timeout for processing ZIP files.
	 *                              We benchmarked 30k files, 1kb each, this is a worst-case.
	 *                              Creation took 2.1 seconds on Mac Mini (M4, internal SSD).
	 *                              Gemini assumes, based on that, 45s on SATA hosting.
	 *                              Since we only process 5 files, we'd need less than a millisecond.
	 *                              Let's add 10 seconds of buffer for drive spin-up time.
	 */
	private const ZIP_PROCESS_TIMEOUT = 10;

	/**
	 * @since 0.0.1184
	 * @var int EXCEPTION_PERMANENT Permanent failure exception code (0b01 = 1).
	 */
	public const EXCEPTION_PERMANENT = 0b01;

	/**
	 * @since 0.0.1184
	 * @var int EXCEPTION_TEMPORARY Temporary failure exception code (0b10 = 2).
	 */
	public const EXCEPTION_TEMPORARY = 0b10;

	/**
	 * @since 0.0.1184
	 * @var bool $lock Whether the ZIP handler is locked.
	 *                 This prevents processing multiple files in the same instance.
	 */
	private $lock = false;

	/**
	 * @since 0.0.1184
	 * @var ?string $origin_url The package's Origin URL.
	 *                          This is set during class construction.
	 */
	public readonly string $origin_url;

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
	 * @param int    $package_id The package's ID to process.
	 * @param string $origin_url Optional. The package's Origin URL.
	 *                           If left empty, this server's Origin URL will be used.
	 * @throws \Exception Via `File_Utils::init_wpfs()` if the WordPress Filesystem can't be
	 *                    initialized or if we cannot create package storage directories.
	 */
	public function __construct(
		public readonly int $package_id,
		$origin_url = null,
	) {

		if ( empty( $this->package_id ) )
			throw new \Exception( 'Invalid package ID provided.', self::EXCEPTION_PERMANENT );

		ignore_user_abort( true );

		API\Utils::increase_time_limit_by( self::ZIP_PROCESS_TIMEOUT );

		File_Utils::init_wpfs();

		$this->origin_url = $origin_url ?? API\Server::get_repo_url();

		// Make plugin storage directories if they do not exist.
		// We want a shield in every plugin storage directory, hence the double call.
		File_Utils::make_shielded_dir( Files::get_package_storage_dir_path() );
		File_Utils::make_shielded_dir( Files::get_package_storage_dir_path( $this->package_id ) );
	}

	/**
	 * Builds a package installer PHP file and zips it.
	 *
	 * @since 0.0.1184
	 *
	 * @return bool True on success.
	 * @throws \Exception When required data is missing or processing fails.
	 */
	public function build() {
		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- no love for goto.

		if ( $this->lock )
			throw new \Exception(
				'Cannot build multiple packages in the same instance.',
				self::EXCEPTION_PERMANENT,
			);

		$this->lock = true;

		$data = new Data( $this->package_id );

		$package = $data->get_packages_row();
		$meta    = $data->get_metas_row();

		if ( ! $package || ! $meta )
			throw new \Exception(
				"Package ID {$this->package_id} not found.",
				self::EXCEPTION_PERMANENT,
			);

		load_template: {
			$template_path = ABSPATH . 'library/packages/troy-installer.php';

			if ( ! file_exists( $template_path ) )
				throw new \Exception( 'Template file not found.', self::EXCEPTION_PERMANENT );

			$content = file_get_contents( $template_path );

			if ( false === $content )
				throw new \Exception( 'Failed to read template file.', self::EXCEPTION_TEMPORARY );
		}

		process_template: {
			// TODO We should've done [ 'type' => [ 'docblock' => 'plugin-header', ... ], ... ] for maintainability.
			$package_title = $meta->name ?: 'Troy Installer';

			// Remove "@troy-package !" lines.
			$content = preg_replace( '/^[^\r\n]*@troy-package\s+!.*(?:[\r\n])?/m', '', $content );

			// Process docblock markers.
			$content = preg_replace_callback(
				'/(^[\t ]*)\*\s@troy-package\s+\*\s+([\w\p{N}\p{Pc}\p{Pd}]+)\R.*?\R[\t ]*\*\s@troy-package\s+\*\s+\2/msu',
				function ( $matches ) use ( $meta, $package_title ) {

					$indent = $matches[1];
					$key    = $matches[2];
					$lines  = [];

					switch ( $key ) {
						case 'plugin-header':
							$lines = [ '@wordpress-plugin' ];

							$lines[] = 'Plugin Name: ' . API\Sanitize::docblock_content( $package_title );

							if ( $meta->plugin_uri )
								$lines[] = 'Plugin URI: ' . API\Sanitize::docblock_content( $meta->plugin_uri );

							if ( $meta->description )
								$lines[] = 'Description: ' . API\Sanitize::docblock_content( $meta->description );

							$lines[] = 'Version: ' . API\Sanitize::docblock_content( $meta->version );

							if ( $meta->author )
								$lines[] = 'Author: ' . API\Sanitize::docblock_content( $meta->author );

							if ( $meta->author_uri )
								$lines[] = 'Author URI: ' . API\Sanitize::docblock_content( $meta->author_uri );

							$lines[] = 'License: MIT';

							if ( $meta->requires_wp )
								$lines[] = 'Requires at least: ' . API\Sanitize::docblock_content( $meta->requires_wp );

							if ( $meta->requires_php )
								$lines[] = 'Requires PHP: ' . API\Sanitize::docblock_content( $meta->requires_php );

							if ( 'require' === $meta->network_activation )
								$lines[] = 'Network: true';
							break;

						default:
							return $matches[0];
					}

					$formatted_lines = array_map(
						fn( $line ) => "{$indent}* $line",
						$lines,
					);

					return implode( "\n", $formatted_lines );
				},
				$content,
			);

			// Process codeblock markers.
			$content = preg_replace_callback(
				'/(^[\t ]*)\/\/\s@troy-package\s+\|\s+([\w\p{N}\p{Pc}\p{Pd}]+)\r?\n.*?\r?\n[\t ]*\/\/\s@troy-package\s+\|\s+\2/msu',
				function ( $matches ) use ( $meta, $package ) {

					$indent = $matches[1];
					$key    = $matches[2];
					$lines  = [];

					switch ( $key ) {
						case 'plugin-options':
							$options = [
								'install_timeout'          => (int) $meta->install_timeout,
								'deactivate_on_completion' => (bool) $meta->deactivate_on_completion,
								'delete_on_completion'     => (bool) $meta->delete_on_completion,
								'notice_severity'          => $meta->notice_severity,
								'network_activation'       => $meta->network_activation,
							];

							$lines = explode(
								"\n",
								'const OPTIONS = ' . API\Sanitize::var_export( $options ) . ';',
							);
							break;

						case 'plugin-install':
							if ( empty( $meta->plugins ) ) {
								$lines = [ 'const INSTALL = [];' ];
								break;
							}

							$install = [];

							foreach ( $meta->plugins as $plugin_config ) {
								$plugin_id = \absint( $plugin_config['id'] ?? 0 );

								if ( ! $plugin_id )
									continue;

								$plugin_data = new Plugins\Data( $plugin_id );
								$plugin_row  = $plugin_data->get_plugins_row();
								$plugin_meta = $plugin_data->get_metas_row();

								if ( ! $plugin_row || ! $plugin_meta )
									throw new \Exception(
										"Plugin ID $plugin_id not found in package ID {$package->id}.",
										self::EXCEPTION_PERMANENT,
									);

								$install[ $plugin_row->slug ] = [
									'name'           => \esc_html( $plugin_meta->name ),
									'repo'           => \trailingslashit( API\Sanitize::url_qualified( $package->origin_url ) ),
									'version'        => 'latest', // TODO Support package-specific plugin version overrides?
									'activate'       => ! empty( $plugin_config['activate'] ),
									'network'        => ! empty( $plugin_config['network'] ),
									'overwrite'      => ! empty( $plugin_config['overwrite'] ),
									'overwrite_troy' => ! empty( $plugin_config['overwrite_troy'] ),
								];
							}

							$lines = explode(
								"\n",
								'const INSTALL = ' . API\Sanitize::var_export( $install ) . ';',
							);
							break;

						default:
							return $matches[0];
					}

					$formatted_lines = array_map(
						static fn( $line ) => "{$indent}$line",
						$lines,
					);

					return implode( "\n", $formatted_lines );
				},
				$content,
			);

			// Process inline markers.
			$content = preg_replace_callback(
				'/(^[\t ]*).*?@troy-package\s+([\w\p{N}\p{Pc}\p{Pd}]+).*$/mu',
				function ( $matches ) use ( $package_title ) {

					$indent = $matches[1];
					$key    = $matches[2];
					$line   = '';

					switch ( $key ) {
						case 'plugin-namespace':
							$namespace = API\Sanitize::php_namespace( $package_title )
								?: 'Package' . bin2hex( random_bytes( 8 ) );

							$line = 'namespace Troy\Installer\\' . API\Sanitize::php_namespace( $package_title ) . ';';
							break;
						case 'plugin-name':
							$line = 'const PLUGIN_NAME = ' . API\Sanitize::var_export( $package_title ) . ';';
							break;

						default:
							return $matches[0];
					}

					return "{$indent}$line";
				},
				$content,
			);
		}

		write_zip: {
			$storage_dir = Files::get_package_storage_dir_path( $this->package_id );
			$zip_path    = "{$storage_dir}{$package->slug}.zip";

			$this->zip_existed = file_exists( $zip_path );

			File_Utils::make_shielded_dir( $storage_dir );

			$temp_installer = \wp_tempnam( $package->slug );

			if ( ! $temp_installer )
				throw new \Exception( 'Failed to create temporary installer file.', self::EXCEPTION_TEMPORARY );

			if ( false === file_put_contents( $temp_installer, $content ) )
				throw new \Exception( 'Failed to write installer file.', self::EXCEPTION_TEMPORARY );

			// Ensure temporary file is cleaned up after processing.
			register_shutdown_function(
				static fn() => is_file( $temp_installer ) and unlink( $temp_installer ),
			);

			// Unlike plugins, we write just one package per slug.
			// So, it's safest to use a single temp ZIP and move it on success.
			$temp_zip = \wp_tempnam( $package->slug . '.zip' );

			if ( ! $temp_zip )
				throw new \Exception( 'Failed to create temporary ZIP file.', self::EXCEPTION_TEMPORARY );

			// Ensure temporary ZIP is cleaned up after processing.
			register_shutdown_function(
				static fn() => is_file( $temp_zip ) and unlink( $temp_zip ),
			);

			$zip = new \ZipArchive();
			$res = $zip->open(
				$temp_zip,
				\ZipArchive::CREATE | \ZipArchive::OVERWRITE,
			);

			$error = match ( $res ) {
				\ZipArchive::ER_MEMORY   => 'Not enough memory to open the new ZIP file.',
				true, \ZipArchive::ER_OK => null,
				default                  => 'An unknown error occurred while creating the new ZIP file.',
			};

			if ( isset( $error ) )
				throw new \Exception( $error, self::EXCEPTION_TEMPORARY );

			$zip->addEmptyDir( $package->slug );
			$zip->addFile( $temp_installer, "{$package->slug}/{$package->slug}.php" );

			$additional_files = [ 'index.php', 'readme.txt', 'license.txt' ];
			$library_path     = ABSPATH . 'library/packages/';

			foreach ( $additional_files as $filename ) {
				$file_path = $library_path . $filename;

				if ( file_exists( $file_path ) )
					$zip->addFile( $file_path, "{$package->slug}/{$filename}" );
			}

			if ( ! $zip->close() )
				throw new \Exception( 'Failed to close the ZIP file.', self::EXCEPTION_TEMPORARY );

			if ( ! is_readable( $temp_zip ) || ! is_file( $temp_zip ) )
				throw new \Exception(
					'ZIP file was not created or is not readable.',
					self::EXCEPTION_TEMPORARY,
				);

			// Move the temporary ZIP to the final location. rename() will overwrite existing files.
			if ( ! rename( $temp_zip, $zip_path ) )
				throw new \Exception(
					'Failed to move ZIP file to storage directory.',
					self::EXCEPTION_TEMPORARY,
				);
		}

		write_db: {
			global $wpdb;

			$wpdb->query( 'START TRANSACTION' );

			try {
				$wpdb->update(
					"{$wpdb->prefix}troy_packages",
					[ 'status' => 'active' ],
					[ 'id' => $this->package_id ],
					[ '%s' ],
					[ '%d' ],
				);

				$wpdb->query( 'COMMIT' );
			} catch ( \Exception $e ) {
				$wpdb->query( 'ROLLBACK' );

				throw new \Exception(
					"Failed to update package status: {$e->getMessage()}",
					self::EXCEPTION_TEMPORARY,
				);
			}
		}

		return true;
		// phpcs:enable Generic.WhiteSpace.ScopeIndent
	}
}
