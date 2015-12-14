<?php

namespace Wikibase\Rdf\Values;

use DataValues\TimeValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Rdf\ValueSnakRdfBuilder;
use Wikibase\Rdf\DateTimeValueCleaner;
use Wikibase\Rdf\RdfVocabulary;
use Wikimedia\Purtle\RdfWriter;

/**
 * RDF mapping for TimeValues.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class TimeRdfBuilder implements ValueSnakRdfBuilder {

	/**
	 * @var DateTimeValueCleaner
	 */
	private $dateCleaner;

	/**
	 * @var ComplexValueRdfHelper|null
	 */
	private $complexValueHelper;

	/**
	 * @param DateTimeValueCleaner $dateCleaner
	 * @param ComplexValueRdfHelper|null $complexValueHelper
	 */
	public function __construct(
		DateTimeValueCleaner $dateCleaner,
		ComplexValueRdfHelper $complexValueHelper = null
	) {
		$this->dateCleaner = $dateCleaner;
		$this->complexValueHelper = $complexValueHelper;
	}

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
		$writer->say( $propertyValueNamespace, $propertyValueLName );

		/** @var TimeValue $value */
		$value = $snak->getDataValue();
		$this->sayDateLiteral( $writer, $value );

		if ( $this->complexValueHelper !== null ) {
			$this->addValueNode( $writer, $propertyValueNamespace, $propertyValueLName, $dataType, $value );
		}
	}

	private function sayDateLiteral( RdfWriter $writer, TimeValue $value ) {
		$dateValue = $this->dateCleaner->getStandardValue( $value );
		if ( !is_null( $dateValue ) ) {
			// XXX: type should perhaps depend on precision.
			$writer->value( $dateValue, 'xsd', 'dateTime' );
		} else {
			$writer->value( $value->getTime() );
		}
	}

	/**
	 * Adds a value node representing all details of $value
	 *
	 * @param RdfWriter $writer
	 * @param string $propertyValueNamespace Property value relation namespace
	 * @param string $propertyValueLName Property value relation name
	 * @param string $dataType Property data type
	 * @param TimeValue $value
	 */
	private function addValueNode(
		RdfWriter $writer,
		$propertyValueNamespace,
		$propertyValueLName,
		$dataType,
		TimeValue $value
	) {
		$valueLName = $this->complexValueHelper->attachValueNode(
			$writer,
			$propertyValueNamespace,
			$propertyValueLName,
			$dataType,
			$value
		);

		if ( $valueLName === null ) {
			// The value node is already present in the output, don't create it again!
			return;
		}

		$valueWriter = $this->complexValueHelper->getValueNodeWriter();

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'timeValue' );
		$this->sayDateLiteral( $valueWriter, $value );

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'timePrecision' )
			->value( $value->getPrecision(), 'xsd', 'integer' ); //TODO: use identifiers

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'timeTimezone' )
			->value( $value->getTimezone(), 'xsd', 'integer' ); //XXX: underspecified

		$valueWriter->say( RdfVocabulary::NS_ONTOLOGY, 'timeCalendarModel' )
			->is( trim( $value->getCalendarModel() ) );
	}

}
