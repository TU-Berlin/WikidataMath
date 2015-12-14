<?php

namespace Wikibase\Test;

use DataValues\DataValue;
use DataValues\NumberValue;
use DataValues\StringValue;
use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpMainSnak;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * @covers Wikibase\ChangeOp\ChangeOpMainSnak
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 * @group ChangeOpMainSnak
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ChangeOpMainSnakTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var ChangeOpTestMockProvider
	 */
	protected $mockProvider;

	/**
	 * @param string|null $name
	 * @param array $data
	 * @param string $dataName
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->mockProvider = new ChangeOpTestMockProvider( $this );
	}

	public function invalidArgumentProvider() {
		$validSnak = new PropertyValueSnak( 7201010, new StringValue( 'o_O' ) );

		$args = array();
		$args[] = array( 123, $validSnak );
		$args[] = array( false, $validSnak );

		return $args;
	}

	/**
	 * @dataProvider invalidArgumentProvider
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidConstruct( $guid, $snak ) {
		new ChangeOpMainSnak(
			$guid,
			$snak,
			$this->mockProvider->getGuidGenerator(),
			$this->mockProvider->getMockSnakValidator()
		);
	}

	/**
	 * @param string $guid
	 * @param Snak $snak
	 *
	 * @return ChangeOpMainSnak
	 */
	protected function newChangeOpMainSnak( $guid, Snak $snak ) {
		return new ChangeOpMainSnak(
			$guid,
			$snak,
			$this->mockProvider->getGuidGenerator(),
			$this->mockProvider->getMockSnakValidator()
		);
	}

	public function provideChangeOps() {
		$snak = $this->makeSnak( 'P5', 'test' );
		$args = array();

		// add a new claim
		$item = $this->makeNewItemWithClaim( 'Q123', $snak );
		$newSnak = $this->makeSnak( 'P8', 'newSnak' );
		$guid = '';
		$changeOp = $this->newChangeOpMainSnak( $guid, $newSnak );
		$expected = $newSnak->getDataValue();
		$args['add new claim'] = array( $item, $changeOp, $expected );

		// update an existing claim with a new main snak value
		$item = $this->makeNewItemWithClaim( 'Q234', $snak );
		$newSnak = $this->makeSnak( 'P5', 'changedSnak' );
		$statements = $item->getStatements()->toArray();

		$guid = $statements[0]->getGuid();
		$changeOp = $this->newChangeOpMainSnak( $guid, $newSnak );
		$expected = $newSnak->getDataValue();
		$args['update claim by guid'] = array( $item, $changeOp, $expected );

		return $args;
	}

	/**
	 * @dataProvider provideChangeOps
	 */
	public function testApply( Item $item, ChangeOpMainSnak $changeOp, DataValue $expected = null ) {
		$changeOp->apply( $item );
		$this->assertNotEmpty( $changeOp->getStatementGuid() );
		$statements = $item->getStatements();
		$statement = $statements->getFirstStatementWithGuid( $changeOp->getStatementGuid() );
		if ( $expected === null ) {
			$this->assertNull( $statement );
		} else {
			$this->assertEquals( $expected, $statement->getMainSnak()->getDataValue() );
		}
	}

	public function provideInvalidApply() {
		$snak = $this->makeSnak( 'P11', 'test' );
		$item = $this->makeNewItemWithClaim( 'Q777', $snak );
		$statements = $item->getStatements()->toArray();
		/** @var Statement $statement */
		$statement = reset( $statements );
		$guid = $statement->getGuid();

		// apply change to the wrong item
		$wrongItem = new Item( new ItemId( 'Q888' ) );
		$newSnak = $this->makeSnak( 'P12', 'newww' );
		$args['wrong entity'] = array( $wrongItem, $this->newChangeOpMainSnak( $guid, $newSnak ) );

		// apply change to an unknown claim
		$wrongClaimId = $item->getId()->getSerialization() . '$DEADBEEF-DEAD-BEEF-DEAD-BEEFDEADBEEF';
		$args['unknown claim'] = array( $item, $this->newChangeOpMainSnak( $wrongClaimId, $newSnak ) );

		// update an existing claim with wrong main snak property
		$newSnak = $this->makeSnak( 'P13', 'changedSnak' );
		$statements = $item->getStatements()->toArray();
		/** @var Statement $statement */
		$statement = reset( $statements );

		$guid = $statement->getGuid();
		$changeOp = $this->newChangeOpMainSnak( $guid, $newSnak );
		$args['wrong main snak property'] = array( $item, $changeOp );

		// apply invalid main snak
		$badSnak = $this->makeSnak( 'P12', new NumberValue( 5 ) );
		$args['bad value type'] = array( $wrongItem, $this->newChangeOpMainSnak( $guid, $badSnak ) );

		// apply invalid main snak
		// NOTE: the mock validator considers "INVALID" to be invalid.
		$badSnak = $this->makeSnak( 'P12', 'INVALID' );
		$args['invalid value'] = array( $wrongItem, $this->newChangeOpMainSnak( $guid, $badSnak ) );

		return $args;
	}

	/**
	 * @dataProvider provideInvalidApply
	 */
	public function testInvalidApply( Entity $item, ChangeOp $changeOp ) {
		$this->setExpectedException( 'Wikibase\ChangeOp\ChangeOpException' );

		$changeOp->apply( $item );
	}

	private function makeNewItemWithClaim( $itemIdString, $snak ) {
		$item = new Item( new ItemId( $itemIdString ) );

		$item->getStatements()->addNewStatement(
			$snak,
			null,
			null,
			$this->mockProvider->getGuidGenerator()->newGuid( $item->getId() )
		);

		return $item;
	}

	private function makeSnak( $propertyId, $value ) {
		if ( is_string( $propertyId ) ) {
			$propertyId = new PropertyId( $propertyId );
		}

		if ( is_string( $value ) ) {
			$value = new StringValue( $value );
		}

		if ( $value === null ) {
			return new PropertyNoValueSnak( $propertyId );
		} else {
			return new PropertyValueSnak( $propertyId, $value );
		}
	}

	public function validateProvider() {
		$p11 = new PropertyId( 'P11' );
		$q17 = new ItemId( 'Q17' );

		//NOTE: the mock validator will consider the string "INVALID" to be invalid.
		$badSnak = new PropertyValueSnak( $p11, new StringValue( 'INVALID' ) );
		$brokenSnak = new PropertyValueSnak( $p11, new NumberValue( 23 ) );

		$guidGenerator = new GuidGenerator();

		$cases = array();

		$guid = $guidGenerator->newGuid( $q17 );
		$cases['bad snak value'] = array( $q17, $guid, $badSnak );

		$guid = $guidGenerator->newGuid( $q17 );
		$cases['broken snak'] = array( $q17, $guid, $brokenSnak );

		return $cases;
	}

	/**
	 * @dataProvider validateProvider
	 */
	public function testValidate( EntityId $entityId, $guid, Snak $snak ) {
		$changeOpMainSnak = new ChangeOpMainSnak(
			$guid,
			$snak,
			new GuidGenerator(),
			$this->mockProvider->getMockSnakValidator()
		);

		$entity = new Item( $entityId );

		$result = $changeOpMainSnak->validate( $entity );
		$this->assertFalse( $result->isValid(), 'isValid()' );
	}

}
