/**
 * @licence GNU GPL v2+
 * @author Daniel Werner < danweetz@web.de >
 */
( function( $, wb, vp, dv, util ) {
'use strict';

wb.parsers = wb.parsers || {};

var PARENT = vp.ValueParser;

/**
 * Returns a constructor for a ValueParser which parses using the given wb.api.ParseValueCaller.
 * @since 0.5
 *
 * This is necessary since valueParser.ValueParserStore returns a constructor, not an instance, and
 * we have to pass in the RepoApi wrapped in a wikibase.api.ParseValueCaller.
 *
 * @param {wikibase.api.ParseValueCaller} apiValueParser
 * @return {Function}
 */
wb.parsers.getApiBasedValueParserConstructor = function( apiValueParser ) {
	/**
	 * Base constructor for objects representing a value parser which is doing an API request to the
	 * 'parseValue' API module.
	 * @constructor
	 * @extends valueParsers.ValueParser
	 * @since 0.5
	 */
	return util.inherit( 'WbApiBasedValueParser', PARENT, {
		/**
		 * The key of the related API parser.
		 * @type {string}
		 */
		API_VALUE_PARSER_ID: null,

		/**
		 * @see valueParsers.ValueParser.parse
		 * @since 0.5
		 *
		 * @param {string} rawValue
		 * @return {Object} jQuery Promise
		 *         Resolved parameters:
		 *         - {dataValues.DataValues}
		 *         Rejected parameters:
		 *         - {string} HTML error message.
		 */
		parse: function( rawValue ) {
			var deferred = $.Deferred();

			apiValueParser.parseValues( this.API_VALUE_PARSER_ID, [rawValue], this._options )
				.done( function( results ) {
					var result;

					if ( results.length === 0 ) {
						deferred.reject( 'Parse API returned an empty result set.' );
						return;
					}

					try {
						result = dv.newDataValue( results[0].type, results[0].value );
						deferred.resolve( result );
					} catch ( error ) {
						deferred.reject( error.message );
					}
				} )
				.fail( function( error ) {
					deferred.reject( error.detailedMessage || error.code );
				} );

			return deferred.promise();
		}

	} );
};

}( jQuery, wikibase, valueParsers, dataValues, util ) );
