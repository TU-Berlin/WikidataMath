<?php

namespace Wikibase\Repo\Test;

use PHPUnit_Framework_TestCase;
use Wikibase\Repo\PidLock;

/**
 * @covers Wikibase\Repo\PidLock
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class PidLockTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider wikiIdProvider
	 */
	public function testPidLock( $wikiId ) {
		$pidLock = new PidLock( 'PidLockTest', $wikiId );

		$this->assertTrue( $pidLock->getLock(), 'Get an initial log' );
		$this->assertFalse( $pidLock->getLock(), 'Try to get the lock, although some already has it' );
		$this->assertTrue( $pidLock->getLock( true ), 'Force getting the lock' );

		$this->assertTrue( $pidLock->removeLock() );

		// Make sure that the given file has actually been removed.
		// unlink gives a warning if you use it a file that doesn't exist, suppress that
		\MediaWiki\suppressWarnings();
		$this->assertFalse( $pidLock->removeLock() );
		\MediaWiki\restoreWarnings();
	}

	public function wikiIdProvider() {
		return array(
			array( wfWikiID() ),
			array( null )
		);
	}

}
