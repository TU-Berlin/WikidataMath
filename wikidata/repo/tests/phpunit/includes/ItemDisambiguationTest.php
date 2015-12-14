<?php

namespace Wikibase\Test;

use Language;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Term;
use Wikibase\ItemDisambiguation;
use Wikibase\Lib\Interactors\TermSearchResult;

/**
 * @covers Wikibase\ItemDisambiguation
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group SpecialPage
 * @group WikibaseSpecialPage
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class ItemDisambiguationTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgLang' => Language::factory( 'qqx' ),
		) );
	}

	/**
	 * @return ItemDisambiguation
	 */
	private function newInstance() {
		$entityTitleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$entityTitleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnValue( $this->getMock( 'Title' ) ) );

		$languageNameLookup = $this->getMock( 'Wikibase\Lib\LanguageNameLookup' );
		$languageNameLookup->expects( $this->any() )
			->method( 'getName' )
			->will( $this->returnValue( '<LANG>' ) );

		return new ItemDisambiguation(
			$entityTitleLookup,
			$languageNameLookup,
			'en'
		);
	}

	public function testNoResults() {
		$html = $this->newInstance()->getHTML( array() );

		$this->assertSame( '<ul class="wikibase-disambiguation"></ul>', $html );
	}

	public function testOneResult() {
		$searchResult = new TermSearchResult(
			new Term( 'en', '<MATCH>' ),
			'<TYPE>',
			new ItemId( 'Q1' ),
			new Term( 'en', '<LABEL>' ),
			new Term( 'en', '<DESC>' )
		);
		$html = $this->newInstance()->getHTML( array( $searchResult ) );

		$this->assertContains( '<ul class="wikibase-disambiguation">', $html );
		$this->assertSame( 1, substr_count( $html, '<li ' ) );

		$this->assertContains( '>Q1</a>', $html );
		$this->assertContains( '<span class="wb-itemlink-label">&lt;LABEL></span>', $html );
		$this->assertContains( '<span class="wb-itemlink-description">&lt;DESC></span>', $html );
		$this->assertContains( '(wikibase-itemlink-userlang-wrapper: &lt;LANG>, &lt;MATCH>)',
			$html
		);
	}

	public function testTwoResults() {
		$searchResults = array(
			new TermSearchResult(
				new Term( 'de', '<MATCH1>' ),
				'<TYPE1>',
				new ItemId( 'Q1' ),
				null,
				new Term( 'en', '<DESC1>' )
			),
			new TermSearchResult(
				new Term( 'de', '<MATCH2>' ),
				'<TYPE2>',
				new ItemId( 'Q2' ),
				new Term( 'en', '<LABEL2>' )
			),
		);
		$html = $this->newInstance()->getHTML( $searchResults );

		$this->assertContains( '<ul class="wikibase-disambiguation">', $html );
		$this->assertSame( 2, substr_count( $html, '<li ' ) );

		$this->assertContains( '>Q1</a>', $html );
		$this->assertContains( '<span class="wb-itemlink-description">&lt;DESC1></span>', $html );
		$this->assertContains( '(wikibase-itemlink-userlang-wrapper: &lt;LANG>, &lt;MATCH1>)',
			$html
		);

		$this->assertContains( '>Q2</a>', $html );
		$this->assertContains( '<span class="wb-itemlink-label">&lt;LABEL2></span>', $html );
		$this->assertContains( '(wikibase-itemlink-userlang-wrapper: &lt;LANG>, &lt;MATCH2>)',
			$html
		);
	}

}
