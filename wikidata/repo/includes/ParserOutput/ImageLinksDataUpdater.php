<?php

namespace Wikibase\Repo\ParserOutput;

use DataValues\StringValue;
use ParserOutput;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\PropertyDataTypeMatcher;

/**
 * Register commonsMedia values as used images in ParserOutput.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Mättig
 */
class ImageLinksDataUpdater implements StatementDataUpdater {

	/**
	 * @var PropertyDataTypeMatcher
	 */
	private $propertyDataTypeMatcher;

	/**
	 * @var null[] Hash set of the file name strings found while processing statements. Only the
	 * array keys are used for performance reasons, the values are meaningless.
	 */
	private $fileNames = array();

	/**
	 * @param PropertyDataTypeMatcher $propertyDataTypeMatcher
	 */
	public function __construct( PropertyDataTypeMatcher $propertyDataTypeMatcher ) {
		$this->propertyDataTypeMatcher = $propertyDataTypeMatcher;
	}

	/**
	 * Add DataValue to list of used images if Snak property data type is commonsMedia.
	 *
	 * @param Statement $statement
	 */
	public function processStatement( Statement $statement ) {
		foreach ( $statement->getAllSnaks() as $snak ) {
			$this->processSnak( $snak );
		}
	}

	/**
	 * @param Snak $snak
	 */
	private function processSnak( Snak $snak ) {
		if ( $snak instanceof PropertyValueSnak ) {
			$id = $snak->getPropertyId();
			$value = $snak->getDataValue();

			if ( $value instanceof StringValue
				&& $this->propertyDataTypeMatcher->isMatchingDataType( $id, 'commonsMedia' )
			) {
				$fileName = str_replace( ' ', '_', $value->getValue() );

				if ( $fileName !== '' ) {
					$this->fileNames[$fileName] = null;
				}
			}
		}
	}

	/**
	 * Treat CommonsMedia values as file transclusions
	 *
	 * @param ParserOutput $parserOutput
	 */
	public function updateParserOutput( ParserOutput $parserOutput ) {
		foreach ( $this->fileNames as $fileName => $null ) {
			$parserOutput->addImage( $fileName );
		}
	}

}
