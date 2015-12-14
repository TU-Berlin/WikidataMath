<?php

namespace Wikibase\Tests\Repo;

use RecentChange;
use Diff\DiffOp\Diff\Diff;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\EntityChange;
use Wikibase\Repo\Store\Sql\SqlChangeStore;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\Store\Sql\SqlChangeStore
 *
 * @group Database
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Marius Hoch
 */
class SqlChangeStoreTest extends \MediaWikiTestCase {

	public function saveChangeInsertProvider() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$factory = $wikibaseRepo->getEntityChangeFactory();

		$time = wfTimestamp( TS_MW );

		$simpleChange = $factory->newForEntity( EntityChange::ADD, new ItemId( 'Q21389475' ) );

		$changeWithDiff = $factory->newForEntity( EntityChange::REMOVE, new ItemId( 'Q42' ) );
		$changeWithDiff->setField( 'time', $time );
		$changeWithDiff->setDiff( new Diff() );

		$rc = new RecentChange();
		$rc->setAttribs( array(
			'rc_user' => 34,
			'rc_user_text' => 'BlackMagicIsEvil',
			'rc_timestamp' => $time,
			'rc_bot' => 0,
			'rc_cur_id' => 2354,
			'rc_this_oldid' => 343,
			'rc_last_oldid' => 897,
			'rc_comment' => 'Fake data!'
		) );

		$changeWithDataFromRC = $factory->newForEntity( EntityChange::REMOVE, new ItemId( 'Q123' ) );
		$changeWithDataFromRC->setMetadataFromRC( $rc );

		return array(
			'Simple change' => array(
				array(
					'change_type' => 'wikibase-item~add',
					'change_time' => $time,
					'change_object_id' => 'q21389475',
					'change_revision_id' => '0',
					'change_user_id' => '0',
					'change_info' => '[]',
				),
				$simpleChange
			),
			'Change with a diff' => array(
				array(
					'change_type' => 'wikibase-item~remove',
					'change_time' => $time,
					'change_object_id' => 'q42',
					'change_revision_id' => '0',
					'change_user_id' => '0',
					'change_info' => '{"diff":{"type":"diff","isassoc":null,"operations":[]}}',
				),
				$changeWithDiff
			),
			'Change with data from RC' => array(
				array(
					'change_type' => 'wikibase-item~remove',
					'change_time' => $time,
					'change_object_id' => 'q123',
					'change_revision_id' => '343',
					'change_user_id' => '34',
					'change_info' => '{"metadata":{"user_text":"BlackMagicIsEvil","bot":0,"page_id":2354,"rev_id":343,' .
						'"parent_id":897,"comment":"Fake data!"}}',
				),
				$changeWithDataFromRC
			)
		);
	}

	/**
	 * @dataProvider saveChangeInsertProvider
	 */
	public function testSaveChange_insert( array $expected, EntityChange $change ) {
		$db = wfGetDB( DB_MASTER );

		$db->delete( 'wb_changes', '*', __METHOD__ );
		$this->tablesUsed[] = 'wb_changes';

		$store = new SqlChangeStore( wfGetLB() );
		$store->saveChange( $change );

		$res = $db->select( 'wb_changes', '*', array(), __METHOD__ );

		$this->assertEquals( 1, $res->numRows(), 'row count' );

		$row = (array)$res->current();
		$this->assertTrue( is_numeric( $row['change_id'] ) );

		$this->assertEquals(
			wfTimestamp( TS_UNIX, $expected['change_time'] ),
			wfTimestamp( TS_UNIX, $row['change_time'] ),
			'Change time',
			60 * 60 // 1 hour
		);

		unset( $row['change_id'] );
		unset( $row['change_time'] );
		unset( $expected['change_time'] );

		$this->assertEquals( $expected, $row );

		$this->assertType( 'int', $change->getId() );
	}

	public function testSaveChange_update() {
		$db = wfGetDB( DB_MASTER );

		$db->delete( 'wb_changes', '*', __METHOD__ );
		$this->tablesUsed[] = 'wb_changes';

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$factory = $wikibaseRepo->getEntityChangeFactory();

		$change = $factory->newForEntity( EntityChange::ADD, new ItemId( 'Q21389475' ) );
		$change->setField( 'time', wfTimestampNow() );

		$store = new SqlChangeStore( wfGetLB() );
		$store->saveChange( $change );
		$expected = array(
			'change_id' => (string)$change->getId(),
			'change_type' => 'wikibase-item~add',
			'change_time' => '20121026200049',
			'change_object_id' => 'q21389475',
			'change_revision_id' => '0',
			'change_user_id' => '0',
			'change_info' => '[]',
		);

		$change->setField( 'time', '20121026200049' );
		$store->saveChange( $change );

		$res = $db->select( 'wb_changes', '*', array(), __METHOD__ );

		$this->assertEquals( 1, $res->numRows(), 'row count' );

		$row = (array)$res->current();

		$this->assertEquals( $expected, $row );
	}

}
