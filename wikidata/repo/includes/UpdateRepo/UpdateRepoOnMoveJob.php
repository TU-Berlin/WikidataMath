<?php

namespace Wikibase\Repo\UpdateRepo;

use OutOfBoundsException;
use SiteStore;
use Title;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\SiteLink;
use Wikibase\EditEntityFactory;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * Job for updating the repo after a page on the client has been moved.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class UpdateRepoOnMoveJob extends UpdateRepoJob {

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	/**
	 * @var string|bool|null
	 */
	private $normalizedPageName = null;

	/**
	 * Constructs a UpdateRepoOnMoveJob propagating a page move to the repo
	 *
	 * @note: This is for use by Job::factory, don't call it directly;
	 *           use newFrom*() instead.
	 *
	 * @note: the constructor's signature is dictated by Job::factory, so we'll have to
	 *           live with it even though it's rather ugly for our use case.
	 *
	 * @see Job::factory
	 * @see UpdateRepoJob::__construct
	 *
	 * @param Title $title
	 * @param array|bool $params
	 */
	public function __construct( Title $title, $params = false ) {
		parent::__construct( 'UpdateRepoOnMove', $title, $params );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$this->initServices(
			$wikibaseRepo->getEntityRevisionLookup( 'uncached' ),
			$wikibaseRepo->getEntityStore(),
			$wikibaseRepo->getSummaryFormatter(),
			$wikibaseRepo->getSiteStore(),
			$wikibaseRepo->newEditEntityFactory()
		);
	}

	public function initServices(
		EntityRevisionLookup $entityRevisionLookup,
		EntityStore $entityStore,
		SummaryFormatter $summaryFormatter,
		SiteStore $siteStore,
		EditEntityFactory $editEntityFactory
	) {
		$this->initRepoJobServices(
			$entityRevisionLookup,
			$entityStore,
			$summaryFormatter,
			$editEntityFactory
		);
		$this->siteStore = $siteStore;
	}

	/**
	 * Get a SiteLink for a specific item and site
	 *
	 * @param Item $item
	 * @param string $globalId
	 *
	 * @return SiteLink|null
	 */
	private function getSiteLink( $item, $globalId ) {
		try {
			return $item->getSiteLinkList()->getBySiteId( $globalId );
		} catch ( OutOfBoundsException $e ) {
			return null;
		}
	}

	/**
	 * Get a Summary object for the edit
	 *
	 * @return Summary
	 */
	public function getSummary() {
		$params = $this->getParams();
		$siteId = $params['siteId'];
		$oldPage = $params['oldTitle'];
		$newPage = $params['newTitle'];

		return new Summary(
			'clientsitelink',
			'update',
			$siteId,
			array(
				$siteId . ":$oldPage",
				$siteId . ":$newPage",
			)
		);
	}

	/**
	 * @return string|bool False in case the normalization failed
	 */
	private function getNormalizedPageName() {
		if ( $this->normalizedPageName === null ) {
			$params = $this->getParams();
			$newPage = $params['newTitle'];
			$siteId = $params['siteId'];

			$site = $this->siteStore->getSite( $siteId );
			$this->normalizedPageName = $site->normalizePageName( $newPage );

			if ( $this->normalizedPageName === false ) {
				wfDebugLog( 'UpdateRepo', "OnMove: Normalizing the page name $newPage on $siteId failed" );
			}

		}

		return $this->normalizedPageName;
	}

	/**
	 * Whether the propagated update is valid (and thus should be applied)
	 *
	 * @param Item $item
	 *
	 * @return bool
	 */
	protected function verifyValid( Item $item ) {
		$params = $this->getParams();
		$siteId = $params['siteId'];
		$oldPage = $params['oldTitle'];

		$oldSiteLink = $this->getSiteLink( $item, $siteId );
		if ( !$oldSiteLink || $oldSiteLink->getPageName() !== $oldPage ) {
			// Probably something changed since the job has been inserted
			wfDebugLog( 'UpdateRepo', "OnMove: The site link to " . $siteId . " is no longer $oldPage" );
			return false;
		}

		// Normalize the name, just in case the page has been updated in the mean time
		if ( $this->getNormalizedPageName() === false ) {
			return false;
		}

		return true;
	}

	/**
	 * Apply the changes needed to the given Item.
	 *
	 * @param Item $item
	 *
	 * @return bool
	 */
	protected function applyChanges( Item $item ) {
		$params = $this->getParams();
		$siteId = $params['siteId'];

		$oldSiteLink = $this->getSiteLink( $item, $siteId );

		$siteLink = new SiteLink(
			$siteId,
			$this->getNormalizedPageName(),
			$oldSiteLink->getBadges() // Keep badges
		);

		$item->getSiteLinkList()->removeLinkWithSiteId( $siteId );
		$item->getSiteLinkList()->addSiteLink( $siteLink );
	}

}
