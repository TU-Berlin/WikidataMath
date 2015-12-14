<?php

namespace Wikibase\Test\Repo\Validators;

use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\Validators\EntityConstraintProvider;

/**
 * @covers Wikibase\Repo\Validators\EntityConstraintProvider
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseValidators
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityConstraintProviderTest extends \PHPUnit_Framework_TestCase {

	private function getEntityConstraintProvider() {
		$duplicateDetector = $this->getMockBuilder( 'Wikibase\LabelDescriptionDuplicateDetector' )
			->disableOriginalConstructor()
			->getMock();

		$siteLinkConflictLookup = $this->getMock( 'Wikibase\Repo\Store\SiteLinkConflictLookup' );

		return new EntityConstraintProvider( $duplicateDetector, $siteLinkConflictLookup );
	}

	public function testGetUpdateValidators() {
		$provider = $this->getEntityConstraintProvider();
		$validators = $provider->getUpdateValidators( Item::ENTITY_TYPE );
		$this->assertValidators( $validators );
	}

	public function testGetCreationValidators() {
		$provider = $this->getEntityConstraintProvider();
		$validators = $provider->getCreationValidators( Item::ENTITY_TYPE );
		$this->assertValidators( $validators );

		// Creation validators must be a superset of update validators
		$updateValidators = $provider->getUpdateValidators( Item::ENTITY_TYPE );
		$this->assertContainsAll( $updateValidators, $validators );
	}

	private function assertValidators( $validators ) {
		$this->assertInternalType( 'array', $validators );

		foreach ( $validators as $validator ) {
			$this->assertInstanceOf( 'Wikibase\Repo\Validators\EntityValidator', $validator );
		}
	}

	private function assertContainsAll( $expected, $array ) {
		foreach ( $expected as $obj ) {
			$this->assertContains( $obj, $array, '', false, false );
		}
	}

}
