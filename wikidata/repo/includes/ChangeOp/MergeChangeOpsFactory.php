<?php

namespace Wikibase\ChangeOp;

use InvalidArgumentException;
use SiteLookup;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\Validators\EntityConstraintProvider;

/**
 * Factory for ChangeOps that merge Items.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class MergeChangeOpsFactory {

	/**
	 * @var EntityConstraintProvider
	 */
	private $constraintProvider;

	/**
	 * @var ChangeOpFactoryProvider
	 */
	private $factoryProvider;

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @param EntityConstraintProvider $constraintProvider
	 * @param ChangeOpFactoryProvider $factoryProvider
	 * @param SiteLookup $siteLookup
	 */
	public function __construct(
		EntityConstraintProvider $constraintProvider,
		ChangeOpFactoryProvider $factoryProvider,
		SiteLookup $siteLookup
	) {
		$this->constraintProvider = $constraintProvider;
		$this->factoryProvider = $factoryProvider;
		$this->siteLookup = $siteLookup;
	}

	/**
	 * @param Item $fromItem
	 * @param Item $toItem
	 * @param string[] $ignoreConflicts list of elements to ignore conflicts for
	 *   can only contain 'label' and or 'description' and or 'sitelink'
	 *
	 * @throws InvalidArgumentException
	 * @return ChangeOpsMerge
	 *
	 * @todo: Injecting ChangeOpFactoryProvider is an Abomination Unto Nuggan, we'll
	 *        need a ItemMergeChangeOpsSequenceBuilder or some such.
	 */
	public function newMergeOps(
		Item $fromItem,
		Item $toItem,
		$ignoreConflicts = array()
	) {
		return new ChangeOpsMerge(
			$fromItem,
			$toItem,
			$ignoreConflicts,
			$this->constraintProvider,
			$this->factoryProvider,
			$this->siteLookup
		);
	}

}
