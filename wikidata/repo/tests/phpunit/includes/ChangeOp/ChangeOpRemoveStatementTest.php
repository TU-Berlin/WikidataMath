<?php

namespace Wikibase\Test;

use DataValues\DataValue;
use DataValues\StringValue;
use InvalidArgumentException;
use Wikibase\ChangeOp\ChangeOpRemoveStatement;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;

/**
 * @covers Wikibase\ChangeOp\ChangeOpRemoveStatement
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ChangeOpRemoveStatementTest extends \PHPUnit_Framework_TestCase {

	public function invalidConstructorProvider() {
		$args = array();
		$args[] = array( array( 'foo' ) );
		$args[] = array( '' );
		return $args;
	}

	/**
	 * @dataProvider invalidConstructorProvider
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidConstruct( $guid ) {
		new ChangeOpRemoveStatement( $guid );
	}

	public function testGetClaimGuid() {
		$guid = 'foobar';
		$changeop = new ChangeOpRemoveStatement( $guid );
		$this->assertEquals( $guid, $changeop->getGuid() );
	}

	public function changeOpProvider() {
		$snak = new PropertyValueSnak( 2754236, new StringValue( 'test' ) );
		$args = array();

		$item = $this->newItemWithClaim( 'q345', $snak );
		$statements = $item->getStatements()->toArray();
		/** @var Statement $statement */
		$statement = reset( $statements );
		$guid = $statement->getGuid();
		$changeOp = new ChangeOpRemoveStatement( $guid );
		$expected = null;
		$args[] = array( $item, $changeOp, $expected );

		return $args;
	}

	/**
	 * @dataProvider changeOpProvider
	 */
	public function testApplyAddNewClaim( Item $item, ChangeOpRemoveStatement $changeOp, DataValue $expected = null ) {
		$changeOp->apply( $item );
		$this->assertNotEmpty( $changeOp->getGuid() );
		$statements = $item->getStatements();
		$this->assertEquals( $expected, $statements->getFirstStatementWithGuid( $changeOp->getGuid() ) );
	}

	private function newItemWithClaim( $itemIdString, $snak ) {
		$item = new Item( new ItemId( $itemIdString ) );

		$item->getStatements()->addNewStatement(
			$snak,
			null,
			null,
			$item->getId()->getSerialization() . '$D8404CDA-25E4-4334-AG93-A3290BCD9C0P'
		);

		return $item;
	}

}
