<?php

namespace Wikibase\Test;

use Exception;
use ValueParsers\ParseException;
use Wikibase\Repo\Localizer\ParseExceptionLocalizer;

/**
 * @covers Wikibase\Repo\Localizer\ParseExceptionLocalizer
 *
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ParseExceptionLocalizerTest extends \PHPUnit_Framework_TestCase {

	public function provideGetExceptionMessage() {
		return array(
			'ParseException' => array( new ParseException( 'Blarg!' ), 'wikibase-parse-error', array() ),
		);
	}

	/**
	 * @dataProvider provideGetExceptionMessage
	 */
	public function testGetExceptionMessage( Exception $ex, $expectedKey, array $expectedParams ) {
		$localizer = new ParseExceptionLocalizer();

		$this->assertTrue( $localizer->hasExceptionMessage( $ex ) );

		$message = $localizer->getExceptionMessage( $ex );

		$this->assertTrue( $message->exists(), 'Message ' . $message->getKey() . ' should exist.' );
		$this->assertEquals( $expectedKey, $message->getKey(), 'Message key:' );
		$this->assertEquals( $expectedParams, $message->getParams(), 'Message parameters:' );
	}

}
