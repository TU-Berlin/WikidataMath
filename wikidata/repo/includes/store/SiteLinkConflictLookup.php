<?php

namespace Wikibase\Repo\Store;

use DatabaseBase;
use Wikibase\DataModel\Entity\Item;

/**
 * Contains methods for looking up SiteLink conflicts
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
interface SiteLinkConflictLookup {

	/**
	 * Returns an array with the conflicts between the item and the sitelinks
	 * currently in the store. The array is empty if there are no such conflicts.
	 *
	 * The items in the return array are arrays with the following elements:
	 * - int itemId Numeric (unprefixed) item id
	 * - string siteId
	 * - string sitePage
	 *
	 * @since 0.1
	 *
	 * @param Item          $item
	 * @param DatabaseBase|null $db The database object to use (optional).
	 *        If conflict checking is performed as part of a save operation,
	 *        this should be used to provide the master DB connection that will
	 *        also be used for saving. This will preserve transactional integrity
	 *        and avoid race conditions.
	 *
	 * @return array[]
	 */
	public function getConflictsForItem( Item $item, DatabaseBase $db = null );

}
