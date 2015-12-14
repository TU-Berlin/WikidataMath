<?php

namespace Wikibase\Test;

use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\EntityRevision;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Store\WikiPageEntityRevisionLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Lib\Store\WikiPageEntityRevisionLookup
 *
 * @group Database
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseEntityLookup
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class WikiPageEntityRevisionLookupTest extends EntityRevisionLookupTest {

	/**
	 * @var EntityRevision[]
	 */
	private static $testEntities = array();

	protected static function storeTestEntity( Entity $entity ) {
		global $wgUser;

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$revision = $store->saveEntity( $entity, "storeTestEntity", $wgUser );

		return $revision;
	}

	protected static function storeTestRedirect( EntityRedirect $redirect ) {
		global $wgUser;

		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$revision = $store->saveRedirect( $redirect, "storeTestEntity", $wgUser );

		return $revision;
	}

	/**
	 * @see EntityLookupTest::newEntityLoader(newEntityLookup
	 *
	 * @param EntityRevision[] $entityRevisions
	 * @param EntityRedirect[] $entityRedirects
	 *
	 * @return EntityLookup
	 */
	protected function newEntityRevisionLookup( array $entityRevisions, array $entityRedirects ) {
		// make sure all test entities are in the database.

		foreach ( $entityRevisions as $entityRev ) {
			$logicalRev = $entityRev->getRevisionId();

			if ( !isset( self::$testEntities[$logicalRev] ) ) {
				$rev = self::storeTestEntity( $entityRev->getEntity() );
				self::$testEntities[$logicalRev] = $rev;
			}
		}

		if ( $this->itemSupportsRedirect() ) {
			foreach ( $entityRedirects as $entityRedir ) {
				self::storeTestRedirect( $entityRedir );
			}
		}

		return new WikiPageEntityRevisionLookup(
			WikibaseRepo::getDefaultInstance()->getEntityContentDataCodec(),
			new WikiPageEntityMetaDataLookup( new BasicEntityIdParser() ),
			false
		);
	}

	protected function resolveLogicalRevision( $revision ) {
		if ( is_int( $revision ) && isset( self::$testEntities[$revision] ) ) {
			$revision = self::$testEntities[$revision]->getRevisionId();
		}

		return $revision;
	}

}
