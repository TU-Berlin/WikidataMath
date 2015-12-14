<?php

namespace Wikibase\Test;

use DataTypes\DataType;
use DataTypes\DataTypeFactory;
use DataValues\DataValue;
use DataValues\NumberValue;
use DataValues\StringValue;
use OutOfBoundsException;
use PHPUnit_Framework_MockObject_MockBuilder;
use PHPUnit_Framework_TestCase;
use ValueValidators\Error;
use ValueValidators\Result;
use ValueValidators\ValueValidator;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\LabelDescriptionDuplicateDetector;
use Wikibase\Repo\DataTypeValidatorFactory;
use Wikibase\Repo\Store\SiteLinkConflictLookup;
use Wikibase\Repo\Validators\CompositeFingerprintValidator;
use Wikibase\Repo\Validators\CompositeValidator;
use Wikibase\Repo\Validators\DataValueValidator;
use Wikibase\Repo\Validators\LabelDescriptionUniquenessValidator;
use Wikibase\Repo\Validators\RegexValidator;
use Wikibase\Repo\Validators\SnakValidator;
use Wikibase\Repo\Validators\TermValidatorFactory;
use Wikibase\Repo\Validators\TypeValidator;

/**
 * A helper class for test cases that deal with claims.
 * Provides mock services frequently used with claims.
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ChangeOpTestMockProvider {

	/**
	 * @var PHPUnit_Framework_TestCase
	 */
	private $mockBuilderFactory;

	/**
	 * @param PHPUnit_Framework_TestCase $mockBuilderFactory
	 */
	public function __construct( PHPUnit_Framework_TestCase $mockBuilderFactory ) {
		$this->mockBuilderFactory = $mockBuilderFactory;
	}

	/**
	 * @see PHPUnit_Framework_TestCase::getMockBuilder
	 *
	 * @param string $class
	 *
	 * @return PHPUnit_Framework_MockObject_MockBuilder
	 */
	private function getMockBuilder( $class ) {
		return $this->mockBuilderFactory->getMockBuilder( $class );
	}

	/**
	 * @see PHPUnit_Framework_TestCase::getMock
	 *
	 * @param string $class
	 *
	 * @return object
	 */
	private function getMock( $class ) {
		return $this->mockBuilderFactory->getMock( $class );
	}

	/**
	 * Convenience method for creating Statements.
	 *
	 * @param string|PropertyId $propertyId
	 *
	 * @param string|int|float|DataValue|null $value The value of the new
	 *        claim's main snak. Null will result in a PropertyNoValueSnak.
	 *
	 * @return Statement A new statement with a main snak based on the parameters provided.
	 */
	public function makeStatement( $propertyId, $value = null ) {
		if ( is_string( $value ) ) {
			$value = new StringValue( $value );
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$value = new NumberValue( $value );
		}

		if ( is_string( $propertyId ) ) {
			$propertyId = new PropertyId( $propertyId );
		}

		if ( $value === null ) {
			$snak = new PropertyNoValueSnak( $propertyId );
		} else {
			$snak = new PropertyValueSnak( $propertyId, $value );
		}

		return new Statement( $snak );
	}

	/**
	 * Returns a normal GuidGenerator.
	 *
	 * @return GuidGenerator
	 */
	public function getGuidGenerator() {
		return new GuidGenerator();
	}

	/**
	 * Returns a mock StatementGuidValidator that accepts any GUID.
	 *
	 * @return StatementGuidValidator
	 */
	public function getMockGuidValidator() {
		$mock = $this->getMockBuilder( 'Wikibase\DataModel\Services\Statement\StatementGuidValidator' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'validate' )
			->will( PHPUnit_Framework_TestCase::returnValue( true ) );
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'validateFormat' )
			->will( PHPUnit_Framework_TestCase::returnValue( true ) );
		return $mock;
	}

	/**
	 * Returns a mock SnakValidator based on getMockPropertyDataTypeLookup()
	 * and getMockDataTypeFactory(), which will accept snaks containing a StringValue
	 * that is not "INVALID".
	 *
	 * @return SnakValidator
	 */
	public function getMockSnakValidator() {
		return new SnakValidator(
			$this->getMockPropertyDataTypeLookup(),
			$this->getMockDataTypeFactory(),
			$this->getMockDataTypeValidatorFactory()
		);
	}

	/**
	 * Returns a mock PropertyDataTypeLookup that will return the
	 * type id "string" for any property.
	 *
	 * @return PropertyDataTypeLookup
	 */
	public function getMockPropertyDataTypeLookup() {
		$mock = $this->getMock( '\Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup' );
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getDataTypeIdForProperty' )
			->will( PHPUnit_Framework_TestCase::returnValue( 'string' ) );

		return $mock;
	}

	/**
	 * Returns a mock MockDataTypeFactory that will return the same DataType for
	 * any type id.
	 *
	 * @return DataTypeFactory
	 */
	public function getMockDataTypeFactory() {
		$stringType = new DataType( 'string', 'string' );

		$types = array(
			'string' => $stringType
		);

		$mock = $this->getMockBuilder( 'DataTypes\DataTypeFactory' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getType' )
			->will( PHPUnit_Framework_TestCase::returnCallback( function( $id ) use ( $types ) {
				if ( !isset( $types[$id] ) ) {
					throw new OutOfBoundsException( "No such type: $id" );
				}

				return $types[$id];
			} ) );

		return $mock;
	}

	/**
	 * Returns a mock DataTypeValidatorFactory that returns validators which will accept any
	 * StringValue, unless the string is "INVALID".
	 *
	 * @return DataTypeValidatorFactory
	 */
	public function getMockDataTypeValidatorFactory() {
		// consider "INVALID" to be invalid
		$topValidator = new DataValueValidator(
			new CompositeValidator( array(
				new TypeValidator( 'string' ),
				new RegexValidator( '/INVALID/', true ),
			), true )
		);

		$validators = array( new TypeValidator( 'DataValues\DataValue' ), $topValidator );

		$mock = $this->getMock( 'Wikibase\Repo\DataTypeValidatorFactory' );
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getValidators' )
			->will( PHPUnit_Framework_TestCase::returnCallback( function( $id ) use ( $validators ) {
				return $validators;
			} ) );

		return $mock;
	}

	/**
	 * Returns a mock validator. The term and the language "INVALID" is considered to be
	 * invalid.
	 *
	 * @return ValueValidator
	 */
	public function getMockTermValidator() {
		$mock = $this->getMock( 'ValueValidators\ValueValidator' );
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'validate' )
			->will( PHPUnit_Framework_TestCase::returnCallback( function( $text ) {
				if ( $text === 'INVALID' ) {
					$error = Error::newError( 'Invalid', '', 'test-invalid' );
					return Result::newError( array( $error ) );
				} else {
					return Result::newSuccess();
				}
			} ) );

		return $mock;
	}

	/**
	 * Returns a mock StatementGuidParser that will return the same ClaimGuid for
	 * all input strings.
	 *
	 * @param EntityId $entityId
	 *
	 * @return StatementGuidParser
	 */
	public function getMockGuidParser( EntityId $entityId ) {
		$mockClaimGuid = $this->getMockBuilder( 'Wikibase\DataModel\Claim\ClaimGuid' )
			->disableOriginalConstructor()
			->getMock();
		$mockClaimGuid->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getSerialization' )
			->will( PHPUnit_Framework_TestCase::returnValue( 'theValidatorIsMockedSoMeh! :D' ) );
		$mockClaimGuid->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getEntityId' )
			->will( PHPUnit_Framework_TestCase::returnValue( $entityId ) );

		$mock = $this->getMockBuilder( 'Wikibase\DataModel\Services\Statement\StatementGuidParser' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'parse' )
			->will( PHPUnit_Framework_TestCase::returnValue( $mockClaimGuid ) );
		return $mock;
	}

	public function detectLabelConflictsForEntity( Entity $entity ) {
		foreach ( $entity->getFingerprint()->getLabels()->toTextArray() as $lang => $label ) {
			if ( $label === 'DUPE' ) {
				return Result::newError( array(
					Error::newError(
						'found conflicting terms',
						'label',
						'label-conflict',
						array(
							'label',
							$lang,
							$label,
							'P666'
						)
					)
				) );
			}
		}

		return Result::newSuccess();
	}

	public function detectLabelDescriptionConflictsForEntity( Entity $entity ) {
		foreach ( $entity->getFingerprint()->getLabels()->toTextArray() as $lang => $label ) {
			if ( !$entity->getFingerprint()->hasDescription( $lang ) ) {
				continue;
			}

			$description = $entity->getFingerprint()->getDescription( $lang )->getText();

			if ( $label === 'DUPE' && $description === 'DUPE' ) {
				return Result::newError( array(
					Error::newError(
						'found conflicting terms',
						'label',
						'label-with-description-conflict',
						array(
							'label',
							$lang,
							$label,
							'Q666'
						)
					)
				) );
			}
		}

		return Result::newSuccess();
	}

	public function detectLabelConflicts(
		$entityType,
		array $labels,
		array $aliases = null,
		EntityId $entityId = null
	) {
		if ( $entityId && $entityId->getSerialization() === 'P666' ) {
			// simulated conflicts always conflict with P666, so if these are
			// ignored as self-conflicts, we don't need to check any labels.
			$labels = array();
		}

		foreach ( $labels as $lang => $text ) {
			if ( $text === 'DUPE' ) {
				return Result::newError( array(
					Error::newError(
						'found conflicting terms',
						'label',
						'label-conflict',
						array(
							'label',
							$lang,
							$text,
							'P666'
						)
					)
				) );
			}
		}

		if ( $aliases === null ) {
			return Result::newSuccess();
		}

		foreach ( $aliases as $lang => $texts ) {
			if ( in_array( 'DUPE', $texts ) ) {
				return Result::newError( array(
					Error::newError(
						'found conflicting terms',
						'alias',
						'label-conflict',
						array(
							'alias',
							$lang,
							'DUPE',
							'P666'
						)
					)
				) );
			}
		}

		return Result::newSuccess();
	}

	public function detectLabelDescriptionConflicts(
		$entityType,
		array $labels,
		array $descriptions = null,
		EntityId $entityId = null
	) {
		if ( $entityId && $entityId->getSerialization() === 'P666' ) {
			// simulated conflicts always conflict with P666, so if these are
			// ignored as self-conflicts, we don't need to check any labels.
			$labels = array();
		}

		foreach ( $labels as $lang => $text ) {
			if ( $descriptions !== null
				&& ( !isset( $descriptions[$lang] ) || $descriptions[$lang] !== 'DUPE' )
			) {
				continue;
			}

			if ( $text === 'DUPE' ) {
				return Result::newError( array(
					Error::newError(
						'found conflicting terms',
						'label',
						'label-with-description-conflict',
						array(
							'label',
							$lang,
							$text,
							'P666'
						)
					)
				) );
			}
		}

		return Result::newSuccess();
	}

	/**
	 * Returns a duplicate detector that will, consider the string "DUPE" to be a duplicate,
	 * unless a specific $returnValue is given. The same value is returned for calls to
	 * detectLabelConflicts() and detectLabelDescriptionConflicts().
	 *
	 * @param null|Result|Error[] $returnValue
	 *
	 * @return LabelDescriptionDuplicateDetector
	 */
	public function getMockLabelDescriptionDuplicateDetector( $returnValue = null ) {
		if ( is_array( $returnValue ) ) {
			if ( empty( $returnValue ) ) {
				$returnValue = Result::newSuccess();
			} else {
				$returnValue = Result::newError( $returnValue );
			}
		}

		if ( $returnValue instanceof Result ) {
			$detectLabelConflicts = $detectLabelDescriptionConflicts = function() use ( $returnValue ) {
				return $returnValue;
			};
		} else {
			$detectLabelConflicts = array( $this, 'detectLabelConflicts' );
			$detectLabelDescriptionConflicts = array( $this, 'detectLabelDescriptionConflicts' );
		}

		$dupeDetector = $this->getMockBuilder( 'Wikibase\LabelDescriptionDuplicateDetector' )
			->disableOriginalConstructor()
			->getMock();

		$dupeDetector->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'detectLabelConflicts' )
			->will( PHPUnit_Framework_TestCase::returnCallback( $detectLabelConflicts ) );

		$dupeDetector->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'detectLabelDescriptionConflicts' )
			->will( PHPUnit_Framework_TestCase::returnCallback( $detectLabelDescriptionConflicts ) );

		return $dupeDetector;
	}

	/**
	 * @see SiteLinkLookup::getConflictsForItem
	 *
	 * The items in the return array are arrays with the following elements:
	 * - integer itemId
	 * - string siteId
	 * - string sitePage
	 *
	 * @param Item $item
	 *
	 * @return array
	 */
	public function getSiteLinkConflictsForItem( Item $item ) {
		$conflicts = array();

		foreach ( $item->getSiteLinks() as $link ) {
			$page = $link->getPageName();
			$site = $link->getSiteId();

			if ( $page === 'DUPE' ) {
				//NOTE: some tests may rely on these exact values!
				$conflicts[] = array(
					'itemId' => 666,
					'siteId' => $site,
					'sitePage' => $page
				);
			}
		}

		return $conflicts;
	}

	/**
	 * @param array $returnValue
	 *
	 * @return SiteLinkConflictLookup
	 */
	public function getMockSiteLinkConflictLookup( $returnValue = null ) {
		if ( is_array( $returnValue ) ) {
			$getConflictsForItem = function() use ( $returnValue ) {
				return $returnValue;
			};
		} else {
			$getConflictsForItem = array( $this, 'getSiteLinkConflictsForItem' );
		}

		$mock = $this->getMock( 'Wikibase\Repo\Store\SiteLinkConflictLookup' );
		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getConflictsForItem' )
			->will( PHPUnit_Framework_TestCase::returnCallback( $getConflictsForItem ) );
		return $mock;
	}

	/**
	 * @return GuidGenerator
	 */
	public function getMockGuidGenerator() {
		return new GuidGenerator();
	}

	/**
	 * Returns a mock fingerprint validator. If $entityType is Item::ENTITY_TYPE,
	 * the validator will detect an error for any fingerprint that contains the string "DUPE"
	 * for both the description and the label for a given language.
	 *
	 * For other entity types, the validator will consider any fingerprint valid.
	 *
	 * @see getMockLabelDescriptionDuplicateDetector()
	 *
	 * @param string $entityType
	 *
	 * @return LabelDescriptionUniquenessValidator|CompositeFingerprintValidator
	 */
	public function getMockFingerprintValidator( $entityType ) {
		switch ( $entityType ) {
			case Item::ENTITY_TYPE:
				return new LabelDescriptionUniquenessValidator( $this->getMockLabelDescriptionDuplicateDetector() );

			default:
				return new CompositeFingerprintValidator( array() );
		}
	}

	/**
	 * Returns a TermValidatorFactory that provides mock validators.
	 * The validators consider the string "INVALID" to be invalid, and "DUPE" to be duplicates.
	 *
	 * @see getMockTermValidator()
	 * @see getMockFingerprintValidator()
	 *
	 * @return TermValidatorFactory
	 */
	public function getMockTermValidatorFactory() {
		$mock = $this->getMockBuilder( 'Wikibase\Repo\Validators\TermValidatorFactory' )
			->disableOriginalConstructor()
			->getMock();

		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getFingerprintValidator' )
			->will( PHPUnit_Framework_TestCase::returnCallback(
				array( $this, 'getMockFingerprintValidator' )
			) );

		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getLanguageValidator' )
			->will( PHPUnit_Framework_TestCase::returnCallback(
				array( $this, 'getMockTermValidator' )
			) );

		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getLabelValidator' )
			->will( PHPUnit_Framework_TestCase::returnCallback(
				array( $this, 'getMockTermValidator' )
			) );

		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getDescriptionValidator' )
			->will( PHPUnit_Framework_TestCase::returnCallback(
				array( $this, 'getMockTermValidator' )
			) );

		$mock->expects( PHPUnit_Framework_TestCase::any() )
			->method( 'getAliasValidator' )
			->will( PHPUnit_Framework_TestCase::returnCallback(
				array( $this, 'getMockTermValidator' )
			) );

		return $mock;
	}

}
