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
 * @description createPopoverProps utility for the Troy Server editor.
 * @since 0.6.1184
 */
( () => {

	// Initialize the shared namespace.
	window.troyServerEditorComponents = window.troyServerEditorComponents || {};

	/**
	 * Creates popover props with consistent settings.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} anchor The anchor element for the popover.
	 * @param {string} title  The title for the popover.
	 * @returns {Object} The popover props object.
	 */
	function createPopoverProps( anchor, title ) {
		return {
			anchor,
			'aria-label': title,
			headerTitle:  title,
			placement:    'left-start',
			offset:       36,
			shift:        true,
			className:    'troy-server-popover',
		};
	}

	// Export to shared namespace.
	Object.assign( window.troyServerEditorComponents, { createPopoverProps } );
} )();
