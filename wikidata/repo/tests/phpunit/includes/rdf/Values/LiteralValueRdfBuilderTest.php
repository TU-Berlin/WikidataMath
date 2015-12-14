<?php

namespace Wikibase\Test\Rdf;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Rdf\Values\LiteralValueRdfBuilder;
use Wikimedia\Purtle\NTriplesRdfWriter;
use Wikibase\DataModel\Snak\PropertyValueSnak;

/**
 * @covers Wikibase\Rdf\Values\LiteralValueRdfBuilder
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseRdf
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class LiteralValueRdfBuilderTest extends \PHPUnit_Framework_TestCase {

	public function provideAddValue() {
		$p11 = new PropertyId( 'P11' );
		$stringSnak = new PropertyValueSnak( $p11, new StringValue( 'Hello World' ) );
		$numberSnak = new PropertyValueSnak( $p11, new StringValue( '15' ) );

		return array(
			'plain string' => array(
				null, null,
				$stringSnak,
				array( '<http://www/Q1> <http://acme/testing> "Hello World" .' )
			),
			'xsd decimal' => array(
				null, 'decimal',
				$numberSnak,
				array( '<http://www/Q1> <http://acme/testing> "15"^^<http://www.w3.org/2001/XMLSchema#decimal> .' )
			),
			'wd id' => array(
				'xx', 'id',
				$stringSnak,
				array( '<http://www/Q1> <http://acme/testing> "Hello World"^^<http://xx/id> .' )
			),
		);
	}

	/**
	 * @dataProvider provideAddValue
	 */
	public function testAddValue(
		$typeBase, $typeLocal,
		PropertyValueSnak $snak,
		array $expected
	) {
		$builder = new LiteralValueRdfBuilder( $typeBase, $typeLocal );

		$writer = new NTriplesRdfWriter();
		$writer->prefix( 'www', "http://www/" );
		$writer->prefix( 'acme', "http://acme/" );
		$writer->prefix( $typeBase, "http://$typeBase/" );

		$writer->start();
		$writer->about( 'www', 'Q1' );

		$builder->addValue( $writer, 'acme', 'testing', 'DUMMY', $snak );

		$triples = explode( "\n", trim( $writer->drain() ) );
		$this->assertEquals( $expected, $triples );
	}

}
