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

document.addEventListener( 'DOMContentLoaded', () => {

	let passiveSupported = false,
		captureSupported = false;
	/**
	 * Sets passive & capture support flag.
	 * @link https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
	 */
	try {
		( () => {
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
			// These EventTarget methods will try to get 'passive' and/or 'capture' when it's supported.
			window.addEventListener( 'troy-server-test-passive', null, options );
			window.removeEventListener( 'troy-server-test-passive', null, options );
		} )();
	} catch ( e ) {
		passiveSupported = false;
		captureSupported = false;
	}
	const options = passiveSupported && captureSupported ? { capture: true, passive: true } : true;

	const ttWraps        = document.querySelectorAll( '[data-troy-server-tooltip]' ),
		  ttActions      = 'mouseenter pointerdown touchstart focus'.split( ' ' ),
		  ttLeaveActions = 'mouseleave mouseout blur'.split( ' ' );

	ttWraps.forEach( wrap => {
		if ( ! wrap.dataset?.troyServerTooltip ) return;

		wrap.tabIndex = 0;

		ttActions.forEach( e => {
			wrap.addEventListener( e, handleTooltip, options );
		} );

		wrap.addEventListener(
			'click',
			preventTooltipHandleClick,
			captureSupported ? { capture: false } : false
		);
	} );

	let instigatingTooltip = false;
	function handleTooltip( event ) {
		if (
			   instigatingTooltip
			|| event.target.dataset?.hasTooltip
		) return;

		instigatingTooltip = true;

		createTooltip( event );
		event.stopPropagation();

		instigatingTooltip = false;
	}
	async function createTooltip( event ) {
		ttLeaveActions.forEach( e => {
			event.target.addEventListener( e, handleTooltipClear );
		} );

		event.target.innerHTML +=
			`<div class=troy-server-tooltip><span class=troy-server-tooltip-text-wrap><span class=troy-server-tooltip-text>${event.target.dataset.troyServerTooltip}</span><div class=troy-server-tooltip-arrow></div></div>`;

		event.target.dataset.hasTooltip = true;

		const tooltip = event.target.querySelector( '.troy-server-tooltip' );
		const rect    = tooltip.querySelector( '.troy-server-tooltip-text-wrap' ).getBoundingClientRect();

		tooltip.style.top = `${
			-rect.height
			-9
		}px`;
		tooltip.style.left = `${
			-rect.width / 2
			+ parseInt( getComputedStyle( tooltip ).fontSize ) * .5
		}px`;
		tooltip.querySelector( '.troy-server-tooltip-arrow' ).style.left = `${
			rect.width / 2 - 4.5 // arrow is 9px wide, 4.5 is middle.
		}px`;
	}
	function handleTooltipClear( event ) {

		removeTooltip( event.target );

		ttActions.forEach( e => {
			event.target.removeEventListener( e, handleTooltipClear );
		} );
	}
	function removeTooltip( element ) {

		if ( element instanceof HTMLElement ) {
			delete element.dataset.hasTooltip;
			_clickLocker( element ).release();
		}

		const tooltip = element.classList.contains( 'troy-server-tooltip' )
			? element
			: element?.querySelector( '.troy-server-tooltip' )

		tooltip?.parentNode.removeChild( tooltip );
	}

	function preventTooltipHandleClick ( event ) {
		if ( _clickLocker( event.target ).isLocked() ) return;
		event.preventDefault();
		// iOS 12 bug causes two clicks at once. Let's set this asynchronously.
		setTimeout( () => _clickLocker( event.target ).lock() );
	}
	const _clickLocker = element => {
		return {
			lock: () => {
				element.dataset.preventedClick = 1;

				// If the element is a label with a "for"-attribute, then we must forward this
				if ( element instanceof HTMLLabelElement && element.htmlFor ) {
					let input = document.getElementById( element.htmlFor );
					if ( input ) input.dataset.preventedClick = 1;
				}
				if ( element instanceof HTMLInputElement && element.id ) {
					document.querySelectorAll( `label[for="${element.id}"]` ).forEach(
						label => { label.dataset.preventedClick = 1; }
					);
				}
			},
			release: () => {
				if ( ! ( element instanceof Element ) ) return;

				delete element.dataset.preventedClick;

				if ( element instanceof HTMLLabelElement && element.htmlFor ) {
					let input = document.getElementById( element.htmlFor );
					if ( input ) delete input.dataset.preventedClick;
				}
				if ( element instanceof HTMLInputElement && element.id ) {
					document.querySelectorAll( `label[for="${element.id}"]` ).forEach(
						la => { delete la.dataset.preventedClick; }
					);
				}
			},
			isLocked: () => element instanceof Element && !!+element.dataset.preventedClick,
		}
	}

	document.querySelectorAll( '.troy-server-settings-accordion' ).forEach( accordion => {
		accordion.addEventListener( 'click', event => {
			if ( ! event.target.classList.contains( 'troy-server-settings-accordion-trigger' ) ) return;

			if ( 'true' === event.target.getAttribute( 'aria-expanded' ) ) {
				event.target.setAttribute( 'aria-expanded', 'false' );
				document.querySelector( `#${event.target.getAttribute('aria-controls')}` ).setAttribute( 'hidden', true );
			} else {
				event.target.setAttribute( 'aria-expanded', 'true' );
				document.querySelector( `#${event.target.getAttribute('aria-controls')}` ).removeAttribute( 'hidden' );
			}
		} );
	} );
} );
