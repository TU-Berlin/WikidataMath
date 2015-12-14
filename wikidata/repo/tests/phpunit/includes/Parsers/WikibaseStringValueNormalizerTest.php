<?php

namespace Wikibase\Repo\Tests\Parsers;

use Wikibase\Repo\Parsers\WikibaseStringValueNormalizer;

/**
 * @covers Wikibase\Repo\Parsers\WikibaseStringValueNormalizer
 *
 * @group ValueParsers
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class WikibaseStringValueNormalizerTest extends \PHPUnit_Framework_TestCase {

	public function testNormalize() {
		$input = 'Kittens';

		$mock = $this->getMock( 'Wikibase\StringNormalizer' );
		$mock->expects( $this->once() )
			->method( 'trimToNFC' )
			->with( $input );

		$normalizer = new WikibaseStringValueNormalizer( $mock );
		$normalizer->normalize( $input );
	}

}
