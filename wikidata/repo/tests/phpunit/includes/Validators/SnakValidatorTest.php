<?php

namespace Wikibase\Test;

use DataTypes\DataTypeFactory;
use DataValues\DataValue;
use DataValues\StringValue;
use DataValues\UnDeserializableValue;
use DataValues\UnknownValue;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\DataTypeValidatorFactory;
use Wikibase\Repo\Validators\SnakValidator;

/**
 * @covers Wikibase\Repo\Validators\SnakValidator
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseValidators
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class SnakValidatorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var DataTypeFactory
	 */
	protected $dataTypeFactory;

	/**
	 * @var PropertyDataTypeLookup
	 */
	protected $propertyDataTypeLookup;

	/**
	 * @var DataTypeValidatorFactory
	 */
	private $validatorFactory;

	protected function setUp() {
		parent::setUp();

		$numericValidator = new TestValidator( '/^\d+$/' );
		$alphabeticValidator = new TestValidator( '/^[A-Z]+$/i' );
		$lengthValidator = new TestValidator( '/^.{1,10}$/' );

		$this->dataTypeFactory = new DataTypeFactory( array(
			'numeric' => 'string',
			'alphabetic' => 'string'
		) );

		$p1 = new PropertyId( 'p1' );
		$p2 = new PropertyId( 'p2' );

		$this->propertyDataTypeLookup = new InMemoryDataTypeLookup();
		$this->propertyDataTypeLookup->setDataTypeForProperty( $p1, 'numeric' );
		$this->propertyDataTypeLookup->setDataTypeForProperty( $p2, 'alphabetic' );

		$this->validatorFactory = $this->getMock( 'Wikibase\Repo\DataTypeValidatorFactory' );
		$this->validatorFactory->expects( $this->any() )
			->method( 'getValidators' )
			->will( $this->returnCallback( function( $dataTypeId ) use (
				$numericValidator,
				$alphabeticValidator,
				$lengthValidator
			) {
				return array(
					$dataTypeId === 'numeric' ? $numericValidator : $alphabeticValidator,
					$lengthValidator
				);
			} ) );
	}

	public function provideValidateClaimSnaks() {
		$p1 = new PropertyId( 'p1' ); // numeric
		$p2 = new PropertyId( 'p2' ); // alphabetic

		$cases = array();

		$claim = new Statement( new PropertyNoValueSnak( $p1 ) );
		$cases[] = array( $claim, 'empty claim', true );

		$claim = new Statement(
			new PropertyValueSnak( $p1, new StringValue( '12' ) )
		);
		$claim->setQualifiers( new SnakList( array(
			new PropertyValueSnak( $p2, new StringValue( 'abc' ) )
		) ) );
		$claim->setReferences( new ReferenceList( array(
			new Reference( new SnakList( array(
				new PropertyValueSnak( $p2, new StringValue( 'xyz' ) )
			) ) )
		) ) );
		$cases[] = array( $claim, 'conforming claim', true );

		$brokenClaim = clone $claim;
		$brokenClaim->setMainSnak(
			new PropertyValueSnak( $p1, new StringValue( 'kittens' ) )
		);
		$cases[] = array( $brokenClaim, 'error in main snak', false );

		$brokenClaim = clone $claim;
		$brokenClaim->setQualifiers( new SnakList( array(
			new PropertyValueSnak( $p2, new StringValue( '333' ) )
		) ) );
		$cases[] = array( $brokenClaim, 'error in qualifier', false );

		$brokenClaim = clone $claim;
		$brokenClaim->setReferences( new ReferenceList( array(
			new Reference( new SnakList( array(
				new PropertyValueSnak( $p1, new StringValue( 'xyz' ) )
			) ) )
		) ) );
		$cases[] = array( $brokenClaim, 'error in reference', false );

		return $cases;
	}

	/**
	 * @dataProvider provideValidateClaimSnaks
	 */
	public function testValidateClaimSnaks( Statement $statement, $description, $expectedValid = true ) {
		$validator = $this->getSnakValidator();

		$result = $validator->validateClaimSnaks( $statement );

		$this->assertEquals( $expectedValid, $result->isValid(), $description );
	}

	public function provideValidateReferences() {
		$p1 = new PropertyId( 'p1' ); // numeric
		$p2 = new PropertyId( 'p2' ); // alphabetic

		$cases = array();

		$references = new ReferenceList();
		$cases[] = array( $references, 'empty reference list', true );

		$references = new ReferenceList( array(
			new Reference( new SnakList( array(
				new PropertyValueSnak( $p1, new StringValue( '123' ) )
			) ) ),
			new Reference( new SnakList( array(
				new PropertyValueSnak( $p2, new StringValue( 'abc' ) )
			) ) )
		) );
		$cases[] = array( $references, 'conforming reference list', true );

		$references = new ReferenceList( array(
			new Reference( new SnakList( array(
				new PropertyValueSnak( $p1, new StringValue( '123' ) )
			) ) ),
			new Reference( new SnakList( array(
				new PropertyValueSnak( $p2, new StringValue( '456' ) )
			) ) )
		) );
		$cases[] = array( $references, 'invalid reference list', false );

		return $cases;
	}

	/**
	 * @dataProvider provideValidateReferences
	 */
	public function testValidateReferences( ReferenceList $references, $description, $expectedValid = true ) {
		$validator = $this->getSnakValidator();

		$result = $validator->validateReferences( $references );

		$this->assertEquals( $expectedValid, $result->isValid(), $description );
	}

	public function provideValidateReference() {
		$p1 = new PropertyId( 'p1' ); // numeric
		$p2 = new PropertyId( 'p2' ); // alphabetic

		$cases = array();

		$reference = new Reference( new SnakList() );
		$cases[] = array( $reference, 'empty reference', true );

		$reference = new Reference( new SnakList( array(
				new PropertyValueSnak( $p1, new StringValue( '123' ) ),
				new PropertyValueSnak( $p2, new StringValue( 'abc' ) )
			) )
		);
		$cases[] = array( $reference, 'conforming reference', true );

		$reference = new Reference( new SnakList( array(
				new PropertyValueSnak( $p1, new StringValue( '123' ) ),
				new PropertyValueSnak( $p2, new StringValue( '456' ) )
			) )
		);
		$cases[] = array( $reference, 'invalid reference', false );

		return $cases;
	}

	private function getSnakValidator() {
		return new SnakValidator(
			$this->propertyDataTypeLookup, $this->dataTypeFactory, $this->validatorFactory
		);
	}

	/**
	 * @dataProvider provideValidateReference
	 */
	public function testValidateReference( Reference $reference, $description, $expectedValid = true ) {
		$validator = $this->getSnakValidator();

		$result = $validator->validateReference( $reference );

		$this->assertEquals( $expectedValid, $result->isValid(), $description );
	}

	public function provideValidate() {
		$p1 = new PropertyId( 'p1' ); // numeric
		$p2 = new PropertyId( 'p2' ); // alphabetic
		$p3 = new PropertyId( 'p3' ); // bad

		$cases = array();

		$snak = new PropertyNoValueSnak( $p1 );
		$cases[] = array( $snak, 'PropertyNoValueSnak', true );

		$snak = new PropertySomeValueSnak( $p2 );
		$cases[] = array( $snak, 'PropertySomeValueSnak', true );

		$snak = new PropertyValueSnak( $p1, new StringValue( '123' ) );
		$cases[] = array( $snak, 'valid numeric value', true );

		$snak = new PropertyValueSnak( $p2, new StringValue( '123' ) );
		$cases[] = array( $snak, 'invalid alphabetic value', false );

		$snak = new PropertyValueSnak( $p2, new StringValue( 'abc' ) );
		$cases[] = array( $snak, 'valid alphabetic value', true );

		$snak = new PropertyValueSnak( $p1, new StringValue( 'abc' ) );
		$cases[] = array( $snak, 'invalid numeric value', false );

		$snak = new PropertyValueSnak( $p1, new UnDeserializableValue( 'abc', 'string', 'error' ) );
		$cases[] = array( $snak, 'bad value', false );

		$snak = new PropertyValueSnak( $p1, new UnknownValue( 'abc' ) );
		$cases[] = array( $snak, 'wrong value type', false );

		$snak = new PropertyValueSnak( $p3, new StringValue( 'abc' ) );
		$cases[] = array( $snak, 'bad property', false );

		return $cases;
	}

	/**
	 * @dataProvider provideValidate
	 */
	public function testValidate( Snak $snak, $description, $expectedValid = true ) {
		$validator = $this->getSnakValidator();

		$result = $validator->validate( $snak );

		$this->assertEquals( $expectedValid, $result->isValid(), $description );
	}

	public function provideValidateDataValue() {
		return array(
			array( new StringValue( '123' ), 'numeric', 'valid numeric value', true ),
			array( new StringValue( '123' ), 'alphabetic', 'invalid alphabetic value', false ),
			array( new StringValue( 'abc' ), 'alphabetic', 'valid alphabetic value', true ),
			array( new StringValue( 'abc' ), 'numeric', 'invalid numeric value', false ),
			array( new StringValue( '01234567890123456789' ), 'numeric', 'overly long numeric value', false ),
			array( new UnknownValue( 'abc' ), 'alphabetic', 'bad value type', false ),
			array( new UnDeserializableValue( 'abc', 'string', 'error' ), 'numeric', 'bad value', false ),
		);
	}

	/**
	 * @dataProvider provideValidateDataValue
	 */
	public function testValidateDataValue( DataValue $dataValue, $dataTypeId, $description, $expectedValid = true ) {
		$validator = $this->getSnakValidator();

		$result = $validator->validateDataValue( $dataValue, $dataTypeId );

		$this->assertEquals( $expectedValid, $result->isValid(), $description );
	}

}
