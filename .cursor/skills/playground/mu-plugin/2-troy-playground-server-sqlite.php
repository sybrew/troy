<?php
/**
 * @package Troy\Playground
 */

defined( 'ABSPATH' ) or die;

/**
 * Troy
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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
 * Seeds $wpdb->dbh->errno so Server record_database_block() does not call
 * PDO::errorInfo() on Playground's uninitialized WP_MySQL_On_SQLite.
 * That call 255s blueprint activatePlugin. errno 0 records a database block.
 */
function troy_playground_sqlite_seed_dbh_errno() {

	global $wpdb;

	if ( ! isset( $wpdb->dbh ) || ! is_object( $wpdb->dbh ) ) return;

	if ( isset( $wpdb->dbh->errno ) ) return;

	$reporting = error_reporting( E_ALL & ~E_DEPRECATED );
	$wpdb->dbh->errno = 0;
	error_reporting( $reporting );
}

troy_playground_sqlite_seed_dbh_errno();

/**
 * Writes the last fatal to persist debug.log. Playground blueprint PHP.run()
 * 255s often leave server.log empty.
 */
register_shutdown_function(
	function () {

		$e = error_get_last();

		if ( ! $e ) return;

		if ( ! in_array( $e['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) )
			return;

		file_put_contents(
			WP_CONTENT_DIR . '/debug.log',
			sprintf(
				"[%s] FATAL %s in %s:%d\n",
				gmdate( 'c' ),
				$e['message'],
				$e['file'],
				$e['line'],
			),
			FILE_APPEND,
		);
	},
);
