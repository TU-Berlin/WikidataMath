<?php

namespace Wikibase\Tests;

use ConfigFactory;
use DerivativeContext;
use ImportStringSource;
use OutputPage;
use RequestContext;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\RepoHooks;
use WikiImporter;

/**
 * @covers Wikibase\RepoHooks
 *
 * @group Wikibase
 * @group WikibaseRepo
 *
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class RepoHooksTest extends \MediaWikiTestCase {

	private $saveAllowImport = false;

	public function setup() {
		parent::setup();

		$this->saveAllowImport = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'allowEntityImport' );
	}

	public function tearDown() {
		WikibaseRepo::getDefaultInstance()->getSettings()->setSetting( 'allowEntityImport', $this->saveAllowImport );

		parent::tearDown();
	}

	public function revisionInfoProvider() {
		return array(
			'empty_allowimport' => array(
				array(),
				true
			),
			'empty_noimport' => array(
				array(),
				true
			),
			'wikitext_allowimport' => array(
				array( 'model' => CONTENT_MODEL_WIKITEXT ),
				true
			),
			'wikitext_noimport' => array(
				array( 'model' => CONTENT_MODEL_WIKITEXT ),
				false
			),
			'item_allowimport' => array(
				array( 'model' => CONTENT_MODEL_WIKIBASE_ITEM ),
				false,
				'MWException'
			),
			'item_noimport' => array(
				array( 'model' => CONTENT_MODEL_WIKIBASE_ITEM ),
				true
			)
		);
	}

	/**
	 * @dataProvider revisionInfoProvider
	 */
	public function testOnImportHandleRevisionXMLTag(
		array $revisionInfo,
		$allowEntityImport,
		$expectedException = null
	) {
		//NOTE: class is unclear, see Bug T66657. But we don't use that object anyway.
		$importer = $this->getMockBuilder( 'Import' )
			->disableOriginalConstructor()
			->getMock();

		WikibaseRepo::getDefaultInstance()->getSettings()->setSetting(
			'allowEntityImport',
			$allowEntityImport
		);

		if ( $expectedException !== null ) {
			$this->setExpectedException( $expectedException );
		}

		RepoHooks::onImportHandleRevisionXMLTag( $importer, array(), $revisionInfo );
		$this->assertTrue( true ); // make PHPUnit happy
	}

	public function importProvider() {
		return array(
			'wikitext' => array( <<<XML
<mediawiki>
  <siteinfo>
    <sitename>TestWiki</sitename>
    <case>first-letter</case>
  </siteinfo>
  <page>
    <title>Bla</title><ns>0</ns>
    <revision>
      <contributor><username>Tester</username><id>0</id></contributor>
      <comment>Test</comment>
      <text>Hallo Welt</text>
      <model>wikitext</model>
      <format>text/x-wiki</format>
    </revision>
  </page>
 </mediawiki>
XML
				,
				false
			),
			'item' => array( <<<XML
<mediawiki>
  <siteinfo>
    <sitename>TestWiki</sitename>
    <case>first-letter</case>
  </siteinfo>
  <page>
    <title>Q123</title><ns>1234</ns>
    <revision>
      <contributor><username>Tester</username><id>0</id></contributor>
      <comment>Test</comment>
      <text>{ "type": "item", "id":"Q123" }</text>
      <model>wikibase-item</model>
      <format>application/json</format>
    </revision>
  </page>
 </mediawiki>
XML
				,
				false,
				'MWException'
			),
			'item (allow)' => array( <<<XML
<mediawiki>
  <siteinfo>
    <sitename>TestWiki</sitename>
    <case>first-letter</case>
  </siteinfo>
  <page>
    <title>Q123</title><ns>1234</ns>
    <revision>
      <contributor><username>Tester</username><id>0</id></contributor>
      <comment>Test</comment>
      <text>{ "type": "item", "id":"Q123" }</text>
      <model>wikibase-item</model>
      <format>application/json</format>
    </revision>
  </page>
 </mediawiki>
XML
			,
				true
			),
		);
	}

	/**
	 * @dataProvider importProvider
	 */
	public function testImportHandleRevisionXMLTag_hook( $xml, $allowImport, $expectedException = null ) {
		// WikiImporter tried to register this protocol every time, so unregister first to avoid errors.
		\MediaWiki\suppressWarnings();
		stream_wrapper_unregister( 'uploadsource' );
		\MediaWiki\restoreWarnings();

		WikibaseRepo::getDefaultInstance()->getSettings()->setSetting( 'allowEntityImport', $allowImport );

		$source = new ImportStringSource( $xml );
		$importer = new WikiImporter( $source, ConfigFactory::getDefaultInstance()->makeConfig( 'main' ) );

		$importer->setNoticeCallback( function() {
			// Do nothing for now. Could collect and compare notices.
		} );
		$importer->setPageOutCallback( function() {
		} );

		if ( $expectedException !== null ) {
			$this->setExpectedException( $expectedException );
		}

		$importer->doImport();
		$this->assertTrue( true ); // make PHPUnit happy
	}

	public function testOnOutputPageParserOutput() {
		$altLinks = array( array( 'a' => 'b' ), array( 'c', 'd' ) );

		$context = new DerivativeContext( RequestContext::getMain() );
		$out = new OutputPage( $context );

		$parserOutput = $this->getMock( 'ParserOutput' );
		$parserOutput->expects( $this->exactly( 3 ) )
			->method( 'getExtensionData' )
			->will( $this->returnCallback( function ( $key ) use ( $altLinks ) {
				if ( $key === 'wikibase-alternate-links' ) {
					return $altLinks;
				} else {
					return $key;
				}
			} ) );

		RepoHooks::onOutputPageParserOutput( $out, $parserOutput );

		$this->assertSame(
			'wikibase-view-chunks',
			$out->getProperty( 'wikibase-view-chunks' )
		);

		$this->assertSame(
			'wikibase-titletext',
			$out->getProperty( 'wikibase-titletext' )
		);

		$this->assertSame( $altLinks, $out->getLinkTags() );
	}

}
