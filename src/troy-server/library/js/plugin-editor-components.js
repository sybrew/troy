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
 * @param {Object} wp The WordPress global wp object.
 */
window.troyServerPluginEditorComponents = ( wp => {
	const {
		createElement: JSX,
		useState,
		useEffect,
		useRef,
		useMemo,
		Fragment,
	} = wp.element;
	const {
		__,
		sprintf,
		_n,
	} = wp.i18n;
	const { decodeEntities } = wp.htmlEntities;
	const {
		TextControl,
		Button,
		TextareaControl,
		Notice,
		Dropdown,
		RadioControl,
		SelectControl,
		ComboboxControl,
		ExternalLink,
	} = wp.components;

	const { useSelect } = wp.data;

	// Experimental components
	const VStack                    = wp.components?.VStack || wp.components?.__experimentalVStack;
	const HStack                    = wp.components?.HStack || wp.components?.__experimentalHStack;
	const Text                      = wp.components?.Text || wp.components?.__experimentalText;
	const InputControl              = wp.components?.InputControl || wp.components?.__experimentalInputControl;
	const InputControlSuffixWrapper = wp.components?.InputControlSuffixWrapper || wp.components?.__experimentalInputControlSuffixWrapper;
	const InspectorPopoverHeader    = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	const apiFetch = wp.apiFetch;
	const { addQueryArgs } = wp.url;

	// Import general components from editor-components
	const {
		StyledHelp,
		MetadataItem,
		PanelRow,
		createPopoverProps,
	} = troyServerEditorComponents;

	const {
		AUTHORS_BASE_QUERY,
		AUTHORS_QUERY,
	} = troyServerConstants;

	const {
		seen,
		unseen,
	} = troyServerIcons;

	/**
	 * MenuDropdown component - wrapper around WordPress Dropdown with custom styling.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props Component properties passed to the WordPress Dropdown component.
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
	 * @param {Object} props {
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

		const [ localSlug, setLocalSlug ] = useState( plugin_slug || '' );

		const [ isLoading, setIsLoading ]       = useState( false );
		const [ notification, setNotification ] = useState( {
			type:    null,
			message: null,
		} );

		const showError         = message => setNotification( { type: 'error', message } );
		const clearNotification = () => setNotification( { type: null, message: null } );

		const handleStoreSlug = () => {

			setIsLoading( true );
			// Clear previous notifications
			clearNotification();

			apiFetch( {
				url:    troyPluginEditorData.restUrls.registerSlug,
				method: 'POST',
				data:   {
					post_id:     postId,
					plugin_slug: localSlug,
				},
			} )
				.then( response => {
					storeSlug( response.plugin_slug );
					storePluginId( response.plugin_id );
					setIsLoading( false );
					onClose();
				} )
				.catch( error => {

					showError( error.message || __( 'Error storing slug.', 'troy-server' ) );

					let _slug = plugin_slug;

					if ( error.post_id ) {
						if ( error.post_id != postId ) {
							showError( __( 'Slug is already registered for another plugin. Use another one.', 'troy-server' ) );
						} else {
							_slug = error.plugin_slug || '';
						}
					}

					setLocalSlug( _slug );
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
				{ spacing: 4 },
				JSX(
					TextControl,
					{
						label:    __( 'Plugin Slug', 'troy-server' ),
						value:    localSlug,
						onChange: value => {
							setLocalSlug(
								value.toLowerCase()
									.replace( /\s+/g, '-' )
									.replace( /[^a-z0-9-]/g, '' )
									.replace( /-{2,}/g, '-' )
									.replace( /^[^a-z]+/, '' )
							);
						},
						onBlur:   () => {
							setLocalSlug( localSlug.replace( /-+$/g, '' ) );
						},
						pattern:  '[a-z][a-z0-9\\-]*',
						help: __( 'A unique identifier. This will become the wp-content plugin folder for all future releases and ZIP file names for all downloads and is used to localize updates. Assume it to be permanent until Troy Client supports slug migrations.', 'troy-server' ),
						disabled: isLoading,
						hideLabelFromVision: true,
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
						disabled: isLoading || ! localSlug || plugin_slug,
					},
					plugin_slug
						? __( 'Update slug (planned feature)', 'troy-server' )
						: __( 'Reserve slug immediately', 'troy-server' ),
				),
				notification.message && JSX(
					Notice,
					{
						status:        notification.type,
						isDismissible: true,
						onRemove:      clearNotification,
					},
					notification.message,
				),
			),
		);
	}

	/**
	 * Plugin Slug Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
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
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin Slug', 'troy-server' ),
			),
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {string}   props.status        The current plugin status.
	 *     @param {Function} props.updateStatus  Function to update the plugin status.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginStatusPopover( { onClose, status, updateStatus } ) {

		const [ localStatus, setLocalStatus ] = useState( status || 'public' );

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
				{ spacing: 4 },
				JSX(
					RadioControl,
					{
						label:    __( 'Status', 'troy-server' ),
						selected: localStatus,
						options:  troyPluginEditorData.pluginStatuses,
						onChange: newStatus => {
							setLocalStatus( newStatus );
							updateStatus( newStatus );
						},
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.status       The current plugin status.
	 *     @param {Function} props.updateStatus Function to update the plugin status.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginStatusControl( { status, updateStatus } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin Status', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const statusLabel = troyPluginEditorData.pluginStatuses?.find(
			option => option.value === status,
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
							updateStatus,
						},
					),
				},
			),
		);
	}

	/**
	 * Auto-Process Tags Popover Control component.
	 *
	 * @since 0.0.1201
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose        Callback function to close the popover.
	 *     @param {string}   props.autoProcess    The current auto-process setting.
	 *                                            Accepts 'all', 'tag', 'beta', and 'none'.
	 *     @param {Function} props.setAutoProcess Function to set the auto-process value.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function AutoProcessTagsPopover( { onClose, autoProcess, setAutoProcess } ) {

		const [ localAutoProcess, setLocalAutoProcess ] = useState( autoProcess );

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: __( 'Auto-Process Tags', 'troy-server' ),
					onClose,
				},
			),
			JSX(
				VStack,
				{ spacing: 4 },
				JSX(
					RadioControl,
					{
						label:    __( 'Auto-process tags', 'troy-server' ),
						selected: localAutoProcess,
						options:  troyPluginEditorData.autoProcess,
						onChange: newValue => {
							setLocalAutoProcess( newValue );
							setAutoProcess( newValue );
						},
						help:     __( 'Automatically import new tags when discovered.', 'troy-server' ),
						hideLabelFromVision: true,
					},
				),
			),
		);
	}

	/**
	 * Auto-Process Tags Control component for sidebar display.
	 *
	 * @since 0.0.1201
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.autoProcess    The current auto-process setting.
	 *                                            Accepts 'all', 'tag', 'beta', and 'none'.
	 *     @param {Function} props.setAutoProcess Function to set the auto-process value.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function AutoProcessTagsControl( { autoProcess, setAutoProcess } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Auto-Process Tags', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const autoProcessLabel = troyPluginEditorData.autoProcess?.find(
			option => option.value === autoProcess,
		)?.label || autoProcess;

		return JSX(
			PanelRow,
			{
				label:       __( 'Auto-process', 'troy-server' ),
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
						autoProcessLabel,
					),
					renderContent: ( { onClose } ) => JSX(
						AutoProcessTagsPopover,
						{
							onClose,
							autoProcess,
							setAutoProcess,
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose            Callback function to close the popover.
	 *     @param {Object}   props.description        The current plugin description.
	 *     @param {Function} props.updateDescription  Function to update the plugin description.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ShortDescriptionPopover( { onClose, description, updateDescription } ) {

		const [ localDescription, setLocalDescription ] = useState( description );

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
				{ spacing: 4 },
				JSX(
					TextareaControl,
					{
						label:     sprintf(
							/* translators: %d is the character count, 150 is the recommended maximum length */
							__( 'Description - %d/150 characters', 'troy-server' ),
							localDescription.length,
						),
						value:     localDescription,
						onChange:  value => {
							setLocalDescription( value );
							updateDescription( value );
						},
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}   props.description        The current plugin description.
	 *     @param {Function} props.updateDescription  Function to update the plugin description.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ShortDescriptionControl( { description, updateDescription } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Short Description', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const displayText = description.length > 150
			? description.substring( 0, 149 ) + '…'
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
							updateDescription,
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose          Callback function to close the popover.
	 *     @param {string}   props.permalink        The current plugin permalink URL.
	 *     @param {string}   props.supportUri       The current plugin support URI.
	 *     @param {Function} props.updatePermalink  Function to update the permalink.
	 *     @param {Function} props.updateSupport    Function to update the support URI.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function UrlsPopover( { onClose, permalink, supportUri, updatePermalink, updateSupport } ) {

		const [ localPermalink, setLocalPermalink ]   = useState( permalink );
		const [ localSupportUri, setLocalSupportUri ] = useState( supportUri );

		const [ permalinkPlaceholder, setPermalinkPlaceholder ] = useState( '' );

		useSelect(
			select => {
				if ( ! permalinkPlaceholder )
					setPermalinkPlaceholder( select( 'core/editor' ).getPermalink() );
			},
			[ permalinkPlaceholder ],
		);

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
				{ spacing: 4 },
				JSX(
					TextControl,
					{
						label:       __( 'Custom Permalink', 'troy-server' ),
						value:       localPermalink,
						placeholder: permalinkPlaceholder,
						onChange:    value => {
							setLocalPermalink( value );
							updatePermalink( value );
						},
						type:        'url',
						help:        __( 'This link is used when information about this plugin is requested, also known as the "plugin homepage".', 'troy-server' ),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize:   true,
					},
				),
				JSX(
					TextControl,
					{
						label:    __( 'Support URI', 'troy-server' ),
						value:    localSupportUri,
						onChange: value => {
							setLocalSupportUri( value );
							updateSupport( value );
						},
						type:     'url',
						help:     __( 'A link to the plugin support forum or contact page.', 'troy-server' ),
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
	 *     @param {string}   props.permalink        The current plugin permalink.
	 *     @param {string}   props.supportUri       The current plugin support URI.
	 *     @param {Function} props.updatePermalink  Function to update the permalink.
	 *     @param {Function} props.updateSupport    Function to update the support URI.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function UrlsControl( { permalink, supportUri, updatePermalink, updateSupport } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin URLs', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const hasUrls = permalink || supportUri;

		return JSX(
			PanelRow,
			{
				label:       __( 'URLs', 'troy-server' ),
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
						hasUrls
							? __( 'Update URLs', 'troy-server' )
							: __( 'Set URLs', 'troy-server' ),
					),
					renderContent: ( { onClose } ) => JSX(
						UrlsPopover,
						{
							onClose,
							permalink,
							supportUri,
							updatePermalink,
							updateSupport,
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose           Callback function to close the popover.
	 *     @param {string}   props.builderType       The current builder type.
	 *     @param {Function} props.updateBuilderType Function to update the builder type.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ReadmeSettingsPopover( { onClose, builderType, updateBuilderType } ) {

		const [ localBuilderType, setLocalBuilderType ] = useState( builderType );

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
				{ spacing: 4 },
				JSX(
					RadioControl,
					{
						label:               __( 'Builder Type', 'troy-server' ),
						selected:            localBuilderType,
						options:             troyPluginEditorData.builderTypes,
						onChange:            value => {
							setLocalBuilderType( value );
							updateBuilderType( value );
						},
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
	 * @param {Object} props {
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
			() => createPopoverProps(
				popoverAnchor,
				__( 'Readme Settings', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		const builderTypeLabel = troyPluginEditorData.builderTypes?.find(
			option => option.value === builderType
		)?.label || builderType;

		return JSX(
			Fragment,
			null,
			JSX(
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

		const { sanitizeRepoUrl } = troyServerEditorUtils;

		const currentRepoUrl           = sanitizeRepoUrl( troyPluginEditorData.originUrl );
		const versionsWithRepoMismatch = useMemo(
			() => {
				if ( ! versions?.length ) return [];

				return versions.filter(
					version => sanitizeRepoUrl( version.repo ) !== currentRepoUrl,
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose                   Callback function to close the popover.
	 *     @param {Object}   props.version                   The version object data.
	 *     @param {number}   props.index                     The version index in the array.
	 *     @param {Boolean}  props.isLatestVersion           Whether this is the latest version.
	 *     @param {Boolean}  props.hasRepoMismatch           Whether this version has a repository mismatch.
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
		handleTypeChange,
		handleUpgradeNoticeChange,
		handleRemoveToggle,
	} ) {

		const isRemovedVersion = version.remove;
		const {
			sanitizeRepoUrl,
			bytesToIbiBytes,
		} = troyServerEditorUtils;

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
				// var_dump() test me:
				version.source_url && JSX(
					VStack,
					{ spacing: 2 },
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
								value: bytesToIbiBytes( version.file_size ),
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
						JSX(
							MetadataItem,
							{
								label: __( 'Repository:', 'troy-server' ),
								value: sanitizeRepoUrl( version.repo ),
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Object}   props.version                   The version object data.
	 *     @param {number}   props.index                     The version index in the array.
	 *     @param {Boolean}  props.isLatestVersion           Whether this is the latest version.
	 *     @param {Boolean}  props.hasRepoMismatch           Whether this version has a repository mismatch.
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
	 * Custom hook for querying authors with search functionality.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string}  search   The search term to filter authors.
	 * @returns {Object} Object containing authorOptions, isLoading, and showCombobox.
	 */
	function useAuthorsQuery( search = '' ) {

		const showCombobox = useSelect(
			select => {
				return select( 'core' ).getUsers( AUTHORS_QUERY )?.length >= 25; // 25 is also used in WordPress core.
			},
			[],
		);

		// Get authors list (and optionally search)
		const { authors, isLoading } = useSelect(
			select => {
				const { getUsers, isResolving } = select( 'core' );
				const query = { ...AUTHORS_QUERY };

				// Add search if using combobox and search term exists
				if ( search ) {
					query.search         = search;
					query.search_columns = [ 'name' ];
				}

				return {
					authors:   getUsers( query ),
					isLoading: isResolving( 'getUsers', [ query ] ),
				};
			},
			[ search ],
		);

		// Create author options
		const authorOptions = useMemo(
			() => {
				const fetchedAuthors = ( authors ?? [] )
					.map( author => ( {
						value: author.id,
						label: decodeEntities( `${author.name} [${author.id}]` ),
					} ) );

				// For SelectControl, prepend placeholder when using SelectControl
				if ( ! showCombobox ) {
					return [
						{
							value: 0,
							label: __( 'Select an author…', 'troy-server' ),
						},
						...fetchedAuthors,
					];
				}

				return fetchedAuthors;
			},
			[ authors, showCombobox ],
		);

		return {
			authorOptions,
			isLoading,
			showCombobox,
		};
	}

	/**
	 * Plugin Author Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {number}   props.authorId      The current author ID.
	 *     @param {Function} props.updateAuthor  Function to update the author ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginAuthorPopover( { onClose, authorId, updateAuthor } ) {

		const [ selectedAuthorId, setSelectedAuthorId ] = useState( authorId || 0 );
		const [ filterValue, setFilterValue ]           = useState( '' );

		const { authorOptions, isLoading, showCombobox } = useAuthorsQuery( filterValue );

		const { debounce } = troyServerEditorUtils;


		const handleAuthorChange = newAuthorId => {
			const authorId = parseInt( newAuthorId ) || 0;
			setSelectedAuthorId( authorId );
			updateAuthor( authorId );
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
				{ spacing: 4 },
				showCombobox
					? JSX(
						ComboboxControl,
						{
							label:               __( 'Author', 'troy-server' ),
							value:               selectedAuthorId || '',
							options:             authorOptions,
							onChange:            handleAuthorChange,
							onFilterValueChange: debounce( setFilterValue, 300 ),
							help:                __( 'Type to search for authors. Choose the author who will be displayed for this plugin.', 'troy-server' ),
							hideLabelFromVision: true,
							isLoading,
							allowReset:          true,
							placeholder:         __( 'Search authors…', 'troy-server' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize:   true,
						},
					)
					: JSX(
						SelectControl,
						{
							label:               __( 'Author', 'troy-server' ),
							value:               selectedAuthorId,
							options:             authorOptions,
							onChange:            newAuthorId => {
								const authorId = parseInt( newAuthorId ) || 0;
								setSelectedAuthorId( authorId );
								updateAuthor( authorId );
							},
							help:                __( 'Choose the author who will be displayed for this plugin.', 'troy-server' ),
							hideLabelFromVision: true,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize:   true,
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
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {number}   props.authorId     The current author ID.
	 *     @param {Function} props.updateAuthor Function to update the author ID.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginAuthorControl( { authorId, updateAuthor } ) {

		const [ popoverAnchor, setPopoverAnchor ] = useState( null );

		const popoverProps = useMemo(
			() => createPopoverProps(
				popoverAnchor,
				__( 'Plugin Author', 'troy-server' ),
			),
			[ popoverAnchor ],
		);

		// Get author name directly from WordPress core data store (following WordPress core pattern)
		const authorName = useSelect(
			select => {
				if ( ! authorId ) return '';

				return select( 'core' ).getUser( authorId, AUTHORS_BASE_QUERY )?.name
					|| '';
			},
			[ authorId ],
		);

		const displayText = authorName
			? decodeEntities( authorName )
			: __( 'No author set', 'troy-server' );

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
							updateAuthor,
						},
					),
				},
			),
		);
	}

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
					style: {
						fontWeight: 'bold',
					},
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
					troyServerEditorUtils.formatTimestamp( tagsRefreshed ),
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
								style: {
									fontWeight: 'bold',
								},
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
								style: {
									color: '#757575',
								},
							},
							__( 'Loading...', 'troy-server' ),
						)
						: JSX(
							'p',
							{
								style: {
									color: '#757575',
								},
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
			url: troyPluginEditorData.restUrls.integrations.tags.process,
			method: 'POST',
			data:   {
				plugin_id:    pluginId,
				version_name: tagName,
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
							{
								className: 'troy-server-version-metadata-separator',
							},
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
							{
								className: 'troy-server-version-metadata-separator',
							},
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
													icon:    tokenState.value ? seen : unseen,
													label:   tokenState.value ? __( 'Hide token', 'troy-server' ) : __( 'Reveal token', 'troy-server' ),
													size:    'small',
													onClick: tokenState.value
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

	return {
		PluginSlugControl,
		PluginStatusControl,
		AutoProcessTagsControl,
		PluginAuthorControl,
		ShortDescriptionControl,
		UrlsControl,
		ReadmeSettingsControl,
		PluginVersionsControl,
		IntegrationControl,
	};
} )( window.wp );
