<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use DataValues\StringValue;
use MediaWikiTestCase;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\Repo\ParserOutput\ParserOutputJsConfigBuilder;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\ParserOutput\ParserOutputJsConfigBuilder
 *
 * @since 0.5
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ParserOutputJsConfigBuilderTest extends MediaWikiTestCase {

	public function testBuildConfigItem() {
		$item = new Item( new ItemId( 'Q5881' ) );
		$this->addLabels( $item );
		$mainSnakPropertyId = $this->addStatements( $item );

		$configBuilder = new ParserOutputJsConfigBuilder();
		$configVars = $configBuilder->build( $item );

		$this->assertWbEntityId( 'Q5881', $configVars );

		$this->assertWbEntity(
			$this->getSerialization( $item, $mainSnakPropertyId ),
			$configVars
		);

		$this->assertSerializationEqualsEntity(
			$item,
			json_decode( $configVars['wbEntity'], true )
		);
	}

	public function testBuildConfigProperty() {
		$property = new Property( new PropertyId( 'P330' ), null, 'string' );
		$this->addLabels( $property );
		$mainSnakPropertyId = $this->addStatements( $property );

		$configBuilder = new ParserOutputJsConfigBuilder();
		$configVars = $configBuilder->build( $property );

		$this->assertWbEntityId( 'P330', $configVars );

		$expectedSerialization = $this->getSerialization( $property, $mainSnakPropertyId );
		$expectedSerialization['datatype'] = 'string';

		$this->assertWbEntity( $expectedSerialization, $configVars );

		$this->assertSerializationEqualsEntity(
			$property,
			json_decode( $configVars['wbEntity'], true )
		);
	}

	public function assertWbEntityId( $expectedId, array $configVars ) {
		$this->assertEquals(
			$expectedId,
			$configVars['wbEntityId'],
			'wbEntityId'
		);
	}

	public function assertWbEntity( array $expectedSerialization, array $configVars ) {
		$this->assertEquals(
			$expectedSerialization,
			json_decode( $configVars['wbEntity'], true ),
			'wbEntity'
		);
	}

	public function assertSerializationEqualsEntity( EntityDocument $entity, $serialization ) {
		$deserializer = WikibaseRepo::getDefaultInstance()->getEntityDeserializer();
		$unserializedEntity = $deserializer->deserialize( $serialization );

		$this->assertTrue(
			$unserializedEntity->equals( $entity ),
			'unserialized entity equals entity'
		);
	}

	private function addLabels( FingerprintProvider $fingerprintProvider ) {
		$fingerprintProvider->getFingerprint()->setLabel( 'en', 'Cake' );
		$fingerprintProvider->getFingerprint()->setLabel( 'de', 'Kuchen' );
	}

	private function addStatements( StatementListProvider $statementListProvider ) {
		$propertyId = new PropertyId( 'P794' );

		$statementListProvider->getStatements()->addNewStatement(
			new PropertyValueSnak( $propertyId, new StringValue( 'kittens!' ) ),
			null,
			null,
			$this->makeGuid( $statementListProvider->getId() )
		);

		return $propertyId;
	}

	private function makeGuid( EntityId $entityId ) {
		return $entityId->getSerialization() . '$muahahaha';
	}

	private function getSerialization( EntityDocument $entity, PropertyId $propertyId ) {
		return array(
			'id' => $entity->getId()->getSerialization(),
			'type' => $entity->getType(),
			'labels' => array(
				'de' => array(
					'language' => 'de',
					'value' => 'Kuchen'
				),
				'en' => array(
					'language' => 'en',
					'value' => 'Cake'
				)
			),
			'claims' => array(
				$propertyId->getSerialization() => array(
					array(
						'id' => $this->makeGuid( $entity->getId() ),
						'mainsnak' => array(
							'snaktype' => 'value',
							'property' => $propertyId->getSerialization(),
							'datavalue' => array(
								'value' => 'kittens!',
								'type' => 'string'
							),
						),
						'type' => 'statement',
						'rank' => 'normal',
					),
				),
			),
		);
	}

}
