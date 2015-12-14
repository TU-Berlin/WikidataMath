<?php

namespace Wikibase\Tests\Repo;

use Wikibase\Repo\Notifications\HookChangeTransmitter;

/**
 * @covers Wikibase\Repo\Notifications\HookChangeTransmitter
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class HookChangeTransmitterTest extends \MediaWikiTestCase {

	public function testTransmitChange() {
		global $wgHooks;

		$this->stashMwGlobals( 'wgHooks' );

		$change = $this->getMockBuilder( 'Wikibase\EntityChange' )
			->disableOriginalConstructor()
			->getMock();

		$called = false;
		$wgHooks['HookChangeTransmitterTest'][] = function( $actualChange ) use ( $change, &$called ) {
			HookChangeTransmitterTest::assertEquals( $change, $actualChange );
			$called = true;
		};

		$transmitter = new HookChangeTransmitter( 'HookChangeTransmitterTest' );
		$transmitter->transmitChange( $change );

		$this->assertTrue( $called, 'The hook function was not called' );
	}

}
