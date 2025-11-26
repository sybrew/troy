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
 * @module troyServerAssign
 * @description Assignment utilities for the Troy Server plugin.
 * @since 0.0.1184
 */
window.troyServerAssign = ( () => {

	/**
	 * Recursively assigns properties from the source object to the defaults
	 * object, ensuring that all existing properties in the source are assigned.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} defaults The default object to assign properties to.
	 * @param {Object} source   The source object to assign properties from.
	 * @return {Object} The resulting object with assigned properties.
	 */
	function deep( defaults, source ) {

		const result = {};

		for ( const key in defaults ) {
			const defVal = defaults[ key ];
			const srcVal = source?.[ key ];

			if ( defVal && 'object' === typeof defVal && ! Array.isArray( defVal ) ) {
				result[ key ] = deep( defVal, srcVal );
			} else {
				// null == undefined, and null == null, nothing else.
				result[ key ] = null == srcVal ? defVal : srcVal;
			}
		}

		return result;
	}

	return {
		deep,
	};
} )();
