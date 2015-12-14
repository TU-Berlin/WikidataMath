<?php

namespace Wikibase\Rdf\Values;

use DataValues\MonolingualTextValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Rdf\ValueSnakRdfBuilder;
use Wikimedia\Purtle\RdfWriter;

/**
 * RDF mapping for MonolingualTextValues.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class MonolingualTextRdfBuilder implements ValueSnakRdfBuilder {

	/**
	 * Adds specific value
	 *
	 * @param RdfWriter $writer
	 * @param string $propertyValueNamespace Property value relation namespace
	 * @param string $propertyValueLName Property value relation name
	 * @param string $dataType Property data type
	 * @param PropertyValueSnak $snak
	 */
	public function addValue(
		RdfWriter $writer,
		$propertyValueNamespace,
		$propertyValueLName,
		$dataType,
		PropertyValueSnak $snak
	) {
		/** @var MonolingualTextValue $value */
		$value = $snak->getDataValue();
		$writer->say( $propertyValueNamespace, $propertyValueLName )
			->text( $value->getText(), $value->getLanguageCode() );
	}

}
