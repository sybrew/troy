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
 * @module troyServerEditorPluginsBlocksTitleAuthorWrap
 * @description Plugin title & author wrapper block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const { createElement: JSX } = wp.element;
	const { __ } = wp.i18n;
	const {
		useBlockProps,
		useInnerBlocksProps,
	} = wp.blockEditor;

	registerBlockType(
		'troy-server/plugin-title-author-wrap',
		{
			title: __( 'Plugin Title & Author Wrapper', 'troy-server' ),
			icon:  'admin-post',
			edit:  () => {
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-title-author-wrap',
				} );
				const innerBlocksProps = useInnerBlocksProps( blockProps, {} );

				return JSX( 'div', innerBlocksProps );
			},
		},
	);
} )( window.wp );
