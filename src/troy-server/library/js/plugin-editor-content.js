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
	const { registerPlugin } = wp.plugins;
	const {
		useEffect,
		useState,
	} = wp.element;
	const { __ } = wp.i18n;
	const {
		useSelect,
		useDispatch,
	} = wp.data;
	const { store: blockEditorStore } = wp.blockEditor;
	const apiFetch = wp.apiFetch;
	const { addQueryArgs } = wp.url;

	/**
	 * We store our plugin content in custom tables. Here, we reset the template
	 * to the default one and refill it with the plugin content.
	 *
	 * @since 0.0.1184
	 *
	 * @returns {null} Nothing is rendered visually.
	 */
	function renderPluginTemplate() {

		const {
			data:         storeData,
			isLoading:    isStoreLoading,
			latestVersion,
			setValue,
			setContent,
		} = troyServerGetPluginStore();

		const [ hasInitialized, setHasInitialized ] = useState( false );

		const template = useSelect(
			select => select( blockEditorStore ).getSettings()?.template,
			[],
		);

		const {
			synchronizeTemplate,
			setTemplateValidity,
		} = useDispatch( blockEditorStore );

		const { removeNotice } = useDispatch( 'core/notices' );
		const { editPost }     = useDispatch( 'core/editor' );

		const { __unstableMarkLastChangeAsPersistent } = useDispatch( 'core' );

		const currentTabsBlock = () => wp.data.select( blockEditorStore )
			.getBlocks()
			.find( b => b.name === 'troy-server/plugin-tabs' );

		// Listen to readme state changes and affect the store and content accordingly.
		useEffect(
			() => {
				if (
					   ! hasInitialized
					|| ! storeData.plugin_id
					|| 'readme' !== storeData.builder_type
				) return;

				if ( ! latestVersion ) {
					// Empty all content tabs if no latest version is set (e.g., all versions are marked for removal)
					Object.keys( troyPluginEditorData.contentTabs ).forEach( tabId => {
						setContent( tabId, '' );
					} );
					return;
				}

				// Prepare abort controller to cancel in-flight requests on dependency changes
				const controller = new AbortController();
				let cancelled = false;

				( async () => {
					try {
						const response = await apiFetch( {
							url:    addQueryArgs(
								troyPluginEditorData.restUrls.getReadmeData,
								{
									plugin_id: storeData.plugin_id,
									version:   latestVersion,
								},
							),
							method: 'GET',
							signal: controller.signal,
						} );

						// If cancelled or builder type changed in-flight, stop processing
						if ( cancelled || 'readme' !== storeData.builder_type ) return;

						const { contents, headers } = response;

						// Update page title if empty and we have plugin_name
						// We should not do this when the title updates, but only when a new readme is fetched.
						if ( headers?.plugin_name && ! storeData.name?.trim().length ) {
							// We shouldn't affect the store's name, for it'll cause an infinite loop
							// Let's rely on WordPress's editor to update the title
							editPost( { title: headers.plugin_name } );
						}

						if ( headers?.short_description && ! storeData.short_description?.trim().length )
							setValue( 'short_description', headers.short_description );

						// Only store, another effect will handle the actual content update
						if ( contents ) {
							Object.keys( troyPluginEditorData.contentTabs ).forEach( tabId => {
								setContent( tabId, contents[ tabId ] || '' );
							} );
						}
					} catch ( error ) {
						if ( ! cancelled )
							console.error( 'Failed to fetch README data:', error );
					}
				} )();

				// Cleanup: abort request if dependencies change
				return () => {
					cancelled = true;
					controller.abort();
				};
			},
			[ hasInitialized, latestVersion, storeData.plugin_id, storeData.builder_type ],
		);

		// Repaint template when content actually changes or when switching modes
		useEffect(
			() => {
				if (
					   ! hasInitialized
					|| ! storeData.plugin_id
				) return;

				// When switching away from readme mode, convert content to editable blocks
				if ( 'readme' !== storeData.builder_type ) {
					// Don't clear content - let it become editable
					// Only clear tabs that were truly empty to start with
					Object.keys( troyPluginEditorData.contentTabs ).forEach( tabId => {
						const content = storeData.contents?.[ tabId ] || '';

						// Only clear if content is genuinely empty (no meaningful readme content)
						if ( ! content.length )
							setContent( tabId, '' );

						// Otherwise preserve the content - it becomes editable in editor mode
					} );
				}

				wp.data.select( blockEditorStore )
					.getBlocks()
					.find( b => b.name === 'troy-server/plugin-tabs' )
					?.innerBlocks
					.forEach(
						tabBlock => {
							const tabId = tabBlock.attributes.troyServerTabId;

							if ( 'readme' === storeData.builder_type ) {
								// In readme mode, use content from store, unless empty.
								if ( tabId in storeData.contents ) {
									wp.data.dispatch( blockEditorStore ).replaceInnerBlocks(
										tabBlock.clientId,
										storeData.contents[ tabId ].length
											? wp.blocks.parse( storeData.contents[ tabId ] )
											: [
												wp.blocks.createBlock(
													'core/paragraph',
													{
														content:   __( 'No content found… upload a plugin ZIP with a valid readme.', 'troy-server' ),
														className: 'troy-server-no-content-message',
														lock:      'all', // lol, doesn't do much
													},
												),
											],
									);
								}
							} else {
								// When in/going-to editor mode, use content from store if available, otherwise empty paragraph
								const content = storeData.contents?.[ tabId ];
								if ( content?.length ) {
									// Only apply if current content differs from expected
									if ( wp.blocks.serialize( tabBlock.innerBlocks || [] ) !== content ) {
										wp.data.dispatch( blockEditorStore ).replaceInnerBlocks(
											tabBlock.clientId,
											wp.blocks.parse( content ),
										);
									}
								} else {
									// Only create empty paragraph if truly no content
									wp.data.dispatch( blockEditorStore ).replaceInnerBlocks(
										tabBlock.clientId,
										[ wp.blocks.createBlock( 'core/paragraph' ) ],
									);
								}
							}
						}
					);
			},
			[ hasInitialized, storeData.builder_type, storeData.plugin_id, storeData.contents ],
		);

		// Handle locking of content blocks when in readme mode
		useEffect(
			() => {
				if ( ! hasInitialized || ! storeData.builder_type ) return;

				const lockTabBlocks = blocks => {
					blocks.forEach( block => {
						// Lock classic editor blocks when in readme mode
						if ( 'readme' === storeData.builder_type ) {
							if ( 'core/freeform' === block.name || 'core/html' === block.name )
								wp.data.dispatch( blockEditorStore ).updateBlockAttributes(
									block.clientId,
									{ lock: 'all' }, // Lock all actions (move, remove, edit -- doesn't prevent editing, bug in WP)
								);
						} else {
							wp.data.dispatch( blockEditorStore ).updateBlockAttributes(
								block.clientId,
								{ lock: undefined }, // Remove all locks
							);
						}

						// Process inner blocks recursively
						if ( block.innerBlocks?.length )
							lockTabBlocks( block.innerBlocks );
					} );
				};

				currentTabsBlock()?.innerBlocks.forEach( tabBlock => {
					if ( 'troy-server/plugin-tab-content' === tabBlock.name )
						lockTabBlocks( tabBlock.innerBlocks );
				} );
			},
			[ hasInitialized, storeData.builder_type ],
		);

		// Reset the template when the post is loaded or the template changes.
		useEffect(
			() => {
				if ( hasInitialized || ! template || isStoreLoading )
					return;

				// We cannot restore autosaves (yet).
				removeNotice( 'wpEditorAutosaveRestore' );
				removeNotice( 'autosave-exists' );

				// Reset the template validity.
				synchronizeTemplate();
				setTemplateValidity( true );

				// Reset the unsaved changes warning state by marking the last change as persistent
				__unstableMarkLastChangeAsPersistent?.();

				setHasInitialized( true );
			},
			[ template, isStoreLoading ],
		);

		return null;
	}

	registerPlugin(
		'troy-server-editor-plugin-template',
		{ render: renderPluginTemplate },
	);
} )( window.wp );
