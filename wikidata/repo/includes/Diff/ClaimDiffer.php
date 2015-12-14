<?php

namespace Wikibase\Repo\Diff;

use Diff\Differ\Differ;
use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;
use Wikibase\DataModel\Statement\Statement;

/**
 * Class for generating a ClaimDifference given two statements.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Adam Shorland
 * @author Thiemo Mättig
 */
class ClaimDiffer {

	private $listDiffer;

	public function __construct( Differ $listDiffer ) {
		$this->listDiffer = $listDiffer;
	}

	/**
	 * Calculates diff of two Statements and stores the difference in a ClaimDifference
	 *
	 * @since 0.4
	 *
	 * @param Statement|null $oldStatement
	 * @param Statement|null $newStatement
	 *
	 * @return ClaimDifference
	 */
	public function diffClaims( Statement $oldStatement = null, Statement $newStatement = null ) {
		if ( $oldStatement === $newStatement ) {
			return new ClaimDifference();
		}

		return new ClaimDifference(
			$this->diffMainSnaks( $oldStatement, $newStatement ),
			$this->diffQualifiers( $oldStatement, $newStatement ),
			$this->diffReferences( $oldStatement, $newStatement ),
			$this->diffRanks( $oldStatement, $newStatement )
		);
	}

	/**
	 * @param Statement|null $oldStatement
	 * @param Statement|null $newStatement
	 *
	 * @return DiffOpChange|null
	 */
	private function diffMainSnaks(
		Statement $oldStatement = null,
		Statement $newStatement = null
	) {
		$oldSnak = $oldStatement === null ? null : $oldStatement->getMainSnak();
		$newSnak = $newStatement === null ? null : $newStatement->getMainSnak();

		if ( $oldSnak !== null && $oldSnak->equals( $newSnak ) ) {
			return null;
		}

		return new DiffOpChange( $oldSnak, $newSnak );
	}

	/**
	 * @param Statement|null $oldStatement
	 * @param Statement|null $newStatement
	 *
	 * @return Diff
	 */
	private function diffQualifiers(
		Statement $oldStatement = null,
		Statement $newStatement = null
	) {
		if ( $oldStatement !== null
			&& $newStatement !== null
			&& $oldStatement->getQualifiers()->equals( $newStatement->getQualifiers() )
		) {
			return null;
		}

		$oldQualifiers = $oldStatement === null
			? array()
			: iterator_to_array( $oldStatement->getQualifiers() );
		$newQualifiers = $newStatement === null
			? array()
			: iterator_to_array( $newStatement->getQualifiers() );

		if ( $oldQualifiers === $newQualifiers ) {
			return null;
		}

		return new Diff( $this->listDiffer->doDiff( $oldQualifiers, $newQualifiers ), false );
	}

	/**
	 * @param Statement|null $oldStatement
	 * @param Statement|null $newStatement
	 *
	 * @return DiffOpChange|null
	 */
	private function diffRanks( Statement $oldStatement = null, Statement $newStatement = null ) {
		$oldRank = $oldStatement === null ? null : $oldStatement->getRank();
		$newRank = $newStatement === null ? null : $newStatement->getRank();

		if ( $oldRank === $newRank ) {
			return null;
		}

		return new DiffOpChange( $oldRank, $newRank );
	}

	/**
	 * @param Statement|null $oldStatement
	 * @param Statement|null $newStatement
	 *
	 * @return Diff
	 */
	private function diffReferences(
		Statement $oldStatement = null,
		Statement $newStatement = null
	) {
		if ( $oldStatement !== null
			&& $newStatement !== null
			&& $oldStatement->getReferences()->equals( $newStatement->getReferences() )
		) {
			return null;
		}

		$oldReferences = $oldStatement === null
			? array()
			: iterator_to_array( $oldStatement->getReferences() );
		$newReferences = $newStatement === null
			? array()
			: iterator_to_array( $newStatement->getReferences() );

		if ( $oldReferences === $newReferences ) {
			return null;
		}

		return new Diff( $this->listDiffer->doDiff( $oldReferences, $newReferences ), false );
	}

}
