<?php

namespace Wikibase\Test;

use Wikibase\Repo\WikibaseRepo;
use Wikibase\SqlStore;
use Wikibase\Store;

/**
 * @covers Wikibase\Store
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseStore
 * @group Database
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class StoreTest extends \MediaWikiTestCase {

	public function instanceProvider() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$instances = array(
			new SqlStore(
				$wikibaseRepo->getEntityContentDataCodec(),
				$wikibaseRepo->getEntityIdParser(),
				$this->getMock( 'Wikibase\Store\EntityIdLookup' ),
				$this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' )
			)
		);

		return array( $instances );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testRebuild( Store $store ) {
		$store->rebuild();
		$this->assertTrue( true );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testNewSiteLinkStore( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\Lib\Store\SiteLinkLookup', $store->newSiteLinkStore() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testNewTermCache( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\TermIndex', $store->getTermIndex() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testGetLabelConflictFinder( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\Lib\Store\LabelConflictFinder', $store->getLabelConflictFinder() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testNewIdGenerator( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\IdGenerator', $store->newIdGenerator() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testGetChangeLookup( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\Lib\Store\ChangeLookup', $store->getChangeLookup() );
	}

	/**
	 * @dataProvider instanceProvider
	 * @param Store $store
	 */
	public function testGetChangeStore( Store $store ) {
		$this->assertInstanceOf( '\Wikibase\Repo\Store\ChangeStore', $store->getChangeStore() );
	}

	/**
	 * @dataProvider instanceProvider
	 */
	public function testGetSiteLinkConflictLookup( Store $store ) {
		$this->assertInstanceOf(
			'\Wikibase\Repo\Store\SiteLinkConflictLookup',
			$store->getSiteLinkConflictLookup()
		);
	}

}
