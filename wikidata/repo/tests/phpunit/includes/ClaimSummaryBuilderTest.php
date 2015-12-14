<?php

namespace Wikibase\Test;

use DataValues\StringValue;
use Diff\Comparer\ComparableComparer;
use Diff\Differ\OrderedListDiffer;
use Wikibase\ClaimSummaryBuilder;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Diff\ClaimDiffer;

/**
 * @covers Wikibase\ClaimSummaryBuilder
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ClaimSummaryBuilder
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Daniel Kinzler
 */
class ClaimSummaryBuilderTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return Snak[]
	 */
	protected function snakProvider() {
		$snaks = array();

		$snaks[] = new PropertyNoValueSnak( 42 );
		$snaks[] = new PropertySomeValueSnak( 9001 );
		$snaks[] = new PropertyValueSnak( 7201010, new StringValue( 'o_O' ) );

		return $snaks;
	}

	/**
	 * @return Statement[]
	 */
	protected function statementProvider() {
		$statements = array();

		$mainSnak = new PropertyValueSnak( 112358, new StringValue( "don't panic" ) );
		$statement = new Statement( $mainSnak );
		$statements[] = $statement;

		foreach ( $this->snakProvider() as $snak ) {
			$statement = clone $statement;
			$snaks = new SnakList( array( $snak ) );
			$statement->getReferences()->addReference( new Reference( $snaks ) );
			$statements[] = $statement;
		}

		$statement = clone $statement;
		$snaks = new SnakList( $this->snakProvider() );
		$statement->getReferences()->addReference( new Reference( $snaks ) );
		$statements[] = $statement;

		/**
		 * @var Statement[] $statements
		 */

		$i = 0;
		foreach ( $statements as &$statement ) {
			$i++;
			$guid = "Q{$i}\$7{$i}d";

			$statement->setGuid( $guid );
			$statement->setRank( Statement::RANK_NORMAL );
		}

		return $statements;
	}

	public function buildUpdateClaimSummaryProvider() {
		$arguments = array();

		foreach ( $this->statementProvider() as $statement ) {
			$testCaseArgs = array();

			//change mainsnak
			$modifiedStatement = clone $statement;
			$modifiedStatement->setMainSnak(
				new PropertyValueSnak( 112358, new StringValue( "let's panic!!!" ) )
			);
			$testCaseArgs[] = $statement;
			$testCaseArgs[] = $modifiedStatement;
			$testCaseArgs[] = 'update';
			$arguments[] = $testCaseArgs;

			//change qualifiers
			$modifiedStatement = clone $statement;
			$modifiedStatement->setQualifiers( new SnakList( $this->snakProvider() ) );
			$testCaseArgs[] = $statement;
			$testCaseArgs[] = $modifiedStatement;
			$testCaseArgs[] = 'update-qualifiers';
			$arguments[] = $testCaseArgs;

			//change rank
			$modifiedStatement = clone $statement;
			$modifiedStatement->setRank( Statement::RANK_PREFERRED );
			$testCaseArgs[] = $statement;
			$testCaseArgs[] = $modifiedStatement;
			$testCaseArgs[] = 'update-rank';
			$arguments[] = $testCaseArgs;

			//change mainsnak & qualifiers
			$modifiedStatement = clone $statement;
			$modifiedStatement->setMainSnak(
				new PropertyValueSnak( 112358, new StringValue( "let's panic!!!" ) )
			);
			$modifiedStatement->setQualifiers( new SnakList( $this->snakProvider() ) );
			$testCaseArgs[] = $statement;
			$testCaseArgs[] = $modifiedStatement;
			$testCaseArgs[] = 'update-rank';
			$arguments[] = $testCaseArgs;
		}

		return $arguments;
	}

	public function testBuildCreateClaimSummary() {
		$claimSummaryBuilder = new ClaimSummaryBuilder(
			'wbsetclaim',
			new ClaimDiffer( new OrderedListDiffer( new ComparableComparer() ) )
		);

		$newStatements = $this->statementProvider();

		foreach ( $newStatements as $newStatement ) {
			$summary = $claimSummaryBuilder->buildClaimSummary( null, $newStatement );
			$this->assertInstanceOf( 'Wikibase\Summary', $summary, "this should return a Summary object" );
			$this->assertEquals( 'wbsetclaim', $summary->getModuleName() );
			$this->assertEquals( 'create', $summary->getActionName() );
		}
	}

	/**
	 * @dataProvider buildUpdateClaimSummaryProvider
	 */
	public function testBuildUpdateClaimSummary(
		Statement $originalStatement,
		Statement $modifiedStatement,
		$action
	) {
		$claimSummaryBuilder = new ClaimSummaryBuilder(
			'wbsetclaim',
			new ClaimDiffer( new OrderedListDiffer( new ComparableComparer() ) )
		);

		$summary = $claimSummaryBuilder->buildClaimSummary( $originalStatement, $modifiedStatement );
		$this->assertInstanceOf( 'Wikibase\Summary', $summary, "this should return a Summary object" );
		$this->assertEquals( 'wbsetclaim', $summary->getModuleName() );
		$this->assertEquals( $action, $summary->getActionName() );
	}

}
