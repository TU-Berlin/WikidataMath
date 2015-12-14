<?php

namespace Wikibase\Repo;

use Site;
use SiteList;
use SiteStore;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 *
 * @author Daniel K
 * @author Adam Shorland
 * @author Marius Hoch < hoo@online.de >
 * @author Thiemo Mättig
 */
class SiteLinkTargetProvider {

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	/**
	 * @var string[]
	 */
	private $specialSiteGroups;

	/**
	 * @param SiteStore $siteStore
	 * @param string[] $specialSiteGroups
	 */
	public function __construct( SiteStore $siteStore, array $specialSiteGroups = array() ) {
		$this->siteStore = $siteStore;
		$this->specialSiteGroups = $specialSiteGroups;
	}

	/**
	 * Returns the list of sites that is suitable as a sitelink target.
	 *
	 * @param string[] $groups sitelink groups to get
	 *
	 * @return SiteList alphabetically ordered by the site's global identifiers.
	 */
	public function getSiteList( array $groups ) {
		// As the special sitelink group actually just wraps multiple groups
		// into one we have to replace it with the actual groups
		$this->substituteSpecialSiteGroups( $groups );

		$sites = new SiteList();
		$allSites = $this->siteStore->getSites();

		/** @var Site $site */
		foreach ( $allSites as $site ) {
			if ( in_array( $site->getGroup(), $groups ) ) {
				$sites->append( $site );
			}
		}

		// Because of the way SiteList is implemented this will not order the array returned by
		// SiteList::getGlobalIdentifiers.
		$sites->uasort( function( Site $a, Site $b ) {
			return strnatcasecmp( $a->getGlobalId(), $b->getGlobalId() );
		} );

		return $sites;
	}

	/**
	 * @param string[] &$groups
	 */
	private function substituteSpecialSiteGroups( &$groups ) {
		if ( !in_array( 'special', $groups ) ) {
			return;
		}

		$groups = array_diff( $groups, array( 'special' ) );
		$groups = array_merge( $groups, $this->specialSiteGroups );
	}

}
