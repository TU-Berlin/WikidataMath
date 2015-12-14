<?php

namespace Wikibase\Test;

use Language;
use Message;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;

/**
 * @covers Wikibase\Repo\Specials\SpecialPageCopyrightView
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class SpecialPageCopyrightViewTest extends \MediaWikiTestCase {

	/**
	 * @dataProvider getHtmlProvider
	 */
	public function testGetHtml( $expected, $message, $languageCode ) {
		$lang = Language::factory( $languageCode );

		$specialPageCopyrightView = new SpecialPageCopyrightView(
			$this->getCopyrightMessageBuilder( $message ), 'x', 'y'
		);

		$html = $specialPageCopyrightView->getHtml( $lang, 'wikibase-submit' );
		$this->assertEquals( $expected, $html );
	}

	private function getCopyrightMessageBuilder( Message $message ) {
		$copyrightMessageBuilder = $this->getMockBuilder( 'Wikibase\CopyrightMessageBuilder' )
			->getMock();

		$copyrightMessageBuilder->expects( $this->any() )
			->method( 'build' )
			->will( $this->returnValue( $message ) );

		return $copyrightMessageBuilder;
	}

	public function getHtmlProvider() {
		$message = new Message(
			'wikibase-shortcopyrightwarning',
			array( 'wikibase-submit', 'copyrightpage', 'copyrightlink' )
		);

		return array(
			array(
				'<div>(wikibase-shortcopyrightwarning: wikibase-submit, copyrightpage, copyrightlink)</div>',
				$message,
				'qqx'
			)
		);
	}

}
