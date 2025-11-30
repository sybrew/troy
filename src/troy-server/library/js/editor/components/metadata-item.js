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
 * @module troyServerEditorComponents
 * @description MetadataItem component for the Troy Server editor.
 * @since 0.6.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	// Initialize the shared namespace.
	window.troyServerEditorComponents = window.troyServerEditorComponents || {};

	const { createElement: JSX } = wp.element;

	// Experimental components
	const HStack = wp.components?.HStack || wp.components?.__experimentalHStack;

	/**
	 * Metadata Item component for displaying key-value pairs.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}  props.label The label text to display.
	 *     @param {string}  props.value The value text to display.
	 *     @param {?string} props.state The state for styling ('warning', 'error').
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function MetadataItem( { label, value, state } ) {

		let stateClass = '';

		switch ( state ) {
			case 'warning':
				stateClass = 'troy-server-metadata-item--warning';
				break;
			case 'error':
				stateClass = 'troy-server-metadata-item--error';
		}

		return JSX(
			HStack,
			{
				spacing:   2,
				alignment: 'left',
				className: `troy-server-metadata-item ${stateClass}`,
			},
			JSX(
				'span',
				{
					className: 'troy-server-metadata-item__label',
				},
				label,
			),
			JSX(
				'span',
				{ className: 'troy-server-metadata-item__value' },
				value,
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerEditorComponents, { MetadataItem } );
} )( window.wp );
