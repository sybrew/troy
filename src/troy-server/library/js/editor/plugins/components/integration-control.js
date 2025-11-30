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
 * @module troyServerPluginEditorComponents
 * @description Integration control components for GitHub and WordPress.org integrations.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const {
		createElement: JSX,
		useState,
		useMemo,
		useEffect,
		useRef,
		Fragment,
	} = wp.element;
	const { __, _n, sprintf } = wp.i18n;
	const {
		Button,
		TextControl,
		Notice,
		ExternalLink,
	} = wp.components;

	// Experimental components
	const VStack                  = wp.components?.VStack || wp.components?.__experimentalVStack;
	const HStack                  = wp.components?.HStack || wp.components?.__experimentalHStack;
	const Text                    = wp.components?.Text || wp.components?.__experimentalText;
	const InputControl            = wp.components?.InputControl || wp.components?.__experimentalInputControl;
	const InputControlSuffixWrapper = wp.components?.InputControlSuffixWrapper || wp.components?.__experimentalInputControlSuffixWrapper;
	const InspectorPopoverHeader  = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const apiFetch     = wp.apiFetch;
	const { addQueryArgs } = wp.url;

	const {
		PanelRow,
		MetadataItem,
		createPopoverProps,
	} = troyServerEditorComponents;

	const { MenuDropdown } = troyServerPluginEditorComponents;

	const {
		seen,
		unseen,
	} = troyServerIcons;

	/**
	 * Build GitHub PAT (personal access token) URL with dynamic parameters.
	 *
	 * @todo move me to integration-utils? Or move and separate all components to a new integrations folder?
	 * @todo make translatable?
	 * @since 0.0.1184
	 *
	 * @return {string} The GitHub PAT URL.
	 */
	function buildGitHubPATUrl() {

		// Get site data with optional chaining
		const siteData = wp.data.select( 'core' )?.getSite?.();
		const siteName = siteData?.title || 'WordPress Site';
		const siteUrl  = siteData?.url;
		const domain   = siteUrl ? new URL( siteUrl ).hostname : window.location.hostname;

		// Get plugin slug from editor store (always exists and is sanitized)
		let pluginSlug = TroyServerPluginEditorStore.get( 'slug' );

		const namePrefix = 'Troy Server fetch ';

		// GitHub limits to 40 characters for token names via URL parameters.
		const maxSlugLength = 40 - namePrefix.length;
		if ( pluginSlug.length > maxSlugLength )
			pluginSlug = pluginSlug.substring( 0, maxSlugLength );

		const url = new URL( 'https://github.com/settings/personal-access-tokens/new' );

		url.searchParams.set( 'name', `${namePrefix}${pluginSlug}` );
		url.searchParams.set( 'description', `This token is used to automatically get the latest tags for ${siteName}'s Troy Server on ${domain}.` );
		url.searchParams.set( 'expires_in', '365' ); // GitHub notifies before expiration
		url.searchParams.set( 'contents', 'read' );  // permission

		return url.toString();
	}

	/**
	 * Shared Integration Tags Popover - Universal component for displaying tags.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Object}   props.tags           The tags object (version => {download_url, type}).
	 *     @param {string}   props.tagsRefreshed  Timestamp of when tags were last refreshed.
	 *     @param {Boolean}  props.isFetchingTags Whether tags are currently being fetched.
	 *     @param {Function} props.onTagProcessed Callback when a tag is processed.
	 *     @param {Function} props.processTag     Function to process a single tag.
	 *     @param {Function} props.refreshTags    Function to refresh tags.
	 *     @param {Function} props.showError      Callback to report errors to parent.
	 *     @param {Function} props.showNotice     Callback to report success notices to parent.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function IntegrationTagsSection( {
		tags,
		tagsRefreshed,
		isFetchingTags,
		onTagProcessed,
		processTag,
		refreshTags,
		showError,
		showNotice,
	} ) {

		const [ isProcessing, setIsProcessing ]       = useState( {} );
		const [ isProcessingAll, setIsProcessingAll ] = useState( false );
		const abortProcessingRef                      = useRef( false );

		const markProcessing = tagName => {
			setIsProcessing( prev => ( {
				...prev,
				[ tagName ]: true,
			} ) );
		};

		const markComplete = tagName => {
			setIsProcessing( prev => ( {
				...prev,
				[ tagName ]: false,
			} ) );
		};

		const handleProcessTag = tagName => {

			if ( ! tagName ) {
				showError( __( 'Invalid tag name.', 'troy-server' ) );
				return;
			}

			markProcessing( tagName );

			processTag( tagName )
				.then( response => {
					onTagProcessed( {
						tagName,
						version: response.version,
					} );
				} )
				.catch( err => {
					showError( err.message || sprintf(
						__( 'Failed to process tag %s.', 'troy-server' ),
						tagName,
					) );
				} )
				.finally( () => {
					markComplete( tagName );
				} );
		};

		const processNewTags = async () => {

			const tagsToProcess = Object.keys( tags || {} );

			if ( ! tagsToProcess.length ) {
				showNotice( __( 'No tags available to process.', 'troy-server' ) );
				return;
			}

			setIsProcessingAll( true );
			abortProcessingRef.current = false;

			let processed = 0;
			let failed    = 0;

			for ( const tagName of tagsToProcess ) {

				if ( abortProcessingRef.current )
					break;

				markProcessing( tagName );

				try {
					await processTag( tagName )
						.then( response => {
							onTagProcessed( {
								tagName,
								version: response.version,
							} );
							processed++;
						} )
						.catch( err => {
							failed++;
							showError( err.message || sprintf(
								__( 'Failed to process tag %s.', 'troy-server' ),
								tagName,
							) );
						} )
						.finally( () => {
							markComplete( tagName );
						} );
				} catch ( err ) {
					failed++;
					markComplete( tagName );
				}
			}

			setIsProcessingAll( false );
			abortProcessingRef.current = false;

			if ( processed > 0 && failed > 0 ) {
				showNotice(
					sprintf(
						/* translators: %1$d: number of processed tags, %2$d: number of failed tags */
						_n(
							'%1$d tag processed successfully. %2$d failed.',
							'%1$d tags processed successfully. %2$d failed.',
							processed,
							'troy-server',
						),
						processed,
						failed,
					),
				);
			} else if ( processed > 0 ) {
				showNotice(
					sprintf(
						/* translators: %d: number of processed tags */
						_n(
							'%d tag processed successfully.',
							'%d tags processed successfully.',
							processed,
							'troy-server',
						),
						processed,
					),
				);
			} else if ( failed > 0 ) {
				showError(
					sprintf(
						/* translators: %d: number of failed tags */
						_n(
							'%d tag failed to process.',
							'%d tags failed to process.',
							failed,
							'troy-server',
						),
						failed,
					),
				);
			}
		};
		return JSX(
			VStack,
			{ spacing: 0 },
			JSX(
				'p',
				{
					style: { fontWeight: 'bold' },
				},
				__( 'Available tags', 'troy-server' ),
			),
			JSX(
				'div',
				{
					style: {
						display: 'flex',
						gap:     '8px',
					},
				},
				JSX(
					Button,
					{
						variant:       'secondary',
						size:          'small',
						onClick:       isProcessingAll
							? () => { abortProcessingRef.current = true; }
							: processNewTags,
						isBusy:        isProcessingAll,
						disabled:      ! isProcessingAll && Object.keys( tags || {} ).length === 0,
						isDestructive: isProcessingAll,
					},
					isProcessingAll
						? __( 'Abort Process All New', 'troy-server' )
						: __( 'Process All New', 'troy-server' ),
				),
				JSX(
					Button,
					{
						variant:  'secondary',
						size:     'small',
						onClick:  refreshTags,
						isBusy:   isFetchingTags,
						disabled: isFetchingTags || isProcessingAll,
					},
					__( 'Refresh', 'troy-server' ),
				),
			),
			JSX(
				'p',
				{},
				sprintf(
					/* translators: %s: relative time */
					__( 'Last refreshed: %s', 'troy-server' ),
					troyServerFormat.timestamp( tagsRefreshed ),
				),
			),
			JSX(
				'div',
				{
					style: {
						maxHeight: '150px',
						overflowY: 'auto',
					},
				},
				Object.keys( tags || {} ).length > 0
					? Object.entries( tags ).map( ( [ version, tagData ] ) => JSX(
						'div',
						{
							key:   version,
							style: {
								display:        'flex',
								alignItems:     'center',
								justifyContent: 'space-between',
								padding:        '8px',
								border:         '1px solid #ddd',
								borderRadius:   '4px',
								marginBottom:   '4px',
							},
						},
						JSX(
							'span',
							{
								style: { fontWeight: 'bold' },
							},
							version,
						),
						JSX(
							Button,
							{
								variant:  'secondary',
								size:     'small',
								onClick:  () => handleProcessTag( version ),
								isBusy:   isProcessing[ version ],
								disabled: isProcessing[ version ] || isProcessingAll,
							},
							__( 'Import', 'troy-server' ),
						),
					) )
					: isFetchingTags
						? JSX(
							'p',
							{
								style: { color: '#757575' },
							},
							__( 'Loading...', 'troy-server' ),
						)
						: JSX(
							'p',
							{
								style: { color: '#757575' },
							},
							__( 'No tags available from the integration source.', 'troy-server' ),
						),
			),
		);
	}

	/**
	 * Integration Popover - Unified component for GitHub and WordPress.org integrations.
	 *
	 * @since 0.0.1194
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose               Callback function to close the popover.
	 *     @param {string}   props.mode                  The integration mode ('github' or 'wporg').
	 *     @param {boolean}  props.isActiveMode          Whether the current mode is active.
	 *     @param {string}   props.title                 The title for the popover header.
	 *     @param {number}   props.pluginId              The plugin ID.
	 *     @param {Object}   props.integration           The integration configuration object.
	 *     @param {Function} props.storeIntegration      Handler to store integration value.
	 *     @param {Function} props.disconnectIntegration Handler to disconnect integration in store.
	 *     @param {Function} props.onTagProcessed        Callback when a tag is processed.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function IntegrationPopover( {
		onClose,
		mode,
		isActiveMode,
		title,
		pluginId,
		integration,
		storeIntegration,
		disconnectIntegration,
		onTagProcessed,
	} ) {

		const [ localIntegration, setLocalIntegration ] = useState( integration || {} );
		const [ isSaving, setIsSaving ]                 = useState( false );
		const [ notification, setNotification ]         = useState( {
			type:    null,
			message: null,
		} );
		const [ disconnectState, setDisconnectState ]   = useState( {
			confirming: false,
			processing: false,
		} );
		const [ tokenState, setTokenState ]             = useState( {
			fetching: false,
			value:    null,
		} );
		const [ isFetchingTags, setIsFetchingTags ]     = useState( false );

		// Sync local integration to store when it changes. This prevents updating parent state during render
		useEffect(
			() => {
				// Only store when no mode is set (initial load/disconnect) or store matches current mode
				if ( ! localIntegration.mode || localIntegration.mode === mode )
					storeIntegration( localIntegration );
			},
			[ localIntegration, mode ],
		);

		const handleSettingsChange = ( what, value ) => {
			setLocalIntegration(
				prev => {
					const updated = { ...prev };

					switch ( what ) {
						case 'all':
							Object.assign( updated, value );
							break;
						case 'settings':
							updated.settings = {
								...updated.settings,
								...value,
							};
							break;
						case 'mode':
							updated.mode = value;
							break;
						default:
							// Generic top-level assignment (tags, tags_refreshed, auto_process, etc.)
							updated[ what ] = value;
					}

					return updated;
				},
			);
		};

		const showError  = message => setNotification( { type: 'error', message } );
		const showNotice = message => setNotification( { type: 'success', message } );

		const clearNotification = () => setNotification( {
			type:    null,
			message: null,
		} );

		const processTag = tagName => apiFetch( {
			url:    troyPluginEditorData.restUrls.integrations.tags.process,
			method: 'POST',
			data:   {
				plugin_id:       pluginId,
				package_version: tagName,
			},
		} );

		const refreshTags = () => {

			setIsFetchingTags( true );

			return apiFetch( {
				url:    troyPluginEditorData.restUrls.integrations.tags.refresh,
				method: 'POST',
				data:   { plugin_id: pluginId },
			} )
				.then( response => {
					handleSettingsChange( 'tags', response.tags );
					handleSettingsChange( 'tags_refreshed', response.tags_refreshed );
					return response;
				} )
				.catch( error => {
					showError( error.message || __( 'Failed to refresh tags.', 'troy-server' ) );
					throw error;
				} )
				.finally( () => setIsFetchingTags( false ) );
		};

		const connectIntegration = () => {

			let settings;

			const localSettings = localIntegration?.settings || {};

			switch ( mode ) {
				case 'github':
					if ( ! localSettings.owner_repo ) {
						showError( __( 'Please enter a repository.', 'troy-server' ) );
						return;
					}

					settings = {
						owner_repo: localSettings.owner_repo,
						...(
							localSettings.pat
								? { pat: localSettings.pat }
								: {}
						),
					};
					break;

				case 'wporg':
					if ( ! localSettings.slug ) {
						showError( __( 'Please enter a plugin slug.', 'troy-server' ) );
						return;
					}

					settings = { slug: localSettings.slug };
					break;

				default:
					showError( __( 'Unsupported integration mode.', 'troy-server' ) );
					return;
			}

			clearNotification();
			setIsSaving( true );

			apiFetch( {
				url:    troyPluginEditorData.restUrls.integrations.connect,
				method: 'POST',
				data:   {
					plugin_id: pluginId,
					mode,
					settings,
				},
			} )
				.then( response => {
					handleSettingsChange(
						'all',
						{
							mode:           response.mode,
							settings:       response.settings,
							tags:           response.tags,
							auto_process:   response.auto_process,
							tags_refreshed: response.tags_refreshed,
						}
					);

					showNotice( __( 'Integration connected successfully. Fetching tags...', 'troy-server' ) );

					refreshTags()
						.then( () => {
							// No notification is necessary. The UI already shows everything is fine.
							clearNotification();
						} )
						.catch( () => { // We ignore the error, a proper one will be shown once the user hits refreshTags manually.
							showError( __( 'Integration connected, but failed to fetch tags.', 'troy-server' ) );
						} );
				} )
				.catch( error => {
					showError( error.message || __( 'Failed to connect integration.', 'troy-server' ) );
				} )
				.finally( () => {
					setIsSaving( false );
				} );
		};

		const handleDisconnect = () => {

			clearNotification();
			setDisconnectState( {
				confirming: true,
				processing: true,
			} );

			apiFetch( {
				url:    troyPluginEditorData.restUrls.integrations.disconnect,
				method: 'DELETE',
				data:   { plugin_id: pluginId },
			} )
				.then( () => {
					disconnectIntegration();
					setLocalIntegration( {} );
					setTokenState( {
						fetching: false,
						value:    null,
					} );
					setDisconnectState( {
						confirming: false,
						processing: false,
					} );
				} )
				.catch( error => {
					showError( error.message || __( 'Failed to disconnect integration.', 'troy-server' ) );
				} )
				.finally( () => {
					setDisconnectState( {
						confirming: false,
						processing: false,
					} );
				} );
		};

		const handleDisconnectClick = () => {
			if ( disconnectState.confirming ) {
				handleDisconnect();
			} else {
				clearNotification();
				setDisconnectState( {
					confirming: true,
					processing: false,
				} );
			}
		};

		const handleRevealToken = () => {

			clearNotification();
			setTokenState( {
				fetching: true,
				value:    null,
			} );

			apiFetch( {
				url:    addQueryArgs(
					troyPluginEditorData.restUrls.integrations.revealToken,
					{ plugin_id: pluginId },
				),
				method: 'GET',
			} )
				.then( response => {
					if ( response?.token ) {
						setTokenState( {
							fetching: false,
							value:    response.token,
						} );
					} else {
						showError( __( 'No token found for this integration.', 'troy-server' ) );
						setTokenState( {
							fetching: false,
							value:    null,
						} );
					}
				} )
				.catch( error => {
					showError( error.message || __( 'Failed to reveal token.', 'troy-server' ) );
					setTokenState( {
						fetching: false,
						value:    null,
					} );
				} );
		};

		const renderConnectionForm = () => {

			const localSettings = localIntegration?.settings || {};

			switch ( mode ) {
				case 'github':
					return JSX(
						VStack,
						{ spacing: 3 },
						JSX(
							TextControl,
							{
								label:    __( 'Repository', 'troy-server' ),
								value:    localSettings?.owner_repo || '',
								onChange: value => handleSettingsChange( 'settings', { owner_repo: value.trim() } ),
								disabled: isSaving,
								help:     __( 'A link or "owner/repo" of the GitHub repository.', 'troy-server' ),
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize:   true,
							},
						),
						JSX(
							TextControl,
							{
								label:    __( 'Personal Access Token (Optional)', 'troy-server' ),
								value:    localSettings?.pat || '',
								onChange: value => handleSettingsChange( 'settings', { pat: value.trim() } ),
								disabled: isSaving,
								help:     JSX(
									Fragment,
									null,
									__( 'Required for private repositories.', 'troy-server' ),
									' ',
									JSX(
										ExternalLink,
										{ href: buildGitHubPATUrl() },
										__( 'Generate a token', 'troy-server' ),
									),
								),
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize:   true,
							},
						),
						JSX(
							'div',
							{ className: 'troy-server-version-metadata-separator' },
						),
						JSX(
							Button,
							{
								variant:  'primary',
								onClick:  connectIntegration,
								isBusy:   isSaving,
								disabled: isSaving || ! localSettings?.owner_repo,
							},
							__( 'Connect', 'troy-server' ),
						),
					);

				case 'wporg':
					return JSX(
						VStack,
						{ spacing: 3 },
						JSX(
							TextControl,
							{
								label:    __( 'Plugin Slug', 'troy-server' ),
								value:    localSettings?.slug || '',
								onChange: value => handleSettingsChange( 'settings', { slug: value.trim() } ),
								help:     __( 'The plugin slug as it appears in the WordPress.org plugin directory URL (e.g., "hello" from https://wordpress.org/plugins/hello/)', 'troy-server' ),
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize:   true,
							},
						),
						JSX(
							'div',
							{ className: 'troy-server-version-metadata-separator' },
						),
						JSX(
							Button,
							{
								variant:  'primary',
								onClick:  connectIntegration,
								isBusy:   isSaving,
								disabled: isSaving || ! localSettings?.slug,
							},
							__( 'Connect', 'troy-server' ),
						),
					);

				default:
					return JSX(
						Text,
						null,
						__( 'Unsupported integration mode.', 'troy-server' ),
					);
			}
		};

		const renderConnectedView = () => {

			const localSettings = localIntegration?.settings || {};

			const renderSettings = () => {

				switch ( mode ) {
					case 'github':
						return JSX(
							VStack,
							{ spacing: 3 },
							JSX(
								'strong',
								null,
								__( 'Connection information', 'troy-server' ),
							),
							JSX(
								VStack,
								{ spacing: 2 },
								JSX(
									MetadataItem,
									{
										label: __( 'Repository:', 'troy-server' ),
										value: localSettings.owner_repo,
									},
								),
								JSX(
									MetadataItem,
									{
										label: __( 'Mode:', 'troy-server' ),
										value: localSettings.has_auth
											? __( 'Authenticated repository', 'troy-server' )
											: __( 'Public repository', 'troy-server' ),
									},
								),
							),
							localSettings.has_auth && JSX(
								Fragment,
								{},
								JSX(
									'div',
									{ className: 'troy-server-version-metadata-separator' },
								),
								JSX(
									InputControl,
									{
										label:    __( 'Personal Access Token', 'troy-server' ),
										type:     'text',
										readOnly: true,
										value:    tokenState.value || '••••••••••••••••',
										suffix:   JSX(
											InputControlSuffixWrapper,
											{ variant: 'control' },
											JSX(
												Button,
												{
													icon:     tokenState.value ? seen : unseen,
													label:    tokenState.value ? __( 'Hide token', 'troy-server' ) : __( 'Reveal token', 'troy-server' ),
													size:     'small',
													onClick:  tokenState.value
														? () => setTokenState( {
															fetching: false,
															value:    null
														} )
														: handleRevealToken,
													isBusy:   tokenState.fetching,
													disabled: tokenState.fetching,
												},
											),
										),
										__nextHasNoMarginBottom: true,
										__next40pxDefaultSize:   true,
									},
								),
							),
						);

					case 'wporg':
						return JSX(
							VStack,
							{ spacing: 3 },
							JSX(
								'strong',
								null,
								__( 'Connection Information', 'troy-server' ),
							),
							JSX(
								VStack,
								{ spacing: 2 },
								JSX(
									MetadataItem,
									{
										label: __( 'Plugin Slug:', 'troy-server' ),
										value: localSettings.slug,
									},
								),
								JSX(
									MetadataItem,
									{
										label: __( 'Repository:', 'troy-server' ),
										value: __( 'WordPress.org SVN', 'troy-server' ),
									},
								),
							),
						);

					default:
						return null;
				}
			};

			const renderDisconnectButtons = () => JSX(
				Fragment,
				null,
				JSX(
					'div',
					{ className: 'troy-server-version-metadata-separator' },
				),
				JSX(
					HStack,
					{
						spacing: 2,
						justify: 'start',
					},
					JSX(
						Button,
						{
							variant:       disconnectState.confirming ? 'primary' : 'secondary',
							isDestructive: true,
							onClick:       handleDisconnectClick,
							isBusy:        disconnectState.processing,
							disabled:      disconnectState.processing,
						},
						disconnectState.confirming
							? __( 'Confirm Disconnect', 'troy-server' )
							: __( 'Disconnect', 'troy-server' ),
					),
					disconnectState.confirming && JSX(
						Button,
						{
							variant:  'tertiary',
							onClick:  () => setDisconnectState( { confirming: false, processing: false } ),
							disabled: disconnectState.processing,
						},
						__( 'Cancel', 'troy-server' ),
					),
				),
			);

			if ( mode !== 'github' && mode !== 'wporg' )
				return JSX(
					'p',
					null,
					__( 'Unsupported integration mode.', 'troy-server' ),
				);

			return JSX(
				VStack,
				{ spacing: 3 },
				renderSettings(),
				JSX(
					IntegrationTagsSection,
					{
						tags:          localIntegration?.tags || {},
						tagsRefreshed: localIntegration?.tags_refreshed,
						isFetchingTags,
						onTagProcessed,
						processTag,
						refreshTags,
						showError,
						showNotice,
					},
				),
				renderDisconnectButtons(),
			);
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title,
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 2 },
				notification.message && JSX(
					Notice,
					{
						status:        notification.type,
						isDismissible: true,
						onRemove:      clearNotification,
					},
					notification.message,
				),
				isActiveMode
					? renderConnectedView()
					: renderConnectionForm(),
			),
		);
	}

	/**
	 * Integration Control - Unified component for GitHub and WordPress.org integrations.
	 *
	 * @since 0.0.1194
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.mode                  The integration mode ('github' or 'wporg').
	 *     @param {string}   props.label                 The label to display for this integration.
	 *     @param {number}   props.pluginId              The plugin ID.
	 *     @param {Object}   props.integration           The integration configuration object.
	 *     @param {Function} props.storeIntegration      Handler to store integration value.
	 *     @param {Function} props.disconnectIntegration Handler to disconnect integration.
	 *     @param {Function} props.onTagProcessed        Callback when a tag is processed.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function IntegrationControl( {
		mode,
		label,
		pluginId,
		integration,
		storeIntegration,
		disconnectIntegration,
		onTagProcessed,
	} ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, label ),
			[ popoverAnchor, label ],
		);

		const storedMode   = integration?.mode || '';
		const isActiveMode = storedMode === mode;

		return JSX(
			PanelRow,
			{
				label,
				onRefChange: setPopoverAnchor,
			},
			JSX(
				MenuDropdown,
				{
					popoverProps,
					focusOnMount: true,
					renderToggle: ( { onToggle, isOpen } ) => JSX(
						Button,
						{
							variant:         'tertiary',
							size:            'compact',
							onClick:         onToggle,
							'aria-expanded': isOpen,
							disabled:        storedMode && ! isActiveMode,
						},
						isActiveMode
							? __( 'Configure (active)', 'troy-server' )
							: __( 'Configure', 'troy-server' ),
					),
					renderContent: ( { onClose } ) => JSX(
						IntegrationPopover,
						{
							onClose,
							mode,
							isActiveMode,
							title: label,
							pluginId,
							integration,
							storeIntegration,
							disconnectIntegration,
							onTagProcessed,
						},
					),
				},
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { IntegrationControl } );
} )( window.wp );
