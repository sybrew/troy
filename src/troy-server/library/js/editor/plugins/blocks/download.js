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
 * @module troyServerEditorPluginsBlocksDownload
 * @description Plugin download block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const { createElement: JSX } = wp.element;
	const { __ } = wp.i18n;
	const { Button } = wp.components;
	const { useBlockProps } = wp.blockEditor;

	registerBlockType(
		'troy-server/plugin-download',
		{
			edit: () => {
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-download',
				} );

				const {
					data: storeData,
					latestVersion,
				} = troyServerGetPluginStore();

				const canDownload = latestVersion
					&& [ 'public', 'unlisted' ].includes( storeData.status );

				return JSX(
					'div',
					blockProps,
					canDownload
						? JSX(
							Button,
							{
								variant: 'primary',
								href:    storeData.versions?.find( v => v.version === latestVersion )?.download_uri,
								onClick: event => {
									// We're in edit mode, so we cannot use href directly.
									// Let's use this trick to trigger a download.
									const a = document.createElement( 'a' );
									a.href   = event.currentTarget.href;
									a.target = '_blank';
									a.rel    = 'noopener noreferrer';

									document.body.appendChild( a );
									a.click();
									document.body.removeChild( a );
								},
								target:  '_blank',
								rel:     'noopener noreferrer',
								icon:    'download',
								size:    'default',
							},
							__( 'Download', 'troy-server' ),
						)
						: JSX(
							Button,
							{
								variant:  'primary',
								disabled: true,
								icon:     'download',
								size:     'default',
							},
							__( 'Download', 'troy-server' ),
						),
				);
			},
		},
	);
} )( window.wp );
