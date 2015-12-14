<?php

namespace Wikibase\Test;

use MediaWikiLangTestCase;
use Wikibase\Repo\View\RepoSpecialPageLinker;

/**
 * @covers Wikibase\Repo\View\RepoSpecialPageLinker
 *
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 */
class RepoSpecialPageLinkerTest extends MediaWikiLangTestCase {

	/**
	 * @dataProvider getLinkProvider
	 *
	 * @param string $specialPageName
	 * @param string[] $subPageParams
	 * @param string $expectedMatch
	 */
	public function testGetLink( $specialPageName, $subPageParams, $expectedMatch ) {
		$linker = new RepoSpecialPageLinker();

		$link = $linker->getLink( $specialPageName, $subPageParams );

		$this->assertRegExp( $expectedMatch, $link );
	}

	public function getLinkProvider() {
		return array(
			array( 'SetLabel', array(), '/Special:SetLabel\/?$/' ),
			array( 'SetLabel', array( 'en' ), '/Special:SetLabel\/en\/?$/' ),
			array( 'SetLabel', array( 'en', 'Q5' ), '/Special:SetLabel\/en\/Q5\/?$/' )
		);
	}

}
