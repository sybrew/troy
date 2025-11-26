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

'use strict';

/**
 * @module troyServerFormat
 * @description Formatting utilities for the Troy Server plugin.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
window.troyServerFormat = ( wp => {

	/**
	 * Formats a UNIX timestamp into a human-readable relative time string.
	 *
	 * Examples:
	 * - "Just now" for timestamps within the last minute.
	 * - "X minutes ago" for timestamps within the last hour.
	 * - "X hours ago" for timestamps within the last day.
	 * - "X days ago" for timestamps older than a day.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number|string} timestamp The UNIX timestamp to format, or a dateTime string.
	 * @return {string} A human-readable relative time string.
	 */
	function timestamp( ts ) {

		const { __, _n, sprintf } = wp.i18n;

		if ( 'string' === typeof ts )
			ts = Math.floor( new Date( ts ).getTime() / 1000 );

		if ( ! +ts )
			return __( 'Never', 'troy-server' );

		const date = new Date( ts * 1000 );
		const now  = new Date();
		const diff = Math.floor( ( now - date ) / 1000 );

		if ( diff < 60 )
			return __( 'Just now', 'troy-server' );

		if ( diff < 3600 )
			return sprintf(
				/* translators: %d: number of minutes */
				_n( '%d minute ago', '%d minutes ago', Math.floor( diff / 60 ), 'troy-server' ),
				Math.floor( diff / 60 ),
			);

		if ( diff < 86400 )
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour ago', '%d hours ago', Math.floor( diff / 3600 ), 'troy-server' ),
				Math.floor( diff / 3600 ),
			);

		return sprintf(
			/* translators: %d: number of days */
			_n( '%d day ago', '%d days ago', Math.floor( diff / 86400 ), 'troy-server' ),
			Math.floor( diff / 86400 ),
		);
	}

	/**
	 * Converts a number of bytes into a human-readable string using binary
	 * prefixes (KiB, MiB, etc.).
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} num         The number of bytes to convert.
	 * @param {number} [decimals=2] The number of decimal places to include in the output.
	 * @return {string} A formatted string with the appropriate binary prefix.
	 *                  Returns '0 bytes' if the input is 0 or falsy.
	 */
	function bytesToIbiBytes( num, decimals = 2 ) {

		if ( ! num )
			return '0 bytes';

		const k     = 1024;
		const sizes = [ 'bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB' ];
		const i     = Math.floor( Math.log( num ) / Math.log( k ) );

		return `${ parseFloat( ( num / Math.pow( k, i ) ).toFixed( decimals ) ).toLocaleString() } ${ sizes[ i ] }`;
	}

	return {
		timestamp,
		bytesToIbiBytes,
	};
} )( window.wp );
