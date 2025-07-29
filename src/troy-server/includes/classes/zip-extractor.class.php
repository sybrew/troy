<?php
/**
 * @package Troy\Server
 * @access  public
 */

namespace Troy\Server;

\defined( 'Troy\Server\ABSPATH' ) or die;

use function Troy\Server\Sanitize\sanitize_file_path;

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

// phpcs:disable TSF.Performance.Functions -- We require slow file operations here.

/**
 * Class Troy\Server\Zip_Extractor.
 *
 * Handles plugin or theme ZIP file extraction to temporary storage.
 *
 * @since 0.0.1184
 */
class Zip_Extractor {

	/**
	 * @since 0.0.1184
	 * @var int ZIP_EXTRACT_TIMEOUT Timeout for extracting ZIP files.
	 *                              We benchmarked 30k files, 1kb each, this is a worst-case.
	 *                              Extraction took 3.4 seconds on Mac Mini (M4, internal SSD).
	 *                              Gemini assumes, based on that, 25s on SATA hosting.
	 */
	private const ZIP_EXTRACT_TIMEOUT = 25;

	/**
	 * @since 0.0.1184
	 * @var string $temp_zip_extraction_dir Temporary directory path for the extracted ZIP.
	 */
	public readonly string $temp_zip_extraction_dir;

	/**
	 * Extracts the plugin or theme ZIP file to temporary storage.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $zip_file_path Path to the plugin ZIP file.
	 * @throws \Exception Via `init_wpfs()` if the WordPress Filesystem cannot be initialized.
	 *                    Or if the ZIP file doesn't exist or is not readable.
	 *                    Or if the temporary extraction directory cannot be created.
	 */
	public function __construct(
		public readonly string $zip_file_path,
	) {
		ignore_user_abort( true );

		increase_time_limit_by( self::ZIP_EXTRACT_TIMEOUT );

		File_Utils::init_wpfs();

		if ( ! is_readable( $zip_file_path ) || ! is_file( $zip_file_path ) )
			throw new \Exception( 'ZIP file not found or not readable.' );

		// Create temp directory with random name to prevent conflicts
		$rand   = str_pad( bin2hex( random_bytes( 8 ) ), 16, '0', STR_PAD_LEFT );
		$minute = round( time() / \MINUTE_IN_SECONDS );

		$name = sanitize_file_path( $zip_file_path ) ?? '_unnamed_';

		// This folder needs no shielding, the OS FS will handle that.
		$this->temp_zip_extraction_dir = \get_temp_dir() . "troy_zip_extractions/plugin_{$name}_{$minute}_{$rand}/";

		if ( ! is_dir( $this->temp_zip_extraction_dir ) && ! \wp_mkdir_p( $this->temp_zip_extraction_dir ) )
			throw new \Exception( 'Failed to create temporary extraction directory.' );

		register_shutdown_function( [ $this, 'clean_temp_zip_extraction_dir' ] );

		extract: {
			$zip = new \ZipArchive();
			$res = $zip->open( $zip_file_path, \ZipArchive::CHECKCONS );

			$error = match ( $res ) {
				\ZipArchive::ER_INCONS   => 'The ZIP file is inconsistent or corrupted.',
				\ZipArchive::ER_MEMORY   => 'Not enough memory to process the ZIP file.',
				\ZipArchive::ER_NOZIP    => 'The provided file is not a valid ZIP file.',
				\ZipArchive::ER_READ     => 'Failed to read the ZIP file.',
				true, \ZipArchive::ER_OK => null, // No error.
				default                  => 'An unknown error occurred while opening the ZIP file.',
			};

			if ( isset( $error ) )
				throw new \Exception( $error );

			$zip->extractTo( $this->temp_zip_extraction_dir );
			$zip->close();
		}
	}

	/**
	 * Cleans up temporary extraction directory recursively.
	 * This automatically runs on shutdown.
	 *
	 * @since 0.0.1184
	 */
	public function clean_temp_zip_extraction_dir() {

		$temp_dir = $this->temp_zip_extraction_dir;

		if ( ! is_dir( $temp_dir ) )
			return;

		File_Utils::clean_dir_recursively( $temp_dir );
	}

	/**
	 * Cleans up old temporary directories.
	 *
	 * The default threshold is 2 days ago, for "yesterday" could be a few seconds ago,
	 * which might accidentally delete a directory that is still being processed.
	 *
	 * @hook troy_cron_clean_temp 10
	 * @since 0.0.1184
	 */
	public static function cron_clean_old_temp_dirs() {

		$old_dirs = glob( \get_temp_dir() . 'troy_zip_extractions/*', GLOB_ONLYDIR );

		foreach ( $old_dirs as $dir )
			if ( filemtime( $dir ) < strtotime( '-2 days' ) )
				File_Utils::clean_dir_recursively( $dir );
	}
}
