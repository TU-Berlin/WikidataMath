<?php

namespace Wikibase\Repo\Diff;

use Comparable;
use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;

/**
 * Represents the difference between two Statement objects.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Thiemo Mättig
 */
// FIXME: Contains references and rank? It's a StatementDifference!
class ClaimDifference implements Comparable {

	/**
	 * @var DiffOpChange|null
	 */
	private $mainSnakChange;

	/**
	 * @var Diff|null
	 */
	private $qualifierChanges;

	/**
	 * @var Diff|null
	 */
	private $referenceChanges;

	/**
	 * @var DiffOpChange|null
	 */
	private $rankChange;

	/**
	 * @since 0.4
	 *
	 * @param DiffOpChange|null $mainSnakChange
	 * @param Diff|null $qualifierChanges
	 * @param Diff|null $referenceChanges
	 * @param DiffOpChange|null $rankChange
	 */
	public function __construct(
		DiffOpChange $mainSnakChange = null,
		Diff $qualifierChanges = null,
		Diff $referenceChanges = null,
		DiffOpChange $rankChange = null
	) {
		$this->mainSnakChange = $mainSnakChange;
		$this->qualifierChanges = $qualifierChanges;
		$this->referenceChanges = $referenceChanges;
		$this->rankChange = $rankChange;
	}

	/**
	 * Returns the set of reference changes.
	 *
	 * @since 0.4
	 *
	 * @return Diff
	 */
	public function getReferenceChanges() {
		return $this->referenceChanges ?: new Diff( array(), false );
	}

	/**
	 * Returns the main snak change.
	 *
	 * @since 0.4
	 *
	 * @return DiffOpChange|null
	 */
	public function getMainSnakChange() {
		return $this->mainSnakChange;
	}

	/**
	 * Returns the rank change.
	 *
	 * @since 0.4
	 *
	 * @return DiffOpChange|null
	 */
	public function getRankChange() {
		return $this->rankChange;
	}

	/**
	 * Returns the set of qualifier changes.
	 *
	 * @since 0.4
	 *
	 * @return Diff
	 */
	public function getQualifierChanges() {
		return $this->qualifierChanges ?: new Diff( array(), false );
	}

	/**
	 * @see Comparable::equals
	 *
	 * @since 0.1
	 *
	 * @param mixed $target
	 *
	 * @return bool
	 */
	public function equals( $target ) {
		if ( $target === $this ) {
			return true;
		}

		if ( !( $target instanceof self ) ) {
			return false;
		}

		return $this->mainSnakChange == $target->mainSnakChange
			&& $this->getQualifierChanges()->equals( $target->getQualifierChanges() )
			&& $this->getReferenceChanges()->equals( $target->getReferenceChanges() )
			&& $this->rankChange == $target->rankChange;
	}

	/**
	 * Checks whether the difference represented by this object is atomic, which means
	 * the Statement has only changed either its main snak, qualifiers, references or rank.
	 *
	 * @since 0.4
	 *
	 * @return bool
	 */
	public function isAtomic() {
		$aspects = 0;

		if ( $this->mainSnakChange !== null ) {
			$aspects++;
		}
		if ( !$this->getQualifierChanges()->isEmpty() ) {
			$aspects++;
		}
		if ( !$this->getReferenceChanges()->isEmpty() ) {
			$aspects++;
		}
		if ( $this->rankChange !== null ) {
			$aspects++;
		}

		return $aspects === 1;
	}

}
