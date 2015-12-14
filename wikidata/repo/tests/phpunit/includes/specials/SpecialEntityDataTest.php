<?php

namespace Wikibase\Test;

use DataValues\Serializers\DataValueSerializer;
use FauxRequest;
use FauxResponse;
use HttpError;
use OutputPage;
use SiteList;
use SpecialPage;
use SpecialPageTestBase;
use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Repo\LinkedData\EntityDataFormatProvider;
use Wikibase\Repo\LinkedData\EntityDataRequestHandler;
use Wikibase\Repo\LinkedData\EntityDataSerializationService;
use Wikibase\Repo\LinkedData\EntityDataUriManager;
use Wikibase\Repo\Specials\SpecialEntityData;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\Specials\SpecialEntityData
 * @covers Wikibase\Repo\Specials\SpecialWikibasePage
 *
 * @group Database
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group SpecialPage
 * @group WikibaseSpecialPage
 * @group WikibaseEntityData
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class SpecialEntityDataTest extends SpecialPageTestBase {

	const URI_BASE = 'http://acme.test/';
	const URI_DATA = 'http://data.acme.test/';

	protected function newSpecialPage() {
		$page = new SpecialEntityData();

		// why is this needed?
		$page->getContext()->setOutput( new OutputPage( $page->getContext() ) );

		$page->setRequestHandler( $this->newRequestHandler() );

		return $page;
	}

	private function newRequestHandler() {
		$mockRepository = EntityDataTestProvider::getMockRepository();

		$titleLookup = $this->getMock( 'Wikibase\Lib\Store\EntityTitleLookup' );
		$titleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				return Title::newFromText( $id->getEntityType() . ':' . $id->getSerialization() );
			} ) );

		$dataTypeLookup = $this->getMock( 'Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup' );
		$dataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnValue( 'string' ) );

		$entityDataFormatProvider = new EntityDataFormatProvider();
		$serializerFactory = new SerializerFactory(
			new DataValueSerializer(),
			SerializerFactory::OPTION_SERIALIZE_MAIN_SNAKS_WITHOUT_HASH +
			SerializerFactory::OPTION_SERIALIZE_REFERENCE_SNAKS_WITHOUT_HASH
		);

		// Note: We are testing with the actual RDF bindings. These should not change for well
		// known data types. Mocking the bindings would be nice, but is complex and not needed.
		$rdfBuilder = WikibaseRepo::getDefaultInstance()->getValueSnakRdfBuilderFactory();

		$serializationService = new EntityDataSerializationService(
			self::URI_BASE,
			self::URI_DATA,
			$mockRepository,
			$titleLookup,
			$dataTypeLookup,
			$rdfBuilder,
			new SiteList(),
			$entityDataFormatProvider,
			$serializerFactory,
			new MockSiteStore()
		);

		$formats = array( 'json', 'rdfxml', 'ntriples' );
		$entityDataFormatProvider->setFormatWhiteList( $formats );

		$defaultFormat = 'rdf';
		$supportedExtensions = array_combine( $formats, $formats );

		$title = SpecialPage::getTitleFor( 'EntityData' );

		$uriManager = new EntityDataUriManager(
			$title,
			$supportedExtensions,
			$titleLookup
		);

		$useSquid = false;
		$apiFrameOptions = 'DENY';

		return new EntityDataRequestHandler(
			$uriManager,
			$titleLookup,
			new BasicEntityIdParser(),
			$mockRepository,
			$mockRepository,
			$serializationService,
			$entityDataFormatProvider,
			$defaultFormat,
			0,
			$useSquid,
			$apiFrameOptions
		);
	}

	public function provideExecute() {
		$cases = EntityDataTestProvider::provideHandleRequest();

		foreach ( $cases as $n => $case ) {
			// cases with no ID given will no longer fail be show an html form

			if ( $case[0] === '' && !isset( $case[1]['id'] ) ) {
				$cases[$n][3] = '!<p>!'; // output regex //TODO: be more specific
				$cases[$n][4] = 200; // http code
				$cases[$n][5] = array(); // response headers
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider provideExecute
	 *
	 * @param string $subpage The subpage to request (or '')
	 * @param array  $params  Request parameters
	 * @param array  $headers  Request headers
	 * @param string $expRegExp   Regex to match the output against.
	 * @param int    $expCode     Expected HTTP status code
	 * @param array  $expHeaders  Expected HTTP response headers
	 */
	public function testExecute(
		$subpage,
		array $params,
		array $headers,
		$expRegExp,
		$expCode = 200,
		array $expHeaders = array()
	) {
		$request = new FauxRequest( $params );
		$request->response()->header( 'Status: 200 OK', true, 200 ); // init/reset

		foreach ( $headers as $name => $value ) {
			$request->setHeader( strtoupper( $name ), $value );
		}

		try {
			/* @var FauxResponse $response */
			list( $output, $response ) = $this->executeSpecialPage( $subpage, $request );

			$this->assertEquals( $expCode, $response->getStatusCode(), "status code" );
			$this->assertRegExp( $expRegExp, $output, "output" );

			foreach ( $expHeaders as $name => $exp ) {
				$value = $response->getheader( $name );
				$this->assertNotNull( $value, "header: $name" );
				$this->assertInternalType( 'string', $value, "header: $name" );
				$this->assertRegExp( $exp, $value, "header: $name" );
			}
		} catch ( HttpError $e ) {
			$this->assertEquals( $expCode, $e->getStatusCode(), "status code" );
			$this->assertRegExp( $expRegExp, $e->getHTML(), "error output" );
		}
	}

}
