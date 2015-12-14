<?php

namespace Wikibase\Test;

use PHPUnit_Framework_TestCase;
use Wikibase\ChangeOp\ChangeOpFactoryProvider;
use Wikibase\ChangeOp\MergeChangeOpsFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @covers Wikibase\ChangeOp\MergeChangeOpsFactory
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class MergeChangeOpsFactoryTest extends PHPUnit_Framework_TestCase {

	/**
	 * @return MergeChangeOpsFactory
	 */
	protected function newChangeOpFactory() {
		$mockProvider = new ChangeOpTestMockProvider( $this );

		$toItemId = new ItemId( 'Q3' );

		$constraintProvider = $this->getMockBuilder( 'Wikibase\Repo\Validators\EntityConstraintProvider' )
			->disableOriginalConstructor()
			->getMock();

		$changeOpFactoryProvider = new ChangeOpFactoryProvider(
			$constraintProvider,
			$mockProvider->getMockGuidGenerator(),
			$mockProvider->getMockGuidValidator(),
			$mockProvider->getMockGuidParser( $toItemId ),
			$mockProvider->getMockSnakValidator(),
			$mockProvider->getMockTermValidatorFactory(),
			MockSiteStore::newFromTestSites()
		);

		return new MergeChangeOpsFactory(
			$constraintProvider,
			$changeOpFactoryProvider,
			MockSiteStore::newFromTestSites()
		);
	}

	public function testNewMergeOps() {
		$fromItem = new Item();
		$toItem = new Item();

		$op = $this->newChangeOpFactory()->newMergeOps( $fromItem, $toItem );
		$this->assertInstanceOf( 'Wikibase\ChangeOp\ChangeOpsMerge', $op );
	}

}
