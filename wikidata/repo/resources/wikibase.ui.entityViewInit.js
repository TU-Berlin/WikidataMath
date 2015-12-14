/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Daniel Werner < daniel.werner at wikimedia.de >
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
( function( $, mw, wb, dataTypeStore, getExpertsStore, getFormatterStore, getParserStore ) {
	'use strict';

	/**
	 * @param {jQuery} $entityview
	 */
	function initToolbarController( $entityview ) {
		// The toolbars (defined per jquery.wikibase.toolbarcontroller.definition) that should
		// be initialized:
		var toolbarControllerConfig = {
			toolbars: {
				addtoolbar: [
					'statementgrouplistview-statementgroupview',
					'statementlistview-statementview',
					'statementview-snakview',
					'statementview-referenceview',
					'referenceview-snakview'
				],
				edittoolbar: [
					'statementview',
					'entitytermsview',
					'sitelinkgroupview'
				],
				removetoolbar: [
					'referenceview',
					'statementview-snakview',
					'referenceview-snakview',
					'sitelinkgroupview-sitelinkview'
				]
			}
		};

		$entityview.toolbarcontroller( toolbarControllerConfig );
	}

	/**
	 * @return {string[]} An ordered list of languages the user wants to use, the first being her
	 *                    preferred language, and thus the UI language (currently wgUserLanguage).
	 */
	function getUserLanguages() {
		var userLanguages = mw.config.get( 'wbUserSpecifiedLanguages' ),
			isUlsDefined = mw.uls && $.uls && $.uls.data,
			languages;

		if ( !userLanguages.length && isUlsDefined ) {
			languages = mw.uls.getFrequentLanguageList().slice( 1, 4 );
		} else {
			languages = userLanguages.slice();
			languages.splice( $.inArray( mw.config.get( 'wgUserLanguage' ), userLanguages ), 1 );
		}

		languages.unshift( mw.config.get( 'wgUserLanguage' ) );

		return languages;
	}

	/**
	 * Builds an entity store.
	 *
	 * @param {wikibase.api.RepoApi} repoApi
	 * @param {string} languageCode The language code of the ui language
	 * @return {wikibase.store.CachingEntityStore}
	 */
	function buildEntityStore( repoApi, languageCode ) {
		return new wb.store.CachingEntityStore(
			new wb.store.ApiEntityStore(
				repoApi,
				new wb.serialization.EntityDeserializer(),
				[ languageCode ]
			)
		);
	}

	/**
	 * @param {wikibase.datamodel.Entity} entity
	 * @param {jQuery} $entityview
	 * @return {string} The name of the entity view widget class
	 *
	 * @throws {Error} if no widget to render the entity exists.
	 */
	function createEntityView( entity, $entityview ) {
		var repoConfig = mw.config.get( 'wbRepo' ),
			repoApiUrl = repoConfig.url + repoConfig.scriptPath + '/api.php',
			mwApi = wb.api.getLocationAgnosticMwApi( repoApiUrl ),
			repoApi = new wb.api.RepoApi( mwApi ),
			userLanguages = getUserLanguages(),
			entityStore = buildEntityStore( repoApi, userLanguages[0] ),
			revisionStore = new wb.RevisionStore( mw.config.get( 'wgCurRevisionId' ) ),
			entityChangersFactory = new wb.entityChangers.EntityChangersFactory(
				repoApi,
				revisionStore,
				entity
			),
			contentLanguages = new wikibase.WikibaseContentLanguages(),
			formatterStore = getFormatterStore( repoApi, dataTypeStore ),
			parserStore = getParserStore( repoApi ),
			entityIdFormatter = new ( formatterStore.getFormatter( wb.datamodel.EntityId.TYPE ) )( { lang: userLanguages[0] } ),
			entityIdParser = new ( parserStore.getParser( wb.datamodel.EntityId.TYPE ) )( { lang: userLanguages[0] } ),
			viewFactory = new wikibase.view.ViewFactory(
				contentLanguages,
				dataTypeStore,
				entityChangersFactory,
				new wb.entityIdFormatter.CachingEntityIdHtmlFormatter(
					new wb.entityIdFormatter.DataValueBasedEntityIdHtmlFormatter( entityIdParser, entityIdFormatter )
				),
				new wb.entityIdFormatter.CachingEntityIdPlainFormatter(
					new wb.entityIdFormatter.DataValueBasedEntityIdPlainFormatter( entityIdParser, entityIdFormatter )
				),
				entityStore,
				getExpertsStore( dataTypeStore ),
				formatterStore,
				{
					getMessage: function( key, params ) {
						return mw.msg.apply( mw, [ key ].concat( params ) );
					}
				},
				parserStore,
				userLanguages,
				repoApiUrl
			);

		var entityView = viewFactory.getEntityView( entity, $entityview );

		return entityView.widgetName;
	}

	/**
	 * @param {jQuery.wikibase.entityview} $entityview
	 * @param {string} viewName
	 * @param {string} entityType
	 */
	function attachAnonymousEditWarningTrigger( $entityview, viewName, entityType ) {
		if ( !mw.user || !mw.user.isAnon() ) {
			return;
		}

		$entityview.on( viewName + 'afterstartediting', function() {
			if ( !$.find( '.mw-notification-content' ).length
				&& !$.cookie( 'wikibase-no-anonymouseditwarning' )
			) {
				var message = mw.msg(
					'wikibase-anonymouseditwarning',
					mw.msg( 'wikibase-entity-' + entityType )
				);
				mw.notify( message, { autoHide: false, type: 'warn', tag: 'wikibase-anonymouseditwarning' } );
			}
		} );
	}

	/**
	 * Update the state of the watch link if the user has watchdefault enabled.
	 */
	function attachWatchLinkUpdater( $entityview, viewName ) {
		var update = mw.page && mw.page.watch ? mw.page.watch.updateWatchLink : null;

		if ( !update || !mw.user.options.get( 'watchdefault' ) ) {
			return;
		}

		function updateWatchLink() {
			// All four supported skins are using the same ID, the other selectors
			// in mediawiki.page.watch.ajax.js are undocumented and probably legacy stuff
			var $link = $( '#ca-watch' ).find( 'a' );

			// Skip if page is already watched and there is no "watch this page" link
			// Note: The exposed function fails for empty jQuery collections
			if ( !$link.length ) {
				return;
			}

			update( $link, 'watch', 'loading' );

			var api = new mw.Api(),
				pageId = mw.config.get( 'wgArticleId' );

			api.get( {
				action: 'query',
				prop: 'info',
				inprop: 'watched',
				pageids: pageId
			} ).done( function( data ) {
				var watched = data.query && data.query.pages[pageId]
					&& data.query.pages[pageId].watched !== undefined;
				update( $link, watched ? 'unwatch' : 'watch' );
			} ).fail( function() {
				update( $link, 'watch' );
			} );
		}

		$entityview.on( viewName + 'afterstopediting', function( event, dropValue ) {
			if ( !dropValue ) {
				updateWatchLink();
			}
		} );
	}

	/**
	 * @param {jQuery} $entityview
	 * @param {jQuery} $origin
	 * @param {string} gravity
	 */
	function showCopyrightTooltip( $entityview, $origin, gravity ) {
		if ( !mw.config.exists( 'wbCopyright' ) ) {
			return;
		}

		gravity = gravity || 'nw';

		var copyRight = mw.config.get( 'wbCopyright' ),
			copyRightVersion = copyRight.version,
			copyRightMessageHtml = copyRight.messageHtml,
			cookieKey = 'wikibase.acknowledgedcopyrightversion',
			optionsKey = 'wb-acknowledgedcopyrightversion';

		if ( $.cookie( cookieKey ) === copyRightVersion
			|| mw.user.options.get( optionsKey ) === copyRightVersion
		) {
			return;
		}

		var $message = $( '<span><p>' + copyRightMessageHtml + '</p></span>' )
				.addClass( 'wikibase-copyrightnotification-container' ),
			$hideMessage = $( '<a/>', {
				text: mw.msg( 'wikibase-copyrighttooltip-acknowledge' )
			} ).appendTo( $message ),
			editableTemplatedWidget = $origin.data( 'EditableTemplatedWidget' );

		// TODO: Use notification system for copyright messages on all widgets.
		if ( editableTemplatedWidget
			&& !( editableTemplatedWidget instanceof $.wikibase.statementview )
			&& !( editableTemplatedWidget instanceof $.wikibase.aliasesview )
		) {
			editableTemplatedWidget.notification( $message, 'wb-edit' );

			$hideMessage.on( 'click', function( event ) {
				event.preventDefault();
				editableTemplatedWidget.notification();
				if ( mw.user.isAnon() ) {
					$.cookie( cookieKey, copyRightVersion, { expires: 365 * 3, path: '/' } );
				} else {
					var api = new mw.Api();
					api.postWithToken( 'options', {
						action: 'options',
						optionname: optionsKey,
						optionvalue: copyRightVersion
					} );
				}
			} );
			return;
		}

		var edittoolbar = $origin.data( 'edittoolbar' );

		if ( !edittoolbar ) {
			return;
		}

		// Tooltip gets its own anchor since other elements might have their own tooltip.
		// we don't even have to add this new toolbar element to the toolbar, we only use it
		// to manage the tooltip which will have the 'save' button as element to point to.
		// The 'save' button can still have its own tooltip though.
		var $messageAnchor = $( '<span/>' )
			.appendTo( 'body' )
			.toolbaritem()
			.wbtooltip( {
				content: $message,
				permanent: true,
				gravity: gravity,
				$anchor: edittoolbar.getContainer()
			} );

		$hideMessage.on( 'click', function( event ) {
			event.preventDefault();
			$messageAnchor.data( 'wbtooltip' ).degrade( true );
			$( window ).off( '.wbCopyrightTooltip' );
			if ( mw.user.isAnon() ) {
				$.cookie( cookieKey, copyRightVersion, { expires: 365 * 3, path: '/' } );
			} else {
				var api = new mw.Api();
				api.postWithToken( 'options', {
					action: 'options',
					optionname: optionsKey,
					optionvalue: copyRightVersion
				} );
			}
		} );

		$messageAnchor.data( 'wbtooltip' ).show();

		// destroy tooltip after edit mode gets closed again:
		$entityview
		.one( 'entityviewafterstopediting.wbCopyRightTooltip', function( event, origin ) {
			var tooltip = $messageAnchor.data( 'wbtooltip' );
			if ( tooltip ) {
				tooltip.degrade( true );
			}
			$( window ).off( '.wbCopyrightTooltip' );
		} );

		$( window ).one(
			'scroll.wbCopyrightTooltip touchmove.wbCopyrightTooltip resize.wbCopyrightTooltip',
			function() {
				var tooltip = $messageAnchor.data( 'wbtooltip' );
				if ( tooltip ) {
					$messageAnchor.data( 'wbtooltip' ).hide();
				}
				$entityview.off( '.wbCopyRightTooltip' );
			}
		);
	}

	/**
	 * @param {jQuery} $entityview
	 */
	function attachCopyrightTooltip( $entityview ) {
		$entityview.on( 'edittoolbarafterstartediting', function( event ) {
			var $target = $( event.target ),
				gravity = 'sw';

			if ( $target.data( 'sitelinkgroupview' ) ) {
				gravity = 'nw';
			} else if ( $target.data( 'entitytermsview' ) ) {
				gravity = 'w';
			}

			showCopyrightTooltip( $entityview, $target, gravity );
		} );
	}

	mw.hook( 'wikipage.content' ).add( function() {
		if ( mw.config.get( 'wbEntity' ) === null ) {
			return;
		}

		var $entityview = $( '.wikibase-entityview' );
		var entityInitializer = new wb.EntityInitializer( 'wbEntity' );
		var canEdit = !mw.config.get( 'wbUserIsBlocked' ) && mw.config.get( 'wbUserCanEdit' )
			&& mw.config.get( 'wbIsEditView' );

		if ( canEdit ) {
			initToolbarController( $entityview );
		}

		entityInitializer.getEntity().done( function( entity ) {
			var viewName = createEntityView( entity, $entityview.first() );

			if ( canEdit ) {
				attachAnonymousEditWarningTrigger( $entityview, viewName, entity.getType() );
				attachWatchLinkUpdater( $entityview, viewName );
			}
		} );

		if ( canEdit ) {
			$entityview
			.on( 'entitytermsviewchange entitytermsviewafterstopediting', function( event ) {
				var $entitytermsview = $( event.target ),
					entitytermsview = $entitytermsview.data( 'entitytermsview' );

				$.each( entitytermsview.value(), function() {
					if ( this.language !== mw.config.get( 'wgUserLanguage' ) ) {
						return true;
					}

					var label = this.label.getText();

					$( 'title' ).text(
						mw.msg( 'pagetitle', label !== '' ? label : mw.config.get( 'wgTitle' ) )
					);

					$( 'h1' ).find( '.wikibase-title' )
						.toggleClass( 'wb-empty', label === '' )
						.find( '.wikibase-title-label' )
						.text( label !== '' ? label : mw.msg( 'wikibase-label-empty' ) );

					return false;
				} );
			} );

			attachCopyrightTooltip( $entityview );
		}
	} );

} )(
	jQuery,
	mediaWiki,
	wikibase,
	wikibase.dataTypeStore,
	wikibase.experts.getStore,
	wikibase.formatters.getStore,
	wikibase.parsers.getStore
);
