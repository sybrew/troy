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

( () => {

	const escape   = window.troyServerEscape;
	const sanitize = window.troyServerSanitize;

	const config   = window.troyServerStats || {};
	const restBase = config.restBase || '';
	const nonce    = config.nonce || '';
	const i18n     = config.i18n || {};

	const elements = new Map();

	/**
	 * Gets an element by ID, with memoization.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} id The element ID (without prefix).
	 * @return {Element|null} The element or null.
	 */
	const getEl = id => {

		if ( ! elements.size ) {
			[
				'range',
				'overview-cards',
				'plugins-table',
				'modal',
				'modal-title',
				'modal-body',
			].forEach( key => elements.set( key, document.getElementById( `troy-server-stats-${ key }` ) ) );
		}

		return elements.get( id );
	};

	/**
	 * Gets date range parameters based on selection.
	 *
	 * @since 0.0.1184
	 *
	 * @return {Object} Object with start_date and end_date, or empty.
	 */
	const getDateParams = () => {

		const range = getEl( 'range' )?.value;

		if ( ! range || 'all' === range )
			return {};

		const days      = parseInt( range, 10 );
		const endDate   = new Date();
		const startDate = new Date();

		startDate.setDate( startDate.getDate() - days );

		return {
			start_date: startDate.toISOString().split( 'T' )[ 0 ],
			end_date:   endDate.toISOString().split( 'T' )[ 0 ],
		};
	};

	/**
	 * Makes a REST API request.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} endpoint The endpoint path.
	 * @param {Object} params   Query parameters.
	 * @return {Promise<Object>} The fetch promise resolving to JSON data.
	 */
	const fetchStats = async ( endpoint, params = {} ) => {

		const url = new URL( `${ restBase }/${ endpoint }` );

		Object.entries( params ).forEach( ( [ key, value ] ) => {
			if ( undefined !== value && null !== value )
				url.searchParams.append( key, value );
		} );

		const response = await fetch(
			url,
			{
				method:  'GET',
				headers: {
					'X-WP-Nonce':   nonce,
					'Content-Type': 'application/json',
				},
			},
		);

		if ( ! response.ok )
			throw new Error( `HTTP ${ response.status }` );

		return response.json();
	};

	/**
	 * Updates the overview cards with new data.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} data Overview data from the API.
	 */
	const updateOverviewCards = data => {

		const overviewCards = getEl( 'overview-cards' );

		if ( ! overviewCards )
			return;

		const statMap = {
			total_downloads:   sanitize.number( data.total_downloads ),
			total_installs:    sanitize.number( data.total_installs ),
			active_installs:   sanitize.number( data.active_installs ),
			inactive_installs: sanitize.number( data.inactive_installs ),
			total_views:       sanitize.number( data.total_views ),
			this_epoch:        String( data.this_epoch ?? '-' ),
			total_plugins:     sanitize.number( data.total_plugins ),
			last_snapshot:     data.last_snapshot || '-',
		};

		Object.entries( statMap ).forEach( ( [ key, value ] ) => {
			const el = overviewCards.querySelector( `[data-stat="${ key }"]` );

			if ( el )
				el.textContent = escape.string( value ); // textContent already escapes, but just in case
		} );
	};

	/**
	 * Updates the plugins table with new data.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Array} plugins Array of plugin data from the API.
	 */
	const updatePluginsTable = plugins => {

		const pluginsTable = getEl( 'plugins-table' );

		if ( ! pluginsTable )
			return;

		const tbody = pluginsTable.querySelector( 'tbody' );

		if ( ! plugins.length ) {
			tbody.innerHTML = `<tr><td colspan="7">${ i18n.noData }</td></tr>`;
			return;
		}

		tbody.innerHTML = plugins
			.map(
				plugin => `
					<tr data-plugin-id="${ +plugin.plugin_id }">
						<td>
							<strong>${ escape.string( plugin.name ) }</strong> <code>(${ escape.string( plugin.slug ) })</code>
						</td>
						<td>${ sanitize.number( plugin.downloads ) }</td>
						<td>${ sanitize.number( plugin.views ) }</td>
						<td>${ sanitize.number( plugin.total_installs ) }</td>
						<td>${ sanitize.number( plugin.active_installs ) }</td>
						<td>${ sanitize.number( plugin.inactive_installs ) }</td>
						<td>
							<button type="button" class="button button-small troy-server-stats-details-btn" data-plugin-id="${ +plugin.plugin_id }">
								${ i18n.details }
							</button>
						</td>
					</tr>
				`,
			)
			.join( '' );

		bindPluginDetailButtons();
	};

	/**
	 * Shows the modal with content.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} title   Modal title.
	 * @param {string} content HTML content for the modal body.
	 */
	const showModal = ( title, content ) => {

		const modal      = getEl( 'modal' );
		const modalTitle = getEl( 'modal-title' );
		const modalBody  = getEl( 'modal-body' );

		if ( ! modal )
			return;

		modalTitle.textContent = title;
		modalBody.innerHTML    = content;
		modal.hidden           = false;

		document.body.style.overflow = 'hidden';
	};

	/**
	 * Hides the modal and restores body scroll.
	 *
	 * @since 0.0.1184
	 */
	const hideModal = () => {

		const modal = getEl( 'modal' );

		if ( ! modal )
			return;

		modal.hidden                 = true;
		document.body.style.overflow = '';
	};

	/**
	 * Shows plugin details in the modal.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} pluginId The plugin ID to fetch details for.
	 */
	const showPluginDetails = async pluginId => {

		showModal( i18n.loading, `<p class="troy-server-stats-loading">${ i18n.loading }</p>` );

		try {
			const data = await fetchStats( `plugin/${ pluginId }`, getDateParams() );

			getEl( 'modal-title' ).textContent = escape.string( data.name ); // textContent already escapes, but just in case
			getEl( 'modal-body' ).innerHTML    = buildPluginDetailsHtml( data );
		} catch ( error ) {
			getEl( 'modal-body' ).innerHTML = `<div class="troy-server-stats-error">${ i18n.error }</div>`;
		}
	};

	/**
	 * Shows package details in the modal.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} packageId The package ID to fetch details for.
	 */
	const showPackageDetails = async packageId => {

		showModal( i18n.loading, `<p class="troy-server-stats-loading">${ i18n.loading }</p>` );

		try {
			const data = await fetchStats( `package/${ packageId }` );

			getEl( 'modal-title' ).textContent = data.name;
			getEl( 'modal-body' ).innerHTML    = buildPackageDetailsHtml( data );
		} catch ( error ) {
			getEl( 'modal-body' ).innerHTML = `<div class="troy-server-stats-error">${ i18n.error }</div>`;
		}
	};

	/**
	 * Builds an HTML detail section with a list of label-value pairs.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} title    The section heading.
	 * @param {Array}  items    Array of data items.
	 * @param {string} labelKey Property name for the label.
	 * @param {string} valueKey Property name for the value.
	 * @param {string} fallback Fallback text when label is empty.
	 * @param {string} formatAs Value format type: 'string' or 'number'.
	 * @return {string} HTML string for the detail section.
	 */
	const buildDetailSection = ( title, items, labelKey, valueKey, fallback = null, formatAs = 'number' ) => {

		if ( ! items?.length )
			return '';

		const listItems = items
			.map( item => {
				const label = escape.string( item[ labelKey ] || fallback || i18n.notReported );
				const value = 'string' === formatAs
					? escape.string( item[ valueKey ] )
					: sanitize.number( item[ valueKey ] );

				return `<li><span class="label">${ label }</span><span class="value">${ value }</span></li>`;
			} )
			.join( '' );

		return `
			<div class="troy-server-stats-detail-section">
				<h4>${ escape.string( title ) }</h4>
				<ul class="troy-server-stats-detail-list">${ listItems }</ul>
			</div>
		`;
	};

	/**
	 * Builds an HTML stats card.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} label    The card label.
	 * @param {*}      value    The card value.
	 * @param {string} formatAs Value format type: 'string' or 'number'.
	 * @return {string} HTML string for the card.
	 */
	const buildCard = ( label, value, formatAs = 'number' ) => `
		<div class="troy-server-stats-card">
			<span class="troy-server-stats-card-label">${ escape.string( label ) }</span>
			<span class="troy-server-stats-card-value">${
				'string' === formatAs ? escape.string( value ) : sanitize.number( value )
			}</span>
		</div>
	`;

	/**
	 * Calculates installations for an epoch from the epoch_installs data.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} epochInstalls The epoch_installs object from the API.
	 * @param {number} epoch         The epoch number to calculate for.
	 * @return {number} Total installations for the epoch.
	 */
	const getEpochInstalls = ( epochInstalls, epoch ) => {

		const epochData = epochInstalls?.[ epoch ];

		if ( ! epochData )
			return 0;

		return ( epochData.active || 0 ) + ( epochData.inactive || 0 );
	};

	/**
	 * Formats a change value with a sign prefix.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} change The change value.
	 * @return {string} Formatted change string with sign.
	 */
	const formatChange = change => {

		if ( 0 === change )
			return '0';

		return change > 0 ? `+${ sanitize.number( change ) }` : sanitize.number( change );
	};

	/**
	 * Calculates percentage change between two values.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} thisEpoch This epoch value.
	 * @param {number} lastEpoch Last epoch value.
	 * @return {number|null} Percentage change, Infinity for new values, or null if no data.
	 */
	const calcChangePercent = ( thisEpoch, lastEpoch ) => {

		if ( ! lastEpoch )
			return thisEpoch ? Infinity : null;

		// Multiply by 1000 and divide by 10 to get one decimal place.
		return Math.round( ( ( thisEpoch - lastEpoch ) / lastEpoch ) * 1000 ) / 10;
	};

	/**
	 * Formats a change value with percentage.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number}      change  The absolute change value.
	 * @param {number|null} percent The percentage change.
	 * @return {string} HTML string with formatted change and percentage in bold.
	 */
	const formatChangeWithPercent = ( change, percent ) => {

		const changeStr = formatChange( change );

		if ( null === percent )
			return changeStr;

		if ( Infinity === percent )
			return `${ changeStr } <b>(+∞%)</b>`;

		const sign       = percent >= 0 ? '+' : '';
		const percentStr = `${ sign }${ percent }%`;

		return `${ changeStr } <b>(${ percentStr })</b>`;
	};

	/**
	 * Gets CSS class for change value.
	 *
	 * @since 0.0.1184
	 *
	 * @param {number} change The change value.
	 * @return {string} CSS class name.
	 */
	const getChangeClass = change => {

		if ( change > 0 )
			return 'troy-server-stats-positive';

		if ( change < 0 )
			return 'troy-server-stats-negative';

		return '';
	};

	/**
	 * Builds the epoch comparison table HTML with per-version breakdown.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} data Plugin details data from the API.
	 * @return {string} HTML string for the epoch comparison table.
	 */
	const buildEpochComparisonTable = data => {

		const thisEpochInstalls = getEpochInstalls( data.epoch_installs, data.this_epoch );
		const lastEpochInstalls = getEpochInstalls( data.epoch_installs, data.last_epoch );
		const installsChange    = thisEpochInstalls - lastEpochInstalls;
		const installsPercent   = calcChangePercent( thisEpochInstalls, lastEpochInstalls );

		const thisRequests    = data.epoch_installs?.[ data.this_epoch ]?.requests || 0;
		const lastRequests    = data.epoch_installs?.[ data.last_epoch ]?.requests || 0;
		const requestsChange  = thisRequests - lastRequests;
		const requestsPercent = calcChangePercent( thisRequests, lastRequests );

		const thisActive    = data.epoch_installs?.[ data.this_epoch ]?.active || 0;
		const lastActive    = data.epoch_installs?.[ data.last_epoch ]?.active || 0;
		const activeChange  = thisActive - lastActive;
		const activePercent = calcChangePercent( thisActive, lastActive );

		const thisInactive    = data.epoch_installs?.[ data.this_epoch ]?.inactive || 0;
		const lastInactive    = data.epoch_installs?.[ data.last_epoch ]?.inactive || 0;
		const inactiveChange  = thisInactive - lastInactive;
		const inactivePercent = calcChangePercent( thisInactive, lastInactive );

		return `
		<div class="troy-server-stats-detail-section">
			<h4>${ escape.string( i18n.epochComparison ) }</h4>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col">${ escape.string( i18n.metric ) }</th>
						<th scope="col">${ escape.string( i18n.lastEpochHeader.replace( '%d', data.last_epoch ) ) }</th>
						<th scope="col">${ escape.string( i18n.thisEpochHeader.replace( '%d', data.this_epoch ) ) }</th>
						<th scope="col">${ escape.string( i18n.change ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>${ escape.string( i18n.updateRequests ) }</td>
						<td>${ sanitize.number( lastRequests ) }</td>
						<td>${ sanitize.number( thisRequests ) }</td>
						<td class="${ getChangeClass( requestsChange ) }">${ formatChangeWithPercent( requestsChange, requestsPercent ) }</td>
					</tr>
					<tr>
						<td>${ escape.string( i18n.totalInstallations ) }</td>
						<td>${ sanitize.number( lastEpochInstalls ) }</td>
						<td>${ sanitize.number( thisEpochInstalls ) }</td>
						<td class="${ getChangeClass( installsChange ) }">${ formatChangeWithPercent( installsChange, installsPercent ) }</td>
					</tr>
					<tr>
						<td>${ escape.string( i18n.activeInstalls ) }</td>
						<td>${ sanitize.number( lastActive ) }</td>
						<td>${ sanitize.number( thisActive ) }</td>
						<td class="${ getChangeClass( activeChange ) }">${ formatChangeWithPercent( activeChange, activePercent ) }</td>
					</tr>
					<tr>
						<td>${ escape.string( i18n.inactiveInstalls ) }</td>
						<td>${ sanitize.number( lastInactive ) }</td>
						<td>${ sanitize.number( thisInactive ) }</td>
						<td class="${ getChangeClass( inactiveChange ) }">${ formatChangeWithPercent( inactiveChange, inactivePercent ) }</td>
					</tr>
				</tbody>
			</table>
		</div>
	`;
	};

	/**
	 * Builds the version details table HTML.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} data Plugin details data from the API.
	 * @return {string} HTML string for the version details table.
	 */
	const buildVersionDetailsTable = data => {

		if ( ! data.version_details?.length )
			return '';

		const rows = data.version_details
			.map( item => {

				const downloads     = parseInt( item.downloads, 10 ) || 0;
				const totalInstalls = parseInt( item.total_installs, 10 ) || 0;
				const active        = parseInt( item.active_installs, 10 ) || 0;
				const inactive      = totalInstalls - active;

				return `
					<tr>
						<td>${ escape.string( item.version ) }</td>
						<td>${ sanitize.number( downloads ) }</td>
						<td>${ sanitize.number( totalInstalls ) }</td>
						<td>${ sanitize.number( active ) }</td>
						<td>${ sanitize.number( inactive ) }</td>
					</tr>
				`;
			} )
			.join( '' );

		return `
		<div class="troy-server-stats-detail-section">
			<h4>${ escape.string( i18n.detailsPerVersion ) }</h4>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col">${ escape.string( i18n.version ) }</th>
						<th scope="col">${ escape.string( i18n.totalDownloads ) }</th>
						<th scope="col">${ escape.string( i18n.installations ) }</th>
						<th scope="col">${ escape.string( i18n.activeInstalls ) }</th>
						<th scope="col">${ escape.string( i18n.inactiveInstalls ) }</th>
					</tr>
				</thead>
				<tbody>
					${ rows }
				</tbody>
			</table>
		</div>
	`;
	};

	/**
	 * Builds HTML for plugin details modal content.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} data Plugin details data from the API.
	 * @return {string} HTML content for the modal.
	 */
	const buildPluginDetailsHtml = data => `
		<div class="troy-server-stats-cards">
			${ buildCard( i18n.totalDownloads, data.total_downloads ) }
			${ buildCard( i18n.installations, data.total_installs ) }
			${ buildCard( i18n.activeInstalls, data.active_installs ) }
			${ buildCard( i18n.inactiveInstalls, data.inactive_installs ) }
			${ buildCard( i18n.lastSnapshot, data.last_snapshot || '-', 'string' ) }
		</div>
		${ buildEpochComparisonTable( data ) }
		${ buildVersionDetailsTable( data ) }
		${ buildDetailSection( i18n.downloadsByType, data.download_types, 'type', 'downloads' ) }
		${ buildDetailSection( i18n.locales, data.locales, 'locale', 'count' ) }
		${ buildDetailSection( i18n.phpVersions, data.php_versions, 'version', 'count' ) }
		${ buildDetailSection( i18n.wpVersions, data.wp_versions, 'version', 'count' ) }
	`;

	/**
	 * Builds HTML for package details modal content.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} data Package details data from the API.
	 * @return {string} HTML content for the modal.
	 */
	const buildPackageDetailsHtml = data => `
		<div class="troy-server-stats-cards">
			${ buildCard( i18n.totalDownloads, data.total_downloads ) }
			${ buildCard( i18n.lastSnapshot, data.last_snapshot || '-', 'string' ) }
		</div>
		${ buildDetailSection( i18n.downloadsByVersion, data.versions, 'version', 'downloads' ) }
	`;

	/**
	 * Binds click handlers to plugin detail buttons.
	 *
	 * @since 0.0.1184
	 */
	const bindPluginDetailButtons = () => {

		document.querySelectorAll( '.troy-server-stats-details-btn' ).forEach( btn => {
			btn.addEventListener(
				'click',
				() => showPluginDetails( btn.dataset.pluginId ),
			);
		} );
	};

	/**
	 * Binds click handlers to package detail buttons.
	 *
	 * @since 0.0.1184
	 */
	const bindPackageDetailButtons = () => {

		document.querySelectorAll( '.troy-server-stats-package-details-btn' ).forEach( btn => {
			btn.addEventListener(
				'click',
				() => showPackageDetails( btn.dataset.packageId ),
			);
		} );
	};

	/**
	 * Refreshes all stats data from the API.
	 *
	 * @since 0.0.1184
	 */
	const refreshAllStats = async () => {

		const params = getDateParams();

		try {
			const [ overview, topPlugins ] = await Promise.all( [
				fetchStats( 'overview', params ),
				fetchStats( 'top-plugins', params ),
			] );

			updateOverviewCards( overview );
			updatePluginsTable( topPlugins );
		} catch ( error ) {
			console.error( 'Failed to refresh stats:', error );
		}
	};

	/**
	 * Initializes the stats dashboard.
	 *
	 * @since 0.0.1184
	 */
	const init = () => {

		const modal      = getEl( 'modal' );
		const modalClose = modal?.querySelector( '.troy-server-stats-modal-close' );

		getEl( 'range' )?.addEventListener( 'change', refreshAllStats );
		modalClose?.addEventListener( 'click', hideModal );

		modal?.addEventListener(
			'click',
			e => {
				if ( e.target === modal )
					hideModal();
			},
		);

		document.addEventListener(
			'keydown',
			e => {
				if ( 'Escape' === e.key && ! modal?.hidden )
					hideModal();
			},
		);

		bindPluginDetailButtons();
		bindPackageDetailButtons();
	};

	if ( 'complete' === document.readyState ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
