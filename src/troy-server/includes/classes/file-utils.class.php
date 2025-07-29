<?php
/**
 * @package Troy\Server
 * @access  public
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

// phpcs:disable TSF.Performance.Functions -- We require slow file operations here.

/**
 * Class Troy\Server\File_Utils.
 *
 * Provides utility functions for file operations in Troy Server.
 *
 * @since 0.0.1184
 */
final class File_Utils {

	/**
	 * @since 0.0.1184
	 * @var bool $wpfs_initialized Whether the WordPress Filesystem has been initialized.
	 */
	private static $wpfs_initialized = false;

	/**
	 * Initializes the WordPress Filesystem and raises the memory limit.
	 *
	 * @since 0.0.1184
	 * @throws \Exception If the WordPress Filesystem fails to initialize.
	 */
	public static function init_wpfs() {

		if ( static::$wpfs_initialized )
			return;

		\wp_raise_memory_limit( 'troy-server-init-fs' );

		// Let's not fully rely on globals to check if the filesystem is initialized.
		if ( empty( $GLOBALS['wp_filesystem'] ) || ! \function_exists( 'WP_Filesystem' ) ) {
			// Ensure WP_Filesystem() is declared
			require_once \ABSPATH . 'wp-admin/includes/file.php';

			if ( ! \WP_Filesystem() )
				throw new \Exception( 'Failed to initialize WordPress Filesystem.' );
		}

		static::$wpfs_initialized = true;
	}

	/**
	 * Creates a shielded directory for the given directory path.
	 * It shields by creating index.php and .htaccess files to help prevent
	 * directory listing and to ensure the directory is not publicly accessible.
	 *
	 * This may not be helpful with NGINX.
	 * Though using NGINX for WordPress isn't helpful, either.
	 *
	 * This does NOT GUARANTEE the directory is secure against direct access.
	 * Hence, we call it "shielded" instead of "secure".
	 *
	 * @since 0.0.1184
	 *
	 * @param string $dir The directory path to create.
	 * @throws \Exception If the directory creation fails.
	 */
	public static function make_shielded_dir( $dir ) {

		static::init_wpfs();

		$dir = \trailingslashit( $dir );

		if ( is_dir( $dir ) )
			return;

		if ( ! \wp_mkdir_p( $dir ) )
			throw new \Exception( 'Failed to create zip directory.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( "{$dir}index.php", "<?php\n" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		chmod( "{$dir}index.php", \FS_CHMOD_FILE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( "{$dir}.htaccess", "Require all denied\n" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		chmod( "{$dir}.htaccess", \FS_CHMOD_FILE );
	}

	/**
	 * Recursively cleans a directory and its contents.
	 *
	 * @since 0.0.1184
	 *
	 * @param string $dir The directory to clean.
	 */
	public static function clean_dir_recursively( $dir ) {

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$dir,
				\RecursiveDirectoryIterator::SKIP_DOTS
			),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions -- WP functions are irrelevant here.
				rmdir( $file->getRealPath() );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions -- WP functions are irrelevant here.
				unlink( $file->getRealPath() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- WP functions are irrelevant here.
		rmdir( $dir );
	}
}
