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
 * @module troyServerSanitize
 * @description Sanitization utilities for the Troy Server plugin.
 * @since 0.0.1184
 */
window.troyServerSanitize = ( () => {

	/**
	 * Formats a number for display using locale-aware formatting.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} num The number to format.
	 * @return {string} Formatted number.
	 */
	function number( num ) {
		return new Intl.NumberFormat().format( num );
	}

	/**
	 * Returns a bare repository URL, containing only domain/path/query.
	 * Supports various repository URL formats including IPv6 addresses.
	 *
	 * This won't return a proper fully qualified URL with a protocol scheme.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} repoUrl The URL to sanitize.
	 * @return {string} The bare URL containing only domain/path/query.
	 */
	function bareRepoUrl( repoUrl ) {

		if ( ! repoUrl || 'string' !== typeof repoUrl )
			return '';

		try {
			// This removes any protocol scheme and leading slashes, keeping everything else
			const fullyQualifiedUrl = repoUrl
				.trim()
				.replace( /^[\s\\\/]+|[\s\\\/]+$/g, '' )
				.replace( /^(?:\w*:)?(?:\/\/)?(.*?)$/, 'https://$1/' );

			const urlObj = new URL( fullyQualifiedUrl );

			// Construct the sanitized URL: hostname + pathname + search (no port)
			let url = urlObj.hostname;

			// Add port if it exists
			if ( urlObj.port )
				url += `:${ urlObj.port }`;

			// Add pathname if it exists and is not just '/'
			if ( urlObj.pathname && '/' !== urlObj.pathname )
				url += urlObj.pathname;

			// Add search params if they exist
			if ( urlObj.search )
				url += urlObj.search;

			// Remove trailing slash
			if ( url.endsWith( '/' ) )
				url = url.slice( 0, -1 );

			return url;
		} catch ( error ) {
			// Fallback for malformed URLs - return original with basic cleanup
			return repoUrl.replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		}
	}

	return {
		number,
		bareRepoUrl,
	};
} )();
