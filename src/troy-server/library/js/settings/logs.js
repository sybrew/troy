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

	const config   = window.troyServerLogs || {};
	const restBase = config.restBase || '';
	const nonce    = config.nonce || '';
	const i18n     = config.i18n || {};

	const elements = new Map();

	let autoRefreshInterval = null;

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
				'refresh',
				'auto-refresh-toggle',
				'failures-count',
				'failures-table',
				'entries-count',
				'entries-table',
			].forEach( key => elements.set( key, document.getElementById( `troy-server-logs-${ key }` ) ) );
		}

		return elements.get( id );
	};

	/**
	 * Makes a REST API request.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} endpoint The endpoint path.
	 * @param {Object} options  Fetch options.
	 * @return {Promise<Object>} The fetch promise resolving to JSON data.
	 */
	const fetchLogs = async ( endpoint, options = {} ) => {

		const url = new URL( `${ restBase }/${ endpoint }` );

		const response = await fetch(
			url,
			{
				method: 'GET',
				...options,
				headers: {
					'X-WP-Nonce':   nonce,
					'Content-Type': 'application/json',
					...( options.headers || {} ),
				},
			},
		);

		if ( ! response.ok )
			throw new Error( `HTTP ${ response.status }` );

		return response.json();
	};

	/**
	 * Builds a table row for a failure entry.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} failure The failure data.
	 * @return {string} HTML string for the table row.
	 */
	const buildFailureRow = failure => {

		const detailsHtml = failure.details
			? `<details class="troy-server-logs-details"><summary>${ escape.string( i18n.details || 'Details' ) }</summary><pre>${ escape.string( failure.details ) }</pre></details>`
			: '';

		return `
			<tr data-failure-id="${ sanitize.number( failure.id ) }">
				<td><strong>${ escape.string( failure.plugin_slug ) }</strong><br><code>${ sanitize.number( failure.plugin_id ) }</code></td>
				<td><code>${ escape.string( failure.package_version ) }</code></td>
				<td>${ escape.string( failure.mode ) }</td>
				<td>
					<span class="troy-server-logs-reason">${ escape.string( failure.reason ) }</span>
					${ detailsHtml }
				</td>
				<td>${ sanitize.number( failure.attempts ) }</td>
				<td class="troy-server-logs-timestamp">${ escape.string( failure.updated_at ) }</td>
			</tr>
		`;
	};

	/**
	 * Builds a table row for a log entry.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Object} log The log data.
	 * @return {string} HTML string for the table row.
	 */
	const buildLogRow = log => `
		<tr data-log-id="${ sanitize.number( log.id ) }" class="troy-server-logs-type-${ escape.string( log.type ) }">
			<td><strong>${ escape.string( log.plugin_slug ) }</strong><br><code>${ sanitize.number( log.plugin_id ) }</code></td>
			<td>
				<span class="troy-server-logs-type troy-server-logs-type-${ escape.string( log.type ) }">
					${ escape.string( log.type ) }
				</span>
			</td>
			<td class="troy-server-logs-message">${ escape.string( log.message ) }</td>
			<td class="troy-server-logs-timestamp">${ escape.string( log.created_at ) }</td>
		</tr>
	`;

	/**
	 * Updates the failures table with new data.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Array} failures Array of failure data from the API.
	 */
	const updateFailuresTable = failures => {

		const table = getEl( 'failures-table' );
		const count = getEl( 'failures-count' );

		if ( ! table )
			return;

		const tbody = table.querySelector( 'tbody' );

		if ( count )
			count.textContent = `(${ failures.length })`;

		if ( ! failures.length ) {
			tbody.innerHTML = `<tr><td colspan="6">${ i18n.noData }</td></tr>`;
			return;
		}

		tbody.innerHTML = failures
			.map( buildFailureRow )
			.join( '' );
	};

	/**
	 * Updates the logs table with new data.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Array} logs Array of log data from the API.
	 */
	const updateLogsTable = logs => {

		const table = getEl( 'entries-table' );
		const count = getEl( 'entries-count' );

		if ( ! table )
			return;

		const tbody = table.querySelector( 'tbody' );

		if ( count )
			count.textContent = `(${ logs.length })`;

		if ( ! logs.length ) {
			tbody.innerHTML = `<tr><td colspan="4">${ i18n.noData }</td></tr>`;
			return;
		}

		tbody.innerHTML = logs
			.map( buildLogRow )
			.join( '' );
	};

	/**
	 * Refreshes all logs data from the API.
	 *
	 * @since 0.0.1184
	 */
	const refreshAllLogs = async () => {

		const refreshBtn = getEl( 'refresh' );

		if ( refreshBtn )
			refreshBtn.disabled = true;

		try {
			const [ failures, logs ] = await Promise.all( [
				fetchLogs( 'failures' ),
				fetchLogs( 'logs' ),
			] );

			updateFailuresTable( failures );
			updateLogsTable( logs );
		} catch ( error ) {
			console.error( 'Failed to refresh logs:', error );
		} finally {
			if ( refreshBtn )
				refreshBtn.disabled = false;
		}
	};

	/**
	 * Clears logs of the specified type.
	 *
	 * @since 0.0.1184
	 *
	 * @param {string} logType The log type to clear ('failures' or 'logs').
	 */
	const clearLogs = async logType => {

		if ( ! confirm( i18n.confirmClear ) )
			return;

		try {
			await fetchLogs(
				`clear-${ logType }`,
				{ method: 'POST' },
			);

			if ( 'failures' === logType )
				updateFailuresTable( [] );
			else
				updateLogsTable( [] );
		} catch ( error ) {
			console.error( 'Failed to clear logs:', error );
			alert( i18n.clearFailed );
		}
	};

	/**
	 * Toggles auto-refresh functionality.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Boolean} enabled Whether auto-refresh should be enabled.
	 */
	const toggleAutoRefresh = enabled => {

		if ( autoRefreshInterval ) {
			clearInterval( autoRefreshInterval );
			autoRefreshInterval = null;
		}

		if ( enabled )
			autoRefreshInterval = setInterval( refreshAllLogs, 20000 );
	};

	/**
	 * Binds click handlers to clear buttons.
	 *
	 * @since 0.0.1184
	 */
	const bindClearButtons = () => {

		document.querySelectorAll( '.troy-server-logs-clear-btn' ).forEach( btn => {
			btn.addEventListener(
				'click',
				() => clearLogs( btn.dataset.logType ),
			);
		} );
	};

	/**
	 * Initializes the logs dashboard.
	 *
	 * @since 0.0.1184
	 */
	const init = () => {

		getEl( 'refresh' )?.addEventListener( 'click', refreshAllLogs );

		getEl( 'auto-refresh-toggle' )?.addEventListener(
			'change',
			e => toggleAutoRefresh( e.target.checked ),
		);

		bindClearButtons();
	};

	if ( 'complete' === document.readyState )
		init();
	else
		document.addEventListener( 'DOMContentLoaded', init );
} )();
