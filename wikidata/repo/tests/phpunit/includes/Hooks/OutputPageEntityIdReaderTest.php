<?php

namespace Wikibase\Repo\Tests\Hooks;

use OutputPage;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Repo\Hooks\OutputPageEntityIdReader;

/**
 * @covers Wikibase\Repo\Hooks\OutputPageEntityIdReader
 *
 * @since 0.5
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
class OutputPageEntityIdReaderTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider getEntityIdFromOutputPageProvider
	 */
	public function testGetEntityIdFromOutputPage( $expected, OutputPage $out, $isEntityContentModel ) {
		$entityContentFactory = $this->getMockBuilder( 'Wikibase\Repo\Content\EntityContentFactory' )
			->disableOriginalConstructor()
			->getMock();

		$entityContentFactory->expects( $this->once() )
			->method( 'isEntityContentModel' )
			->with( 'bar' )
			->will( $this->returnValue( $isEntityContentModel ) );

		$outputPageEntityIdReader = new OutputPageEntityIdReader(
			$entityContentFactory,
			new BasicEntityIdParser()
		);

		$this->assertEquals(
			$expected,
			$outputPageEntityIdReader->getEntityIdFromOutputPage( $out )
		);
	}

	public function getEntityIdFromOutputPageProvider() {
		$title = $this->getMock( 'Title' );
		$title->expects( $this->any() )
			->method( 'getContentModel' )
			->will( $this->returnValue( 'bar' ) );

		$context = $this->getMock( 'IContextSource' );
		$context->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );

		$outputPage = new OutputPage( $context );
		$outputPageEntityId = clone $outputPage;
		$outputPageEntityId->addJsConfigVars( 'wbEntityId', 'Q42' );

		return array(
			'Entity id set' => array(
				new ItemId( 'Q42' ),
				$outputPageEntityId,
				true
			),
			'entity content model, but no entity id set' => array(
				null,
				$outputPage,
				true
			),
			'no entity content model, should abort early' => array(
				null,
				$outputPageEntityId,
				false
			),
		);
	}

}
