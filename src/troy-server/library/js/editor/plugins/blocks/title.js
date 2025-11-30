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
 * @module troyServerEditorPluginsBlocksTitle
 * @description Plugin title block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const {
		createElement: JSX,
		useEffect,
	} = wp.element;
	const { __ } = wp.i18n;
	const {
		useSelect,
		useDispatch,
	} = wp.data;
	const {
		useBlockProps,
		PlainText,
	} = wp.blockEditor;

	registerBlockType(
		'troy-server/plugin-title',
		{
			edit: ( { attributes, setAttributes } ) => {
				const { content: titleInputValue } = attributes;
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-title',
				} );
				const { editPost } = useDispatch( 'core/editor' );
				const postTitle = useSelect(
					select => select( 'core/editor' ).getEditedPostAttribute( 'title' ),
					[],
				);

				// Update post title when block content changes
				useEffect(
					() => {
						if ( titleInputValue !== postTitle )
							setAttributes( { content: postTitle } );
					},
					[ postTitle ],
				);

				return JSX(
					'h1',
					{
						className: 'troy-server-block-plugin-title-wrap',
					},
					JSX(
						PlainText,
						{
							...blockProps,
							value:       titleInputValue,
							onChange:    newContent => {
								setAttributes( { content: newContent } );
								editPost( { title: newContent } );
							},
							placeholder: __( 'Enter plugin title here…', 'troy-server' ),
						},
					),
				);
			},
			// We don't save the content of this block; handled via select( 'core/editor' ).getEditedPostAttribute( 'title' )
		},
	);
} )( window.wp );
