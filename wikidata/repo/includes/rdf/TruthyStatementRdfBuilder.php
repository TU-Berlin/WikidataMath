<?php

namespace Wikibase\Rdf;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikimedia\Purtle\RdfWriter;

/**
 * "Truthy" RDF mapping for wikibase statements, directly mapping properties to "best" values
 * without modelling statements as identifiable objects. "Best" statements per property are
 * statements that have the best non-deprecated rank.
 *
 * This simple property to value mapping excludes deprecated and non-"best" statements, ranks,
 * qualifiers, and references. This allows for a much simpler, much easier to query RDF structure
 * that allows searching for values similar to what would have been shown in infoboxes via Lua.
 *
 * If more information is needed, use FullStatementRdfBuilder instead.
 *
 * @see FullStatementRdfBuilder
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class TruthyStatementRdfBuilder implements EntityRdfBuilder {

	/**
	 * @var RdfVocabulary
	 */
	private $vocabulary;

	/**
	 * @var RdfWriter
	 */
	private $writer;

	/**
	 * @var SnakRdfBuilder
	 */
	private $snakBuilder;

	/**
	 * @param RdfVocabulary $vocabulary
	 * @param RdfWriter $writer
	 * @param SnakRdfBuilder $snakBuilder
	 */
	public function __construct( RdfVocabulary $vocabulary, RdfWriter $writer, SnakRdfBuilder $snakBuilder ) {
		$this->vocabulary = $vocabulary;
		$this->writer = $writer;
		$this->snakBuilder = $snakBuilder;
	}

	/**
	 * Adds Statements to the RDF graph.
	 *
	 * @param EntityId $entityId
	 * @param StatementList $statementList
	 */
	public function addStatements( EntityId $entityId, StatementList $statementList ) {
		// FIXME: getBestStatementPerProperty() uis expensive, share the result with FullStatementRdfBuilder!
		foreach ( $statementList->getPropertyIds() as $propertyId ) {
			foreach ( $statementList->getByPropertyId( $propertyId )->getBestStatements() as $statement ) {
				$this->addMainSnak( $entityId, $statement );
			}
		}
	}

	/**
	 * Adds the given Statement's main Snak to the RDF graph.
	 *
	 * @todo share more of this code with FullStatementRdfBuilder
	 *
	 * @param EntityId $entityId
	 * @param Statement $statement
	 *
	 * @throws InvalidArgumentException
	 */
	private function addMainSnak( EntityId $entityId, Statement $statement ) {
		$snak = $statement->getMainSnak();

		$entityLName = $this->vocabulary->getEntityLName( $entityId );

		$this->writer->about( RdfVocabulary::NS_ENTITY, $entityLName );

		$this->snakBuilder->addSnak( $this->writer, $snak, RdfVocabulary::NSP_DIRECT_CLAIM );
	}

	/**
	 * Add truthy statements for the given entity to the RDF graph.
	 *
	 * @param EntityDocument $entity the entity to output.
	 */
	public function addEntity( EntityDocument $entity ) {
		$entityId = $entity->getId();

		if ( $entity instanceof StatementListProvider ) {
			$this->addStatements( $entityId, $entity->getStatements() );
		}
	}

}
