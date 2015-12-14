<?php

namespace Wikibase\Test\Repo\Api;

use ApiBase;
use DOMDocument;
use DOMXPath;
use Wikibase\Repo\Api\SetSiteLink;
use Wikibase\Repo\SiteLinkTargetProvider;
use Wikibase\Test\MockSiteStore;

/**
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group WikibaseRepo
 * @group Database
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Adam Shorland
 */
class ApiXmlFormatTest extends ApiFormatTestCase {

	public function testGetEntitiesXmlFormat() {
		$entityRevision = $this->getNewEntityRevision( true );
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbgetentities',
			'ids' => $entityId
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\GetEntities', 'wbgetentities', $params );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'getentities' ), $actual );
	}

	public function testGetClaimsXmlFormat() {
		$entityRevision = $this->getNewEntityRevision( true );
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbgetclaims',
			'entity' => $entityId
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\GetClaims', 'wbgetclaims', $params );
		$actual = $this->executeApiModule( $module );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'getclaims' ), $actual );
	}

	public function testSetLabelXmlFormat() {
		$entityRevision = $this->getNewEntityRevision();
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbsetlabel',
			'id' => $entityId,
			'language' => 'en-gb',
			'value' => 'enGbLabel',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetLabel', 'wbsetlabel', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setlabel' ), $actual );

		$params = array(
			'action' => 'wbsetlabel',
			'id' => $entityId,
			'language' => 'en-gb',
			'value' => '',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetLabel', 'wbsetlabel', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setlabel-removed' ), $actual );
	}

	public function testSetDescriptionXmlFormat() {
		$entityRevision = $this->getNewEntityRevision();
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbsetdescription',
			'id' => $entityId,
			'language' => 'en-gb',
			'value' => 'enGbDescription',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetDescription', 'wbsetdescription', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setdescription' ), $actual );

		$params = array(
			'action' => 'wbsetdescription',
			'id' => $entityId,
			'language' => 'en-gb',
			'value' => '',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetDescription', 'wbsetdescription', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setdescription-removed' ), $actual );
	}

	public function testSetAliasesXmlFormat() {
		$entityRevision = $this->getNewEntityRevision();
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbsetaliases',
			'id' => $entityId,
			'language' => 'en-gb',
			'set' => 'AA|BB|CC',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetAliases', 'wbsetaliases', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setaliases' ), $actual );

		$params = array(
			'action' => 'wbsetaliases',
			'id' => $entityId,
			'language' => 'en-gb',
			'remove' => 'BB|CC',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetAliases', 'wbsetaliases', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setaliases-removed' ), $actual );
	}

	public function testSetSitelinkXmlFormat() {
		$entityRevision = $this->getNewEntityRevision();
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbsetsitelink',
			'id' => $entityId,
			'linksite' => 'enwiki',
			'linktitle' => 'Japan',
			// TODO: Test badges in output.
		);

		/** @var SetSiteLink $module */
		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetSiteLink', 'wbsetsitelink', $params, true );
		$siteTargetProvider = new SiteLinkTargetProvider( MockSiteStore::newFromTestSites(), array() );
		$module->setServices( $siteTargetProvider );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );
		//If a URL has been added just remove it as it is not always present
		$actual = str_replace( 'url="https://en.wikipedia.org/wiki/Japan"', '', $actual );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setsitelink' ), $actual );

		$params = array(
			'action' => 'wbsetsitelink',
			'id' => $entityId,
			'linksite' => 'enwiki',
			//TODO test badges in output
		);

		/** @var SetSiteLink $module */
		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetSiteLink', 'wbsetsitelink', $params, true );
		$module->setServices( $siteTargetProvider );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setsitelink-removed' ), $actual );
	}

	public function testSetClaimXmlFormat() {
		$this->getNewEntityRevision( true );

		$json = file_get_contents( __DIR__ . '/../../data/api/setclaim.json' );
		$json = $this->replaceIdsInString( $json );
		$params = array(
			'action' => 'wbsetclaim',
			'claim' => $json,
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetClaim', 'wbsetclaim', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setclaim' ), $actual );
	}

	public function testSetReferenceXmlFormat() {
		$entityRevision = $this->getNewEntityRevision( true );
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$json = file_get_contents( __DIR__ . '/../../data/api/setreference.json' );
		$json = $this->replaceIdsInString( $json );
		$params = array(
			'action' => 'wbsetreference',
			'statement' => $entityId . '$1111AAAA-43cb-ed6d-3adb-760e85bd17ee',
			'snaks' => $json,
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetReference', 'wbsetreference', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result );
		$actual = $this->replaceHashWithMock( $actual );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setreference' ), $actual );
	}

	public function testSetQualiferXmlFormat() {
		$entityRevision = $this->getNewEntityRevision( true );
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$params = array(
			'action' => 'wbsetqualifier',
			'claim' => $entityId . '$1111AAAA-43cb-ed6d-3adb-760e85bd17ee',
			'property' => $this->lastPropertyId->getSerialization(),
			'value' => '"QualiValue"',
			'snaktype' => 'value',
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\SetQualifier', 'wbsetqualifier', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result );
		$actual = $this->replaceHashWithMock( $actual );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'setqualifier' ), $actual );
	}

	public function testEditEntityXmlFormat() {
		$this->storeNewProperty();
		$entityRevision = $this->getNewEntityRevision();
		$entityId = $entityRevision->getEntity()->getId()->getSerialization();

		$json = file_get_contents( __DIR__ . '/../../data/api/editentity.json' );
		$json = $this->replaceIdsInString( $json );

		$params = array(
			'action' => 'wbeditentity',
			'id' => $entityId,
			'data' => $json,
		);

		$module = $this->getApiModule( '\Wikibase\Repo\Api\EditEntity', 'wbeditEntity', $params, true );
		$result = $this->executeApiModule( $module );
		$actual = $this->removePageInfoAttributes( $result, $entityId );
		$actual = $this->replaceHashWithMock( $actual );

		$this->assertXmlStringEqualsXmlString( $this->getExpectedXml( 'editentity' ), $actual );
	}

	private function getExpectedXml( $moduleIdentifier ) {
		$xml = file_get_contents( __DIR__ . '/../../data/api/' . $moduleIdentifier . '.xml' );
		$xml = $this->replaceIdsInString( $xml );
		$xml = $this->replaceHashWithMock( $xml );
		return $xml;
	}

	private function replaceIdsInString( $string ) {
		if ( $this->lastPropertyId !== null ) {
			$string = str_replace( '$propertyIdUnderTest', $this->lastPropertyId->getSerialization(), $string );
		}
		if ( $this->lastItemId !== null ) {
			$string = str_replace( '$itemIdUnderTest', $this->lastItemId->getSerialization(), $string );
		}
		return $string;
	}

	private function replaceHashWithMock( $string ) {
		$string = preg_replace( '/hash="\w+"/', 'hash="XXX"', $string );
		return $string;
	}

	/**
	 * @param string $xml
	 * @param string $entityId
	 *
	 * @return string
	 */
	private function removePageInfoAttributes( $xml, $entityId = null ) {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->loadXML( $xml );

		$xpath = new DOMXPath( $dom );
		if ( $entityId !== null ) {
			$element = $xpath->query( "//*[@id='$entityId']" )->item( 0 );
		} else {
			$element = $xpath->query( "//pageinfo" )->item( 0 );
		}

		$attributesToRemove = array( 'pageid', 'lastrevid', 'modified', 'title', 'ns' );

		foreach ( $attributesToRemove as $attributeToRemove ) {
			$element->removeAttribute( $attributeToRemove );
		}

		return $dom->saveXML();
	}

	/**
	 * This mimics ApiMain::executeAction with the relevant parts,
	 * including setupExternalResponse where the printer is set.
	 * The module is then executed and results printed.
	 */
	private function executeApiModule( ApiBase $module ) {
		$printer = $module->getMain()->createPrinterByName( 'xml' );

		$module->execute();

		$printer->initPrinter();
		$printer->disable();

		$printer->execute();

		return $printer->getBuffer();
	}

}
