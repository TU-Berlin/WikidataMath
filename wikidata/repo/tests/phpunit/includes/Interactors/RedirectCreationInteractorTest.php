<?php

namespace Wikibase\Test\Interactors;

use FauxRequest;
use PHPUnit_Framework_MockObject_Matcher_InvokedRecorder;
use RequestContext;
use Status;
use User;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Repo\Hooks\EditFilterHookRunner;
use Wikibase\Repo\Interactors\RedirectCreationException;
use Wikibase\Repo\Interactors\RedirectCreationInteractor;
use Wikibase\Repo\Store\EntityPermissionChecker;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Test\MockRepository;

/**
 * @covers Wikibase\Repo\Interactors\RedirectCreationInteractor
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseInteractor
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class RedirectCreationInteractorTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var MockRepository|null
	 */
	private $mockRepository = null;

	protected function setUp() {
		parent::setUp();

		$this->mockRepository = new MockRepository();

		// empty item
		$item = new Item( new ItemId( 'Q11' ) );
		$this->mockRepository->putEntity( $item );

		// non-empty item
		$item->setLabel( 'en', 'Foo' );
		$item->setId( new ItemId( 'Q12' ) );
		$this->mockRepository->putEntity( $item );

		// a property
		$prop = Property::newFromType( 'string' );
		$prop->setId( new PropertyId( 'P11' ) );
		$this->mockRepository->putEntity( $prop );

		// another property
		$prop->setId( new PropertyId( 'P12' ) );
		$this->mockRepository->putEntity( $prop );

		// redirect
		$redirect = new EntityRedirect( new ItemId( 'Q22' ), new ItemId( 'Q12' ) );
		$this->mockRepository->putRedirect( $redirect );
	}

	/**
	 * @return EntityPermissionChecker
	 */
	private function getPermissionCheckers() {
		$permissionChecker = $this->getMock( 'Wikibase\Repo\Store\EntityPermissionChecker' );

		$permissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntityId' )
			->will( $this->returnCallback( function( User $user, $permission, EntityId $id ) {
				$userWithoutPermissionName = 'UserWithoutPermission-' . $permission;

				if ( $user->getName() === $userWithoutPermissionName ) {
					return Status::newFatal( 'permissiondenied' );
				} else {
					return Status::newGood();
				}
			} ) );

		return $permissionChecker;
	}

	/**
	 * @param PHPUnit_Framework_MockObject_Matcher_InvokedRecorder|null $invokeCount
	 * @param Status|null $hookReturn
	 *
	 * @return EditFilterHookRunner
	 */
	public function getMockEditFilterHookRunner(
		PHPUnit_Framework_MockObject_Matcher_InvokedRecorder $invokeCount = null,
		Status $hookReturn = null
	) {
		if ( $invokeCount === null ) {
			$invokeCount = $this->any();
		}
		if ( $hookReturn === null ) {
			$hookReturn = Status::newGood();
		}
		$mock = $this->getMockBuilder( 'Wikibase\Repo\Hooks\EditFilterHookRunner' )
			->setMethods( array( 'run' ) )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $invokeCount )
			->method( 'run' )
			->will( $this->returnValue( $hookReturn ) );
		return $mock;
	}

	/**
	 * @param PHPUnit_Framework_MockObject_Matcher_InvokedRecorder|null $efHookCalls
	 * @param Status|null $efHookStatus
	 * @param User|null $user
	 *
	 * @return RedirectCreationInteractor
	 */
	private function newInteractor(
		PHPUnit_Framework_MockObject_Matcher_InvokedRecorder $efHookCalls = null,
		Status $efHookStatus = null,
		User $user = null
	) {
		if ( !$user ) {
			$user = $GLOBALS['wgUser'];
		}

		$summaryFormatter = WikibaseRepo::getDefaultInstance()->getSummaryFormatter();

		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		$interactor = new RedirectCreationInteractor(
			$this->mockRepository,
			$this->mockRepository,
			$this->getPermissionCheckers(),
			$summaryFormatter,
			$user,
			$this->getMockEditFilterHookRunner( $efHookCalls, $efHookStatus ),
			$this->mockRepository
		);

		return $interactor;
	}

	public function createRedirectProvider_success() {
		return array(
			'redirect empty entity' => array( new ItemId( 'Q11' ), new ItemId( 'Q12' ) ),
			'update redirect' => array( new ItemId( 'Q22' ), new ItemId( 'Q11' ) ),
		);
	}

	/**
	 * @dataProvider createRedirectProvider_success
	 */
	public function testCreateRedirect_success( EntityId $fromId, EntityId $toId ) {
		$interactor = $this->newInteractor( $this->once() );

		$interactor->createRedirect( $fromId, $toId, false );

		try {
			$this->mockRepository->getEntity( $fromId );
			$this->fail( 'getEntity( ' . $fromId->getSerialization() . ' ) did not throw an UnresolvedRedirectException' );
		} catch ( RevisionedUnresolvedRedirectException $ex ) {
			$this->assertEquals( $toId->getSerialization(), $ex->getRedirectTargetId()->getSerialization() );
		}
	}

	public function createRedirectProvider_failure() {
		return array(
			'source not found' => array( new ItemId( 'Q77' ), new ItemId( 'Q12' ), 'no-such-entity' ),
			'target not found' => array( new ItemId( 'Q11' ), new ItemId( 'Q77' ), 'no-such-entity' ),
			'target is a redirect' => array( new ItemId( 'Q11' ), new ItemId( 'Q22' ), 'target-is-redirect' ),
			'target is incompatible' => array( new ItemId( 'Q11' ), new PropertyId( 'P11' ), 'target-is-incompatible' ),

			'source not empty' => array( new ItemId( 'Q12' ), new ItemId( 'Q11' ), 'target-not-empty' ),
			'can\'t redirect' => array( new PropertyId( 'P11' ), new PropertyId( 'P12' ), 'cant-redirect' ),
			'can\'t redirect EditFilter' => array( new ItemId( 'Q11' ), new ItemId( 'Q12' ), 'cant-redirect', Status::newFatal( 'EF' ) ),
		);
	}

	/**
	 * @dataProvider createRedirectProvider_failure
	 */
	public function testCreateRedirect_failure( EntityId $fromId, EntityId $toId, $expectedCode, Status $efStatus = null ) {
		$interactor = $this->newInteractor( null, $efStatus );

		try {
			$interactor->createRedirect( $fromId, $toId, false );
			$this->fail( 'createRedirect not fail with error ' . $expectedCode . ' as expected!' );
		} catch ( RedirectCreationException $ex ) {
			$this->assertEquals( $expectedCode, $ex->getErrorCode() );
		}
	}

	public function permissionProvider() {
		return array(
			'edit' => array( 'edit' ),
			'item-redirect' => array( 'item-redirect' ),
		);
	}

	/**
	 * @dataProvider permissionProvider
	 */
	public function testSetRedirect_noPermission( $permission ) {
		$this->setExpectedException( 'Wikibase\Repo\Interactors\RedirectCreationException' );

		$user = User::newFromName( 'UserWithoutPermission-' . $permission );

		$interactor = $this->newInteractor( null, null, $user );
		$interactor->createRedirect( new ItemId( 'Q11' ), new ItemId( 'Q12' ), false );
	}

}
