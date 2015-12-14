<?php

namespace Wikibase\Test;

use LoadBalancer;
use MediaWikiTestCase;
use Title;
use WikiPage;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\Store\SQL\WikiPageEntityRedirectLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\Store\SQL\WikiPageEntityRedirectLookup
 *
 * @group Medium
 * @group Database
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @license GNU GPL v2+
 * @author Marius Hoch
 */
class WikiPageEntityRedirectLookupTest extends MediaWikiTestCase {

	/**
	 * @var ItemId|null
	 */
	private $itemId = null;

	/**
	 * @var ItemId[]
	 */
	private $redirectItemIds = array();

	public function setUp() {
		parent::setUp();

		if ( $this->itemId === null ) {
			$this->setUpEntities();
			$this->setUpNonEntityRedirect();
		}
	}

	private function setUpEntities() {
		global $wgUser;

		$entityStore = WikibaseRepo::getDefaultInstance()->getEntityStore();

		$item = new Item();
		$entityStore->saveEntity( $item, "WikiPageEntityRedirectLookupTest", $wgUser, EDIT_NEW );

		$redirectItem1 = new Item();
		$entityStore->assignFreshId( $redirectItem1 );
		$redirect = new EntityRedirect( $redirectItem1->getId(), $item->getId() );
		$entityStore->saveRedirect( $redirect, "WikiPageEntityRedirectLookupTest", $wgUser, EDIT_NEW );

		$redirectItem2 = new Item();
		$entityStore->assignFreshId( $redirectItem2 );
		$redirect = new EntityRedirect( $redirectItem2->getId(), $item->getId() );
		$entityStore->saveRedirect( $redirect, "WikiPageEntityRedirectLookupTest", $wgUser, EDIT_NEW );

		$this->itemId = $item->getId();
		$this->redirectItemIds = array( $redirectItem1->getId(), $redirectItem2->getId() );
	}

	/**
	 * Create a redirect from a non-Entity NS to the entity created, to make sure this doesn't
	 * interfere with WikiPageEntityRedirectLookup (especially getRedirectIds).
	 */
	private function setUpNonEntityRedirect() {
		global $wgUser;

		$entityTitleLookup = WikibaseRepo::getDefaultInstance()->getEntityTitleLookup();
		$title = $entityTitleLookup->getTitleForId( $this->itemId );

		$wikiText = '#REDIRECT [[' . $title->getFullText() . ']]';

		$page = WikiPage::factory( Title::newFromText( 'Help:WikiPageEntityMetaDataLookupTest' ) );
		$page->doEdit( $wikiText, 'test', 0, false, $wgUser );
	}

	public function testGetRedirectForEntityId() {
		$res = $this->getWikiPageEntityRedirectLookup()->getRedirectForEntityId( $this->redirectItemIds[0] );

		$this->assertEquals( $this->itemId, $res );
	}

	public function testGetRedirectForEntityId_entityDoesNotExist() {
		$this->setExpectedException( 'Wikibase\DataModel\Services\Lookup\EntityRedirectLookupException' );
		$this->getWikiPageEntityRedirectLookup()->getRedirectForEntityId( new ItemId( 'Q48758903' ) );
	}

	public function testGetRedirectForEntityId_entityNotARedirect() {
		$res = $this->getWikiPageEntityRedirectLookup()->getRedirectForEntityId( $this->itemId );

		$this->assertNull( $res );
	}

	public function testGetRedirectForEntityId_itemsInMainNamespace() {
		$row = array(
			'page_id' => 10,
			'rd_namespace' => NS_MAIN,
			'rd_title' => 'Q10'
		);

		$entityRedirectLookup = new WikiPageEntityRedirectLookup(
			$this->getMockEntityTitleLookup(),
			$this->getMockEntityIdLookup(),
			$this->getMockLoadBalancer( $row )
		);

		$redirect = $entityRedirectLookup->getRedirectForEntityId( new ItemId( 'Q2' ) );

		$this->assertEquals( new ItemId( 'Q10' ), $redirect );
	}

	private function getMockEntityTitleLookup() {
		$entityTitleLookup = $this->getMock( '\Wikibase\Lib\Store\EntityTitleLookup' );

		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				return Title::makeTitle( NS_MAIN, $id->getSerialization() );
			} ) );

		return $entityTitleLookup;
	}

	private function getMockEntityIdLookup() {
		$entityIdLookup = $this->getMock( 'Wikibase\Store\EntityIdLookup' );

		$entityIdLookup->expects( $this->any() )
			->method( 'getEntityIdForTitle' )
			->will( $this->returnCallback( function( Title $title ) {
				return new ItemId( $title->getText() );
			} ) );

		return $entityIdLookup;
	}

	private function getMockLoadBalancer( array $row ) {
		$db = $this->getMockDatabase( $row );

		$loadBalancer = $this->getMockBuilder( 'LoadBalancer' )
			->disableOriginalConstructor()
			->getMock();

		$loadBalancer->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $db ) );

		return $loadBalancer;
	}

	private function getMockDatabase( array $row ) {
		$db = $this->getMockBuilder( 'DatabaseMysql' )
			->disableOriginalConstructor()
			->getMock();

		$db->expects( $this->any() )
			->method( 'selectRow' )
			->will( $this->returnValue( (object)$row ) );

		return $db;
	}

	public function testGetRedirectIds() {
		$res = $this->getWikiPageEntityRedirectLookup()->getRedirectIds( $this->itemId );

		$this->assertEquals( $this->redirectItemIds, $res );
	}

	public function testGetRedirectIds_entityDoesNotExist() {
		$res = $this->getWikiPageEntityRedirectLookup()->getRedirectIds( new ItemId( 'Q48758903' ) );

		$this->assertSame( array(), $res );
	}

	private function getWikiPageEntityRedirectLookup( LoadBalancer $loadBalancer = null ) {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new WikiPageEntityRedirectLookup(
			$wikibaseRepo->getEntityTitleLookup(),
			$wikibaseRepo->getEntityIdLookup(),
			$loadBalancer ?: wfGetLB()
		);
	}

}
