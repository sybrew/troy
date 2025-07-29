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
 * Plugin Editor Store for managing plugin data fetched from WordPress REST API.
 *
 * @since 0.0.1184
 * @type {Object} TroyServerPluginEditorStore {
 *     Plugin Editor Store for managing plugin data fetched from WordPress REST API.
 *
 *     @property {Function} init        Initializes the store by fetching data from the API.
 *     @property {Function} get         Gets a value from the data by property name.
 *     @property {Function} set         Sets a value in the data and updates the store.
 *     @property {Function} getContents Gets all content sections.
 *     @property {Function} getContent  Gets a specific content section.
 *     @property {Function} setContent  Sets a specific content section.
 * }
 */
const TroyServerPluginEditorStore = new class {

	/**
	 * The redux store for the plugin editor.
	 *
	 * @since 0.0.1184
	 * @private
	 * @type {Object}
	 */
	#store;

	/**
	 * The current post ID registered for the store init.
	 *
	 * @since 0.0.1184
	 * @private
	 * @type {int}
	 */
	#postIdInit;

	/**
	 * Default configuration options for a plugin.
	 *
	 * @since 0.0.1184
	 * @private
	 * @property {Object} PluginDefaults {
	 *     Default configuration options for a plugin.
	 *
	 *     @property {number|string} plugin_id               Unique plugin identifier, always a number.
	 *     @property {string}        name                    The name of the plugin.
	 *     @property {string}        slug                    A URL-friendly version of the plugin name.
	 *     @property {string}        status                  The current status of the plugin.
	 *     @property {number|string} author_id               Identifier for the author, always a number.
	 *     @property {string}        builder_type            The type of builder used for the plugin.
	 *     @property {Array<Object>} versions                An array of version objects.
	 *     @property {string}        versions.version        The version number as a string.
	 *     @property {string}        versions.type           The type of the version (default: "unreleased").
	 *     @property {number}        versions.file_size      The file size of the version in bytes.
	 *     @property {string}        versions.tested_wp      The maximum tested WordPress version.
	 *     @property {string}        versions.requires_wp    The minimum required WordPress version.
	 *     @property {string}        versions.requires_php   The minimum required PHP version.
	 *     @property {string}        versions.repo           The Troy repository header value.
	 *     @property {string}        versions.dependencies   The Troy dependencies header value.
	 *     @property {string}        versions.upgrade_notice The upgrade notice for the version.
	 *     @property {string}        versions.origin_url     The origin URL of the version.
	 *     @property {string}        versions.created_at     The creation date of the version.
	 *     @property {string}        versions.updated_at     The last updated date of the version.
	 *     @property {Boolean}       versions.remove         Whether the version is marked for removal.
	 *     @property {string}        permalink               The permanent URL for the plugin.
	 *     @property {string}        support_uri             URL for the plugin's support page.
	 *     @property {string}        short_description       A brief description of the plugin.
	 *     @property {string}        banner_uri              URI of the plugin's banner image.
	 *     @property {string}        logo_uri                URI of the plugin's logo image.
	 *     @property {Array<Object>} contributors            An array of contributor objects.
	 *     @property {number|string} contributors.user_id    Unique identifier for the contributor, always a number.
	 *     @property {string}        contributors.role       Role of the contributor in the plugin.
	 *     @property {Object}        contents                Detailed content information for the plugin.
	 *     @property {string}        contents.details        Detailed description of the plugin.
	 *     @property {string}        contents.usage          Usage instructions for the plugin.
	 *     @property {string}        contents.faq            Frequently asked questions about the plugin.
	 *     @property {string}        contents.api            API documentation for the plugin.
	 *     @property {string}        contents.changelog      Changelog information for the plugin.
	 *     @property {string}        contents.screenshots    Screenshots of the plugin in use.
	 * }
	 */
	#defaults = troyPluginEditorStoreData.defaultData;

	/**
	 * Constructor for the TroyServerPluginEditorStore.
	 *
	 * @since 0.0.1184
	 * @private
	 */
	constructor() {

		Object.freeze( this.#defaults );

		const DEFAULT_STATE = {
			editorData: { ...this.#defaults },
			isLoading:  true,
		};

		/**
		 * Synchronizes the provided data with the post meta in the WordPress editor.
		 * This way, the data is saved in the post meta and can be accessed later.
		 *
		 * @since 0.0.1184
		 * @param {Object} data The data to synchronize with the post meta.
		 */
		const syncMeta = data => {
			// Sync the data to the post meta
			wp.data.dispatch( 'core/editor' ).editPost( {
				meta: {
					troy_server_plugin_data: data,
				},
			} );
		}

		const { createReduxStore, register } = wp.data;

		this.#store = createReduxStore(
			'troy-server/plugin-editor-store',
			{
				reducer:   ( state = DEFAULT_STATE, action ) => {
					switch ( action.type ) {
						case 'SET_EDITOR_DATA':
							return {
								...state,
								editorData: action.editorData,
							};

						case 'UPDATE_EDITOR_DATA':
							// Ensure editorData exists before updating
							if ( ! state.editorData ) return state;

							return {
								...state,
								editorData: {
									...state.editorData,
									[action.key]: action.value,
								},
							};

						case 'UPDATE_NESTED_EDITOR_DATA':
							// Ensure editorData and the parent key exist before updating
							if ( ! state.editorData || ! state.editorData[ action.parentKey ] )
								return state;

							return {
								...state,
								editorData: {
									...state.editorData,
									[action.parentKey]: {
										...state.editorData[ action.parentKey ],
										[action.childKey]: action.value,
									},
								},
							};

						case 'SET_IS_LOADING':
							return {
								...state,
								isLoading: action.isLoading,
							};

						default:
							return state;
					}
				},
				actions:   {
					setEditorData:          editorData =>
						( { dispatch } ) => {
							dispatch( {
								type: 'SET_EDITOR_DATA',
								editorData,
							} );
							// There's no need to select the data again, we just set it.
							syncMeta( editorData );
						},

					updateEditorData:       ( key, value ) =>
						( { dispatch, select } ) => {
							dispatch( {
								type: 'UPDATE_EDITOR_DATA',
								key,
								value,
							} );
							// After the update, pull fresh data and sync
							syncMeta( select.getEditorData() );
						},

					updateNestedEditorData: ( parentKey, childKey, value ) =>
						( { dispatch, select } ) => {
							dispatch( {
								type: 'UPDATE_NESTED_EDITOR_DATA',
								parentKey,
								childKey,
								value,
							} );
							// After the nested update, pull fresh data and sync
							syncMeta( select.getEditorData() );
						},

					setIsLoading:           isLoading =>
						( {
							type: 'SET_IS_LOADING',
							isLoading,
						} ),
				},
				selectors: {
					getEditorData: state => state.editorData,
					isLoading:     state => state.isLoading,
				},
			},
		);

		register( this.#store );

		this.storeName = this.#store.name;
		Object.freeze( this.storeName );
	}

	/**
	 * Initializes the store by fetching data from the API and setting up event listeners.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} postId The post ID to initialize the store with.
	 *                        Note that this is a singleton store, so it supports
	 *                        only one post ID at a time. Post switching might be
	 *                        supported but is not tested yet.
	 */
	async init( postId ) {

		// Don't reinitialize if the post ID is the same as the last one.
		if ( this.#postIdInit === postId || ! postId )
			return;

		this.#postIdInit = postId;

		const { addAction, removeAction } = wp.hooks;
		const { syncStore, awaitSave }    = this.#saveHandler( postId );

		syncStore();

		// When we move to a new post, we need to deregister any actions with the previous post.
		removeAction(
			'editor.savePost',
			'troy-server/plugin-editor-store',
		);

		addAction(
			'editor.savePost',
			'troy-server/plugin-editor-store',
			// we must send a synchronous function to the action.
			( post, options ) => {
				if ( options.isAutosave ) return;

				// Spawn a new thread to avoid blocking the save thread, which, stupidly, didn't save yet.
				setTimeout( awaitSave );
			},
		);
	}

	/**
	 * Handles the save action for the plugin editor.
	 *
	 * This function sets up the necessary actions and event listeners to synchronize
	 * the store with the server data. It also handles loading states and error
	 * notifications.
	 *
	 * @since 0.0.1184
	 * @private
	 *
	 * @param {Number} postId The post ID to save the data for.
	 * @returns
	 */
	#saveHandler( postId ) {
		const { apiFetch }                    = wp;
		const { dispatch, subscribe, select } = wp.data;
		const { __ }                          = wp.i18n;
		const { assignDeepObject }            = troyServerEditorUtils;

		const { setIsLoading, setEditorData } = dispatch( this.storeName );

		const {
			createSuccessNotice,
			createErrorNotice,
			removeNotice,
		} = dispatch( 'core/notices' );

		// Track the current fetch controller to allow cancellation.
		let currentFetchController;

		/**
		 * Synchronizes the store with the server data.
		 *
		 * @since 0.0.1184
		 * @returns {boolean} True if the store was successfully synchronized, false otherwise.
		 */
		const syncStore = async () => {

			setIsLoading( true );

			// Abort any ongoing fetch before starting a new one.
			if ( currentFetchController )
				currentFetchController.abort();

			currentFetchController = new AbortController();

			let success = false;

			try {
				const response = await wp.apiFetch( {
					url:    `${troyPluginEditorData.restUrls.getEditorStore}?post_id=${postId}`,
					method: 'GET',
					signal: currentFetchController.signal,
				} );

				setEditorData( assignDeepObject(
					this.#defaults,
					response || {},
				) );

				success = true;
			} catch ( error ) {
				console.error( 'Failed to fetch plugin data:', error );
			}

			currentFetchController = null;

			setIsLoading( false );
			return success;
		}

		/**
		 * Waits for the save action to complete and then synchronizes the store with the server.
		 * This function handles the loading state and error handling.
		 *
		 * @since 0.0.1184
		 */
		const awaitSave = async () => {

			setIsLoading( true );

			const actuallySaved = await new Promise( ( resolve, reject ) => {
				let didPostSaveRequestSucceed = false;

				const unsubscribe = subscribe( () => {
					if ( select( 'core/editor' ).didPostSaveRequestSucceed() ) {
						unsubscribe();
						didPostSaveRequestSucceed = true;
						resolve();
					}
				} );

				// Seppuku after 10 seconds of uninterrupted fail.
				setTimeout(
					() => {
						if ( ! didPostSaveRequestSucceed ) {
							unsubscribe();
							reject();
						}
					},
					10000,
				);
			} )
				.then( () => true )
				.catch( () => false );

			const noticeId  = 'troy-server-post-save-editor-notice';
			const noticeOps = {
				id:            noticeId,
				isDismissible: true,
				speak:         true,
			};

			if ( ! actuallySaved ) {
				createErrorNotice(
					__( 'Failed to synchronize plugin data. Try saving again if saving failed. Otherwise, please reload the editor.', 'troy-server' ),
					noticeOps,
				);
			} else try {
				const response = await apiFetch( {
					url:    `${troyPluginEditorData.restUrls.getSaveStatus}?post_id=${postId}`,
					method: 'GET',
				} );

				if ( response?.type === 'updated' ) {
					createSuccessNotice(
						response.message
							|| __( 'Plugin data updated successfully. Fetching latest data...', 'troy-server' ),
						noticeOps,
					);

					if ( syncStore( postId ) ) { // async call
						removeNotice( noticeId );
						createSuccessNotice(
							__( 'Plugin data successfully synchronized with the server.', 'troy-server' ),
							noticeOps,
						);
					} else {
						// Don't use the same notice ID, as it can be removed automatically -- this one must stick.
						createErrorNotice(
							__( 'Failed to synchronize plugin data. Please reload the editor.', 'troy-server' ),
							{
								...noticeOps,
								id: 'troy-server-post-save-editor-notice-sync-fail',
							},
						);
					}
				} else {
					createErrorNotice(
						response?.message
							|| __( 'Failed to synchronize plugin data after saving.', 'troy-server' ),
						noticeOps,
					);
				}
			} catch ( error ) {
				console.error( 'Failed to synchronize plugin data after saving:', error );
				createErrorNotice(
					__( 'Failed to synchronize plugin data after saving.', 'troy-server' ),
					noticeOps,
				);
			}

			setIsLoading( false );
		}

		return {
			syncStore,
			awaitSave,
		};
	}

	/**
	 * Gets a value from the data by property name.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} property The property to retrieve.
	 * @return {*} The property value.
	 */
	get( property ) {
		return wp.data.select( this.storeName )
			.getEditorData()?.[ property ];
	}

	/**
	 * Sets a value in the data and updates the store.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} property The property to set.
	 * @param {*} value The value to set.
	 */
	set( property, value ) {

		if ( 'contents' === property ) {
			console.warn( 'Setting "contents" directly is not allowed. Use setContent() instead.' );
			return;
		}

		// Check against internal data to prevent unnecessary dispatches
		const currentValue = wp.data.select( this.storeName )
			.getEditorData()?.[ property ];

		if ( currentValue !== value ) {
			wp.data.dispatch( this.storeName )
				.updateEditorData( property, value );
		}
	}

	/**
	 * Gets all content sections.
	 *
	 * @since 0.0.1184
	 *
	 * @return {Object} All content sections.
	 */
	getContents() {
		return this.get( 'contents' );
	}

	/**
	 * Gets a specific content section.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} section The section name.
	 * @return {*} The section content.
	 */
	getContent( section ) {
		return this.getContents()?.[ section ];
	}

	/**
	 * Sets a specific content section.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} section The section name.
	 * @param {*} content The section content.
	 */
	setContent( section, content ) {

		const contents = this.getContents();

		if ( ! ( section in contents ) ) {
			console.warn( `Section "${ section }" does not exist in contents.` );
			return;
		}

		if ( contents[ section ] !== content ) {
			wp.data.dispatch( this.storeName )
				.updateNestedEditorData( 'contents', section, content );
		}
	}
}

/**
 * A React hook to initialize and interact with the Plugin Editor store.
 *
 * This hook manages the lifecycle of the {@link TroyServerPluginEditorStore},
 * fetches initial data based on the current WordPress post ID, and provides
 * reactive data and methods to update the store.
 *
 * @since 0.0.11840
 * @hook
 *
 * @return {Object} An object containing:
 *   @property {number|undefined}                          postId         The current WordPress post ID, or undefined if
 *                                                                        not available (e.g., for a new post).
 *   @property {PluginDefaults}                            data           Reactive editor data ({@link PluginDefaults})
 *                                                                        from the store.
 *   @property {boolean}                                   isLoading      Indicator whether the editor data is currently
 *                                                                        being fetched or processed.
 *   @property {Array<Object>}                             sortedVersions Sorted versions array, sorted in descending order
 *                                                                        by version number.
 *   @property {?string}                                   latestVersion  The latest version string, calculated from the
 *                                                                        versions array with priority: tag > beta > other.
 *                                                                        If no versions are available, this will be null.
 *   @property {function(property:string,value:any):void}  setValue       Function to update a specific top-level property
 *                                                                        in the editor data.
 *   @property {function(section:string,content:any):void} setContent     Function to update a specific section within the
 *                                                                        'contents' property of the editor data.
 */
function troyServerGetPluginStore() {
	const { useEffect, useMemo } = wp.element;
	const { useSelect } = wp.data;
	const { sortVersions } = troyServerEditorUtils;

	const postId = useSelect(
		select => select( 'core/editor' ).getCurrentPostId(),
		[],
	);

	// Initialize the store only when postId is available.
	// The store handles multiple calls to init() gracefully.
	useEffect(
		() => {
			TroyServerPluginEditorStore.init( postId );
		},
		[ postId ],
	);

	// Get editor data from the store
	const { editorData, isLoading } = useSelect(
		select => {
			const storeSelect = select( 'troy-server/plugin-editor-store' );
			return {
				editorData: storeSelect.getEditorData(),
				isLoading:  storeSelect.isLoading(),
			};
		},
		[], // Empty dependency array means this runs once and subscribes to changes
	);

	const sortedVersions = useMemo(
		() => sortVersions( editorData.versions, 'DESC' ),
		[ editorData.versions ],
	);
	const latestVersion = useMemo(
		() => {
			// Filter out versions marked for removal first.
			const availableVersions = sortedVersions?.filter( v => ! v.remove ) || [];

			return availableVersions?.find( v => 'tag' === v?.type )?.version
				|| availableVersions?.find( v => 'beta' === v?.type )?.version
				|| availableVersions?.[0]?.version
				|| null;
		},
		[ sortedVersions ],
	);
	const allTabsEmpty = useMemo(
		() => {
			if ( ! editorData.contents ) return true;

			return Object.values( editorData.contents ).every( content => ! content.length );
		},
		[ editorData.contents ],
	);

	// Return everything needed by components
	return {
		postId,
		data:         editorData, // Reactive data from the store
		isLoading:    isLoading,
		sortedVersions,
		latestVersion,
		allTabsEmpty,
		setValue:     TroyServerPluginEditorStore.set.bind( TroyServerPluginEditorStore ),
		setContent:   TroyServerPluginEditorStore.setContent.bind( TroyServerPluginEditorStore ),
	};
}
