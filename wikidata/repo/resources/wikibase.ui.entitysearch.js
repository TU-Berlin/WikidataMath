/**
 * Replacing the native MediaWiki search suggestions with the jQuery.wikibase.entitysearch widget.
 *
 * @license GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $, mw ) {
	'use strict';

	$( function() {
		var $form = $( '#searchform ' ),
			$input = $( '#searchInput' ),
			// Both inputs must be named "search" to support Firefox' smart keyword feature (T60467)
			$hiddenInput = $( '<input type="hidden" name="search"/>' );

		/**
		 * @param {jQuery} $form
		 * @return {string}
		 */
		function getHref( $form ) {
			var href = $form.attr( 'action' ),
				params = {};

			href += href.indexOf( '?' ) === -1 ? '?' : '&';

			$.each( $form.serializeArray(), function( i, param ) {
				params[param.name] = param.value;
			} );

			params.search = $input.val();

			return href + $.param( params );
		}

		/**
		 * Updates the suggestion list special item that triggers a full-text search.
		 *
		 * @param {jQuery.ui.ooMenu.CustomItem} searchContaining
		 */
		function updateSuggestionSpecial( searchContaining ) {
			var $suggestionsSpecial = $( '.wb-entitysearch-suggestions .suggestions-special' );
			$suggestionsSpecial.find( '.special-query' ).text( $input.val() );

			searchContaining.setLink( getHref( $form ) + '&fulltext=1' );
		}

		/**
		 * Removes the native search box suggestion list.
		 *
		 * @param {HTMLElement} input Search box node
		 */
		function removeSuggestionContext( input ) {
			// Native fetch() updates/re-sets the data attribute with the suggestion context.
			$.data( input, 'suggestionsContext' ).config.fetch = function() {};
			$.removeData( input, 'suggestionsContext' );
		}

		var suggestionsPlaceholder = new $.ui.ooMenu.CustomItem(
			$( '<div/>' ).append( $.createSpinner() )
		);

		var $searchContaining = $( '<div>' )
			.addClass( 'suggestions-special' )
			.append(
				$( '<div>' )
					.addClass( 'special-label' )
					.text( mw.msg( 'searchsuggest-containing' ) ),
				$( '<div>' )
					.addClass( 'special-query' )
			);

		var searchContaining = new $.ui.ooMenu.CustomItem( $searchContaining, null, function() {
			$form.submit();
		}, 'wb-entitysearch-suggestions' );

		var $searchMenu = $( '<ul/>' ).ooMenu( {
			customItems: [searchContaining]
		} );

		// Must be placed in that order to support Firefox' smart keyword feature (T60467)
		$input.before( $hiddenInput );

		$input
		.one( 'focus', function( event ) {
			if ( $.data( this, 'suggestionsContext' ) ) {
				removeSuggestionContext( this );
			} else {
				// Suggestion context might not be initialized when focusing the search box while
				// the page is still rendered.
				var $input = $( this );
				$input.on( 'keypress.entitysearch', function( event ) {
					if ( $.data( this, 'suggestionsContext' ) ) {
						removeSuggestionContext( this );
						$input.off( '.entitysearch' );
					}
				} );
			}
		} )
		.entitysearch( {
			url: mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php',
			menu: $searchMenu.data( 'ooMenu' ),
			position: $.extend(
				{},
				$.wikibase.entityselector.prototype.options.position,
				{ offset: '-1 2' }
			),
			confineMinWidthTo: $form,
			suggestionsPlaceholder: suggestionsPlaceholder
		} )
		.on( 'entityselectoropen', function( event ) {
			updateSuggestionSpecial( searchContaining );
		} )
		.on( 'eachchange', function( event, oldVal ) {
			$hiddenInput.val( '' );
			updateSuggestionSpecial( searchContaining );
		} )
		.on( 'entityselectorselected', function( event, entityId ) {
			$hiddenInput.val( entityId );
		} );

		// TODO: Re-evaluate entity selector input (e.g. hitting "Go" after having hit "Search"
		// before. However, this will require triggering the entity selector's API call and waiting
		// for its response.

		$( '#searchGoButton' ).on( 'click keydown', function( event ) {
			if ( !$input.data( 'entityselector' ) ) {
				return;
			}

			// If an entity is selected, redirect to that entity's page.
			if ( event.type === 'click'
				|| event.keyCode === $.ui.keyCode.ENTER
				|| event.keyCode === $.ui.keyCode.SPACE
			) {
				var entity = $input.data( 'entityselector' ).selectedEntity();
				if ( entity && entity.url ) {
					event.preventDefault(); // Prevent default form submit action.
					window.location.href = entity.url;
				}
			}

		} );

		// Default form submit action: Imitate full-text search.
		// Since we are using the entity selector, if an entity is selected, the entity id is stored
		// in a hidden input element (which has ripped the "name" attribute from the original search
		// box). Therefore, the entity id needs to be replaced by the actual search box (entity
		// selector) content.
		$form.on( 'submit', function( event ) {
			$( this ).find( 'input[name="search"]' ).val( $input.val() );
		} );

	} );

}( jQuery, mediaWiki ) );
