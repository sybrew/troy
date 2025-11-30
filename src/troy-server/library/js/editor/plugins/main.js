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

( wp => {
	const { registerPlugin }               = wp.plugins;
	const { PluginDocumentSettingPanel }   = wp.editor;
	const { __, sprintf }                  = wp.i18n;
	const { useDispatch }                  = wp.data;
	const { createElement: JSX, Fragment } = wp.element;

	// Experimental components
	const VStack  = wp.components?.VStack || wp.components?.__experimentalVStack;
	const HStack  = wp.components?.HStack || wp.components?.__experimentalHStack;

	const { Spinner, Button } = wp.components;
	const { Fill } = wp.components;
	const apiFetch = wp.apiFetch;

	// Import general components from editor-components
	const {
		ImageUploadControl,
		PanelRow,
	} = troyServerEditorComponents;

	// Import plugin-specific components from plugin-editor-components.
	const {
		PluginSlugControl,
		PluginStatusControl,
		AutoProcessTagsControl,
		PluginAuthorControl,
		ShortDescriptionControl,
		UrlsControl,
		ReadmeSettingsControl,
		PluginVersionsControl,
		IntegrationControl,
	} = troyServerPluginEditorComponents;

	/**
	 * Plugin Document Settings component.
	 *
	 * @since 0.0.1184
	 *
	 * @returns {JSX.Element} The rendered component.
	 */
	function PluginDocumentSettings() {
		const {
			postId,
			data:           storeData,
			isLoading:      isStoreLoading,
			sortedVersions,
			latestVersion,
			setValue:       setStoreValue,
		} = troyServerGetPluginStore();

		const { createNotice } = useDispatch( 'core/notices' );
		const slugSet          = !! storeData.slug;

		const addVersion = versionData => {

			const versions      = storeData.versions || [];
			const existingIndex = versions.findIndex( v => v.version === versionData.version );

			if ( -1 !== existingIndex ) {
				const newVersions = [ ...versions ];
				newVersions[ existingIndex ] = versionData;
				setStoreValue( 'versions', newVersions );
			} else {
				setStoreValue( 'versions', [ ...versions, versionData ] );
			}
		};

		const updateVersion = versionData => {

			const versions      = storeData.versions || [];
			const existingIndex = versions.findIndex( v => v.version === versionData.version );

			if ( -1 !== existingIndex ) {
				const newVersions = [ ...versions ];
				newVersions[ existingIndex ] = versionData;
				setStoreValue( 'versions', newVersions );
			}
		};

		return JSX(
			Fragment,
			null,
			JSX( // Always render to reserve the slot position.
				Fill,
				{ name: 'PluginDocumentSettingPanel' }, // Hack? Not much else is accepted for plugins.
				isStoreLoading && JSX(
					VStack,
					{
						spacing: 4,
						className: 'troy-server-panel-loading-container',
					},
					JSX(
						HStack,
						{
							spacing:   2,
							alignment: 'left',
						},
						JSX(
							Spinner,
							{ className: 'troy-server-panel-loading-spinner' },
						),
						JSX(
							'span',
							null,
							__( 'Loading plugin data…', 'troy-server' ),
						),
					),
				),
			),
			storeData && JSX(
				PluginDocumentSettingPanel,
				{
					name:        'troy-plugin-settings-panel',
					title:       __( 'Plugin Settings', 'troy-server' ),
					icon:        'admin-plugins',
					initialOpen: ! slugSet,
					className:   isStoreLoading ? 'troy-server-panel--loading' : '',
				},
				JSX(
					VStack,
					{ spacing: 1 },
					storeData.plugin_id && JSX(
						PanelRow,
						{
							label: __( 'Plugin ID', 'troy-server' ),
						},
						JSX(
							Button,
							{
								variant:  'tertiary',
								size:     'compact',
								// disabled: true, // Let's not disable it, it's too grey.
								style:    {
									// No interaction available -- just copying styles.
									cursor:        'default',
									pointerEvents: 'none',
								},
							},
							storeData.plugin_id,
						),
					),
					JSX(
						PluginSlugControl,
						{
							postId,
							plugin_slug:   storeData.slug,
							storeSlug:     value => setStoreValue( 'slug', value ),
							storePluginId: value => setStoreValue( 'plugin_id', value ),
						},
					),
					slugSet && JSX(
						Fragment,
						null,
						JSX(
							PluginStatusControl,
							{
								status:       storeData.status,
								updateStatus: value => setStoreValue( 'status', value ),
							},
						),
						JSX(
							PluginAuthorControl,
							{
								authorId:     storeData.author_id,
								updateAuthor: value => setStoreValue( 'author_id', value ),
							},
						),
						JSX(
							ShortDescriptionControl,
							{
								description:       storeData.short_description || '',
								updateDescription: value => setStoreValue( 'short_description', value ),
							},
						),
						JSX(
							UrlsControl,
							{
								permalink:       storeData.permalink || '',
								supportUri:      storeData.support_uri || '',
								donateUri:       storeData.donate_uri || '',
								updatePermalink: value => setStoreValue( 'permalink', value ),
								updateSupport:   value => setStoreValue( 'support_uri', value ),
								updateDonate:    value => setStoreValue( 'donate_uri', value ),
							},
						),
						JSX(
							ImageUploadControl,
							{
								label:         __( 'Banner', 'troy-server' ),
								aspectRatio:   '772/250',
								value:         storeData.banner_uri,
								help:          __( 'This image is displayed at the top of the plugin info sections. Recommended size: 1544x500 pixels.', 'troy-server' ),
								copyToBlock:   'troy-server/plugin-banner',
								storeImageUri: value => setStoreValue( 'banner_uri', value ),
							},
						),
						JSX(
							ImageUploadControl,
							{
								label:         __( 'Logo', 'troy-server' ),
								aspectRatio:   '1/1',
								value:         storeData.logo_uri,
								help:          __( 'This image is displayed as the plugin logo. Recommended size: 256x256 pixels.', 'troy-server' ),
								copyToBlock:   'troy-server/plugin-logo',
								storeImageUri: value => setStoreValue( 'logo_uri', value ),
							},
						),
						JSX(
							ReadmeSettingsControl,
							{
								builderType:       storeData.builder_type,
								updateBuilderType: value => setStoreValue( 'builder_type', value ),
							},
						),
					),
				),
			),
			slugSet && JSX(
				PluginDocumentSettingPanel,
				{
					name:        'troy-plugin-integrations-panel',
					title:       __( 'Integrations', 'troy-server' ),
					initialOpen: false,
					icon:        'admin-plugins',
					className:   isStoreLoading ? 'troy-server-panel--loading' : '',
				},
				JSX(
					VStack,
					{ spacing: 1 },
					JSX(
						Fragment,
						null,
						storeData.integrations?.mode && JSX(
							AutoProcessTagsControl,
							{
								autoProcess:    storeData.integrations?.auto_process || 'all',
								setAutoProcess: value => setStoreValue(
									'integrations',
									{
										...storeData.integrations,
										auto_process: value,
									},
								),
							},
						),
					),
					JSX(
						Fragment,
						null,
						[
							{
								mode:  'github',
								label: __( 'GitHub', 'troy-server' ),
							},
							{
								mode:  'wporg',
								label: __( 'WordPress.org', 'troy-server' ),
							},
						].map( integration => JSX(
							IntegrationControl,
							{
								key:                   `troy-plugin-integration-control--${integration.mode}`,
								mode:                  integration.mode,
								label:                 integration.label,
								pluginId:              storeData.plugin_id,
								integration:           storeData.integrations,
								storeIntegration:      value => setStoreValue( 'integrations', value ),
								disconnectIntegration: () => setStoreValue( 'integrations', null ),
								onTagProcessed: ( { tagName, version } ) => {

									const versions      = storeData.versions || [];
									const existingIndex = versions.findIndex( v => v.version === version.version );

									if ( -1 !== existingIndex ) {
										updateVersion( version );
									} else {
										addVersion( version );
									}

									createNotice(
										'success',
										sprintf(
											/* translators: %1$s is the tag name, %2$s is the version number */
											__( 'Tag %1$s is imported as version %2$s.', 'troy-server' ),
											tagName,
											version.version,
										),
										{
											isDismissible: true,
											type:          'snackbar',
										},
									);
								},
							},
						) ),
					),
				),
			),
			slugSet && JSX(
				PluginDocumentSettingPanel,
				{
					name:         'troy-plugin-versions-panel',
					title:        __( 'Plugin Versions', 'troy-server' ),
					initialOpen:  true,
					icon:         'media-archive',
					className:    isStoreLoading ? 'troy-server-panel--loading' : '',
				},
				JSX(
					PluginVersionsControl,
					{
						pluginId:      storeData.plugin_id,
						versions:      sortedVersions,
						latestVersion,
						canDownload:   [ 'public', 'unlisted' ].includes( storeData.status ),
						addVersion,
						updateVersion,
						removeVersion: version => apiFetch( {
							url:    troyPluginEditorData.restUrls.removeVersion,
							method: 'POST',
							data:   {
								plugin_id: storeData.plugin_id,
								version,
							},
						} )
							.then( () => {
								// Update local state after successful removal
								setStoreValue(
									'versions',
									storeData.versions.filter( v => v.version !== version ),
								);

								// Show success notification
								createNotice(
									'success',
									sprintf(
										/* translators: %s is the version number */
										__( 'Version %s removed successfully.', 'troy-server' ),
										version
									),
									{
										isDismissible: true,
										type:          'snackbar',
									},
								);

								return true;
							} )
							.catch( error => {
								// Show error notification
								createNotice(
									'error',
									sprintf(
										/* translators: %1$s is the version number, %2$s is the error message */
										__( 'Failed to remove version %1$s: %2$s', 'troy-server' ),
										version,
										error.message || __( 'Unknown error', 'troy-server' )
									),
									{
										isDismissible: true,
										type:          'snackbar',
									},
								);
								console.error( 'Failed to remove version:', error );

								return false;
							} ),
					},
				),
			),
		);
	}

	registerPlugin(
		'troy-plugin-settings',
		{ render: PluginDocumentSettings },
	);
} )( window.wp );
