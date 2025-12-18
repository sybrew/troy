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
 * @module troyServerSort
 * @description Sorting utilities for the Troy Server plugin.
 * @since 0.0.1184
 */
window.troyServerSort = ( () => {

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
	 * @since 1.5.1184 Added support for 2-part versions (e.g., "6.9" -> "6.9.0")
	 * @link https://gist.github.com/sybrew/fd5b447d1a9ccd4a3344d8267828d7a1
	 * @see https://github.com/php/php-src/blob/php-8.4.8/ext/standard/versioning.c#L87-L99
	 *
	 * @param {Array<Object>} items   An array of objects, each containing a
	 *                                version string property (index).
	 * @param {string}        orderby The order to sort the versions by.
	 *                                Can be 'ASC' for ascending or 'DESC' for
	 *                                descending order. Defaults to 'ASC'.
	 * @param {string}        index   The property name to use for extracting
	 *                                the version string from each object.
	 * @return {Array<Object>} The sorted array of version objects.
	 */
	function versions( items = [], orderby = 'ASC', index = 'version' ) {

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

			// Normalize 2-part versions (e.g., "6.9" → "6.9.0") for WordPress compatibility.
			const normalized = /^\d+\.\d+$/.test( version ) ? `${ version }.0` : version;
			const match      = normalized.match( versionRegex );

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
						const letter     = letterMatch[ 0 ].toLowerCase();
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
		};

		const direction = 'DESC' === orderby.toUpperCase() ? -1 : 1;

		return items.sort( ( a, b ) => {

			const versionA = parseVersionIndex( a[ index ] );
			const versionB = parseVersionIndex( b[ index ] );

			for ( let i = 0; i < Math.max( versionA.length, versionB.length ); i++ )
				if ( versionA[ i ] !== versionB[ i ] )
					return ( versionA[ i ] - versionB[ i ] ) * direction;

			return 0;
		} );
	}

	return {
		versions,
	};
} )();
