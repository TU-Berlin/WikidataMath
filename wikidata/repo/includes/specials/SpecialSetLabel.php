<?php

namespace Wikibase\Repo\Specials;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Summary;

/**
 * Special page for setting the label of a Wikibase entity.
 *
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class SpecialSetLabel extends SpecialModifyTerm {

	/**
	 * @since 0.4
	 */
	public function __construct() {
		parent::__construct( 'SetLabel' );
	}

	/**
	 * @see SpecialSetEntity::getPostedValue()
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	protected function getPostedValue() {
		return $this->getRequest()->getVal( 'label' );
	}

	/**
	 * @see SpecialSetEntity::getValue()
	 *
	 * @since 0.4
	 *
	 * @param Fingerprint $fingerprint
	 * @param string $languageCode
	 *
	 * @return string
	 */
	protected function getValue( Fingerprint $fingerprint, $languageCode ) {
		return $fingerprint->hasLabel( $languageCode )
			? $fingerprint->getLabel( $languageCode )->getText()
			: '';
	}

	/**
	 * @see SpecialSetEntity::setValue()
	 *
	 * @since 0.4
	 *
	 * @param Entity $entity
	 * @param string $languageCode
	 * @param string $value
	 *
	 * @return Summary
	 */
	protected function setValue( $entity, $languageCode, $value ) {
		$value = $value === '' ? null : $value;
		$summary = new Summary( 'wbsetlabel' );

		if ( $value === null ) {
			$changeOp = $this->termChangeOpFactory->newRemoveLabelOp( $languageCode );
		} else {
			$changeOp = $this->termChangeOpFactory->newSetLabelOp( $languageCode, $value );
		}

		$this->applyChangeOp( $changeOp, $entity, $summary );

		return $summary;
	}

}
