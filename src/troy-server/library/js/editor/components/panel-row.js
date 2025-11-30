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
 * @description PanelRow component for the Troy Server editor.
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
	 * Panel Row component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}    props.label       The label for the panel row.
	 *     @param {ReactNode} props.children    The child components to render.
	 *     @param {string}    props.className   Additional CSS classes.
	 *     @param {Function}  props.onRefChange Callback for ref changes.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PanelRow( { label, children, className, onRefChange, ...props } ) {
		return JSX(
			HStack,
			{
				ref:       onRefChange,
				className: `troy-server-panel-row ${className || ''}`,
				...props,
			},
			label && JSX(
				'div',
				{
					className: children
						? 'troy-server-panel__row-label'
						: 'troy-server-panel__row-label--no-control',
				},
				label,
			),
			children && JSX(
				'div',
				{ className: 'troy-server-panel__row-control' },
				children,
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerEditorComponents, { PanelRow } );
} )( window.wp );
