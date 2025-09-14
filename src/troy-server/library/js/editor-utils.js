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
 * @module troy-server-editor-utils
 * @description Utilities for the Troy Server plugin and theme editor.
 * @since 0.0.1184
 * @link
 */
window.troyServerEditorUtils = ( () => {

	/**
	 * Recursively assigns properties from the source object to the defaults
	 * object, ensuring that all existing properties in the source are assigned.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} defaults The default object to assign properties to.
	 * @param {Object} source   The source object to assign properties from.
	 * @returns {Object} The resulting object with assigned properties.
	 */
	function assignDeepObject( defaults, source ) {
		const result = {};

		for ( const key in defaults ) {
			const defVal = defaults[ key ];
			const srcVal = source?.[ key ];

			if ( defVal && 'object' === typeof defVal && ! Array.isArray( defVal ) ) {
				result[ key ] = assignDeepObject( defVal, srcVal );
			} else {
				// null == undefined, and null == null, nothing else.
				result[ key ] = null == srcVal ? defVal : srcVal;
			}
		}

		return result;
	}

	/**
	 * Sorts an array of version objects based on their semantic version numbers,
	 * including pre-release versions.
	 *
	 * This function parses each version string using a regular expression to
	 * extract its numeric, pre-release, and build components and then compares
	 * them to order the array.
	 *
	 * It supports complex versions such as (in order) "1.4.9", "1.5.0-alpha",
	 * "1.5.0-alpha2", and "1.5.0", ensuring that pre-release versions are sorted
	 * correctly relative to final releases.
	 *
	 * 1.1 is not allowed. Write 1.1.0 instead.
	 *
	 * This function is based on the PHP versioning logic from the
	 * `versioning.c` file in the PHP source code, adapted for JavaScript.
	 *
	 * @since 0.0.1184
	 * @link https://gist.github.com/sybrew/fd5b447d1a9ccd4a3344d8267828d7a1
	 * @see https://github.com/php/php-src/blob/php-8.4.8/ext/standard/versioning.c#L87-L99
	 *
	 * @param {Array<Object>} versions An array of objects, each containing a
	 *                                 version string property (index).
	 * @param {string}        orderby  The order to sort the versions by.
	 *                                 Can be 'ASC' for ascending or 'DESC' for
	 *                                 descending order. Defaults to 'ASC'.
	 * @param {string}        index    The property name to use for extracting
	 *                                 the version string from each object.
	 * @return {Array<Object>} The sorted array of version objects.
	 */
	function sortVersions( versions = [], orderby = 'ASC', index = 'version' ) {

		// Copied from https://semver.org/
		const versionRegex = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/;

		/**
		 * Mapping of pre-release identifiers to their respective numeric values.
		 * This is used to ensure that pre-release versions are sorted correctly.
		 *
		 * - unknown identifiers are considered even earlier than `dev`.
		 * - `dev` and `alpha` are considered the earliest pre-release versions.
		 * - `beta` and `b` are considered the middle pre-release versions.
		 * - `rc` and `a` are considered the latest pre-release versions.
		 * - Final releases (no pre-release identifier) are considered latest.
		 */
		const versionMapping = {
			dev:      0,
			alpha:    1,
			a:        1,
			beta:     2,
			b:        2,
			rc:       3,
			'#':      4,
			pl:       5,
			p:        5,
			_unknown: 6,
		};

		const parseVersionIndex = version => {
			const match = version.match( versionRegex );

			if ( ! match ) return [ 0, 0, 0, 0 ];

			const major = parseInt( match[ 1 ], 10 );
			const minor = parseInt( match[ 2 ], 10 );
			const patch = parseInt( match[ 3 ], 10 );

			let prerelease = [];

			if ( match[ 4 ] ) {
				const parts = match[ 4 ].split( '.' );
				parts.forEach( part => {
					const letterMatch = part.match( /^[a-zA-Z]+/ );
					if ( letterMatch ) {
						const letter = letterMatch[0].toLowerCase();
						const numberPart = part.slice( letter.length ).replace( /^-+|-+$/g, '' );
						prerelease.push( versionMapping?.[ letter ] || versionMapping._unknown );
						prerelease.push( numberPart ? parseInt( numberPart, 10 ) : 0 );
					} else {
						prerelease.push( -1 );
					}
				} );
			} else {
				prerelease.push( 7 ); // Final release indicator (higher than pre-releases)
			}

			const build = match[ 5 ]?.split( '.' ).map( s => parseInt( s, 10 ) ) || [];

			return [ major, minor, patch, ...prerelease, ...build ];
		}

		const direction = orderby.toUpperCase() === 'DESC' ? -1 : 1;

		return versions.sort( ( a, b ) => {
			const versionA = parseVersionIndex( a[ index ] );
			const versionB = parseVersionIndex( b[ index ] );

			for ( let i = 0; i < Math.max( versionA.length, versionB.length ); i++ )
				if ( versionA[ i ] !== versionB[ i ] )
					return ( versionA[ i ] - versionB[ i ] ) * direction;

			return 0;
		} );
	}

	/**
	 * Debounces the input function.
	 *
	 * @since 0.0.1184
	 *
	 * @param {CallableFunction} func    The function to debounce.
	 * @param {number}           timeout The debounce timeout in milliseconds.
	 * @return {Function} The debounced function.
	 */
	function debounce( func, timeout = 0 ) {
		let timeoutId;
		return ( ...args ) => {
			clearTimeout( timeoutId );
			return {
				timeoutId: timeoutId = setTimeout( () => func( ...args ), timeout ),
				cancel:    () => clearTimeout( timeoutId ),
			};
		};
	}

	/**
	 * Delays script execution. The caller must be asynchronous.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Int} ms The milliseconds to delay script execution.
	 * @return {Promise}
	 */
	function delay( ms ) {
		return new Promise( resolve => setTimeout( resolve, ms ) );
	}

	/**
	 * Converts a number of bytes into a human-readable string using binary
	 * prefixes (KiB, MiB, etc.).
	 *
	 * @param {number} bytes        The number of bytes to convert.
	 * @param {number} [decimals=2] The number of decimal places to include in the output.
	 * @returns {string} A formatted string with the appropriate binary prefix.
	 *                   Returns '0 bytes' if the input is 0 or falsy.
	 */
	function bytesToIbiBytes( bytes, decimals = 2 ) {

		if ( ! bytes ) return '0 bytes';

		const k     = 1024;
		const sizes = [ 'bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB' ];
		const i     = Math.floor( Math.log( bytes ) / Math.log( k ) )

		return `${parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( decimals ) ).toLocaleString()} ${sizes[i]}`
	}

	/**
	 * Sanitizes a URL to only include the domain, port, path, and query components.
	 * Supports various repository URL formats including IPv6 addresses.
	 *
	 * This won't return a proper fully qualified URL with a protocol scheme.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} url The URL to sanitize.
	 * @returns {string} The sanitized URL containing only domain/path/query.
	 */
	function sanitizeRepoUrl( url ) {

		if ( ! url || typeof url !== 'string' )
			return '';

		try {
			// This removes any protocol scheme and leading slashes, keeping everything else
			const fullyQualifiedUrl = url
				.trim()
				.replace( /^[\s\\\/]+|[\s\\\/]+$/g, '' )
				.replace( /^(?:\w*:)?(?:\/\/)?(.*?)$/, 'https://$1/' );

			const urlObj = new URL( fullyQualifiedUrl );

			// Construct the sanitized URL: hostname + pathname + search (no port)
			let url = urlObj.hostname;

			// Add port if it exists
			if ( urlObj.port )
				url += `:${urlObj.port}`;

			// Add pathname if it exists and is not just '/'
			if ( urlObj.pathname && urlObj.pathname !== '/' )
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
			return url.replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
		}
	}

	return {
		assignDeepObject,
		sortVersions,
		debounce,
		delay,
		bytesToIbiBytes,
		sanitizeRepoUrl,
	};
} )();
