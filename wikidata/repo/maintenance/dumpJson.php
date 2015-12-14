<?php

namespace Wikibase;

use DataValues\Serializers\DataValueSerializer;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Dumpers\DumpGenerator;
use Wikibase\Dumpers\JsonDumpGenerator;
use Wikibase\Lib\Store\RevisionBasedEntityLookup;
use Wikibase\Repo\Store\EntityPerPage;
use Wikibase\Repo\WikibaseRepo;

require_once __DIR__ . '/dumpEntities.php';

class DumpJson extends DumpScript {

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var EntityPrefetcher
	 */
	private $entityPrefetcher;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDatatypeLookup;

	/**
	 * @var bool
	 */
	private $hasHadServicesSet = false;

	public function __construct() {
		parent::__construct();

		$this->addOption(
			'snippet',
			'Output a JSON snippet without square brackets at the start and end. Allows output to'
				. ' be combined more freely.',
			false,
			false
		);
	}

	public function setServices(
		EntityPerPage $entityPerPage,
		EntityPrefetcher $entityPrefetcher,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		EntityLookup $entityLookup
	) {
		parent::setDumpEntitiesServices( $entityPerPage );
		$this->entityPrefetcher = $entityPrefetcher;
		$this->propertyDatatypeLookup = $propertyDataTypeLookup;
		$this->entityLookup = $entityLookup;
		$this->hasHadServicesSet = true;
	}

	public function execute() {
		if ( !$this->hasHadServicesSet ) {
			$wikibaseRepo = WikibaseRepo::getDefaultInstance();
			$revisionLookup = $wikibaseRepo->getEntityRevisionLookup( 'uncached' );
			$this->setServices(
				$wikibaseRepo->getStore()->newEntityPerPage(),
				$wikibaseRepo->getStore()->getEntityPrefetcher(),
				$wikibaseRepo->getPropertyDataTypeLookup(),
				new RevisionBasedEntityLookup( $revisionLookup )
			);
		}
		parent::execute();
	}

	/**
	 * Create concrete dumper instance
	 * @param resource $output
	 * @return DumpGenerator
	 */
	protected function createDumper( $output ) {
		$serializerOptions = SerializerFactory::OPTION_SERIALIZE_MAIN_SNAKS_WITHOUT_HASH +
			SerializerFactory::OPTION_SERIALIZE_REFERENCE_SNAKS_WITHOUT_HASH;
		$serializerFactory = new SerializerFactory( new DataValueSerializer(), $serializerOptions );

		$entitySerializer = $serializerFactory->newEntitySerializer();
		$dataTypeLookup = $this->propertyDatatypeLookup;

		$dumper = new JsonDumpGenerator(
			$output,
			$this->entityLookup,
			$entitySerializer,
			$this->entityPrefetcher,
			$dataTypeLookup
		);

		$dumper->setUseSnippets( (bool)$this->getOption( 'snippet', false ) );
		return $dumper;
	}

}

$maintClass = 'Wikibase\DumpJson';
require_once RUN_MAINTENANCE_IF_MAIN;
