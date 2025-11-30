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
 * @module troyServerEditorPluginsBlocksLogo
 * @description Plugin logo block for Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const { registerBlockType } = wp.blocks;
	const {
		createElement: JSX,
		useState,
		useEffect,
	} = wp.element;
	const { __ } = wp.i18n;
	const { useBlockProps } = wp.blockEditor;
	const apiFetch = wp.apiFetch;
	const { addQueryArgs } = wp.url;

	registerBlockType(
		'troy-server/plugin-logo',
		{
			title: __( 'Plugin Logo', 'troy-server' ),
			icon:  'format-image',
			edit:  () => {
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-logo',
				} );

				const {
					data: storeData,
				} = troyServerGetPluginStore();

				const [ placeholderUri, setPlaceholderUri ] = useState( null );

				useEffect(
					() => {
						// Don't fetch if we have a real logo or if we already have a placeholder URI.
						if ( placeholderUri || storeData.logo_uri )
							return;

						// Prepare abort controller to cancel in-flight requests on dependency changes
						const controller = new AbortController();
						let cancelled = false;

						( async () => {
							try {
								const response = await apiFetch( {
									url:    addQueryArgs(
										troyPluginEditorData.restUrls.getPlaceholderLogo,
										{
											width:  192,
											height: 192,
										},
									),
									method: 'GET',
									signal: controller.signal,
								} );

								// If cancelled or logo_uri was set in-flight, stop processing
								if ( cancelled || storeData.logo_uri )
									return;

								setPlaceholderUri( `data:${response.mime_type};base64,${response.image_data}` );
							} catch ( error ) {
								if ( ! cancelled )
									console.error( 'Failed to fetch placeholder logo:', error );
							}
						} )();

						return () => {
							cancelled = true;
							controller.abort();
						};
					},
					[ storeData.logo_uri ],
				);

				const displayUri = storeData.logo_uri || placeholderUri;

				return JSX(
					'div',
					{
						className: 'troy-server-block-plugin-logo-wrap',
					},
					displayUri && JSX(
						'img',
						{
							...blockProps,
							src:       displayUri,
							className: 'troy-server-block-plugin-logo',
							alt:       __( 'Plugin Logo', 'troy-server' ),
							width:     96,
							height:    96,
						},
					),
				);
			},
		},
	);
} )( window.wp );
