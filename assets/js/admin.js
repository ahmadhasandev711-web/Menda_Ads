/* global wp, jQuery */
( function ( $, wp ) {
	'use strict';

	// ── Media Upload ─────────────────────────────────────────────────────────
	$( document ).on( 'click', '.menda-upload-btn', function ( e ) {
		e.preventDefault();
		var btn       = $( this );
		var targetId  = btn.data( 'target' );
		var previewId = btn.data( 'preview' );

		var frame = wp.media( {
			title:    wp.i18n.__( 'Select Image', 'menda-ads' ),
			button:   { text: wp.i18n.__( 'Use this image', 'menda-ads' ) },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#' + targetId ).val( attachment.id );

			var src = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$( '#' + previewId )
				.html( '<img src="' + src + '" data-full="' + attachment.url + '" style="max-width:200px;max-height:100px;display:block;margin-top:6px;">' );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.menda-upload-clear', function ( e ) {
		e.preventDefault();
		var btn = $( this );
		$( '#' + btn.data( 'target' ) ).val( '0' );
		$( '#' + btn.data( 'preview' ) ).empty();
	} );

	// ── Ad Type Switcher ─────────────────────────────────────────────────────
	var $typeSelect = $( '#menda_ad_type' );
	var $targetSelect = $( '#menda_ad_target_type' );

	function currentTab() {
		return $( '.menda-tab.is-active' ).data( 'tab' ) || 'creative';
	}

	function toggleVideoRow() {
		if ( ! $typeSelect.length ) {
			return;
		}
		var type = $typeSelect.val();
		var tab = currentTab();
		var isSkin = type === 'skin';
		$( '.menda-row-video' ).toggle( tab === 'creative' && type === 'sticky_video' );
		$( '.menda-row-impression-cap' ).toggle( tab === 'delivery' && type === 'banner' );
		$( '.menda-row-skin' ).each( function () {
			var rowTab = $( this ).data( 'tab' ) || 'creative';
			$( this ).toggle( isSkin && tab === rowTab );
		} );
		$( '.menda-label-skin' ).toggle( isSkin );
		$( '.menda-label-banner' ).toggle( ! isSkin );
		$( '.menda-skin-preview-actions' ).toggle( isSkin );
		toggleSkinLayout();
	}

	function toggleSkinLayout() {
		var isSkin = $typeSelect.length ? $typeSelect.val() === 'skin' : false;
		var boxed = $( 'select[name="menda_ad_skin_layout"]' ).val() === 'boxed';
		$( '.menda-skin-boxed-only' ).toggle( isSkin && boxed );
		$( '.menda-skin-overlay-only' ).toggle( isSkin && ! boxed );
	}

	function toggleTargetRows() {
		if ( ! $targetSelect.length ) {
			return;
		}
		var target = $targetSelect.val();
		var tab = currentTab();
		if ( tab !== 'targeting' ) {
			$( '.menda-row-target-ids, .menda-row-target-cats, .menda-row-target-woo, .menda-row-target-taxonomy' ).hide();
			return;
		}
		$( '.menda-row-target-ids' ).toggle( target === 'pages' );
		$( '.menda-row-target-cats' ).toggle( target === 'categories' );
		$( '.menda-row-target-woo' ).toggle( target === 'woocommerce' );
		$( '.menda-row-target-taxonomy' ).toggle( target === 'taxonomy' );
	}

	function togglePlacementBox() {
		var isBanner = $typeSelect.length ? $typeSelect.val() === 'banner' : true;
		$( '#tagsdiv-menda_ad_placement, #menda_ad_placementdiv' ).toggle( isBanner );
	}

	function applyMetaTabs() {
		var tab = currentTab();
		$( '.menda-tab-row' ).each( function () {
			$( this ).toggle( $( this ).data( 'tab' ) === tab );
		} );
		toggleVideoRow();
		toggleTargetRows();
		togglePlacementBox();
	}

	if ( $( '.menda-tabs' ).length ) {
		$( document ).on( 'click', '.menda-tab', function () {
			$( '.menda-tab' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			applyMetaTabs();
		} );
		applyMetaTabs();
	}

	if ( $typeSelect.length ) {
		$typeSelect.on( 'change', applyMetaTabs );
	}

	$( document ).on( 'change', 'select[name="menda_ad_skin_layout"]', toggleSkinLayout );

	if ( $targetSelect.length ) {
		$targetSelect.on( 'change', toggleTargetRows );
		toggleTargetRows();
	}

	$( document ).on( 'change', 'input[name="menda_ad_languages[]"]', function () {
		var $boxes = $( 'input[name="menda_ad_languages[]"]' );
		var $all = $boxes.filter( '[value="all"]' );
		if ( $( this ).val() === 'all' && $( this ).is( ':checked' ) ) {
			$boxes.not( $all ).prop( 'checked', false );
			return;
		}
		if ( $( this ).val() !== 'all' && $( this ).is( ':checked' ) ) {
			$all.prop( 'checked', false );
		}
		if ( ! $boxes.filter( ':checked' ).length ) {
			$all.prop( 'checked', true );
		}
	} );

	// ── Live Ad Preview ──────────────────────────────────────────────────────
	$( document ).on( 'click', '.menda-preview-btn', function ( e ) {
		e.preventDefault();

		var type       = $( '#menda_ad_type' ).val();
		var link       = $( '#menda_ad_link' ).val() || '#';
		var customHtml = $( '#menda_ad_custom_code' ).val() || '';
		var imgUrl     = previewFull( 'menda_ad_image_id_preview' );
		var videoUrl   = $( '#menda_ad_video_url' ).val() || '';

		var $modal = $( '#menda-preview-modal' );
		if ( ! $modal.length ) {
			$modal = $(
				'<div id="menda-preview-modal" class="menda-preview-overlay">' +
					'<div class="menda-preview-window">' +
						'<div class="menda-preview-header">' +
							'<h3>' + wp.i18n.__( 'Live Ad Preview', 'menda-ads' ) + '</h3>' +
							'<div class="menda-preview-tabs">' +
								'<button type="button" class="menda-preview-tab active" data-device="desktop">🖥️ ' + wp.i18n.__( 'Desktop', 'menda-ads' ) + '</button>' +
								'<button type="button" class="menda-preview-tab" data-device="mobile">📱 ' + wp.i18n.__( 'Mobile', 'menda-ads' ) + '</button>' +
							'</div>' +
							'<button type="button" class="menda-preview-close">✕</button>' +
						'</div>' +
						'<div class="menda-preview-body desktop-view">' +
							'<div class="menda-preview-screen">' +
								'<div class="menda-preview-frame-container">' +
								'</div>' +
							'</div>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
			$( 'body' ).append( $modal );
		}

		var $container = $modal.find( '.menda-preview-frame-container' );
		$container.empty();

		var previewHtml = '';
		if ( customHtml ) {
			var $iframe = $( '<iframe src="about:blank" sandbox="allow-scripts allow-same-origin" style="width:100%; height:100%; border:none;"></iframe>' );
			$container.append( $iframe );
			$iframe[0].srcdoc = customHtml;
		} else if ( type === 'sticky_video' && videoUrl ) {
			if ( videoUrl.match( /\.(mp4|webm|ogg)/i ) ) {
				previewHtml = '<video src="' + videoUrl + '" controls style="max-width:100%; max-height:100%; display:block; margin:auto;"></video>';
			} else {
				previewHtml = '<iframe src="' + videoUrl + '" style="width:100%; height:100%; border:none;"></iframe>';
			}
			$container.html( previewHtml );
		} else if ( type === 'skin' ) {
			$container.html( skinPreviewHtml( imgUrl, link ) );
		} else if ( imgUrl ) {
			previewHtml = '<a href="' + link + '" target="_blank" style="display:block; text-align:center;"><img src="' + imgUrl + '" style="max-width:100%; height:auto; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1)"></a>';
			$container.html( previewHtml );
		} else {
			previewHtml = '<div style="padding:40px; text-align:center; color:#646970;">' + wp.i18n.__( 'Please select an image or add custom code to preview.', 'menda-ads' ) + '</div>';
			$container.html( previewHtml );
		}

		$modal.removeClass( 'type-banner type-interstitial type-sticky_video type-skin' )
			  .addClass( 'type-' + type );

		$modal.css('display', 'flex').hide().fadeIn( 200 );
	} );

	$( document ).on( 'click', '.menda-preview-close', function () {
		$( '#menda-preview-modal' ).fadeOut( 200 );
	} );

	$( document ).on( 'click', '.menda-preview-tab', function () {
		var $btn = $( this );
		$btn.addClass( 'active' ).siblings().removeClass( 'active' );
		var device = $btn.data( 'device' );
		$( '#menda-preview-modal .menda-preview-body' )
			.removeClass( 'desktop-view mobile-view' )
			.addClass( device + '-view' );
		if ( $( '#menda_ad_type' ).val() === 'skin' ) {
			$( '#menda-preview-modal .menda-preview-frame-container' ).html(
				skinPreviewHtml( previewFull( 'menda_ad_image_id_preview' ), $( '#menda_ad_link' ).val() || '#' )
			);
		}
	} );

	function previewFull( previewId ) {
		var $img = $( '#' + previewId + ' img' );
		return $img.attr( 'data-full' ) || $img.attr( 'src' ) || '';
	}

	function skinPreviewHtml( wallpaper, link ) {
		var left = previewFull( 'menda_ad_skin_left_id_preview' );
		var right = previewFull( 'menda_ad_skin_right_id_preview' );
		var top = previewFull( 'menda_ad_skin_top_id_preview' );
		var mobile = previewFull( 'menda_ad_skin_mobile_id_preview' );
		var color = $( 'input[name="menda_ad_skin_bg_color"]' ).val() || '#e8eef4';
		var isMobile = $( '#menda-preview-modal .menda-preview-body' ).hasClass( 'mobile-view' );
		if ( isMobile ) {
			var mobileSrc = mobile || left || right || wallpaper;
			if ( mobileSrc ) {
				return '<div class="menda-skin-mock menda-skin-mock--mobile"><div class="menda-skin-mock__article"></div><a class="menda-skin-mock__dock" href="' + link + '"><img src="' + mobileSrc + '" alt=""></a></div>';
			}
			var layoutM = $( 'select[name="menda_ad_skin_layout"]' ).val() || 'overlay';
			if ( layoutM === 'boxed' && color && color.toLowerCase() !== '#ffffff' ) {
				return '<div class="menda-skin-mock menda-skin-mock--mobile"><div class="menda-skin-mock__article"></div><a class="menda-skin-mock__dock" href="' + link + '" style="background:' + color + ';color:#fff;font-weight:700;justify-content:center;">' + wp.i18n.__( 'Sponsored', 'menda-ads' ) + '</a></div>';
			}
			return '<div class="menda-skin-mock menda-skin-mock--empty">' + wp.i18n.__( 'Phones show a sticky bar from the left/right panel if no mobile image is set.', 'menda-ads' ) + '</div>';
		}
		var layout = $( 'select[name="menda_ad_skin_layout"]' ).val() || 'overlay';
		if ( layout === 'overlay' ) {
			var leftSrc = left || wallpaper;
			var rightSrc = right || wallpaper;
			var htmlO = '<div class="menda-skin-mock menda-skin-mock--overlay">';
			htmlO += '<div class="menda-skin-mock__page menda-skin-mock__page--full"><span>' + wp.i18n.__( 'Site stays full width — side banners float on top', 'menda-ads' ) + '</span></div>';
			htmlO += '<a class="menda-skin-mock__rail menda-skin-mock__rail--overlay-left" href="' + link + '">' + ( leftSrc ? '<img src="' + leftSrc + '" alt="">' : '' ) + '</a>';
			htmlO += '<a class="menda-skin-mock__rail menda-skin-mock__rail--overlay-right" href="' + link + '">' + ( rightSrc ? '<img src="' + rightSrc + '" alt="">' : '' ) + '</a>';
			htmlO += '</div>';
			return htmlO;
		}
		var html = '<div class="menda-skin-mock" style="background-color:' + color + ';' + ( wallpaper ? 'background-image:url(' + wallpaper + ');background-size:cover;background-position:center top;background-repeat:no-repeat;' : '' ) + '">';
		if ( $( 'input[name="menda_ad_skin_header_full"][value="1"]' ).is( ':checked' ) ) {
			html += '<div class="menda-skin-mock__fullhead">' + wp.i18n.__( 'Full-width header', 'menda-ads' ) + '</div>';
		}
		if ( top ) {
			html += '<div class="menda-skin-mock__billboard"><img src="' + top + '" alt=""></div>';
		}
		html += '<div class="menda-skin-mock__row">';
		html += '<div class="menda-skin-mock__rail">' + ( left ? '<img src="' + left + '" alt="">' : '' ) + '</div>';
		html += '<div class="menda-skin-mock__page"><span>' + wp.i18n.__( 'Article column', 'menda-ads' ) + '</span></div>';
		html += '<div class="menda-skin-mock__rail">' + ( right ? '<img src="' + right + '" alt="">' : '' ) + '</div>';
		html += '</div></div>';
		return html;
	}

} )( jQuery, window.wp );
