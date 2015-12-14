<?php

namespace Wikibase\Dumpers;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\EntityLookupException;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\Lib\Reporting\ExceptionHandler;
use Wikibase\Lib\Reporting\MessageReporter;
use Wikibase\Lib\Reporting\NullMessageReporter;
use Wikibase\Lib\Reporting\RethrowingExceptionHandler;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Repo\Store\EntityIdPager;

/**
 * DumpGenerator generates a dump of a given set of entities, excluding
 * redirects.
 *
 * @since 0.5
 * @license GPL 2+
 * @author Daniel Kinzler
 */
abstract class DumpGenerator {

	/**
	 * @var int The max number of entities to process in a single batch.
	 *      Also controls the interval for progress reports.
	 */
	private $batchSize = 100;

	/**
	 * @var resource File handle for output
	 */
	protected $out;

	/**
	 * @var int Total number of shards a request should be split into
	 */
	protected $shardingFactor = 1;

	/**
	 * @var int Number of the requested shard
	 */
	protected $shard = 0;

	/**
	 * @var MessageReporter
	 */
	protected $progressReporter;

	/**
	 * @var ExceptionHandler
	 */
	protected $exceptionHandler;

	/**
	 * @var EntityPrefetcher
	 */
	protected $entityPrefetcher;

	/**
	 * @var string
	 */
	protected $entityType;

	/**
	 * Entity count limit - dump will generate this many
	 *
	 * @var int
	 */
	protected $limit = 0;

	/**
	 * @param resource $out
	 * @param EntityPrefetcher $entityPrefetcher
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $out, EntityPrefetcher $entityPrefetcher ) {
		if ( !is_resource( $out ) ) {
			throw new InvalidArgumentException( '$out must be a file handle!' );
		}

		$this->out = $out;

		$this->entityPrefetcher = $entityPrefetcher;
		$this->progressReporter = new NullMessageReporter();
		$this->exceptionHandler = new RethrowingExceptionHandler();
	}

	/**
	 * Set maximum number of entities produced
	 *
	 * @param int $limit
	 */
	public function setLimit( $limit ) {
		$this->limit = (int)$limit;
	}

	/**
	 * Sets the batch size for processing. The batch size is used as the limit
	 * when listing IDs via the EntityIdPager::getNextBatchOfIds() method, and
	 * also controls the interval of progress reports.
	 *
	 * @param int $batchSize
	 *
	 * @throws InvalidArgumentException
	 */
	public function setBatchSize( $batchSize ) {
		if ( !is_int( $batchSize ) || $batchSize < 1 ) {
			throw new InvalidArgumentException( '$batchSize must be an integer >= 1' );
		}

		$this->batchSize = $batchSize;
	}

	/**
	 * @see setBatchSize()
	 *
	 * @return int
	 */
	public function getBatchSize() {
		return $this->batchSize;
	}

	/**
	 * @param MessageReporter $progressReporter
	 */
	public function setProgressReporter( MessageReporter $progressReporter ) {
		$this->progressReporter = $progressReporter;
	}

	/**
	 * @return MessageReporter
	 */
	public function getProgressReporter() {
		return $this->progressReporter;
	}

	/**
	 * @param ExceptionHandler $exceptionHandler
	 */
	public function setExceptionHandler( ExceptionHandler $exceptionHandler ) {
		$this->exceptionHandler = $exceptionHandler;
	}

	/**
	 * @return ExceptionHandler
	 */
	public function getExceptionHandler() {
		return $this->exceptionHandler;
	}

	/**
	 * Set the sharding factor and desired shard.
	 * For instance, to generate four dumps in parallel, use setShardingFilter( 4, 0 )
	 * for the first dump, setShardingFilter( 4, 1 ) for the second dump, etc.
	 *
	 * @param int $shardingFactor
	 * @param int $shard
	 *
	 * @throws InvalidArgumentException
	 */
	public function setShardingFilter( $shardingFactor, $shard ) {
		if ( !is_int( $shardingFactor ) || $shardingFactor < 1 ) {
			throw new InvalidArgumentException( '$shardingFactor must be a positive integer.' );
		}

		if ( !is_int( $shard ) || $shard < 0 ) {
			throw new InvalidArgumentException( '$shard must be a non-negative integer.' );
		}

		if ( $shard >= $shardingFactor ) {
			throw new InvalidArgumentException( '$shard must be smaller than $shardingFactor.' );
		}

		$this->shardingFactor = $shardingFactor;
		$this->shard = $shard;
	}

