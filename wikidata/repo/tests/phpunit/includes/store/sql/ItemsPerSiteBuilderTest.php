<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Entity\NullEntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\SiteLinkTable;
use Wikibase\Repo\Store\EntityIdPager;
use Wikibase\Repo\Store\SQL\ItemsPerSiteBuilder;

/**
 * @covers Wikibase\Repo\Store\SQL\ItemsPerSiteBuilder
 *
 * @license GPL 2+
 *
 * @group Wikibase
 * @group WikibaseStore
 * @group WikibaseRepo
 *
 * @author Marius Hoch < hoo@online.de >
 */
class ItemsPerSiteBuilderTest extends \MediaWikiTestCase {

	const BATCH_SIZE = 5;

	/**
	 * @return ItemId
	 */
	private function getTestItemId() {
		return new ItemId( 'Q1234' );
	}

	/**
	 * @return Item
	 */
	private function getTestItem() {
		return new Item( $this->getTestItemId() );
	}

	/**
	 * @return SiteLinkTable
	 */
	private function getSiteLinkTable() {
		$mock = $this->getMockBuilder( 'Wikibase\Lib\Store\SiteLinkTable' )
			->disableOriginalConstructor()
			->getMock();

		$item = $this->getTestItem();
		$mock->expects( $this->exactly( 10 ) )
			->method( 'saveLinksOfItem' )
			->will( $this->returnValue( true ) )
			->with( $this->equalTo( $item ) );

		return $mock;
	}

	/**
	 * @return EntityLookup
	 */
	private function getEntityLookup() {
		$mock = $this->getMockBuilder( 'Wikibase\DataModel\Services\Lookup\EntityLookup' )
			->disableOriginalConstructor()
			->getMock();

		$item = $this->getTestItem();
		$mock->expects( $this->exactly( 10 ) )
			->method( 'getEntity' )
			->will( $this->returnValue( $item ) )
			->with( $this->equalTo( $this->getTestItemId() ) );

		return $mock;
	}

	/**
	 * @return ItemsPerSiteBuilder
	 */
	private function getItemsPerSiteBuilder() {
		return new ItemsPerSiteBuilder(
			$this->getSiteLinkTable(),
			$this->getEntityLookup(),
			new NullEntityPrefetcher()
		);
	}

	/**
	 * @return EntityIdPager
	 */
	private function getEntityIdPager() {
		$mock = $this->getMock( 'Wikibase\Repo\Store\EntityIdPager' );

		$itemIds = array(
			$this->getTestItemId(),
			$this->getTestItemId(),
			$this->getTestItemId(),
			$this->getTestItemId(),
			$this->getTestItemId()
		);

		$mock->expects( $this->at( 0 ) )
			->method( 'fetchIds' )
			->will( $this->returnValue( $itemIds ) )
			->with( $this->equalTo( self::BATCH_SIZE ) );

		$mock->expects( $this->at( 1 ) )
			->method( 'fetchIds' )
			->will( $this->returnValue( $itemIds ) )
			->with( $this->equalTo( self::BATCH_SIZE ) );

		$mock->expects( $this->at( 2 ) )
			->method( 'fetchIds' )
			->will( $this->returnValue( array() ) )
			->with( $this->equalTo( self::BATCH_SIZE ) );

		return $mock;
	}

	public function testRebuild() {
		$itemsPerSiteBuilder = $this->getItemsPerSiteBuilder();
		$itemsPerSiteBuilder->setBatchSize( self::BATCH_SIZE );

		$entityIdPager = $this->getEntityIdPager();
		$itemsPerSiteBuilder->rebuild( $entityIdPager );

		// The various mocks already verify they get called correctly,
		// so no need for assertions
		$this->assertTrue( true );
	}

}
