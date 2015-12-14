<?php

namespace Wikibase\Repo\ParserOutput;

use Wikibase\DataModel\SiteLink;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
interface SiteLinkDataUpdater extends ParserOutputDataUpdater {

	/**
	 * Extract some data or do processing on a SiteLink during parsing.
	 *
	 * This method is invoked when processing a SiteLinkList on an Item,
	 * or other entity type that has SiteLinks.
	 *
	 * @param SiteLink $siteLink
	 */
	public function processSiteLink( SiteLink $siteLink );

}
