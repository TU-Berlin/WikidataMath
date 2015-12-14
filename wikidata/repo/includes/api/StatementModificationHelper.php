<?php

namespace Wikibase\Repo\Api;

use ApiBase;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use UsageException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\ChangeOpValidationException;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Repo\SnakConstructionService;
use Wikibase\Summary;

/**
 * Helper class for modifying an entities statements.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Adam Shorland
 * @author Daniel Kinzler
 */
class StatementModificationHelper {

	/**
	 * @var SnakConstructionService
	 */
	private $snakConstructionService;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var StatementGuidValidator
	 */
	private $guidValidator;

	/**
	 * @var ApiErrorReporter
	 *
	 * @param SnakConstructionService $snakConstructionService
	 * @param EntityIdParser $entityIdParser
	 * @param StatementGuidValidator $guidValidator
	 * @param ApiErrorReporter $errorReporter
	 */
	public function __construct(
		SnakConstructionService $snakConstructionService,
		EntityIdParser $entityIdParser,
		StatementGuidValidator $guidValidator,
		ApiErrorReporter $errorReporter
	) {
		$this->snakConstructionService = $snakConstructionService;
		$this->entityIdParser = $entityIdParser;
		$this->guidValidator = $guidValidator;
		$this->errorReporter = $errorReporter;
	}

	/**
	 * @param string $guid
	 *
	 * @throws UsageException
	 * @return bool
	 */
	public function validateStatementGuid( $guid ) {
		return $this->guidValidator->validate( $guid );
	}

	/**
	 * @param string $guid
	 * @param EntityDocument $entity
	 *
	 * @throws UsageException
	 * @return Statement
	 */
	public function getStatementFromEntity( $guid, EntityDocument $entity ) {
		if ( !( $entity instanceof StatementListProvider ) ) {
			$this->errorReporter->dieError( 'Entity type does not support statements', 'no-such-claim' );
		}

		$statement = $entity->getStatements()->getFirstStatementWithGuid( $guid );

		if ( $statement === null ) {
			$this->errorReporter->dieError( 'Could not find the statement', 'no-such-claim' );
		}

		return $statement;
	}

	/**
	 * @param string[] $params Array with a 'snaktype' and an optional 'value' element.
	 * @param PropertyId $propertyId
	 *
	 * @throws UsageException
	 * @throws LogicException
	 * @return Snak
	 */
	public function getSnakInstance( array $params, PropertyId $propertyId ) {
		$valueData = null;

		if ( isset( $params['value'] ) ) {
			$valueData = json_decode( $params['value'], true );

			if ( $valueData === null ) {
				$this->errorReporter->dieError( 'Could not decode snak value', 'invalid-snak' );
			}
		}

		try {
			$snak = $this->snakConstructionService->newSnak( $propertyId, $params['snaktype'], $valueData );
			return $snak;
		} catch ( InvalidArgumentException $ex ) {
			$this->errorReporter->dieException( $ex, 'invalid-snak' );
		} catch ( OutOfBoundsException $ex ) {
			$this->errorReporter->dieException( $ex, 'invalid-snak' );
		} catch ( PropertyDataTypeLookupException $ex ) {
			$this->errorReporter->dieException( $ex, 'invalid-snak' );
		}

		throw new LogicException( 'ApiErrorReporter::dieException did not throw an exception' );
	}

	/**
	 * Parses an entity id string coming from the user
	 *
	 * @param string $entityIdParam
	 *
	 * @throws UsageException
	 * @return EntityId
	 * @todo this could go into an EntityModificationHelper or even in a ApiWikibaseHelper
	 */
	public function getEntityIdFromString( $entityIdParam ) {
		try {
			$entityId = $this->entityIdParser->parse( $entityIdParam );
		} catch ( EntityIdParsingException $ex ) {
			$this->errorReporter->dieException( $ex, 'invalid-entity-id' );
		}

		/** @var EntityId $entityId */
		return $entityId;
	}

	/**
	 * Creates a new Summary instance suitable for representing the action performed by this module.
	 *
	 * @param array $params
	 * @param ApiBase $module
	 *
	 * @return Summary
	 */
	public function createSummary( array $params, ApiBase $module ) {
		$summary = new Summary( $module->getModuleName() );
		if ( isset( $params['summary'] ) ) {
			$summary->setUserSummary( $params['summary'] );
		}
		return $summary;
	}

	/**
	 * Applies the given ChangeOp to the given Entity.
	 * Any ChangeOpException is converted into a UsageException with the code 'modification-failed'.
	 *
	 * @param ChangeOp $changeOp
	 * @param Entity $entity
	 * @param Summary $summary The summary object to update with information about the change.
	 */
	public function applyChangeOp( ChangeOp $changeOp, Entity $entity, Summary $summary = null ) {
		try {
			$result = $changeOp->validate( $entity );

			if ( !$result->isValid() ) {
				throw new ChangeOpValidationException( $result );
			}

			$changeOp->apply( $entity, $summary );
		} catch ( ChangeOpException $ex ) {
			$this->errorReporter->dieException( $ex, 'modification-failed' );
		}
	}

}
