<?php

namespace Wikibase\Rdf;

use SiteList;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\SiteLink;
use Wikimedia\Purtle\RdfWriter;

/**
 * RDF mapping for entity SiteLinks.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Anja Jentzsch < anja.jentzsch@wikimedia.de >
 * @author Thomas Pellissier Tanon
 * @author Daniel Kinzler
 * @author Stas Malyshev
 */
class SiteLinksRdfBuilder implements EntityRdfBuilder {

	/**
	 * @var RdfVocabulary
	 */
	private $vocabulary;

	/**
	 * @var RdfWriter
	 */
	private $writer;

	/**
	 * @var SiteList
	 */
	private $siteLookup;

	/**
	 * @var string[]|null a list of desired sites, or null for all sites.
	 */
	private $sites;

	/**
	 * @param RdfVocabulary $vocabulary
	 * @param RdfWriter $writer
	 * @param SiteList $siteLookup
	 * @param string[]|null $sites
	 */
	public function __construct( RdfVocabulary $vocabulary, RdfWriter $writer, SiteList $siteLookup, array $sites = null ) {
		$this->vocabulary = $vocabulary;
		$this->writer = $writer;
		$this->siteLookup = $siteLookup;
		$this->sites = $sites === null ? null : array_flip( $sites );
	}

	/**
	 * Site filter
	 *
	 * @param string $lang
	 *
	 * @return bool
	 */
	private function isSiteIncluded( $lang ) {
		return $this->sites === null || isset( $this->sites[$lang] );
	}

	/**
	 * Adds the site links of the given item to the RDF graph.
	 *
	 * @param Item $item
	 */
	public function addSiteLinks( Item $item ) {
		$entityLName = $this->vocabulary->getEntityLName( $item->getId() );

		/** @var SiteLink $siteLink */
		foreach ( $item->getSiteLinkList() as $siteLink ) {
			if ( !$this->isSiteIncluded( $siteLink->getSiteId() ) ) {
				continue;
			}

			// FIXME: we should check the site exists using hasGlobalId here before asuming it does
			$site = $this->siteLookup->getSite( $siteLink->getSiteId() );

			// XXX: ideally, we'd use https if the target site supports it.
			$baseUrl = str_replace( '$1', rawurlencode( $siteLink->getPageName() ), $site->getLinkPath() );
			// $site->getPageUrl( $siteLink->getPageName() );
			if ( !parse_url( $baseUrl, PHP_URL_SCHEME ) ) {
				$url = "http:".$baseUrl;
			} else {
				$url = $baseUrl;
			}

			$this->writer->about( $url )
				->a( RdfVocabulary::NS_SCHEMA_ORG, 'Article' )
				->say( RdfVocabulary::NS_SCHEMA_ORG, 'about' )->is( RdfVocabulary::NS_ENTITY, $entityLName )
				->say( RdfVocabulary::NS_SCHEMA_ORG, 'inLanguage' )->text(
						$this->vocabulary->getCanonicalLanguageCode( $site->getLanguageCode() ) );

			foreach ( $siteLink->getBadges() as $badge ) {
				$this->writer
					->say( RdfVocabulary::NS_ONTOLOGY, 'badge' )
						->is( RdfVocabulary::NS_ENTITY, $this->vocabulary->getEntityLName( $badge ) );
			}
		}
	}

	/**
	 * Add the entity's sitelinks to the RDF graph.
	 *
	 * @param EntityDocument $entity the entity to output.
	 */
	public function addEntity( EntityDocument $entity ) {
		if ( $entity instanceof Item ) {
			$this->addSiteLinks( $entity );
		}
	}

}
