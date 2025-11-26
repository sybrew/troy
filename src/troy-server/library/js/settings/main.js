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

	let passiveSupported = false;
	let captureSupported = false;

	/**
	 * Detects passive and capture event listener support.
	 *
	 * @since 0.0.1184
	 * @link https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
	 */
	const detectEventListenerSupport = () => {

		try {
			const options = {
				get passive() {
					passiveSupported = true;
					return false;
				},
				get capture() {
					captureSupported = true;
					return false;
				},
			};

			window.addEventListener( 'troy-server-test-passive', null, options );
			window.removeEventListener( 'troy-server-test-passive', null, options );
		} catch ( e ) {
			passiveSupported = false;
			captureSupported = false;
		}
	};

	/**
	 * Returns event listener options based on browser support.
	 *
	 * @since 0.0.1184
	 *
	 * @return {Object|Boolean} Event listener options object or Boolean fallback.
	 */
	const getListenerOptions = () => passiveSupported && captureSupported
		? { capture: true, passive: true }
		: true;

	let instigatingTooltip = false;

	/**
	 * Handles tooltip creation on hover/focus events.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Event} event The triggering event.
	 */
	const handleTooltip = event => {

		if ( instigatingTooltip || event.target.dataset?.hasTooltip )
			return;

		instigatingTooltip = true;

		createTooltip( event );
		event.stopPropagation();

		instigatingTooltip = false;
	};

	/**
	 * Creates and positions a tooltip element.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Event} event The triggering event.
	 */
	const createTooltip = event => {

		const ttLeaveActions = [ 'mouseleave', 'mouseout', 'blur' ];

		ttLeaveActions.forEach( e => {
			event.target.addEventListener( e, handleTooltipClear );
		} );

		event.target.innerHTML +=
			`<div class=troy-server-tooltip><span class=troy-server-tooltip-text-wrap><span class=troy-server-tooltip-text>${ event.target.dataset.troyServerTooltip }</span><div class=troy-server-tooltip-arrow></div></div>`;

		event.target.dataset.hasTooltip = true;

		const tooltip = event.target.querySelector( '.troy-server-tooltip' );
		const rect    = tooltip.querySelector( '.troy-server-tooltip-text-wrap' ).getBoundingClientRect();

		tooltip.style.top = `${ -rect.height - 9 }px`;
		tooltip.style.left = `${
			-rect.width / 2
			+ parseInt( getComputedStyle( tooltip ).fontSize ) * .5
		}px`;
		tooltip.querySelector( '.troy-server-tooltip-arrow' ).style.left = `${ rect.width / 2 - 4.5 }px`;
	};

	/**
	 * Handles tooltip cleanup on leave/blur events.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Event} event The triggering event.
	 */
	const handleTooltipClear = event => {

		removeTooltip( event.target );

		const ttActions = [ 'mouseenter', 'pointerdown', 'touchstart', 'focus' ];

		ttActions.forEach( e => {
			event.target.removeEventListener( e, handleTooltipClear );
		} );
	};

	/**
	 * Removes a tooltip element from the DOM.
	 *
	 * @since 0.0.1184
	 *
	 * @param {HTMLElement} element The element containing the tooltip.
	 */
	const removeTooltip = element => {

		if ( element instanceof HTMLElement ) {
			delete element.dataset.hasTooltip;
			clickLocker( element ).release();
		}

		const tooltip = element.classList.contains( 'troy-server-tooltip' )
			? element
			: element?.querySelector( '.troy-server-tooltip' );

		tooltip?.parentNode.removeChild( tooltip );
	};

	/**
	 * Prevents default click behavior on tooltip elements.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Event} event The click event.
	 */
	const preventTooltipHandleClick = event => {

		if ( clickLocker( event.target ).isLocked() )
			return;

		event.preventDefault();

		// iOS 12 bug causes two clicks at once. Let's set this asynchronously.
		setTimeout( () => clickLocker( event.target ).lock() );
	};

	/**
	 * Creates a click locker interface for an element.
	 *
	 * Manages click prevention state for elements and their associated
	 * labels/inputs to handle iOS double-click bugs.
	 *
	 * @since 0.0.1184
	 *
	 * @param {Element} element The element to manage click state for.
	 * @return {Object} Object with lock, release, and isLocked methods.
	 */
	const clickLocker = element => ( {
		/**
		 * Locks the element to prevent clicks.
		 *
		 * @since 0.0.1184
		 */
		lock: () => {

			element.dataset.preventedClick = 1;

			if ( element instanceof HTMLLabelElement && element.htmlFor ) {
				const input = document.getElementById( element.htmlFor );

				if ( input )
					input.dataset.preventedClick = 1;
			}

			if ( element instanceof HTMLInputElement && element.id ) {
				document.querySelectorAll( `label[for="${ element.id }"]` ).forEach( label => {
					label.dataset.preventedClick = 1;
				} );
			}
		},
		/**
		 * Releases the click lock on the element.
		 *
		 * @since 0.0.1184
		 */
		release: () => {

			if ( ! ( element instanceof Element ) )
				return;

			delete element.dataset.preventedClick;

			if ( element instanceof HTMLLabelElement && element.htmlFor ) {
				const input = document.getElementById( element.htmlFor );

				if ( input )
					delete input.dataset.preventedClick;
			}

			if ( element instanceof HTMLInputElement && element.id ) {
				document.querySelectorAll( `label[for="${ element.id }"]` ).forEach( label => {
					delete label.dataset.preventedClick;
				} );
			}
		},
		/**
		 * Checks if the element is currently locked.
		 *
		 * @since 0.0.1184
		 *
		 * @return {Boolean} True if the element is locked.
		 */
		isLocked: () => element instanceof Element && !! +element.dataset.preventedClick,
	} );

	/**
	 * Initializes tooltip functionality for all tooltip-enabled elements.
	 *
	 * @since 0.0.1184
	 */
	const initTooltips = () => {

		const options   = getListenerOptions();
		const ttWraps   = document.querySelectorAll( '[data-troy-server-tooltip]' );
		const ttActions = [ 'mouseenter', 'pointerdown', 'touchstart', 'focus' ];

		ttWraps.forEach( wrap => {

			if ( ! wrap.dataset?.troyServerTooltip )
				return;

			wrap.tabIndex = 0;

			ttActions.forEach( e => {
				wrap.addEventListener( e, handleTooltip, options );
			} );

			wrap.addEventListener(
				'click',
				preventTooltipHandleClick,
				captureSupported ? { capture: false } : false,
			);
		} );
	};

	/**
	 * Initializes accordion functionality for settings accordions.
	 *
	 * @since 0.0.1184
	 */
	const initAccordions = () => {

		document.querySelectorAll( '.troy-server-settings-accordion' ).forEach( accordion => {
			accordion.addEventListener( 'click', event => {

				if ( ! event.target.classList.contains( 'troy-server-settings-accordion-trigger' ) )
					return;

				const controlsId = event.target.getAttribute( 'aria-controls' );
				const panel      = document.getElementById( controlsId );

				if ( 'true' === event.target.getAttribute( 'aria-expanded' ) ) {
					event.target.setAttribute( 'aria-expanded', 'false' );
					panel.setAttribute( 'hidden', true );
				} else {
					event.target.setAttribute( 'aria-expanded', 'true' );
					panel.removeAttribute( 'hidden' );
				}
			} );
		} );
	};

	/**
	 * Initializes the settings page functionality.
	 *
	 * @since 0.0.1184
	 */
	const init = () => {

		detectEventListenerSupport();
		initTooltips();
		initAccordions();
	};

	if ( 'complete' === document.readyState ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
