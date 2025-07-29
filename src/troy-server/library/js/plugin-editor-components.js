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
 * @description Plugin-specific components for the Troy Server plugin editor.
 * @since 0.0.1184
 */
window.troyServerPluginEditorComponents = ( wp => {
	const {
		createElement: JSX,
		useState,
		useEffect,
		useMemo,
		Fragment,
	} = wp.element;
	const {
		__,
		sprintf,
		_n,
	} = wp.i18n;
	const {
		TextControl,
		Button,
		TextareaControl,
		Notice,
		Dropdown,
		RadioControl,
		SelectControl,
		Spinner,
	} = wp.components;
	const { useSelect } = wp.data;

	// Experimental components
	const VStack  = wp.components?.VStack || wp.components?.__experimentalVStack;
	const HStack  = wp.components?.HStack || wp.components?.__experimentalHStack;

	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;
	const apiFetch = wp.apiFetch;

	// Import general components from editor-components
	const {
		StyledHelp,
		MetadataItem,
		PanelRow,
		createPopoverProps,
	} = troyServerEditorComponents;

	/**
	 * MenuDropdown component - wrapper around WordPress Dropdown with custom styling.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props - Component properties passed to the WordPress Dropdown component.
	 * @returns {JSX.Element} The rendered MenuDropdown component.
	 */
	function MenuDropdown( props ) {
		const {
			className = '',
			...otherProps
		} = props;

		return JSX(
			Dropdown,
			{
				...otherProps,
				className: `components-dropdown-menu ${className}`.trim(),
			},
		);
	}

	/**
	 * Plugin Slug Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {string}   props.postId        The ID of the current post.
	 *     @param {string}   props.plugin_slug   The current plugin slug value.
	 *     @param {Function} props.storeSlug     Function to store the plugin slug.
	 *     @param {Function} props.storePluginId Function to store the plugin ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginSlugPopover( { onClose, postId, plugin_slug, storeSlug, storePluginId } ) {

		const [ currentSlug, setCurrentSlug ] = useState( plugin_slug || '' );
		const [ isLoading, setIsLoading ] = useState( false );
		const [ error, setError ] = useState( null );

		const handleStoreSlug = () => {
			setIsLoading( true );
			setError( null );

			apiFetch( {
				url:    troyPluginEditorData.restUrls.registerSlug,
				method: 'POST',
				data:   {
					post_id:     postId,
					plugin_slug: currentSlug,
				},
			} )
				.then( response => {
					storeSlug( response.plugin_slug );
					storePluginId( response.plugin_id );
					setIsLoading( false );
					onClose();
				} )
				.catch( error => {
					setError(
						error.message || __( 'Error storing slug.', 'troy-server' ),
					);

					let _slug = plugin_slug;

					if ( error.post_id ) {
						if ( error.post_id != postId ) {
							setError( __( 'Slug is already registered for another plugin. Use another one.', 'troy-server' ) );
						} else {
							_slug = error.plugin_slug || '';
						}
					}

					setCurrentSlug( _slug );
					storeSlug( _slug );
					setIsLoading( false );
				} );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin Slug', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				JSX(
					TextControl,
					{
						label:    __( 'Plugin Slug', 'troy-server' ),
						value:    currentSlug,
						onChange: value => {
							setCurrentSlug(
								value.toLowerCase()
									.replace( /\s+/g, '-' )
									.replace( /[^a-z0-9-]/g, '' )
									.replace( /-{2,}/g, '-' )
									.replace( /^[^a-z]+/, '' )
							);
						},
						onBlur:   () => {
							setCurrentSlug( currentSlug.replace( /-+$/g, '' ) );
						},
						pattern:  '[a-z][a-z0-9\\-]*',
						help: __( 'A unique identifier. This will become the wp-content plugin folder for all future releases and ZIP file names for all downloads and is used to localize updates. Assume it to be permanent until Troy Client supports slug migrations.', 'troy-server' ),
						disabled: isLoading,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					},
				),
				JSX(
					Button,
					{
						variant:  'primary',
						onClick:  handleStoreSlug,
						isBusy:   isLoading,
						disabled: isLoading || ! currentSlug || plugin_slug,
					},
					plugin_slug
						? __( 'Update slug (planned feature)', 'troy-server' )
						: __( 'Reserve slug immediately', 'troy-server' ),
				),
				error && JSX(
					Notice,
					{
						status:        'error',
						isDismissible: true,
						onRemove:      () => setError( null ),
					},
					error,
				),
			),
		);
	}

	/**
	 * Plugin Slug Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {string}   props.postId        The ID of the current post.
	 *     @param {string}   props.plugin_slug   The current plugin slug value.
	 *     @param {Function} props.storeSlug     Function to store the plugin slug.
	 *     @param {Function} props.storePluginId Function to store the plugin ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginSlugControl( { postId, plugin_slug, storeSlug, storePluginId } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, __( 'Plugin Slug', 'troy-server' ) ),
			[ popoverAnchor ],
		);

		return JSX(
			PanelRow,
			{
				label:       __( 'Plugin slug', 'troy-server' ),
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
						},
						plugin_slug || __( 'Not set', 'troy-server' ),
					),
					renderContent: ( { onClose } ) => JSX(
						PluginSlugPopover,
						{
							onClose,
							postId,
							plugin_slug,
							storeSlug,
							storePluginId,
						},
					),
				},
			),
		);
	}

	/**
	 * Plugin Status Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {string}   props.status        The current plugin status.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginStatusPopover( { onClose, status, setStoreValue } ) {

		const [ currentStatus, setCurrentStatus ] = useState( status || 'public' );

		const handleStatusChange = ( newStatus ) => {
			setCurrentStatus( newStatus );
			setStoreValue( 'status', newStatus );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin Status', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				JSX(
					RadioControl,
					{
						label:    __( 'Status', 'troy-server' ),
						selected: currentStatus,
						options:  troyPluginEditorData.pluginStatuses,
						onChange: handleStatusChange,
						help:     __( 'Control the visibility of this plugin.', 'troy-server' ),
						hideLabelFromVision: true,
					},
				),
			),
		);
	}

	/**
	 * Plugin Status Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {string}   props.status     The current plugin status.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginStatusControl( { status, setStoreValue } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, __( 'Plugin Status', 'troy-server' ) ),
			[ popoverAnchor ]
		);

		const statusLabel = troyPluginEditorData.pluginStatuses?.find(
			option => option.value === status
		)?.label || status;

		return JSX(
			PanelRow,
			{
				label:       __( 'Status', 'troy-server' ),
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
						},
						statusLabel,
					),
					renderContent: ( { onClose } ) => JSX(
						PluginStatusPopover,
						{
							onClose,
							status,
							setStoreValue,
						},
					),
				},
			),
		);
	}

	/**
	 * Short Description Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {Object}   props.description   The current plugin description.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ShortDescriptionPopover( { onClose, description, setStoreValue } ) {

		const [ currentDescription, setCurrentDescription ] = useState( description );

		const handleDescriptionChange = value => {
			setCurrentDescription( value );
			setStoreValue( 'short_description', value );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Short Description', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				JSX(
					TextareaControl,
					{
						label:     sprintf(
							/* translators: %d is the character count, 150 is the recommended maximum length */
							__( 'Description - %d/150 characters', 'troy-server' ),
							currentDescription.length,
						),
						value:     currentDescription,
						onChange:  handleDescriptionChange,
						rows:      4,
						help:      __( 'A brief description of this plugin that appears in plugin listings.', 'troy-server' ),
						maxLength: 191,
						__nextHasNoMarginBottom: true,
					},
				),
			),
		);
	}

	/**
	 * Short Description Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Object}   props.storeData  The current plugin store data.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ShortDescriptionControl( { storeData, setStoreValue } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, __( 'Short Description', 'troy-server' ) ),
			[ popoverAnchor ]
		);

		const description = storeData.short_description || '';
		const displayText = description.length > 150
			? description.substring( 0, 149 ) + '...'
			: description || __( 'No description', 'troy-server' );

		return JSX(
			PanelRow,
			{
				label: __( 'Description', 'troy-server' ),
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
							style:           { textAlign: 'left' },
						},
						displayText,
					),
					renderContent: ( { onClose } ) => JSX(
						ShortDescriptionPopover,
						{
							onClose,
							description,
							setStoreValue,
						},
					),
				},
			),
		);
	}

	/**
	 * URLs Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose    Callback function to close the popover.
	 *     @param {Object}   props.storeData  The current plugin store data.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function UrlsPopover( { onClose, storeData, setStoreValue } ) {

		const [ currentUrls, setCurrentUrls ] = useState( {
			permalink:   storeData.permalink || '',
			support_uri: storeData.support_uri || '',
			banner_uri:  storeData.banner_uri || '',
			logo_uri:    storeData.logo_uri || '',
		} );

		const handleUrlChange = ( key, value ) => {
			const newUrls = { ...currentUrls, [key]: value };
			setCurrentUrls( newUrls );
			setStoreValue( key, value );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin URLs', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				JSX(
					TextControl,
					{
						label:    __( 'Custom Permalink', 'troy-server' ),
						value:    currentUrls.permalink,
						onChange: value => handleUrlChange( 'permalink', value ),
						type:     'url',
						help:     __( 'This link is used when information about this plugin is requested, also known as the "plugin homepage".', 'troy-server' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
				JSX(
					TextControl,
					{
						label:    __( 'Support URI', 'troy-server' ),
						value:     currentUrls.support_uri,
						onChange: value => handleUrlChange( 'support_uri', value ),
						type:     'url',
						help:     __( 'A link to this plugin support forum or contact page.', 'troy-server' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
			),
		);
	}

	/**
	 * URLs Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Object}   props.storeData  The current plugin store data.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function UrlsControl( { storeData, setStoreValue } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, __( 'Plugin URLs', 'troy-server' ) ),
			[ popoverAnchor ]
		);

		const urlCount = [ storeData.permalink, storeData.support_uri ]
			.filter( Boolean ).length;

		const displayText = urlCount > 0
			? sprintf( _n( '%d URL set', '%d URLs set', urlCount, 'troy-server' ), urlCount )
			: __( 'No URLs set', 'troy-server' );

		return JSX(
			PanelRow,
			{
				label: __( 'URLs', 'troy-server' ),
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
						},
						displayText,
					),
					renderContent: ( { onClose } ) => JSX(
						UrlsPopover,
						{
							onClose,
							storeData,
							setStoreValue,
						},
					),
				},
			),
		);
	}

	/**
	 * Readme Settings Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose           Callback function to close the popover.
	 *     @param {string}   props.builderType       The current builder type.
	 *     @param {Function} props.updateBuilderType Function to update the builder type.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ReadmeSettingsPopover( { onClose, builderType, updateBuilderType } ) {

		const [ currentBuilderType, setCurrentBuilderType ] = useState( builderType );

		const handleBuilderTypeChange = value => {
			setCurrentBuilderType( value );
			updateBuilderType( value );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Readme builder', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				JSX(
					RadioControl,
					{
						label:               __( 'Builder Type', 'troy-server' ),
						selected:            currentBuilderType,
						options:             troyPluginEditorData.builderTypes,
						onChange:            handleBuilderTypeChange,
						help:                __( 'Note: Changes in the Block Editor will be lost if you switch to another builder.', 'troy-server' ),
						hideLabelFromVision: true,
					},
				),
			),
		);
	}

	/**
	 * Readme Settings Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {string}   props.builderType       The current builder type.
	 *     @param {Function} props.updateBuilderType Function to update the builder type.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ReadmeSettingsControl( { builderType, updateBuilderType } ) {
		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, __( 'Readme Settings', 'troy-server' ) ),
			[ popoverAnchor ]
		);

		const builderTypeLabel = troyPluginEditorData.builderTypes?.find(
			option => option.value === builderType
		)?.label || builderType;

		return JSX(
			PanelRow,
			{
				label:       __( 'Builder', 'troy-server' ),
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
						},
						builderTypeLabel,
					),
					renderContent: ( { onClose } ) => JSX(
						ReadmeSettingsPopover,
						{
							onClose,
							builderType,
							updateBuilderType,
						},
					),
				},
			),
		);
	}

	/**
	 * Add Version Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
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
			() => createPopoverProps( popoverAnchor, __( 'Add New Version', 'troy-server' ) ),
			[ popoverAnchor ]
		);

		return JSX(
			PanelRow,
			{
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
	 * Add Version Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
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
		const [ error, setError ]                     = useState( null );

		useEffect(
			() => {
				// If the file was cleared, increment the key to force a re-mount.
				if ( zipFile === null )
					setZipFileInputKey( prevKey => ++prevKey );
			},
			[ zipFile ],
		);

		const handleFileChange = event => {
			const zip = event.target.files[ 0 ];

			setZipUrl( '' );

			if ( zip.size > troyPluginEditorData.maxFileSize ) {
				setZipFile( null ); // Unset selected file
				setError( __( 'The selected file exceeds the maximum allowed size.', 'troy-server' ) );
			} else {
				setZipFile( event.target.files[ 0 ] );
				setError( null );
			}
		};

		const handleUrlChange = value => {
			setZipUrl( value );
			setZipFile( null ); // Clear file if URL is entered
			setError( null );
		};

		const handleProcess = () => {
			setIsLoading( true );
			setError( null );

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
				setError( __( 'Please select a file or enter a URL.', 'troy-server' ) );
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
					setError(
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
				{
					spacing: 4,
				},
				error && JSX(
					Notice,
					{
						status:        'error',
						isDismissible: true,
						onRemove:      () => setError( null )
					},
					error,
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
						__next40pxDefaultSize: true,
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
	 *     @param {Function} props.addVersion    Function to add a new version.
	 *     @param {Function} props.updateVersion Function to update an existing version.
	 *     @param {Function} props.removeVersion Function to remove a version.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginVersionsControl( { pluginId, versions, latestVersion, addVersion, updateVersion, removeVersion } ) {

		const handleAddVersion = versionData => {
			versionData?.version && addVersion( versionData );
		}
		const handleTypeChange = ( index, newType ) => {
			if ( versions?.[ index ] )
				updateVersion( { ...versions[ index ], type: newType } );
		}
		const handleUpgradeNoticeChange = ( index, newNotice ) => {
			if ( versions?.[ index ] )
				updateVersion( { ...versions[ index ], upgrade_notice: newNotice } );
		}
		const handleRemoveToggle = ( index, remove ) => {
			if ( versions?.[ index ] )
				updateVersion( { ...versions[ index ], remove } );
		}

		const [ noticeMessage, setNoticeMessage ] = useState( null );

		// Count versions marked for removal via the remove property
		const versionsToRemove     = versions.filter( v => true === v.remove );
		const removedVersionsCount = versionsToRemove.length;

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
			{
				spacing: 3,
			},
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

						handleAddVersion( version );
					},
				},
			),
			removedVersionsCount > 0 && JSX(
				VStack,
				{
					spacing: 2,
				},
				JSX(
					Notice,
					{
						status:        'warning',
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
			versions?.length > 0 && JSX(
				VStack,
				{
					spacing: 2,
				},
				JSX(
					'strong',
					null,
					__( 'Available versions', 'troy-server' ),
				),
				versions.map( ( version, index ) => {
					return JSX(
						Fragment,
						{
							key: version.version.replace( /\./g, '-' ),
						},
						JSX(
							VersionControl,
							{
								version,
								index,
								isLatestVersion: version?.version === latestVersion,
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

	/**
	 * Single Version Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose                   Callback function to close the popover.
	 *     @param {Object}   props.version                   The version object data.
	 *     @param {number}   props.index                     The version index in the array.
	 *     @param {Boolean}  props.isLatestVersion           Whether this is the latest version.
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
		handleTypeChange,
		handleUpgradeNoticeChange,
		handleRemoveToggle,
	} ) {

		const isRemovedVersion = version.remove;

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: isLatestVersion
						? sprintf( __( 'Version %s (current)', 'troy-server' ), version.version )
						: sprintf( __( 'Version %s', 'troy-server' ), version.version ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 3,
				},
				JSX(
					RadioControl,
					{
						label:    __( 'Type', 'troy-server' ),
						selected: version.type,
						options:  troyPluginEditorData.versionTypes,
						onChange: newType => handleTypeChange( index, newType ),
					},
				),
				// var_dump() test me:
				version.source_url && JSX(
					VStack,
					{
						spacing: 2,
					},
					JSX(
						'strong',
						null,
						__( 'Original source', 'troy-server' ),
					),
					JSX(
						Button,
						{
							variant:  'link',
							href:     version.source_url,
							target:   '_blank',
							rel:      'noopener noreferrer',
						},
						version.source_url,
					),
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
					{
						className: 'troy-server-version-metadata-separator',
					},
				),
				JSX(
					VStack,
					{
						spacing: 3,
					},
					JSX(
						'strong',
						null,
						__( 'Version Information', 'troy-server' ),
					),
					JSX(
						VStack,
						{
							spacing: 2,
						},
						version.file_size && JSX(
							MetadataItem,
							{
								label: __( 'File size:', 'troy-server' ),
								value: troyServerEditorUtils.ibiBytes( version.file_size ),
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
						version.repo && JSX(
							MetadataItem,
							{
								label: __( 'Repository:', 'troy-server' ),
								value: version.repo,
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
						version.created_at && JSX(
							MetadataItem,
							{
								label: __( 'Created at:', 'troy-server' ),
								value: version.created_at,
							},
						),
						version.updated_at && JSX(
							MetadataItem,
							{
								label: __( 'Updated at:', 'troy-server' ),
								value: version.updated_at,
							},
						),
					),
					JSX(
						'div',
						{
							className: 'troy-server-version-metadata-separator',
						},
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
								variant: 'secondary',
								href:    version.download_uri,
								target:  '_blank',
								rel:     'noopener noreferrer',
								icon:    'download',
								size:    'default',
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
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Object}   props.version                   The version object data.
	 *     @param {number}   props.index                     The version index in the array.
	 *     @param {Boolean}  props.isLatestVersion           Whether this is the latest version.
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
		handleTypeChange,
		handleUpgradeNoticeChange,
		handleRemoveToggle,
	} ) {
		const isRemovedVersion = version.remove;

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, sprintf( __( 'Version %s', 'troy-server' ), version.version ) ),
			[ popoverAnchor, version.version ]
		);

		const typeLabel = troyPluginEditorData.versionTypes?.find(
			option => option.value === version.type
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
								handleTypeChange,
								handleUpgradeNoticeChange,
								handleRemoveToggle,
							},
						),
					},
				),
				onRefChange: setPopoverAnchor,
				className:  isRemovedVersion
					? 'troy-server-plugin-version-remove'
					: ( isLatestVersion ? 'troy-server-plugin-version-current' : '' ),
			},
		);
	}

	/**
	 * Plugin Author Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {number}   props.authorId      The current author ID.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginAuthorPopover( { onClose, authorId, setStoreValue } ) {

		const [ selectedAuthorId, setSelectedAuthorId ] = useState( authorId || 0 );

		const { authors, isLoading } = useSelect(
			select => {
				const { getUsers, isResolving } = select( 'core' );
				const args = {
					who:      'authors',
					per_page: -1,
					_fields:  'id,name',
					context:  'view',
				};
				return {
					authors:   getUsers( args ) || [],
					isLoading: isResolving( 'getUsers', [ args ] ),
				};
			},
			[],
		);

		const handleAuthorChange = newAuthorId => {
			const authorId = parseInt( newAuthorId );
			setSelectedAuthorId( authorId );
			setStoreValue( 'author_id', authorId );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Plugin Author', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				isLoading
					? JSX(
						HStack,
						{
							spacing:   2,
							alignment: 'center',
						},
						JSX(
							Spinner,
							{
								size: 16,
							},
						),
						JSX(
							'span',
							null,
							__( 'Loading authors...', 'troy-server' ),
						),
					)
					: JSX(
						SelectControl,
						{
							label:    __( 'Author', 'troy-server' ),
							value:    selectedAuthorId,
							options:  [
								{
									label: __( 'Select an author...', 'troy-server' ),
									value: 0,
								},
								...( authors || [] ).map( author => ( {
									label: `${author.name} [${author.id}]`,
									value: author.id,
								} ) ),
							],
							onChange: handleAuthorChange,
							help:     __( 'Choose the author who will be displayed for this plugin.', 'troy-server' ),
							hideLabelFromVision: true,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						},
					),
			),
		);
	}

	/**
	 * Plugin Author Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {number}   props.authorId      The current author ID.
	 *     @param {Function} props.setStoreValue Function to set values in the store.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginAuthorControl( { authorId, setStoreValue } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, __( 'Plugin Author', 'troy-server' ) ),
			[ popoverAnchor ]
		);

		// Get author name from WordPress core data store
		const authorName = useSelect(
			select => {
				if ( ! authorId ) return '';

				const user = select( 'core' ).getUser( authorId );
				return user?.name || '';
			},
			[ authorId ],
		);

		const displayText = authorName || __( 'No author set', 'troy-server' );

		return JSX(
			PanelRow,
			{
				label:       __( 'Author', 'troy-server' ),
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
						},
						displayText,
					),
					renderContent: ( { onClose } ) => JSX(
						PluginAuthorPopover,
						{
							onClose,
							authorId,
							setStoreValue,
						},
					),
				},
			),
		);
	}

	return {
		PluginSlugControl,
		PluginStatusControl,
		PluginAuthorControl,
		ShortDescriptionControl,
		UrlsControl,
		ReadmeSettingsControl,
		PluginVersionsControl,
	};
} )( window.wp );
