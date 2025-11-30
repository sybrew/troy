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
 * @description Plugin versions control components for the Troy Server plugin editor.
 * @since 0.0.1184
 * @param {Object} wp The WordPress global wp object.
 */
( wp => {

	const {
		createElement: JSX,
		useState,
		useMemo,
		useEffect,
		Fragment,
	} = wp.element;
	const { __, _n, sprintf } = wp.i18n;
	const {
		Button,
		TextControl,
		TextareaControl,
		RadioControl,
		Notice,
	} = wp.components;

	// Experimental components
	const VStack                 = wp.components?.VStack || wp.components?.__experimentalVStack;
	const HStack                 = wp.components?.HStack || wp.components?.__experimentalHStack;
	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const apiFetch = wp.apiFetch;

	const {
		PanelRow,
		StyledHelp,
		MetadataItem,
		createPopoverProps,
	} = troyServerEditorComponents;

	const { MenuDropdown } = troyServerPluginEditorComponents;

	/**
	 * Add Version Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose            Callback function to close the popover.
	 *     @param {string}   props.pluginId           The ID of the plugin to which the version will be added.
	 *     @param {Function} props.onVersionProcessed Callback function to handle the processed version data.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function AddVersionPopover( { onClose, pluginId, onVersionProcessed } ) {

		const [ zipFileInputKey, setZipFileInputKey ] = useState( 1 );
		const [ zipFile, setZipFile ]                 = useState( null );
		const [ zipUrl, setZipUrl ]                   = useState( '' );
		const [ isLoading, setIsLoading ]             = useState( false );
		const [ notification, setNotification ]       = useState( {
			type:    null,
			message: null,
		} );

		useEffect(
			() => {
				// If the file was cleared, increment the key to force a re-mount.
				if ( zipFile === null )
					setZipFileInputKey( prevKey => ++prevKey );
			},
			[ zipFile ],
		);

		const showError = message => setNotification( { type: 'error', message } );
		const clearNotification = () => setNotification( { type: null, message: null } );

		const handleFileChange = event => {

			const zip = event.target.files[ 0 ];

			setZipUrl( '' );

			if ( zip.size > troyPluginEditorData.maxFileSize ) {
				setZipFile( null ); // Unset selected file
				showError( __( 'The selected file exceeds the maximum allowed size.', 'troy-server' ) );
			} else {
				setZipFile( event.target.files[ 0 ] );
				clearNotification();
			}
		};

		const handleUrlChange = value => {
			setZipUrl( value );
			setZipFile( null ); // Clear file if URL is entered
			clearNotification();
		};

		const handleProcess = () => {

			setIsLoading( true );
			clearNotification();

			let apiArgs = {};

			if ( zipFile ) {
				let data = new FormData();

				data.append( 'plugin_id', pluginId );
				data.append( 'file', zipFile, zipFile.name );

				apiArgs = {
					url:    troyPluginEditorData.restUrls.processZipFile,
					method: 'POST',
					body:   data,
				};
			} else if ( zipUrl ) {
				apiArgs = {
					url:     troyPluginEditorData.restUrls.processZipUrl,
					method:  'POST',
					data:    {
						plugin_id: pluginId,
						zip_url:   zipUrl,
					},
					headers: {
						'Content-Type': 'application/json',
					},
				};
			} else {
				showError( __( 'Please select a file or enter a URL.', 'troy-server' ) );
				setIsLoading( false );
				return;
			}

			apiFetch( apiArgs )
				.then( response => {
					onVersionProcessed( response.message, response.version );

					// Clear input fields.
					setZipFile( null );
					setZipUrl( '' );
					setIsLoading( false );

					// Reset the file input key to force a re-mount.
					onClose();
				} )
				.catch( error => {
					showError(
						error.message || __( 'Error processing ZIP.', 'troy-server' ),
					);
					setIsLoading( false );
				} );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Add New Version', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				notification.type && JSX(
					Notice,
					{
						status:        notification.type,
						isDismissible: true,
						onRemove:      clearNotification,
					},
					notification.message,
				),
				JSX(
					'div',
					null,
					JSX(
						'input',
						{
							key:      `troy-server-editor-plugin-version-input-${zipFileInputKey}`,
							type:     'file',
							onChange: handleFileChange,
							accept:   '.zip,application/zip',
							disabled: isLoading,
						},
					),
					troyPluginEditorData.maxFileSizeStr && JSX(
						StyledHelp,
						null,
						sprintf(
							/* translators: %s is the maximum file size in human-readable format */
							__( 'Max file size: %s', 'troy-server' ),
							troyPluginEditorData.maxFileSizeStr,
						),
					),
				),
				JSX(
					TextControl,
					{
						label:    __( 'Or Enter ZIP URL', 'troy-server' ),
						value:    zipUrl,
						onChange: handleUrlChange,
						type:     'url',
						disabled: isLoading,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
				JSX(
					StyledHelp,
					null,
					__( 'If a version with the same number already exists, it will be overridden. The upgrade notice will be lost.', 'troy-server' ),
				),
				JSX(
					Button,
					{
						variant:  'primary',
						onClick:  handleProcess,
						isBusy:   isLoading,
						disabled: isLoading || ( ! zipFile && ! zipUrl.trim() ),
					},
					__( 'Process ZIP', 'troy-server' ),
				),
			),
		);
	}

	/**
	 * Add Version Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.pluginId           The ID of the plugin to which the version will be added.
	 *     @param {Function} props.onVersionProcessed Callback function to handle the processed version data.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function AddVersionControl( { pluginId, onVersionProcessed } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Add New Version', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		return JSX(
			PanelRow,
			{ onRefChange: setPopoverAnchor },
			JSX(
				MenuDropdown,
				{
					popoverProps,
					focusOnMount: true,
					renderToggle: ( { onToggle, isOpen } ) => JSX(
						Button,
						{
							variant:         'secondary',
							onClick:         onToggle,
							'aria-expanded': isOpen,
						},
						__( 'Add New Version', 'troy-server' ),
					),
					renderContent: ( { onClose } ) => JSX(
						AddVersionPopover,
						{
							onClose,
							pluginId,
							onVersionProcessed,
						},
					),
				},
			),
		);
	}

	/**
	 * Single Version Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose                   Callback function to close the popover.
	 *     @param {Object}   props.version                   The version object data.
	 *     @param {number}   props.index                     The version index in the array.
	 *     @param {Boolean}  props.isLatestVersion           Whether this is the latest version.
	 *     @param {Boolean}  props.hasRepoMismatch           Whether this version has a repository mismatch.
	 *     @param {Boolean}  props.canDownload               Whether the download button should be enabled.
	 *     @param {Function} props.handleTypeChange          Function to handle version type changes.
	 *     @param {Function} props.handleUpgradeNoticeChange Function to handle upgrade notice changes.
	 *     @param {Function} props.handleRemoveToggle        Function to set version removal state.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function VersionPopover( {
		onClose,
		version,
		index,
		isLatestVersion,
		hasRepoMismatch,
		canDownload,
		handleTypeChange,
		handleUpgradeNoticeChange,
		handleRemoveToggle,
	} ) {

		const isRemovedVersion = version.remove;
		const format           = troyServerFormat;

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: isLatestVersion
						/* translators: %s is the version number */
						? sprintf( __( 'Version %s (current)', 'troy-server' ), version.version )
						/* translators: %s is the version number */
						: sprintf( __( 'Version %s', 'troy-server' ), version.version ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 3 },
				hasRepoMismatch && JSX(
					Notice,
					{
						status:        'warning',
						isDismissible: false,
					},
					__( 'This version has a repository mismatch.', 'troy-server' ),
				),
				JSX(
					RadioControl,
					{
						label:    __( 'Type', 'troy-server' ),
						selected: version.type,
						options:  troyPluginEditorData.versionTypes,
						onChange: newType => handleTypeChange( index, newType ),
					},
				),
				JSX(
					TextareaControl,
					{
						label:     sprintf(
							/* translators: %d is the character count, 191 is the maximum length */
							__( 'Upgrade Notice - %d/191 characters', 'troy-server' ),
							version.upgrade_notice.length,
						),
						value:     version.upgrade_notice,
						onChange:  value => handleUpgradeNoticeChange( index, value ),
						rows:      3,
						help:      __( 'Warn users about a breaking change.', 'troy-server' ),
						maxLength: 191,
						__nextHasNoMarginBottom: true,
					},
				),
				JSX(
					'div',
					{ className: 'troy-server-version-metadata-separator' },
				),
				JSX(
					VStack,
					{ spacing: 3 },
					JSX(
						'strong',
						null,
						__( 'Version information', 'troy-server' ),
					),
					JSX(
						VStack,
						{ spacing: 2 },
						JSX(
							MetadataItem,
							{
								label: __( 'File size:', 'troy-server' ),
								value: format.bytesToIbiBytes( version.file_size ),
							},
						),
						version.tested_wp && JSX(
							MetadataItem,
							{
								label: __( 'Tested WP:', 'troy-server' ),
								value: version.tested_wp,
							},
						),
						version.requires_wp && JSX(
							MetadataItem,
							{
								label: __( 'Requires WP:', 'troy-server' ),
								value: version.requires_wp,
							},
						),
						version.requires_php && JSX(
							MetadataItem,
							{
								label: __( 'Requires PHP:', 'troy-server' ),
								value: version.requires_php,
							},
						),
						// We may need this when we merge origin URL support.
						// JSX(
						// 	MetadataItem,
						// 	{
						// 		label: __( 'Original source:', 'troy-server' ),
						// 		value: version.origin_url,
						// 	},
						// ),
						JSX(
							MetadataItem,
							{
								label: __( 'Repository:', 'troy-server' ),
								value: version.repo,
								state: hasRepoMismatch ? 'warning' : undefined,
							},
						),
						version.dependencies && JSX(
							MetadataItem,
							{
								label: __( 'Dependencies:', 'troy-server' ),
								value: version.dependencies,
							},
						),
						// TODO: Later, use for this feature needs to be implemented; now, it will always point to itself.
						// version.origin_url && JSX(
						// 	MetadataItem,
						// 	{
						// 		label: __( 'Origin URL:', 'troy-server' ),
						// 		value: version.origin_url,
						// 	},
						// ),
						JSX(
							MetadataItem,
							{
								label: __( 'Created at:', 'troy-server' ),
								value: version.created_at,
							},
						),
						JSX(
							MetadataItem,
							{
								label: __( 'Updated at:', 'troy-server' ),
								value: version.updated_at,
							},
						),
					),
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
								variant:  'secondary',
								disabled: ! canDownload,
								href:     version.download_uri,
								target:   '_blank',
								rel:      'noopener noreferrer',
								icon:     'download',
								size:     'default',
							},
							__( 'Download', 'troy-server' ),
						),
						JSX(
							Button,
							{
								variant:       'secondary',
								icon:          isRemovedVersion ? 'undo' : 'trash',
								size:          'default',
								isDestructive: true, // Always red; WordPress doesn't have a green button.
								onClick:       () => handleRemoveToggle( index, ! isRemovedVersion ),
								'aria-label':  isRemovedVersion
									? __( 'Restore version', 'troy-server' )
									: __( 'Mark for removal', 'troy-server' ),
							},
							isRemovedVersion ? __( 'Restore', 'troy-server' ) : __( 'Remove', 'troy-server' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Single Version Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Object}   props.version                   The version object data.
	 *     @param {number}   props.index                     The version index in the array.
	 *     @param {Boolean}  props.isLatestVersion           Whether this is the latest version.
	 *     @param {Boolean}  props.hasRepoMismatch           Whether this version has a repository mismatch.
	 *     @param {Boolean}  props.canDownload               Whether the download button should be enabled.
	 *     @param {Function} props.handleTypeChange          Function to handle version type changes.
	 *     @param {Function} props.handleUpgradeNoticeChange Function to handle upgrade notice changes.
	 *     @param {Function} props.handleRemoveToggle        Function to set version removal state.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function VersionControl( {
		version,
		index,
		isLatestVersion,
		hasRepoMismatch,
		canDownload,
		handleTypeChange,
		handleUpgradeNoticeChange,
		handleRemoveToggle,
	} ) {

		const isRemovedVersion = version.remove;

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				sprintf( __( 'Version %s', 'troy-server' ),
				version.version,
			) ),
			[ popoverAnchor, version.version ],
		);

		const typeLabel = troyPluginEditorData.versionTypes?.find(
			option => option.value === version.type,
		)?.label || version.type;

		const getVersionDisplayText = () => {

			let format;

			if ( isRemovedVersion ) {
				/* translators: %1: version number, %2: release type (tag, beta, unreleased) */
				format = __( '%1$s - %2$s (remove)', 'troy-server' );
			} else if ( isLatestVersion ) {
				/* translators: %1: version number, %2: release type (tag, beta, unreleased) */
				format = __( '%1$s - %2$s (current)', 'troy-server' );
			} else {
				/* translators: %1: version number, %2: release type (tag, beta, unreleased) */
				format = __( '%1$s - %2$s', 'troy-server' );
			}

			return sprintf(
				format,
				version.version,
				typeLabel,
			);
		};

		// Determine CSS class based on version state
		let versionClassName = '';
		if ( isRemovedVersion )
			versionClassName += 'troy-server-plugin-version-remove ';
		if ( hasRepoMismatch )
			versionClassName += 'troy-server-plugin-version-warning ';
		if ( isLatestVersion )
			versionClassName += 'troy-server-plugin-version-current ';

		versionClassName = versionClassName.trim();

		return JSX(
			PanelRow,
			{
				label:       JSX(
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
								icon:            'edit',
								'aria-label':    sprintf(
									/* translators: %s is the version number */
									__( 'Edit version %s', 'troy-server' ),
									version.version,
								),
							},
							getVersionDisplayText(),
						),
						renderContent: ( { onClose } ) => JSX(
							VersionPopover,
							{
								onClose,
								version,
								index,
								isLatestVersion,
								hasRepoMismatch,
								canDownload,
								handleTypeChange,
								handleUpgradeNoticeChange,
								handleRemoveToggle,
							},
						),
					},
				),
				onRefChange: setPopoverAnchor,
				className:   versionClassName,
			},
		);
	}

	/**
	 * Plugin Versions Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.pluginId      The ID of the plugin.
	 *     @param {Array}    props.versions      The list of plugin versions.
	 *     @param {Object}   props.latestVersion The latest version object.
	 *     @param {Boolean}  props.canDownload   Whether the download button should be enabled.
	 *     @param {Function} props.addVersion    Function to add a new version.
	 *     @param {Function} props.updateVersion Function to update an existing version.
	 *     @param {Function} props.removeVersion Function to remove a version.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginVersionsControl( { pluginId, versions, latestVersion, canDownload, addVersion, updateVersion, removeVersion } ) {

		const handleTypeChange = ( index, newType ) => {
			if ( versions?.[ index ] )
				updateVersion( {
					...versions[ index ],
					type: newType,
				} );
		}
		const handleUpgradeNoticeChange = ( index, newNotice ) => {
			if ( versions?.[ index ] )
				updateVersion( {
					...versions[ index ],
					upgrade_notice: newNotice,
				} );
		}
		const handleRemoveToggle = ( index, remove ) => {
			if ( versions?.[ index ] )
				updateVersion( {
					...versions[ index ],
					remove,
				} );
		}

		// Count versions marked for removal via the remove property
		const versionsToRemove     = versions.filter( v => true === v.remove );
		const removedVersionsCount = versionsToRemove.length;

		const currentRepoUrl = troyPluginEditorData.repoUrl;

		const versionsWithRepoMismatch = useMemo(
			() => {
				if ( ! versions?.length )
					return [];

				return versions.filter(
					version => version.repo !== currentRepoUrl,
				);
			},
			[ versions, currentRepoUrl ],
		);

		const [ noticeMessage, setNoticeMessage ] = useState( null );

		const handleFinalizeRemovals = async () => {

			let removedCount = 0;

			for ( const v of versionsToRemove )
				if ( await removeVersion( v.version ) )
					removedCount++;

			if ( removedCount > 0 ) {
				setNoticeMessage(
					sprintf(
						_n(
							'%d version removed successfully.',
							'%d versions removed successfully.',
							removedCount,
							'troy-server',
						),
						removedCount,
					)
				);
			}
		}

		return JSX(
			VStack,
			{ spacing: 3 },
			noticeMessage && JSX(
				Notice,
				{
					status:        'success',
					isDismissible: true,
					onRemove:      () => setNoticeMessage( null ),
				},
				noticeMessage,
			),
			JSX(
				AddVersionControl,
				{
					pluginId,
					onVersionProcessed: ( message, version ) => {
						if ( message )
							setNoticeMessage( message );

						version?.version && addVersion( version );
					},
				},
			),
			removedVersionsCount > 0 && JSX(
				VStack,
				{ spacing: 2 },
				JSX(
					Notice,
					{
						status:        'error',
						isDismissible: false,
					},
					__( 'Versions are marked for removal, you must still finalize this.', 'troy-server' ),
				),
				JSX(
					Button,
					{
						onClick:       handleFinalizeRemovals,
						variant:       'primary',
						isDestructive: true,
					},
					sprintf(
						_n(
							'Finalize %d removal',
							'Finalize %d removals',
							removedVersionsCount,
							'troy-server',
						),
						removedVersionsCount,
					),
				),
			),
			versionsWithRepoMismatch.length > 0 && JSX(
				Notice,
				{
					status:        'warning',
					isDismissible: false,
				},
				sprintf(
					/* translators: %s is the current repository URL */
					__( 'Some versions have repository mismatches with this server. Releasing these will redirect clients to other update servers. The current repository URL is: %s', 'troy-server' ),
					currentRepoUrl,
				),
			),
			versions?.length > 0 && JSX(
				VStack,
				{ spacing: 2 },
				JSX(
					'strong',
					null,
					__( 'Available versions', 'troy-server' ),
				),
				versions.map( ( version, index ) => {
					const hasRepoMismatch = versionsWithRepoMismatch.some( v => v.version === version.version );

					return JSX(
						Fragment,
						{ key: version.version.replace( /\./g, '-' ) },
						JSX(
							VersionControl,
							{
								version,
								index,
								isLatestVersion: version?.version === latestVersion,
								hasRepoMismatch,
								canDownload,
								handleTypeChange,
								handleUpgradeNoticeChange,
								handleRemoveToggle,
							},
						),
					);
				} ),
			),
		);
	}

	// Export to shared namespace.
	Object.assign( window.troyServerPluginEditorComponents, { PluginVersionsControl } );
} )( window.wp );
