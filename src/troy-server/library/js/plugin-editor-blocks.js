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

( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const {
		createElement: JSX,
		useEffect,
		useState,
		useMemo,
	} = wp.element;
	const {
		__,
		sprintf,
	} = wp.i18n;
	const {
		useSelect,
		useDispatch,
	} = wp.data;
	const { Button } = wp.components;
	const {
		InnerBlocks,
		useBlockProps,
		useInnerBlocksProps,
		store: blockEditorStore,
		DefaultBlockAppender,
		PlainText,
	} = wp.blockEditor;
	const apiFetch = wp.apiFetch;

	registerBlockType(
		'troy-server/plugin-headergroup',
		{
			title:    __( 'Plugin Header', 'troy-server' ),
			icon:     'admin-post',
			edit: () => {
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-headergroup',
				} );
				const innerBlocksProps = useInnerBlocksProps( blockProps, {} );

				return JSX( 'div', innerBlocksProps );
			},
			save: () => {
				return null; // We don't save the content of this block.
			},
		},
	);

	registerBlockType(
		'troy-server/plugin-heading',
		{
			title:    __( 'Plugin Heading', 'troy-server' ),
			icon:     'admin-post',
			edit: () => {
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-heading',
				} );
				const innerBlocksProps = useInnerBlocksProps( blockProps, {} );

				return JSX( 'div', innerBlocksProps );
			},
			save: () => {
				return null; // We don't save the content of this block.
			},
		},
	);

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
			save: () => {
				return null; // We don't save the content of this block.
			},
		},
	);

	registerBlockType(
		'troy-server/plugin-tabs',
		{
			providesContext: {
				'troy-server/plugin-tabs/activeTab': 'activeTab',
			},
			edit: ( { attributes, setAttributes, clientId } ) => {
				const { activeTab } = attributes;
				const { dynamicInnerBlocks } = useSelect(
					select => ( {
						dynamicInnerBlocks: select( blockEditorStore ).getBlocks( clientId ),
					} ),
					[ clientId ],
				);

				const {
					data: storeData,
					allTabsEmpty,
				} = troyServerGetPluginStore();

				// Auto-switch to first available tab if current tab is hidden
				useEffect(
					() => {
						if (
							   'readme' !== storeData.builder_type
							|| ! dynamicInnerBlocks.length
						) return;

						const currentTabBlock   = dynamicInnerBlocks[ activeTab ];
						const currentTabContent = storeData.contents?.[ currentTabBlock?.attributes?.troyServerTabId ];

						if ( ! currentTabContent?.length ) {
							// Find first available tab
							const firstAvailableTabIndex = dynamicInnerBlocks.findIndex(
								block => storeData.contents?.[ block.attributes?.troyServerTabId ]?.length > 0,
							);

							if ( -1 !== firstAvailableTabIndex && firstAvailableTabIndex !== activeTab ) {
								// Small delay to ensure store data has stabilized and prevent race conditions
								const timeoutId = setTimeout(
									() => {
										setAttributes( { activeTab: firstAvailableTabIndex } );
									},
									10,
								);

								return () => clearTimeout( timeoutId );
							}
						}
					},
					[ activeTab, dynamicInnerBlocks, storeData.builder_type, storeData.contents ],
				);

				// We share the blockProps with the inner blocks.
				// Oddly, this prevents the editor from jumping sporadically when switching tabs.
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-tabs-container',
				} );

				return JSX(
					'div',
					{ ...blockProps },
					JSX(
						'div',
						{
							className: 'troy-server-block-plugin-tabs-nav',
						},
						dynamicInnerBlocks.map(
							( block, index ) => {
								// Hide the tab if it's empty. But, show if all tabs are empty; a11y: show context.
								if ( 'readme' === storeData.builder_type && ! allTabsEmpty )
									if ( ! storeData.contents?.[ block.attributes?.troyServerTabId ]?.length )
										return null;

								return JSX(
									Button,
									{
										key:         block.clientId,
										isPrimary:   activeTab === index,
										isSecondary: activeTab !== index,
										onClick:     () => {
											setAttributes( { activeTab: index } );
										},
										className:   'troy-server-block-plugin-tab-button',
									},
									block.attributes.title || `Tab ${ index + 1 }`
								);
							},
						),
					),
					JSX(
						'div',
						useInnerBlocksProps(
							blockProps,
							{
								allowedBlocks:  [ 'troy-server/plugin-tab-content' ],
								template:       Object.values( troyPluginEditorData.contentTabs ).map( tab => [
									'troy-server/plugin-tab-content',
									{
										title:           tab.title,
										troyServerTabId: tab.id,
									},
								] ),
								templateLock:   true,
								renderAppender: false, // Don't allow adding new tabs here
							},
						),
					),
				);
			},
			save: () => {
				// Save only the inner blocks' content.
				return JSX(
					'div',
					useInnerBlocksProps.save(
						useBlockProps.save(),
					),
				);
			},
		},
	);

	/**
	 * Renders the tab panel in editor mode.
	 *
	 * @param {object}  props          The component props.
	 * @param {boolean} props.isActive Whether the tab is active.
	 * @returns {JSX.Element} The rendered component.
	 */
	function TabViewEditorMode( { isActive } ) {

		const blockProps = useBlockProps( {
			className: `troy-server-block-plugin-tab-panel ${
				isActive ? 'is-active' : 'is-inactive'
			}`,
			style: {
				...( ! isActive && { display: 'none' } ),
			},
		} );

		const innerBlocksProps = useInnerBlocksProps(
			blockProps,
			{
				template:       [ [ 'core/paragraph', {} ] ],
				templateLock:   false,
				renderAppender: DefaultBlockAppender,
			},
		);

		return JSX( 'div', innerBlocksProps );
	}

	/**
	 * Renders the tab panel in readme mode.
	 *
	 * @param {object}  props                The component props.
	 * @param {boolean} props.isActive       Whether the tab is active.
	 * @param {object}  props.storeData      The plugin store data.
	 * @param {boolean} props.allTabsEmpty   Whether all tabs are empty.
	 * @param {object}  props.attributes     The block attributes.
	 * @returns {JSX.Element} The rendered component.
	 */
	function TabViewReadmeMode( { isActive, storeData, allTabsEmpty, attributes } ) {

		const isEmpty        = ! storeData.contents?.[ attributes?.troyServerTabId ]?.length;
		const displayContent = isActive && ( ! isEmpty || allTabsEmpty );

		return JSX(
			'div',
			{
				// Force remount when content state changes via key that checks content state; not visibility
				// This key prevents race conditions during auto-switching while still allowing remounting when needed
				key: `${attributes?.troyServerTabId}-${+isEmpty}-${+allTabsEmpty}`,
				...useInnerBlocksProps(
					useBlockProps( {
						className: `troy-server-block-plugin-tab-panel ${
							isActive ? 'is-active' : 'is-inactive'
						}`,
						style: {
							...( ! displayContent && { display: 'none' } ),
						},
					} ),
					{
						templateLock:   'all',
						renderAppender: false,
					},
				),
			}
		);
	}

	registerBlockType(
		'troy-server/plugin-tab-content',
		{
			usesContext: [ 'troy-server/plugin-tabs/activeTab' ],
			edit: ( { clientId, context, attributes } ) => {
				const index = useSelect(
					select => select( 'core/block-editor' ).getBlockIndex( clientId ),
					[ clientId ],
				);

				const {
					data: storeData,
					allTabsEmpty,
				} = troyServerGetPluginStore();

				const isActive = index === context[ 'troy-server/plugin-tabs/activeTab' ];

				if ( 'readme' === storeData.builder_type ) {
					return JSX(
						TabViewReadmeMode,
						{
							isActive,
							storeData,
							allTabsEmpty,
							attributes,
						},
					);
				}

				return JSX(
					TabViewEditorMode,
					{
						isActive,
					},
				);
			},
			save: () => {
				// NOTE: We do not sync the block content with troyPluginEditorStoreData.
				// Instead, we save the content of the InnerBlocks on save.
				// Note that the contents will be overwritten when the builder_type changes,
				// such as the 'readme' type from the 'post' type.
				return JSX(
					'div',
					useInnerBlocksProps.save(
						useBlockProps.save( {
							className: 'troy-server-block-plugin-tab-panel',
						} ),
					),
				);
			},
		},
	);

	registerBlockType(
		'troy-server/plugin-banner',
		{
			title: __( 'Plugin Banner', 'troy-server' ),
			icon: 'format-image',
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
						className: 'troy-server-block-plugin-banner-wrap',
					},
					storeData.banner_uri && JSX(
						'img',
						{
							...blockProps,
							src:       storeData.banner_uri,
							className: 'troy-server-block-plugin-banner',
							alt:       __( 'Plugin Banner', 'troy-server' ),
							width:     772,
							height:    250,
							style:     {
								aspectRatio: '772/250',
							},
						}
					),
				);
			},
			// We don't save the content of this block.
		},
	);

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
						if ( storeData.logo_uri || placeholderUri ) return;

						let cancelled = false;
						const controller = new AbortController();

						( async () => {
							try {
								const response = await apiFetch( {
									url:    `${troyPluginEditorData.restUrls.getPlaceholderLogo}?width=192&height=192`,
									method: 'GET',
									signal: controller.signal,
								} );

								if ( cancelled ) return;

								setPlaceholderUri( `data:${response.mime_type};base64,${response.image_data}` );
							} catch ( error ) {
								if ( ! cancelled ) console.error( 'Failed to fetch placeholder logo:', error );
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
							value:    titleInputValue,
							onChange: newContent => {
								setAttributes( { content: newContent } );
								editPost( { title: newContent } );
							},
							placeholder: __( 'Enter plugin title here...', 'troy-server' ),
						},
					),
				);
			},
			// We don't save the content of this block; handled via select( 'core/editor' ).getEditedPostAttribute( 'title' )
		},
	);

	registerBlockType(
		'troy-server/plugin-author',
		{
			edit: ( { attributes } ) => {
				const { authorId } = attributes;
				const blockProps = useBlockProps( {
					className: 'troy-server-block-plugin-author',
				} );

				const postAuthor = useSelect(
					select => select( 'core/editor' ).getEditedPostAttribute( 'author' ),
					[],
				);

				const {
					data: storeData,
					setValue: setStoreValue,
				} = troyServerGetPluginStore();

				// Get the effective author ID (store takes precedence, then post author, then block attribute)
				const effectiveAuthorId = storeData.author_id || postAuthor || authorId;

				// Update store if we're using post author or block attribute
				useEffect(
					() => {
						if ( effectiveAuthorId !== storeData.author_id ) {
							if ( postAuthor && postAuthor !== storeData.author_id ) {
								setStoreValue( 'author_id', postAuthor );
							} else if ( authorId && authorId !== storeData.author_id ) {
								setStoreValue( 'author_id', authorId );
							}
						}
					},
					[ effectiveAuthorId, storeData.author_id, postAuthor, authorId ],
				);

				// Get author name from WordPress core data store
				const authorName = useSelect( ( select ) => {
					if ( ! effectiveAuthorId ) return '';

					const user = select( 'core' ).getUser( effectiveAuthorId );
					return user?.name || '';
				}, [ effectiveAuthorId ] );

				return JSX(
					'div',
					{
						...blockProps,
						className: 'troy-server-block-plugin-author-wrap',
					},
					JSX(
						'span',
						{
							className: `troy-server-block-plugin-author ${
								! authorName ? 'troy-server-no-content-message' : ''
							}`,
						},
						authorName
							? sprintf( __( 'By %s', 'troy-server' ), authorName )
							: __( 'Set plugin author in the sidebar...', 'troy-server' ),
					),
				);
			},
			// We don't save the content of this block; handled via store author_id
		},
	);

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

				return JSX(
					'div',
					blockProps,
					latestVersion
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
			save: () => {
				return null; // We don't save the content of this block
			},
		},
	);
} )( window.wp );
