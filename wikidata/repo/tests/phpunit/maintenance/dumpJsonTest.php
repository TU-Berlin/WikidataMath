<?php

namespace Wikibase\Test;

use DataValues\StringValue;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Services\Entity\NullEntityPrefetcher;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\AliasGroup;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\DumpJson;
use Wikibase\Repo\Test\MockEntityPerPage;

/**
 * @covers Wikibase\DumpJson
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class DumpJsonTest extends MediaWikiTestCase {

	public function testScript() {
		$dumpScript = new DumpJson();

		$mockRepo = new MockRepository();
		$mockEntityPerPage = new MockEntityPerPage();

		$snakList = new SnakList();
		$snakList->addSnak( new PropertySomeValueSnak( new PropertyId( 'P12' ) ) );
		$snakList->addSnak( new PropertyValueSnak( new PropertyId( 'P12' ), new StringValue( 'stringVal' ) ) );
		/** @var Entity[] $testEntities */
		$testEntities = array(
			new Item( new ItemId( 'Q1' ) ),
			new Property( new PropertyId( 'P1' ), null, 'string' ),
			new Property(
				new PropertyId( 'P12' ),
				null,
				'string',
				new StatementList( array(
					new Statement(
						// P999 is non existent thus the datatype will not be present
						new PropertySomeValueSnak( new PropertyId( 'P999' ) ),
						null,
						null,
						'GUID1'
					)
				) )
			),
			new Item(
				new ItemId( 'Q2' ),
				new Fingerprint(
					new TermList( array(
						new Term( 'en', 'en-label' ),
						new Term( 'de', 'de-label' ),
					) ),
					new TermList( array(
						new Term( 'fr', 'en-desc' ),
						new Term( 'de', 'de-desc' ),
					) ),
					new AliasGroupList( array(
						new AliasGroup( 'en', array( 'ali1', 'ali2' ) ),
						new AliasGroup( 'dv', array( 'ali11', 'ali22' ) )
					) )
				),
				new SiteLinkList( array(
					new SiteLink( 'enwiki', 'Berlin' ),
					new SiteLink( 'dewiki', 'England', array( new ItemId( 'Q1' ) ) )
				) ),
				new StatementList( array(
					new Statement(
						new PropertySomeValueSnak( new PropertyId( 'P12' ) ),
						null,
						null,
						'GUID1'
					),
					new Statement(
						new PropertySomeValueSnak( new PropertyId( 'P12' ) ),
						$snakList,
						new ReferenceList( array(
							new Reference( array(
								new PropertyValueSnak( new PropertyId( 'P12' ), new StringValue( 'refSnakVal' ) ),
								new PropertyNoValueSnak( new PropertyId( 'P12' ) ),
							) ),
						) ),
						'GUID2'
					)
				) )
			)
		);

		foreach ( $testEntities as $key => $testEntity ) {
			$mockRepo->putEntity( $testEntity );
			$mockEntityPerPage->addEntityPage( $testEntity->getId(), $key );
		}

		$dumpScript->setServices(
			$mockEntityPerPage,
			new NullEntityPrefetcher(),
			$this->getMockPropertyDataTypeLookup(),
			$mockRepo
		);

		$logFileName = tempnam( sys_get_temp_dir(), "Wikibase-DumpJsonTest" );
		$outFileName = tempnam( sys_get_temp_dir(), "Wikibase-DumpJsonTest" );

		$dumpScript->loadParamsAndArgs(
			null,
			array(
				'log' => $logFileName,
				'output' => $outFileName,
			)
		);

		$dumpScript->execute();

		$expectedLog = file_get_contents( __DIR__ . '/../data/maintenance/dumpJson-log.txt' );
		$expectedOut = file_get_contents( __DIR__ . '/../data/maintenance/dumpJson-out.txt' );

		$this->assertEquals(
			$this->fixLineEndings( $expectedLog ),
			$this->fixLineEndings( file_get_contents( $logFileName ) )
		);
		$this->assertEquals(
			$this->fixLineEndings( $expectedOut ),
			$this->fixLineEndings( file_get_contents( $outFileName ) )
		);
	}

	/**
	 * @return PropertyDataTypeLookup
	 */
	private function getMockPropertyDataTypeLookup() {
		$mockDataTypeLookup = $this->getMock(
			'\Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup'
		);
		$mockDataTypeLookup->expects( $this->any() )
			->method( 'getDataTypeIdForProperty' )
			->will( $this->returnCallback( function( PropertyId $id ) {
				if ( $id->getSerialization() === 'P999' ) {
					throw new PropertyDataTypeLookupException( $id );
				}
				return 'DtIdFor_' . $id->getSerialization();
			} ) );
		return $mockDataTypeLookup;
	}

	private function fixLineEndings( $string ) {
		return preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", $string );
	}

}
