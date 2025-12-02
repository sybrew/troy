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
 * @module troyServerEditorPluginsBlocksBanner
 * @description Plugin banner block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const { createElement: JSX } = wp.element;
	const { __ } = wp.i18n;
	const { useBlockProps } = wp.blockEditor;

	registerBlockType(
		'troy-server/plugin-banner',
		{
			title: __( 'Plugin Banner', 'troy-server' ),
			icon:  'format-image',
			edit: () => {
				// Defines the editor view.
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-banner',
				} );

				const {
					data: storeData,
				} = troyServerGetPluginStore();

				return JSX(
					'div',
					{
						...blockProps,
						className: `${blockProps.className} troy-server-block-plugin-banner-wrap`,
					},
					storeData.banner_uri && JSX(
						'img',
						{
							src:       storeData.banner_uri,
							className: 'troy-server-block-plugin-banner',
							alt:       __( 'Plugin Banner', 'troy-server' ),
							width:     772,
							height:    250,
							style:     { aspectRatio: '772/250' },
						}
					),
				);
			},
			// We don't save the content of this block.
		},
	);
} )( window.wp );
