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
 * @module troyServerEscape
 * @description Escaping utilities for the Troy Server plugin.
 * @since 0.0.1184
 */
window.troyServerEscape = ( () => {

	let _decodeEntitiesDOMParser = void 0;

	const _decodeEntitiesMap = {
		'<':  '&#x3C;',
		'>':  '&#x3E;',
		'\\': '&#x5C;',
	};

	const _escapeStringMap = {
		'&':  '&#x26;',
		'<':  '&#x3C;',
		'>':  '&#x3E;',
		'"':  '&#x22;',
		"'":  '&#x27;',
		'\\': '&#x5C;',
		'/':  '&#x2F;',
	};

	/**
	 * Mimics PHP's strip_tags in a rudimentary form, without allowed tags.
	 *
	 * PHP's version checks every single character to comply with the allowed tags,
	 * whereas we simply use regex. This acts as a carbon-copy, regardless.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} str The text to strip tags from.
	 * @return {string} The stripped tags.
	 */
	function tags( str ) {
		return str.length && str.replace( /(<([^>]+)?>?)/ig, '' ) || '';
	}

	/**
	 * Decodes string entities securely.
	 *
	 * Uses a fallback when the browser doesn't support DOMParser.
	 * This fallback sends out exactly the same output.
	 *
	 * The rendering of this function is considered secure against XSS attacks.
	 * However, you must consider the output as insecure HTML, and may only append via innerText.
	 *
	 * @since 0.0.1184
	 * @see string
	 *
	 * @credit <https://stackoverflow.com/questions/1912501/unescape-html-entities-in-javascript/34064434#34064434>
	 * Modified to allow <, >, and \ entities, and cached the parser.
	 *
	 * @param {string} str The text to decode.
	 * @return {string} The decoded text.
	 */
	function entities( str ) {

		if ( ! str?.length )
			return '';

		_decodeEntitiesDOMParser ||= new DOMParser();

		return _decodeEntitiesDOMParser.parseFromString(
			// Prevent "tags" from being stripped. When not string, return ''.
			str.replace?.( /[<>\\]/g, m => _decodeEntitiesMap[ m ] ) || '',
			'text/html',
		).documentElement.textContent;
	}

	/**
	 * Escapes input string for safe HTML output.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} str The string to escape.
	 * @return {string} The escaped string.
	 */
	function string( str ) {

		if ( ! str?.length )
			return '';

		// When not string, return ''
		return str.replace?.( /[&<>"'\\\/]/g, m => _escapeStringMap[ m ] ) || '';
	}

	return {
		tags,
		entities,
		string,
	};
} )();
