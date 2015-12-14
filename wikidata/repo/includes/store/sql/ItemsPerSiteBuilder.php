<?php

namespace Wikibase\Repo\Store\SQL;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Reporting\MessageReporter;
use Wikibase\Lib\Store\SiteLinkTable;
use Wikibase\Repo\Store\EntityIdPager;

/**
 * Utility class for rebuilding the wb_items_per_site table.
 *
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class ItemsPerSiteBuilder {

	/**
	 * @var SiteLinkTable
	 */
	private $siteLinkTable;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntityPrefetcher
	 */
	private $entityPrefetcher;

	/**
	 * @var MessageReporter|null
	 */
	private $reporter = null;

	/**
	 * The batch size, giving the number of rows to be updated in each database transaction.
	 * @var int
	 */
	private $batchSize = 100;

	/**
	 * @param SiteLinkTable $siteLinkTable
	 * @param EntityLookup $entityLookup
	 * @param EntityPrefetcher $entityPrefetcher
	 */
	public function __construct( SiteLinkTable $siteLinkTable, EntityLookup $entityLookup, EntityPrefetcher $entityPrefetcher ) {
		$this->siteLinkTable = $siteLinkTable;
		$this->entityLookup = $entityLookup;
		$this->entityPrefetcher = $entityPrefetcher;
	}

	/**
	 * @since 0.5
	 *
	 * @param int $batchSize
	 */
	public function setBatchSize( $batchSize ) {
		$this->batchSize = $batchSize;
	}

	/**
	 * Sets the reporter to use for reporting preogress.
	 *
	 * @param MessageReporter $reporter
	 */
	public function setReporter( MessageReporter $reporter ) {
		$this->reporter = $reporter;
	}

	/**
	 * @since 0.5
	 *
	 * @param EntityIdPager $entityIdPager
	 */
	public function rebuild( EntityIdPager $entityIdPager ) {
		$this->report( 'Start rebuild...' );

		$total = 0;
		while ( true ) {
			$ids = $entityIdPager->fetchIds( $this->batchSize );
			if ( !$ids ) {
				break;
			}

			$total += $this->rebuildSiteLinks( $ids );
			$this->report( 'Processed ' . $total . ' entities.' );
		};

		$this->report( 'Rebuild done.' );
	}

	/**
	 * Rebuilds EntityPerPageTable for specified pages
	 *
	 * @param ItemId[] $itemIds
	 *
	 * @return int
	 */
	private function rebuildSiteLinks( array $itemIds ) {
		$this->entityPrefetcher->prefetch( $itemIds );

		$c = 0;
		foreach ( $itemIds as $itemId ) {
			if ( !( $itemId instanceof ItemId ) ) {
				// Just in case someone is using a EntityIdPager which doesn't filter non-Items
				continue;
			}
			$item = $this->entityLookup->getEntity( $itemId );

			if ( !$item ) {
				continue;
			}

			$ok = $this->siteLinkTable->saveLinksOfItem( $item );
			if ( !$ok ) {
				$this->report( 'Saving sitelinks for Item ' . $item->getId()->getSerialization() . ' failed' );
			}

			$c++;
		}
		// Wait for the slaves, just in case we e.g. hit a range of ids which need a lot of writes.
		wfWaitForSlaves();

		return $c;
	}

	/**
	 * reports a message
	 *
	 * @since 0.5
	 *
	 * @param string $msg
	 */
	protected function report( $msg ) {
		if ( $this->reporter ) {
			$this->reporter->reportMessage( $msg );
		}
	}

}