	/**
	 * Set the entity type to be included in the output.
	 *
	 * @param string|null $type The desired type (use null for any type).
	 *
	 * @throws InvalidArgumentException
	 */
	public function setEntityTypeFilter( $type ) {
		$this->entityType = $type;
	}

	private function idMatchesFilters( EntityId $entityId ) {
		return $this->idMatchesShard( $entityId ) && $this->idMatchesType( $entityId );
	}

	private function idMatchesShard( EntityId $entityId ) {
		// Shorten out
		if ( $this->shardingFactor === 1 ) {
			return true;
		}

		$hash = sha1( $entityId->getSerialization() );
		$shard = (int)hexdec( substr( $hash, 0, 8 ) ); // 4 bytes of the hash
		$shard = abs( $shard ); // avoid negative numbers on 32 bit systems
		$shard %= $this->shardingFactor; // modulo number of shards

		return $shard === $this->shard;
	}

	private function idMatchesType( EntityId $entityId ) {
		return $this->entityType === null || ( $entityId->getEntityType() === $this->entityType );
	}

	/**
	 * Writers the given string to the output provided to the constructor.
	 *
	 * @param string $data
	 */
	protected function writeToDump( $data ) {
		//TODO: use output stream object
		fwrite( $this->out, $data );
	}

	/**
	 * Do something before dumping data
	 */
	protected function preDump() {
		// Nothing by default
	}

	/**
	 * Do something after dumping data
	 */
	protected function postDump() {
		// Nothing by default
	}

	/**
	 * Do something before dumping entity
	 *
	 * @param int $dumpCount
	 */
	protected function preEntityDump( $dumpCount ) {
		// Nothing by default
	}

	/**
	 * Do something after dumping entity
	 *
	 * @param int $dumpCount
	 */
	protected function postEntityDump( $dumpCount ) {
		// Nothing by default
	}

	/**
	 * Generates a dump, writing to the file handle provided to the constructor.
	 *
	 * @param EntityIdPager $idPager an Iterator that returns EntityId instances
	 */
	public function generateDump( EntityIdPager $idPager ) {
		$dumpCount = 0;

		$this->preDump();

		// Iterate over batches of IDs, maintaining the current position of the pager in the $position variable.
		while ( true ) {
			$ids = $idPager->fetchIds( $this->batchSize );
			if ( !$ids ) {
				break;
			}

			$this->dumpEntities( $ids, $dumpCount );

			$this->progressReporter->reportMessage( 'Processed ' . $dumpCount . ' entities.' );

			if ( $this->limit && $dumpCount >= $this->limit ) {
				break;
			}
		}

		$this->postDump();
	}

	/**
	 * Dump list of entities
	 *
	 * @param EntityId[] $entityIds
	 * @param int &$dumpCount The number of entities already dumped (will be updated).
	 */
	private function dumpEntities( array $entityIds, &$dumpCount ) {
		$toLoad = array();
		foreach ( $entityIds as $entityId ) {
			if ( $this->idMatchesFilters( $entityId ) ) {
				$toLoad[] = $entityId;
			}
		}
		$this->entityPrefetcher->prefetch( $toLoad );

		foreach ( $toLoad as $entityId ) {
			try {
				$data = $this->generateDumpForEntityId( $entityId );
				if ( !$data ) {
					continue;
				}

				$this->preEntityDump( $dumpCount );
				$this->writeToDump( $data );
				$this->postEntityDump( $dumpCount );

				$dumpCount ++;
				if ( $this->limit && $dumpCount >= $this->limit ) {
					break;
				}
			} catch ( EntityLookupException $ex ) {
				$this->exceptionHandler->handleException( $ex, 'failed-to-dump', 'Failed to dump ' . $entityId );
			} catch ( StorageException $ex ) {
				$this->exceptionHandler->handleException( $ex, 'failed-to-dump', 'Failed to dump ' . $entityId );
			}
		}
	}

	/**
	 * Produce dump data for specific entity
	 *
	 * @param EntityId $entityId
	 *
	 * @throws EntityLookupException
	 * @throws StorageException
	 * @return string|null
	 */
	abstract protected function generateDumpForEntityId( EntityId $entityId );

}
