<?php

namespace Wikibase\Repo;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Services\Statement\Grouper\NullStatementGrouper;
use Wikibase\DataModel\Services\Statement\Grouper\StatementGrouper;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidParsingException;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Thiemo Mättig
 */
class DispatchingEntityTypeStatementGrouper implements StatementGrouper {

	/**
	 * @var StatementGrouper[]
	 */
	private $statementGroupers = array();

	/**
	 * @var StatementGuidParser
	 */
	private $guidParser;

	/**
	 * @param StatementGrouper[] $statementGroupers An associative array, mapping entity types
	 *  (typically "item" and "property") to StatementGrouper objects.
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( array $statementGroupers ) {
		foreach ( $statementGroupers as $key => $grouper ) {
			if ( !is_string( $key ) || !( $grouper instanceof StatementGrouper ) ) {
				throw new InvalidArgumentException(
					'$statementGroupers must map strings to StatementGroupers'
				);
			}
			$this->statementGroupers[$key] = $grouper;
		}

		// TODO: Inject?
		$this->guidParser = new StatementGuidParser( new BasicEntityIdParser() );
	}

	/**
	 * @param StatementList $statements
	 *
	 * @return StatementList[]
	 */
	public function groupStatements( StatementList $statements ) {
		return $this->guessStatementGrouper( $statements )->groupStatements( $statements );
	}

	/**
	 * @param StatementList $statements
	 *
	 * @return StatementGrouper
	 */
	private function guessStatementGrouper( StatementList $statements ) {
		foreach ( $statements->toArray() as $statement ) {
			$entityType = $this->getEntityType( $statement );

			if ( array_key_exists( $entityType, $this->statementGroupers ) ) {
				return $this->statementGroupers[$entityType];
			}

			// FIXME: Check all statements and fail if they don't share the same entity type?
		}

		return new NullStatementGrouper();
	}

	/**
	 * @param Statement $statement
	 *
	 * @return string|null
	 */
	private function getEntityType( Statement $statement ) {
		try {
			$guid = $this->guidParser->parse( $statement->getGuid() );
		} catch ( StatementGuidParsingException $ex ) {
			// FIXME: Fail when there is a statement with no GUID?
			return null;
		}

		return $guid->getEntityId()->getEntityType();
	}

}
