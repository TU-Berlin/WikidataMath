<?php

namespace Wikibase\Repo\Api;

use ApiBase;
use ApiResult;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module to query available badge items.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class AvailableBadges extends ApiBase {

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.5
	 */
	public function execute() {
		$badgeItems = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'badgeItems' );
		$idStrings = array_keys( $badgeItems );
		ApiResult::setIndexedTagName( $idStrings, 'badge' );
		$this->getResult()->addValue(
			null,
			'badges',
			$idStrings
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wbavailablebadges' =>
				'apihelp-wbavailablebadges-example-1',
		);
	}

}
