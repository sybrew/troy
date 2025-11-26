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
 * @module troyServerTiming
 * @description Timing and async control utilities for the Troy Server plugin.
 * @since 0.0.1184
 */
window.troyServerTiming = ( () => {

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
	 * @param {number} ms The milliseconds to delay script execution.
	 * @return {Promise} A promise that resolves after the delay.
	 */
	function delay( ms ) {
		return new Promise( resolve => setTimeout( resolve, ms ) );
	}

	return {
		debounce,
		delay,
	};
} )();
