<?php

namespace Wikibase\Rdf;

/**
 * Interface for a facility that avoids duplicates based on value hashes.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
interface DedupeBag {

	/**
	 * Check whether alreadySeen() has been called with the same $hash and $namespace
	 * before on this DedupeBag instance. This can be used to avoid processing or
	 * generating data multiple times, based on a hash value that can be checked against
	 * the bag.
	 *
	 * @note False negatives are acceptable, while false positives are not.
	 * This means that implementations are free to return false if it is not
	 * sure whether the hash was seen before, but should never return true
	 * if it is not certain that the hash was seen before.
	 *
	 * @param string $hash Hash to check
	 * @param string $namespace Optional namespace to allow a compartmentalized bag,
	 *        tracking hashes from multiple value sets.
	 *
	 * @return bool
	 */
	public function alreadySeen( $hash, $namespace = '' );

}
