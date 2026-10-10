/* global mendaFront */
( function () {
	'use strict';

	var cfg = window.mendaFront || {};

	function track( adId, event ) {
		if ( ! navigator.sendBeacon || ! cfg.track || ! adId ) {
			return;
		}
		var fd = new FormData();
		fd.append( 'ad_id', adId );
		fd.append( 'event', event );
		fd.append( 'nonce', cfg.nonce || '' );
		navigator.sendBeacon( cfg.track, fd );
	}

	function bindUnit( el ) {
		if ( el.id === 'menda-skin' || el.classList.contains( 'menda-skin' ) ) {
			return;
		}
		if ( el.getAttribute( 'data-menda-bound' ) ) {
			return;
		}
		el.setAttribute( 'data-menda-bound', '1' );
		var adId = parseInt( el.getAttribute( 'data-menda-unit' ) || '0', 10 );
		if ( ! adId ) {
			return;
		}
		var cap = parseInt( el.getAttribute( 'data-impression-cap' ) || '0', 10 );
		var parent = parseInt( el.getAttribute( 'data-parent' ) || String( adId ), 10 );
		var storageKey = 'menda_unit_cap_' + adId;
		document.cookie = encodeURIComponent( 'menda_ab_' + parent ) + '=' + adId + '; path=/; max-age=86400; SameSite=Lax';

		if ( cap > 0 ) {
			var views = parseInt( sessionStorage.getItem( storageKey ) || '0', 10 );
			if ( views >= cap ) {
				el.style.display = 'none';
				return;
			}
		}

		var tracked = false;
		function onView() {
			if ( tracked ) {
				return;
			}
			tracked = true;
			track( adId, 'view' );
			if ( cap > 0 ) {
				sessionStorage.setItem( storageKey, String( parseInt( sessionStorage.getItem( storageKey ) || '0', 10 ) + 1 ) );
			}
		}

		if ( 'IntersectionObserver' in window ) {
			var obs = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.intersectionRatio >= 0.5 ) {
						onView();
						obs.disconnect();
					}
				} );
			}, { threshold: 0.5 } );
			obs.observe( el );
		} else {
			onView();
		}

		el.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a, .menda-unit__link, .menda-gate__link, .menda-dock__link' ) ) {
				track( adId, 'click' );
			}
		} );

		if ( cfg.adblock && ! el.closest( '#menda-dock, #menda-skin' ) ) {
			setTimeout( function () {
				var style = window.getComputedStyle( el );
				if ( el.offsetParent === null || style.display === 'none' || style.visibility === 'hidden' ) {
					track( adId, 'adblock' );
					el.style.setProperty( 'display', 'block', 'important' );
					el.style.setProperty( 'visibility', 'visible', 'important' );
					el.textContent = '';
					var box = document.createElement( 'div' );
					box.className = 'menda-unit-fallback';
					box.textContent = cfg.fallback || '';
					el.appendChild( box );
				}
			}, 600 );
		}
	}

	function initDock() {
		var dock = document.getElementById( 'menda-dock' );
		if ( ! dock ) {
			return;
		}
		if ( sessionStorage.getItem( 'menda_dock_closed' ) ) {
			dock.remove();
			return;
		}
		document.documentElement.classList.add( 'menda-has-dock' );
		var close = dock.querySelector( '.menda-dock__close' );
		if ( close ) {
			close.addEventListener( 'click', function () {
				sessionStorage.setItem( 'menda_dock_closed', '1' );
				dock.remove();
				document.documentElement.classList.remove( 'menda-has-dock' );
			} );
		}
		dock.querySelectorAll( '[data-menda-unit]' ).forEach( bindUnit );
	}

	function initSkin() {
		var root = document.getElementById( 'menda-skin' );
		if ( ! root ) {
			return;
		}
		var html = document.documentElement;
		if ( html.getAttribute( 'data-menda-skin-skip' ) === '1' ) {
			return;
		}
		var adId = parseInt( root.getAttribute( 'data-menda-unit' ) || '0', 10 );
		var minVp = parseInt( root.getAttribute( 'data-min-vp' ) || '0', 10 );
		var preview = root.getAttribute( 'data-preview' ) === '1';

		var showDesktop = root.getAttribute( 'data-show-desktop' ) !== '0';
		var showMobile = root.getAttribute( 'data-show-mobile' ) !== '0';
		var layout = root.getAttribute( 'data-layout' ) || 'overlay';
		var headerFull = root.getAttribute( 'data-header-full' ) === '1' && layout === 'boxed';
		var chromeHeaderSel = '#masthead, #kadence-header, #site-header, #header, .kadence-header-wrap, .site-header-wrap, .elementor-location-header, header.wp-block-template-part, .site > header, #page > header, #wrapper > header, body > header, .site-header';
		var chromeFooterSel = '#colophon, .site-footer, .elementor-location-footer, footer.wp-block-template-part, .site > footer, #page > footer, #wrapper > footer, body > footer';

		function pickEl( sel ) {
			if ( ! sel ) {
				return null;
			}
			try {
				return document.querySelector( sel );
			} catch ( err ) {
				return null;
			}
		}

		function updateChrome() {
			document.querySelectorAll( '.menda-skin-column' ).forEach( function ( el ) {
				el.classList.remove( 'menda-skin-column' );
			} );
			if ( html.getAttribute( 'data-menda-header-full' ) !== '1' ) {
				document.querySelectorAll( '.menda-skin-chrome' ).forEach( function ( el ) {
					el.classList.remove( 'menda-skin-chrome', 'menda-skin-chrome--header', 'menda-skin-chrome--footer' );
				} );
				html.style.setProperty( '--menda-header-h', '0px' );
				return;
			}
			var extra = root.getAttribute( 'data-header-sel' ) || '';
			var header = pickEl( extra ) || pickEl( chromeHeaderSel );
			var footer = pickEl( chromeFooterSel );
			if ( header ) {
				header.classList.add( 'menda-skin-chrome', 'menda-skin-chrome--header' );
				html.style.setProperty( '--menda-header-h', Math.max( 0, Math.round( header.getBoundingClientRect().bottom ) ) + 'px' );
			} else {
				html.style.setProperty( '--menda-header-h', '0px' );
			}
			if ( footer ) {
				footer.classList.add( 'menda-skin-chrome', 'menda-skin-chrome--footer' );
			}
			var column = pickEl( '#inner-wrap' ) || pickEl( '#content' ) || pickEl( '.site-content' ) || pickEl( 'main' );
			if ( column && header && column.contains( header ) ) {
				column = pickEl( '#content' ) || pickEl( '.site-content' ) || pickEl( 'main' );
			}
			if ( column && footer && column.contains( footer ) ) {
				column = pickEl( '.site-content' ) || pickEl( 'main' );
			}
			if ( column && ! ( header && column.contains( header ) ) ) {
				column.classList.add( 'menda-skin-column' );
			}
		}

		function applyViewport() {
			if ( html.getAttribute( 'data-menda-skin-skip' ) === '1' ) {
				return;
			}
			var wide = window.innerWidth >= minVp;
			var hasTop = !! root.querySelector( '.menda-skin-top' );
			var hasMob = !! document.getElementById( 'menda-skin-mobile' );
			html.removeAttribute( 'data-menda-skin' );
			html.removeAttribute( 'data-menda-skin-layout' );
			html.removeAttribute( 'data-menda-skin-top' );
			html.removeAttribute( 'data-menda-skin-mobile' );
			html.removeAttribute( 'data-menda-header-full' );
			if ( wide && showDesktop ) {
				html.setAttribute( 'data-menda-skin', '1' );
				html.setAttribute( 'data-menda-skin-layout', layout );
				if ( hasTop && layout === 'boxed' ) {
					html.setAttribute( 'data-menda-skin-top', '1' );
				}
				if ( headerFull ) {
					html.setAttribute( 'data-menda-header-full', '1' );
				}
			} else if ( ! wide && showMobile && hasMob ) {
				html.setAttribute( 'data-menda-skin-mobile', '1' );
			}
			updateChrome();
		}

		applyViewport();
		window.addEventListener( 'resize', applyViewport );
		window.addEventListener( 'scroll', updateChrome, { passive: true } );

		if ( ! preview && ( html.getAttribute( 'data-menda-skin' ) === '1' || html.getAttribute( 'data-menda-skin-mobile' ) === '1' ) ) {
			track( adId, 'view' );
		}

		root.querySelectorAll( '[data-menda-click]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				if ( preview ) {
					return;
				}
				track( adId, el.getAttribute( 'data-menda-click' ) || 'click' );
			} );
		} );

		var dock = document.getElementById( 'menda-skin-mobile' );
		if ( dock ) {
			var closeKey = 'menda_skin_mobile_closed_' + adId;
			if ( ! preview && sessionStorage.getItem( closeKey ) ) {
				dock.remove();
				html.removeAttribute( 'data-menda-skin-mobile' );
			} else {
				var close = dock.querySelector( '.menda-skin-mobile__close' );
				if ( close ) {
					close.addEventListener( 'click', function () {
						sessionStorage.setItem( closeKey, '1' );
						dock.remove();
						html.removeAttribute( 'data-menda-skin-mobile' );
					} );
				}
			}
		}

		var railsClose = root.querySelector( '.menda-skin-rails__close' );
		if ( railsClose ) {
			var railsKey = 'menda_skin_rails_closed_' + adId;
			var railsEl = root.querySelector( '.menda-skin-rails' );
			if ( ! preview && sessionStorage.getItem( railsKey ) && railsEl ) {
				railsEl.remove();
			} else {
				railsClose.addEventListener( 'click', function () {
					try {
						sessionStorage.setItem( railsKey, '1' );
					} catch ( e3 ) {}
					if ( railsEl ) {
						railsEl.remove();
					}
				} );
			}
		}
	}

	function initGate() {
		var el = document.getElementById( 'menda-gate' );
		if ( ! el ) {
			return;
		}
		var showMobile = el.getAttribute( 'data-show-mobile' ) !== '0';
		var showDesktop = el.getAttribute( 'data-show-desktop' ) !== '0';
		var isMobile = window.matchMedia( '(max-width: 781px)' ).matches;
		if ( ( isMobile && ! showMobile ) || ( ! isMobile && ! showDesktop ) ) {
			el.remove();
			return;
		}
		var freq = el.getAttribute( 'data-frequency' ) || 'once_per_session';
		var minViews = parseInt( el.getAttribute( 'data-min-views' ) || '0', 10 );
		var skipDelay = parseInt( el.getAttribute( 'data-skip' ) || '5', 10 );
		var adId = parseInt( el.getAttribute( 'data-ad' ) || '0', 10 );
		var storageKey = 'menda_gate';
		var pvKey = 'menda_pv';
		var pv = parseInt( sessionStorage.getItem( pvKey ) || '0', 10 ) + 1;
		sessionStorage.setItem( pvKey, String( pv ) );

		if ( freq === 'once_per_session' && sessionStorage.getItem( storageKey ) ) {
			el.remove();
			return;
		}
		if ( freq === 'once_per_day' ) {
			var last = localStorage.getItem( storageKey );
			if ( last && ( Date.now() - parseInt( last, 10 ) ) < 86400000 ) {
				el.remove();
				return;
			}
		}
		if ( minViews > 0 && pv < minViews ) {
			el.remove();
			return;
		}

		var overlay = el.querySelector( '.menda-gate__overlay' );
		var skipBtn = document.getElementById( 'menda-gate-skip' );
		var countdown = document.getElementById( 'menda-gate-countdown' );
		var inertSiblings = [];
		var scrollY = window.scrollY || window.pageYOffset;

		function lockScroll() {
			document.documentElement.style.overflow = 'hidden';
		}
		function unlockScroll() {
			document.documentElement.style.overflow = '';
			window.scrollTo( 0, scrollY );
		}
		function setInert( on ) {
			Array.prototype.forEach.call( document.body.children, function ( child ) {
				if ( child === el ) {
					return;
				}
				if ( on ) {
					if ( 'inert' in child ) {
						child.inert = true;
					}
					child.setAttribute( 'aria-hidden', 'true' );
					inertSiblings.push( child );
				} else if ( 'inert' in child ) {
					child.inert = false;
					child.removeAttribute( 'aria-hidden' );
				}
			} );
			if ( ! on ) {
				inertSiblings = [];
			}
		}
		function trapFocus( e ) {
			if ( e.key !== 'Tab' || ! el.classList.contains( 'menda-gate--active' ) ) {
				return;
			}
			var nodes = el.querySelectorAll( 'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])' );
			if ( ! nodes.length ) {
				return;
			}
			var first = nodes[ 0 ];
			var last = nodes[ nodes.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
		function closeDialog() {
			el.classList.remove( 'menda-gate--active' );
			unlockScroll();
			setInert( false );
			document.removeEventListener( 'keydown', trapFocus, true );
		}

		el.classList.add( 'menda-gate--active' );
		lockScroll();
		setInert( true );
		if ( skipBtn ) {
			skipBtn.focus();
		}
		document.addEventListener( 'keydown', trapFocus, true );
		track( adId, 'view' );
		if ( freq === 'once_per_session' ) {
			sessionStorage.setItem( storageKey, '1' );
		}
		if ( freq === 'once_per_day' ) {
			localStorage.setItem( storageKey, Date.now().toString() );
		}

		var remaining = skipDelay;
		if ( skipBtn ) {
			if ( remaining <= 0 ) {
				skipBtn.disabled = false;
				skipBtn.textContent = cfg.skip || 'Skip';
			} else {
				var timer = setInterval( function () {
					remaining--;
					if ( countdown ) {
						countdown.textContent = remaining;
					}
					if ( remaining <= 0 ) {
						clearInterval( timer );
						skipBtn.disabled = false;
						skipBtn.textContent = cfg.skip || 'Skip';
						skipBtn.focus();
					}
				}, 1000 );
			}
			skipBtn.addEventListener( 'click', closeDialog );
		}
		if ( overlay ) {
			overlay.addEventListener( 'click', function () {
				if ( skipBtn && ! skipBtn.disabled ) {
					closeDialog();
				}
			} );
		}
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && skipBtn && ! skipBtn.disabled ) {
				closeDialog();
			}
		} );
		el.querySelectorAll( '[data-menda-unit]' ).forEach( bindUnit );
	}

	function boot() {
		document.querySelectorAll( '[data-menda-unit]' ).forEach( bindUnit );
		initDock();
		initGate();
		initSkin();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
