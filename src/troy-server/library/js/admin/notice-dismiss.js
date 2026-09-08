/**
 * Troy Server
 *
 * Copyright (c) 2026 Sybre Waaijer, CyberWire B.V.
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
 * @module troyServerNotices
 * @description Dismisses persistent admin notices: animates them out and DELETEs via REST.
 * @since 1.8.1184
 */
window.troyServerNotices = ( () => {

	const apiFetch = wp.apiFetch;
	const restBase = ( window.troyServerNotices || {} ).restBase || '';

	/**
	 * Dismisses a persistent notice: animates it out and DELETEs via REST.
	 *
	 * @since 1.8.1184
	 *
	 * @param {string} key The notice key.
	 */
	function dismiss( key ) {

		if ( ! key ) return;

		const notice = document.querySelector( `.troy-server-notice-dismiss[data-key="${key}"]` )
			?.closest( '.troy-server-notice' );

		if ( notice ) {
			notice.style.transformOrigin = 'bottom';

			const animation = notice.animate(
				[
					{
						transform: 'scaleY(1)',
						maxHeight: `${ notice.clientHeight }px`,
						opacity:   1,
					},
					{
						transform: 'scaleY(1)',
						opacity:   0,
					},
					{
						transform:     'scaleY(0)',
						maxHeight:     0,
						paddingTop:    0,
						paddingBottom: 0,
						marginTop:     0,
						marginBottom:  0,
						opacity:       0,
					},
				],
				{
					duration:   200,
					iterations: 1,
				},
			);

			animation.onfinish = () => notice.remove();
		}

		if ( ! restBase ) return;

		return apiFetch( {
			url:    `${ restBase }/${ key }`,
			method: 'DELETE',
		} )
			.catch( () => {} );
	}

	/**
	 * Handles clicks on persistent notice dismiss buttons.
	 *
	 * @since 1.8.1184
	 * @access private
	 *
	 * @param {Event} event
	 */
	function _onDismissClick( event ) {

		const button = event.target.closest( '.troy-server-notice-dismiss' );

		if ( ! button ) return;

		dismiss( button.dataset.key );
	}

	document.addEventListener( 'click', _onDismissClick );

	return { dismiss };
} )();
