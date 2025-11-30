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
 * @description StyledHelp component for the Troy Server editor.
 * @since 0.6.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	// Initialize the shared namespace.
	window.troyServerEditorComponents = window.troyServerEditorComponents || {};

	const { createElement: JSX } = wp.element;

	/**
	 * StyledHelp component mimicking the WordPress components StyledHelp styling.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}           props.className Additional CSS classes.
	 *     @param {string}           props.id        Element ID.
	 *     @param {React.ReactNode}  props.children  Help text content.
	 * }
	 * @returns {JSX.Element} The styled help element.
	 */
	function StyledHelp( { className = '', id, children, ...props } ) {
		return JSX(
			'p',
			{
				className: `components-base-control__help ${className}`,
				id,
				style: {
					marginTop:    '8px',
					marginBottom: '0',
					fontSize:     '12px',
					fontStyle:    'normal',
					color:        '#757575',
				},
				...props,
			},
			children,
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerEditorComponents, { StyledHelp } );
} )( window.wp );
