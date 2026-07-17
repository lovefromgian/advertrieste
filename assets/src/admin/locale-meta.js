/**
 * Media picker per il meta box "Dati locale" (logo singolo + galleria multipla).
 * Usa wp.media (caricato da wp_enqueue_media). Nessuna dipendenza esterna oltre jQuery.
 *
 * @package AdverTrieste
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $logoId = $( '#advtr_logo_id' );
		var $logoPreview = $( '#advtr_logo_preview' );
		var $galleriaIds = $( '#advtr_galleria_ids' );
		var $galleriaPreview = $( '#advtr_galleria_preview' );

		if ( ! $logoId.length && ! $galleriaIds.length ) {
			return;
		}

		// --- Logo (selezione singola) ---
		var logoFrame;
		$( '#advtr_logo_select' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( logoFrame ) {
				logoFrame.open();
				return;
			}
			logoFrame = wp.media( {
				title: 'Seleziona logo',
				library: { type: 'image' },
				multiple: false
			} );
			logoFrame.on( 'select', function () {
				var att = logoFrame.state().get( 'selection' ).first().toJSON();
				$logoId.val( att.id );
				var url = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
				$logoPreview.html( $( '<img>', { src: url, alt: '' } ) );
			} );
			logoFrame.open();
		} );

		$( '#advtr_logo_remove' ).on( 'click', function ( e ) {
			e.preventDefault();
			$logoId.val( '' );
			$logoPreview.empty();
		} );

		// --- Galleria (selezione multipla) ---
		function renderGalleria( ids ) {
			$galleriaPreview.empty();
			ids.forEach( function ( id ) {
				var attachment = wp.media.attachment( id );
				attachment.fetch().done( function () {
					var data = attachment.toJSON();
					var url = ( data.sizes && data.sizes.thumbnail ) ? data.sizes.thumbnail.url : data.url;
					$galleriaPreview.append( $( '<img>', { src: url, alt: '' } ) );
				} );
			} );
		}

		function currentGalleriaIds() {
			var raw = $galleriaIds.val();
			if ( ! raw ) {
				return [];
			}
			return raw.split( ',' ).map( function ( n ) {
				return parseInt( n, 10 );
			} ).filter( function ( n ) {
				return ! isNaN( n );
			} );
		}

		var galleriaFrame;
		$( '#advtr_galleria_select' ).on( 'click', function ( e ) {
			e.preventDefault();
			galleriaFrame = wp.media( {
				title: 'Gestisci galleria',
				library: { type: 'image' },
				multiple: 'add'
			} );
			galleriaFrame.on( 'open', function () {
				var selection = galleriaFrame.state().get( 'selection' );
				currentGalleriaIds().forEach( function ( id ) {
					var attachment = wp.media.attachment( id );
					selection.add( attachment ? [ attachment ] : [] );
				} );
			} );
			galleriaFrame.on( 'select', function () {
				var ids = galleriaFrame.state().get( 'selection' ).map( function ( att ) {
					return att.id;
				} );
				$galleriaIds.val( ids.join( ',' ) );
				renderGalleria( ids );
			} );
			galleriaFrame.open();
		} );

		$( '#advtr_galleria_clear' ).on( 'click', function ( e ) {
			e.preventDefault();
			$galleriaIds.val( '' );
			$galleriaPreview.empty();
		} );

		// Anteprima iniziale della galleria.
		renderGalleria( currentGalleriaIds() );
	} );
} )( jQuery );
