/**
 * Acronym UI — accessible tooltip for .aui-acronym elements.
 *
 * The server renders a native `title` (fallback for no-JS). Once this script
 * runs, the text moves to data-aui-title so the browser's own title popup
 * doesn't show on top of the bubble, and the bubble is linked to the term via
 * aria-describedby for screen readers. The bubble is shown on
 * hover/focus for pointer devices and toggled by click/tap everywhere —
 * which is what makes it usable on mobile, where hover doesn't exist.
 * No jQuery dependency.
 */
( function () {
	'use strict';

	function initAcronymTooltips() {
		var triggers = document.querySelectorAll( '.aui-acronym[title]' );
		if ( ! triggers.length ) {
			return;
		}

		var bubble = document.createElement( 'div' );
		bubble.className = 'aui-tooltip';
		bubble.id = 'aui-tooltip';
		bubble.setAttribute( 'role', 'tooltip' );
		document.body.appendChild( bubble );

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			trigger.setAttribute( 'data-aui-title', trigger.getAttribute( 'title' ) || '' );
			trigger.removeAttribute( 'title' );
		} );

		var current = null;

		function position( trigger ) {
			var rect = trigger.getBoundingClientRect();
			var gutter = 8;
			var maxLeft = document.documentElement.clientWidth - bubble.offsetWidth - gutter;
			var left = Math.max( gutter, Math.min( rect.left, maxLeft ) );
			bubble.style.top = rect.bottom + window.scrollY + 6 + 'px';
			bubble.style.left = left + window.scrollX + 'px';
		}

		function show( trigger ) {
			if ( current && current !== trigger ) {
				current.removeAttribute( 'aria-describedby' );
			}
			bubble.textContent = trigger.getAttribute( 'data-aui-title' ) || '';
			position( trigger );
			bubble.classList.add( 'is-visible' );
			trigger.setAttribute( 'aria-describedby', bubble.id );
			current = trigger;
		}

		function hide() {
			bubble.classList.remove( 'is-visible' );
			if ( current ) {
				current.removeAttribute( 'aria-describedby' );
			}
			current = null;
		}

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			trigger.addEventListener( 'mouseenter', function () {
				show( trigger );
			} );
			trigger.addEventListener( 'mouseleave', hide );
			trigger.addEventListener( 'focus', function () {
				show( trigger );
			} );
			trigger.addEventListener( 'blur', hide );
			trigger.addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				if ( current === trigger ) {
					hide();
				} else {
					show( trigger );
				}
			} );
		} );

		document.addEventListener( 'click', function () {
			if ( current ) {
				hide();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && current ) {
				hide();
			}
		} );

		window.addEventListener(
			'scroll',
			function () {
				if ( current ) {
					position( current );
				}
			},
			{ passive: true }
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAcronymTooltips );
	} else {
		initAcronymTooltips();
	}
} )();
