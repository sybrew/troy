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
 * @module troyServerEditorComponents
 * @description Reusable components for the Troy Server plugin and theme editor.
 * @since 0.0.1184
 */
window.troyServerEditorComponents = ( wp => {
	const {
		createElement: JSX,
		useState,
		useMemo,
		Fragment,
	} = wp.element;
	const {
		__,
	} = wp.i18n;
	const {
		useSelect,
		useDispatch,
	} = wp.data;
	const {
		Button,
		Dropdown,
	} = wp.components;

	// Experimental components
	const VStack  = wp.components?.VStack || wp.components?.__experimentalVStack;
	const HStack  = wp.components?.HStack || wp.components?.__experimentalHStack;

	const {
		store: blockEditorStore,
		MediaUpload,
		MediaUploadCheck,
	} = wp.blockEditor;

	const InspectorPopoverHeader = wp.blockEditor?.InspectorPopoverHeader || wp.blockEditor?.__experimentalInspectorPopoverHeader;

	/**
	 * StyledHelp component mimicking the WordPress components StyledHelp styling.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}           props.className Additional CSS classes.
	 *     @param {string}           props.id        Element ID.
	 *     @param {React.ReactNode}  props.children  Help text content.
	 * }
	 * @returns {JSX.Element} The styled help element.
	 */
	function StyledHelp( { className = '', id, children, ...props } ) {
		return JSX(
			'p',
			{
				className: `components-base-control__help ${className}`,
				id,
				style: {
					marginTop:    '8px',
					marginBottom: '0',
					fontSize:     '12px',
					fontStyle:    'normal',
					color:        '#757575',
				},
				...props,
			},
			children,
		);
	}

	/**
	 * Metadata Item component for displaying key-value pairs.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}  props.label The label text to display.
	 *     @param {string}  props.value The value text to display.
	 *     @param {?string} props.state The state for styling ('warning', 'error').
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function MetadataItem( { label, value, state } ) {

		let stateClass = '';

		switch ( state ) {
			case 'warning':
				stateClass = 'troy-server-metadata-item--warning';
				break;
			case 'error':
				stateClass = 'troy-server-metadata-item--error';
		}

		return JSX(
			HStack,
			{
				spacing:   2,
				alignment: 'left',
				className: `troy-server-metadata-item ${stateClass}`,
			},
			JSX(
				'span',
				{
					className: 'troy-server-metadata-item__label',
				},
				label,
			),
			JSX(
				'span',
				{
					className: 'troy-server-metadata-item__value',
				},
				value,
			),
		);
	}

	/**
	 * Panel Row component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} props {
	 *     Component properties.
	 *
	 *     @param {string}    props.label       The label for the panel row.
	 *     @param {ReactNode} props.children    The child components to render.
	 *     @param {string}    props.className   Additional CSS classes.
	 *     @param {Function}  props.onRefChange Callback for ref changes.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function PanelRow( { label, children, className, onRefChange, ...props } ) {
		return JSX(
			HStack,
			{
				ref:        onRefChange,
				className: `troy-server-panel-row ${className || ''}`,
				...props,
			},
			label && JSX(
				'div',
				{
					className: children
						? 'troy-server-panel-row__label'
						: 'troy-server-panel-row__label--no-control',
				},
				label,
			),
			children && JSX(
				'div',
				{
					className: 'troy-server-panel-row__control',
				},
				children,
			),
		);
	}

	/**
	 * Creates popover props with consistent settings.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} anchor The anchor element for the popover.
	 * @param {string} title  The title for the popover.
	 * @returns {Object} The popover props object.
	 */
	function createPopoverProps( anchor, title ) {
		return {
			anchor,
			'aria-label': title,
			headerTitle:  title,
			placement:    'left-start',
			offset:       36,
			shift:        true,
			className:    'troy-server-popover',
		};
	}

	/**
	 * Image Upload Popover Control component.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {Function} props.onClose       Callback function to close the popover.
	 *     @param {string}   props.label         The label for the image upload control.
	 *     @param {string}   props.value         The current image URL.
	 *     @param {string}   props.aspectRatio   The aspect ratio for the image preview.
	 *     @param {string}   props.help          Help text for the control.
	 *     @param {Function} props.storeImageUri Function to store the image URL.
	 *     @param {string}   props.copyToBlock   The block name to copy the image to.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ImageUploadPopover( { onClose, label, value, aspectRatio, help, storeImageUri, copyToBlock } ) {
		const { updateBlockAttributes } = useDispatch( 'core/block-editor' );
		const targetBlockClientIds = useSelect(
			select => select( blockEditorStore )
				.getBlocksByName( copyToBlock )
				.map( block => block.clientId ),
			[ copyToBlock ],
		);

		const updateImageBlockAttributes = attributes => {
			if ( targetBlockClientIds.length )
				updateBlockAttributes( targetBlockClientIds, attributes );
		};

		return JSX(
			Fragment,
			null,
			JSX(
				InspectorPopoverHeader,
				{
					title: label,
					onClose,
				},
			),
			JSX(
				VStack,
				{
					spacing: 4,
				},
				value && JSX(
					'div',
					{
						className: 'troy-server-editor-plugin-image-preview',
					},
					JSX(
						'img',
						{
							src:   value,
							alt:   label,
							style: aspectRatio ? { aspectRatio } : {},
						},
					),
				),
				JSX(
					MediaUploadCheck,
					null,
					JSX(
						MediaUpload,
						{
							onSelect: media => {
								storeImageUri( media.url );
								updateImageBlockAttributes( { url: media.url } );
							},
							allowedTypes: [ 'image' ],
							value: value ? { url: value } : undefined,
							render: ( { open } ) => JSX(
								Button,
								{
									onClick: open,
									variant: 'secondary',
									size:    'compact',
								},
								value ? __( 'Replace Image', 'troy-server' ) : __( 'Select Image', 'troy-server' ),
							),
						},
					),
				),
				value && JSX(
					Button,
					{
						onClick: () => {
							storeImageUri( '' );
							updateImageBlockAttributes( { url: '' } );
						},
						variant:       'secondary',
						size:          'compact',
						isDestructive: true,
					},
					__( 'Remove Image', 'troy-server' ),
				),
				help && JSX(
					StyledHelp,
					null,
					help,
				),
			),
		);
	}

	/**
	 * Image Upload Control component for sidebar display.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object}   props {
	 *     Component properties.
	 *
	 *     @param {string}   props.aspectRatio   The aspect ratio for the image preview.
	 *     @param {string}   props.label         The label for the image upload control.
	 *     @param {string}   props.value         The current image URL.
	 *     @param {string}   [props.help]        Optional help text for the control.
	 *     @param {string}   props.copyToBlock   The block name to which the image URL should be copied.
	 *     @param {Function} props.storeImageUri Function to store the image URL in the plugin state.
	 * }
	 * @returns {JSX.Element} The rendered component.
	 */
	function ImageUploadControl( {
		label,
		aspectRatio,
		value,
		help,
		copyToBlock,
		storeImageUri,
	} ) {
		const [ popoverAnchor, setPopoverAnchor ] = useState( null );
		const popoverProps = useMemo(
			() => createPopoverProps( popoverAnchor, label ),
			[ popoverAnchor, label ]
		);

		return JSX(
			PanelRow,
			{
				label,
				onRefChange: setPopoverAnchor,
			},
			JSX(
				Dropdown,
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
						JSX(
							HStack,
							{
								spacing:   2,
								alignment: 'center',
							},
							JSX(
								'span',
								null,
								value ? __( 'Image set', 'troy-server' ) : __( 'No image', 'troy-server' ),
							),
							value && JSX(
								'div',
								{
									className: 'troy-server-editor-plugin-image-preview-thumbnail',
								},
								JSX(
									'img',
									{
										src:   value,
										style: aspectRatio ? { aspectRatio } : {},
									},
								),
							),
						),
					),
					renderContent: ( { onClose } ) => JSX(
						ImageUploadPopover,
						{
							onClose,
							label,
							value,
							aspectRatio,
							help,
							storeImageUri,
							copyToBlock,
						},
					),
				},
			),
		);
	}

	return {
		StyledHelp,
		MetadataItem,
		PanelRow,
		createPopoverProps,
		ImageUploadPopover,
		ImageUploadControl,
	};
} )( window.wp );
