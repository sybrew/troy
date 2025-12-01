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
			current_epoch:     data.current_epoch || '-',
			total_plugins:     sanitize.number( data.total_plugins ),
			last_snapshot:     data.last_snapshot || '-',
		};

		Object.entries( statMap ).forEach( ( [ key, value ] ) => {
			const el = overviewCards.querySelector( `[data-stat="${ key }"]` );

			if ( el )
				el.textContent = value;
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
					<tr data-plugin-id="${ plugin.plugin_id }">
						<td>
							<strong>${ escape.string( plugin.name ) }</strong> <code>(${ escape.string( plugin.slug ) })</code>
						</td>
						<td>${ sanitize.number( plugin.downloads ) }</td>
						<td>${ sanitize.number( plugin.total_installs ) }</td>
						<td>${ sanitize.number( plugin.active_installs ) }</td>
						<td>${ sanitize.number( plugin.inactive_installs ) }</td>
						<td>${ sanitize.number( plugin.views ) }</td>
						<td>
							<button type="button" class="button button-small troy-server-stats-details-btn" data-plugin-id="${ plugin.plugin_id }">
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

			getEl( 'modal-title' ).textContent = data.name;
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
				<h4>${ title }</h4>
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
			<span class="troy-server-stats-card-label">${ label }</span>
			<span class="troy-server-stats-card-value">${
				'string' === formatAs ? escape.string( value ) : sanitize.number( value )
			}</span>
		</div>
	`;

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
			${ buildCard( i18n.lastSnapshot, data.last_snapshot || '-', 'string' ) }
			${ buildCard( i18n.installations, data.total_installs ) }
			${ buildCard( i18n.activeInstalls, data.active_installs ) }
			${ buildCard( i18n.inactiveInstalls, data.inactive_installs ) }
		</div>
		${ buildDetailSection( i18n.downloadsByVersion, data.versions, 'version', 'downloads' ) }
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
			${ buildCard( i18n.currentVersion, data.current_version, 'string' ) }
			${ buildCard( i18n.totalDownloads, data.total_downloads ) }
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
