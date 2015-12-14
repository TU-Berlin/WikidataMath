<?php

namespace Wikibase\Repo\Tests\UpdateRepo;

use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\EditEntityFactory;
use Wikibase\Repo\UpdateRepo\UpdateRepoOnMoveJob;
use Wikibase\Test\MockRepository;

/**
 * @covers Wikibase\Repo\UpdateRepo\UpdateRepoOnMoveJob
 * @covers Wikibase\Repo\UpdateRepo\UpdateRepoJob
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseIntegration
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class UpdateRepoOnMoveJobTest extends \MediaWikiTestCase {

	public function testGetSummary() {
		$job = new UpdateRepoOnMoveJob(
			Title::newMainPage(),
			array(
				'siteId' => 'SiteID',
				'oldTitle' => 'Test',
				'newTitle' => 'MoarTest',
			)
		);

		$summary = $job->getSummary();

		$this->assertEquals( 'clientsitelink-update', $summary->getMessageKey() );
		$this->assertEquals( 'SiteID', $summary->getLanguageCode() );
		$this->assertEquals(
			array( 'SiteID:Test', 'SiteID:MoarTest' ),
			$summary->getCommentArgs()
		);
	}

	private function getSiteStore( $normalizedPageName ) {
		$enwiki = $this->getMock( 'Site' );
		$enwiki->expects( $this->any() )
			->method( 'normalizePageName' )
			->will( $this->returnValue( $normalizedPageName ) );

		$siteStore = $this->getMock( 'SiteStore' );
		$siteStore->expects( $this->any() )
			->method( 'getSite' )
			->with( 'enwiki' )
			->will( $this->returnValue( $enwiki ) );

		return $siteStore;
	}

	private function getEntityTitleLookup( ItemId $itemId ) {
		$entityTitleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->with( $itemId )
			->will( $this->returnValue( Title::newFromText( $itemId->getSerialization() ) ) );

		return $entityTitleLookup;
	}

	private function getEntityPermissionChecker() {
		$entityPermissionChecker = $this->getMock( 'Wikibase\Repo\Store\EntityPermissionChecker' );
		$entityPermissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntity' )
			->will( $this->returnValue( Status::newGood() ) );

		return $entityPermissionChecker;
	}

	private function getSummaryFormatter() {
		$summaryFormatter = $this->getMockBuilder( 'Wikibase\SummaryFormatter' )
				->disableOriginalConstructor()->getMock();

		return $summaryFormatter;
	}

	private function getMockEditFitlerHookRunner() {
		$runner = $this->getMockBuilder( 'Wikibase\Repo\Hooks\EditFilterHookRunner' )
			->setMethods( array( 'run' ) )
			->disableOriginalConstructor()
			->getMock();
		$runner->expects( $this->any() )
			->method( 'run' )
			->will( $this->returnValue( Status::newGood() ) );
		return $runner;
	}

	public function runProvider() {
		return array(
			array( 'New page name', 'New page name', 'Old page name' ),
			// Client normalization gets applied
			array( 'Even newer page name', 'Even newer page name', 'Old page name' ),
			// The title in the repo item is not the one we expect, so don't change it
			array( 'Old page name', 'New page name', 'Something else' ),
		);
	}

	/**
	 * @dataProvider runProvider
	 * @param string $expected
	 * @param string $normalizedPageName
	 * @param string $oldTitle
	 */
	public function testRun( $expected, $normalizedPageName, $oldTitle ) {
		$user = User::newFromName( 'UpdateRepo' );

		// Needed as UpdateRepoOnMoveJob instantiates a User object
		$user->addToDatabase();

		$item = new Item();
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Old page name', array( new ItemId( 'Q42' ) ) );

		$mockRepository = new MockRepository();

		$mockRepository->saveEntity( $item, 'UpdateRepoOnDeleteJobTest', $user, EDIT_NEW );

		$params = array(
			'siteId' => 'enwiki',
			'entityId' => $item->getId()->getSerialization(),
			'oldTitle' => $oldTitle,
			'newTitle' => 'New page name',
			'user' => $user->getName()
		);

		$job = new UpdateRepoOnMoveJob( Title::newMainPage(), $params );
		$job->initServices(
			$mockRepository,
			$mockRepository,
			$this->getSummaryFormatter(),
			$this->getSiteStore( $normalizedPageName ),
			new EditEntityFactory(
				$this->getEntityTitleLookup( $item->getId() ),
				$mockRepository,
				$mockRepository,
				$this->getEntityPermissionChecker(),
				$this->getMockEditFitlerHookRunner()
			)
		);

		$job->run();

		/** @var Item $item */
		$item = $mockRepository->getEntity( $item->getId() );

		$this->assertSame(
			$expected,
			$item->getSiteLinkList()->getBySiteId( 'enwiki' )->getPageName(),
			'Title linked on enwiki after the job ran'
		);

		$this->assertEquals(
			$item->getSiteLinkList()->getBySiteId( 'enwiki' )->getBadges(),
			array( new ItemId( 'Q42' ) )
		);
	}

}
