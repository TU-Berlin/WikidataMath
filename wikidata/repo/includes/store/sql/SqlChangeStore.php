<?php

namespace Wikibase\Repo\Store\Sql;

use DBQueryError;
use LoadBalancer;
use Wikibase\Change;
use Wikibase\ChangeRow;
use Wikibase\Repo\Store\ChangeStore;
use Wikimedia\Assert\Assert;

/**
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Marius Hoch
 */
class SqlChangeStore implements ChangeStore {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @param LoadBalancer $loadBalancer
	 */
	public function __construct( LoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Saves the change to a database table.
	 *
	 * @note Only supports Change objects that are derived from ChangeRow.
	 *
	 * @param Change $change
	 *
	 * @throws DBQueryError
	 */
	public function saveChange( Change $change ) {
		Assert::parameterType( 'Wikibase\ChangeRow', $change, '$change' );

		if ( $change->getId() === null ) {
			$this->insertChange( $change );
		} else {
			$this->updateChange( $change );
		}
	}

	private function updateChange( Change $change ) {
		$values = $this->getValues( $change );

		$dbw = $this->loadBalancer->getConnection( DB_MASTER );

		$dbw->update(
			'wb_changes',
			$values,
			array( 'change_id' => $change->getId() ),
			__METHOD__
		);

		$this->loadBalancer->reuseConnection( $dbw );
	}

	private function insertChange( Change $change ) {
		$values = $this->getValues( $change );

		$dbw = $this->loadBalancer->getConnection( DB_MASTER );

		$dbw->insert( 'wb_changes', $values, __METHOD__ );
		$change->setField( 'id', $dbw->insertId() );

		$this->loadBalancer->reuseConnection( $dbw );
	}

	/**
	 * @param ChangeRow $change
	 *
	 * @return array
	 */
	private function getValues( ChangeRow $change ) {
		$fields = $change->getFields();

		return array(
			'change_type' => $fields['type'],
			'change_time' => isset( $fields['time'] ) ? $fields['time'] : wfTimestampNow(),
			'change_object_id' => isset( $fields['object_id'] ) ? $fields['object_id'] : '',
			'change_revision_id' => isset( $fields['revision_id'] ) ? $fields['revision_id'] : '0',
			'change_user_id' => isset( $fields['user_id'] ) ? $fields['user_id'] : '0',
			'change_info' => $change->serializeInfo( $fields['info'] )
		);
	}

}
