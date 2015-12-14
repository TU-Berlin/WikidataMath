<?php

namespace Wikibase\Test;

use SpecialPageTestBase;
use Wikibase\Repo\Specials\SpecialEntitiesWithoutPage;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\TermIndexEntry;

/**
 * @covers Wikibase\Repo\Specials\SpecialEntitiesWithoutPage
 * @covers Wikibase\Repo\Specials\SpecialWikibaseQueryPage
 * @covers Wikibase\Repo\Specials\SpecialWikibasePage
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group SpecialPage
 * @group WikibaseSpecialPage
 *
 * @group Database
 *        ^---- needed because we rely on Title objects internally
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@googlemail.com >
 * @author Adam Shorland
 */
class SpecialEntitiesWithoutPageTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$termsLanguages = $this->getMock( 'Wikibase\Lib\ContentLanguages' );
		$termsLanguages->expects( $this->any() )
			->method( 'getLanguages' )
			->will( $this->returnValue( array( 'acceptedlanguage' ) ) );
		$termsLanguages->expects( $this->any() )
			->method( 'hasLanguage' )
			->will( $this->returnCallback( function( $code ) {
				return $code === 'acceptedlanguage';
			} ) );

		return new SpecialEntitiesWithoutPage(
			'EntitiesWithoutLabel',
			TermIndexEntry::TYPE_LABEL,
			'wikibase-entitieswithoutlabel-legend',
			$wikibaseRepo->getStore()->newEntityPerPage(),
			$wikibaseRepo->getEntityFactory(),
			$termsLanguages
		);
	}

	public function testExecute() {
		$matchers['language'] = array(
			'tag' => 'input',
			'attributes' => array(
				'id' => 'wb-entitieswithoutpage-language',
				'name' => 'language',
			) );

		$matchers['submit'] = array(
			'tag' => 'input',
			'attributes' => array(
				'id' => 'wikibase-entitieswithoutpage-submit',
				'type' => 'submit',
				'name' => '',
			) );

		list( $output, ) = $this->executeSpecialPage( '' );
		foreach ( $matchers as $key => $matcher ) {
			$this->assertTag( $matcher, $output, "Failed to match html output with tag '{$key}''" );
		}

		list( $output, ) = $this->executeSpecialPage( 'acceptedlanguage' );
		$this->assertContains( 'value="acceptedlanguage"', $output );
	}

}
