<?php

namespace Wikibase\Repo\Api;

use ApiResult;
use Revision;
use SiteStore;
use Status;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\TermList;
use Wikibase\EntityRevision;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\Serialization\CallbackFactory;
use Wikibase\Lib\Serialization\SerializationModifier;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikimedia\Assert\Assert;

/**
 * Builder for Api Results
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 * @author Daniel Kinzler
 */
class ResultBuilder {

	/**
	 * @var ApiResult
	 */
	private $result;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var SerializerFactory
	 */
	private $serializerFactory;

	/**
	 * @var SiteStore
	 */
	private $siteStore;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $dataTypeLookup;

	/**
	 * @var bool|null when special elements such as '_element' are needed by the formatter.
	 */
	private $addMetaData;

	/**
	 * @var SerializationModifier
	 */
	private $modifier;

	/**
	 * @var CallbackFactory
	 */
	private $callbackFactory;

	/**
	 * @var int
	 */
	private $missingEntityCounter = -1;

	/**
	 * @param ApiResult $result
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param SerializerFactory $serializerFactory
	 * @param SiteStore $siteStore
	 * @param PropertyDataTypeLookup $dataTypeLookup
	 * @param bool|null $addMetaData when special elements such as '_element' are needed
	 */
	public function __construct(
		ApiResult $result,
		EntityTitleLookup $entityTitleLookup,
		SerializerFactory $serializerFactory,
		SiteStore $siteStore,
		PropertyDataTypeLookup $dataTypeLookup,
		$addMetaData = null
	) {
		$this->result = $result;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->serializerFactory = $serializerFactory;
		$this->siteStore = $siteStore;
		$this->dataTypeLookup = $dataTypeLookup;
		$this->addMetaData = $addMetaData;

		$this->modifier = new SerializationModifier();
		$this->callbackFactory = new CallbackFactory();
	}

	/**
	 * @since 0.5
	 *
	 * @param $success bool|int|null
	 */
	public function markSuccess( $success = true ) {
		$value = (int)$success;

		Assert::parameter(
			$value == 1 || $value == 0,
			'$success',
			'$success must evaluate to either 1 or 0 when casted to integer'
		);

		$this->result->addValue( null, 'success', $value );
	}

	/**
	 * Adds a list of values for the given path and name.
	 * This automatically sets the indexed tag name, if appropriate.
	 *
	 * To set atomic values or records, use setValue() or appendValue().
	 *
	 * @see ApiResult::addValue
	 * @see ApiResult::setIndexedTagName
	 * @see ResultBuilder::setValue()
	 * @see ResultBuilder::appendValue()
	 *
	 * @since 0.5
	 *
	 * @param $path array|string|null
	 * @param $name string
	 * @param $values array
	 * @param string $tag tag name to use for elements of $values if not already present
	 */
	public function setList( $path, $name, array $values, $tag ) {
		$this->checkPathType( $path );
		Assert::parameterType( 'string', $name, '$name' );
		Assert::parameterType( 'string', $tag, '$tag' );

		if ( $this->addMetaData ) {
			if ( !array_key_exists( ApiResult::META_TYPE, $values ) ) {
				ApiResult::setArrayType( $values, 'array' );
			}
			if ( !array_key_exists( ApiResult::META_INDEXED_TAG_NAME, $values ) ) {
				ApiResult::setIndexedTagName( $values, $tag );
			}
		}

		$this->result->addValue( $path, $name, $values );
	}

	/**
	 * Set an atomic value (or record) for the given path and name.
	 * If the value is an array, it should be a record (associative), not a list.
	 * For adding lists, use setList().
	 *
	 * @see ResultBuilder::setList()
	 * @see ResultBuilder::appendValue()
	 * @see ApiResult::addValue
	 *
	 * @since 0.5
	 *
	 * @param $path array|string|null
	 * @param $name string
	 * @param $value mixed
	 */
	public function setValue( $path, $name, $value ) {
		$this->checkPathType( $path );
		Assert::parameterType( 'string', $name, '$name' );
		$this->checkValueIsNotList( $value );

		$this->result->addValue( $path, $name, $value );
	}

	/**
	 * Appends a value to the list at the given path.
	 * This automatically sets the indexed tag name, if appropriate.
	 *
	 * If the value is an array, it should be associative, not a list.
	 * For adding lists, use setList().
	 *
	 * @see ResultBuilder::setList()
	 * @see ResultBuilder::setValue()
	 * @see ApiResult::addValue
	 * @see ApiResult::setIndexedTagName_internal
	 *
	 * @since 0.5
	 *
	 * @param $path array|string|null
	 * @param $key int|string|null the key to use when appending, or null for automatic.
	 * May be ignored even if given, based on $this->addMetaData.
	 * @param $value mixed
	 * @param string $tag tag name to use for $value in indexed mode
	 */
	public function appendValue( $path, $key, $value, $tag ) {
		$this->checkPathType( $path );
		$this->checkKeyType( $key );
		Assert::parameterType( 'string', $tag, '$tag' );
		$this->checkValueIsNotList( $value );

		$this->result->addValue( $path, $key, $value );
		if ( $this->addMetaData ) {
			$this->result->addIndexedTagName( $path, $tag );
		}
	}

	/**
	 * @param array|string|null $path
	 */
	private function checkPathType( $path ) {
		Assert::parameter(
			is_string( $path ) || is_array( $path ) || is_null( $path ),
			'$path',
			'$path must be an array (or null)'
		);
	}

	/**
	 * @param $key int|string|null the key to use when appending, or null for automatic.
	 */
	private function checkKeyType( $key ) {
		Assert::parameter(
			is_string( $key ) || is_int( $key ) || is_null( $key ),
			'$key',
			'$key must be an array (or null)'
		);
	}

	/**
	 * @param mixed $value
	 */
	private function checkValueIsNotList( $value ) {
		Assert::parameter(
			!( is_array( $value ) && isset( $value[0] ) ),
			'$value',
			'$value must not be a list'
		);
	}

	/**
	 * Get serialized entity for the EntityRevision and add it to the result
	 *
	 * @param string|null $sourceEntityIdSerialization EntityId used to retreive $entityRevision
	 *        Used as the key for the entity in the 'entities' structure and for adding redirect
	 *     info Will default to the entity's serialized ID if null. If given this must be the
	 *     entity id before any redirects were resolved.
	 * @param EntityRevision $entityRevision
	 * @param string[]|string $props a list of fields to include, or "all"
	 * @param string[]|null $filterSiteIds A list of site IDs to filter by
	 * @param string[] $filterLangCodes A list of language codes to filter by
	 * @param LanguageFallbackChain[] $fallbackChains with keys of the origional language
	 *
	 * @since 0.5
	 */
	public function addEntityRevision(
		$sourceEntityIdSerialization,
		EntityRevision $entityRevision,
		$props = 'all',
		array $filterSiteIds = null,
		array $filterLangCodes = array(),
		array $fallbackChains = array()
	) {
		$entity = $entityRevision->getEntity();
		$entityId = $entity->getId();

		if ( $sourceEntityIdSerialization === null ) {
			$sourceEntityIdSerialization = $entityId->getSerialization();
		}

		$record = array();

		//if there are no props defined only return type and id..
		if ( $props === array() ) {
			$record['id'] = $entityId->getSerialization();
			$record['type'] = $entityId->getEntityType();
		} else {
			if ( $props == 'all' || in_array( 'info', $props ) ) {
				$title = $this->entityTitleLookup->getTitleForId( $entityId );
				$record['pageid'] = $title->getArticleID();
				$record['ns'] = $title->getNamespace();
				$record['title'] = $title->getPrefixedText();
				$record['lastrevid'] = $entityRevision->getRevisionId();
				$record['modified'] = wfTimestamp( TS_ISO_8601, $entityRevision->getTimestamp() );
			}
			if ( $sourceEntityIdSerialization !== $entityId->getSerialization() ) {
				$record['redirects'] = array(
					'from' => $sourceEntityIdSerialization,
					'to' => $entityId->getSerialization()
				);
			}

			$entitySerialization = $this->getEntityArray(
				$entity,
				$props,
				$filterSiteIds,
				$filterLangCodes,
				$fallbackChains
			);

			$record = array_merge( $record, $entitySerialization );
		}

		$this->appendValue( array( 'entities' ), $sourceEntityIdSerialization, $record, 'entity' );
		if ( $this->addMetaData ) {
			$this->result->addArrayType( array( 'entities' ), 'kvp', 'id' );
			$this->result->addValue(
				array( 'entities' ),
				ApiResult::META_KVP_MERGE,
				true,
				ApiResult::OVERRIDE
			);
		}
	}

	/**
	 * @see ResultBuilder::addEntityRevision
	 *
	 * @param Entity $entity
	 * @param array|string $props
	 * @param string[]|null $filterSiteIds
	 * @param string[] $filterLangCodes
	 * @param LanguageFallbackChain[] $fallbackChains
	 *
	 * @return array
	 */
	private function getEntityArray(
		Entity $entity,
		$props,
		array $filterSiteIds = null,
		array $filterLangCodes,
		array $fallbackChains
	) {
		$entitySerializer = $this->serializerFactory->newEntitySerializer();
		$serialization = $entitySerializer->serialize( $entity );

		$serialization = $this->filterEntitySerializationUsingProps( $serialization, $props );

		if ( $props == 'all' || in_array( 'sitelinks/urls', $props ) ) {
			$serialization = $this->injectEntitySerializationWithSiteLinkUrls( $serialization );
		}
		$serialization = $this->sortEntitySerializationSiteLinks( $serialization );
		$serialization = $this->injectEntitySerializationWithDataTypes( $serialization );
		$serialization = $this->filterEntitySerializationUsingSiteIds( $serialization, $filterSiteIds );
		if ( !empty( $fallbackChains ) ) {
			$serialization = $this->addEntitySerializationFallbackInfo( $serialization, $fallbackChains );
		}
		$serialization = $this->filterEntitySerializationUsingLangCodes(
			$serialization,
			$filterLangCodes
		);

		if ( $this->addMetaData ) {
			$serialization = $this->getEntitySerializationWithMetaData( $serialization );
		}

		return $serialization;
	}

	/**
	 * @param array $serialization
	 * @param string|array $props
	 *
	 * @return array
	 */
	private function filterEntitySerializationUsingProps( array $serialization, $props ) {
		if ( $props !== 'all' ) {
			if ( !in_array( 'labels', $props ) ) {
				unset( $serialization['labels'] );
			}
			if ( !in_array( 'descriptions', $props ) ) {
				unset( $serialization['descriptions'] );
			}
			if ( !in_array( 'aliases', $props ) ) {
				unset( $serialization['aliases'] );
			}
			if ( !in_array( 'claims', $props ) ) {
				unset( $serialization['claims'] );
			}
			if ( !in_array( 'sitelinks', $props ) ) {
				unset( $serialization['sitelinks'] );
			}
		}
		return $serialization;
	}

	private function injectEntitySerializationWithSiteLinkUrls( array $serialization ) {
		if ( isset( $serialization['sitelinks'] ) ) {
			$serialization['sitelinks'] = $this->getSiteLinkListArrayWithUrls( $serialization['sitelinks'] );
		}
		return $serialization;
	}

	private function sortEntitySerializationSiteLinks( array $serialization ) {
		if ( isset( $serialization['sitelinks'] ) ) {
			ksort( $serialization['sitelinks'] );
		}
		return $serialization;
	}

	private function injectEntitySerializationWithDataTypes( array $serialization ) {
		$serialization = $this->modifier->modifyUsingCallback(
			$serialization,
			'claims/*/*/mainsnak',
			$this->callbackFactory->getCallbackToAddDataTypeToSnak( $this->dataTypeLookup )
		);
		$serialization = $this->getArrayWithDataTypesInGroupedSnakListAtPath(
			$serialization,
			'claims/*/*/qualifiers'
		);
		$serialization = $this->getArrayWithDataTypesInGroupedSnakListAtPath(
			$serialization,
			'claims/*/*/references/*/snaks'
		);
		return $serialization;
	}

	private function filterEntitySerializationUsingSiteIds(
		array $serialization,
		array $siteIds = null
	) {
		if ( !empty( $siteIds ) && array_key_exists( 'sitelinks', $serialization ) ) {
			foreach ( $serialization['sitelinks'] as $siteId => $siteLink ) {
				if ( is_array( $siteLink ) && !in_array( $siteLink['site'], $siteIds ) ) {
					unset( $serialization['sitelinks'][$siteId] );
				}
			}
		}
		return $serialization;
	}

	/**
	 * @param array $serialization
	 * @param LanguageFallbackChain[] $fallbackChains
	 *
	 * @return array
	 */
	private function addEntitySerializationFallbackInfo(
		array $serialization,
		array $fallbackChains
	) {
		if ( isset( $serialization['labels'] ) ) {
			$serialization['labels'] = $this->getTermsSerializationWithFallbackInfo(
				$serialization['labels'],
				$fallbackChains
			);
		}

		if ( isset( $serialization['descriptions'] ) ) {
			$serialization['descriptions'] = $this->getTermsSerializationWithFallbackInfo(
				$serialization['descriptions'],
				$fallbackChains
			);
		}

		return $serialization;
	}

	/**
	 * @param array $serialization
	 * @param LanguageFallbackChain[] $fallbackChains
	 *
	 * @return array
	 */
	private function getTermsSerializationWithFallbackInfo(
		array $serialization,
		array $fallbackChains
	) {
		$newSerialization = $serialization;
		foreach ( $fallbackChains as $requestedLanguageCode => $fallbackChain ) {
			if ( !array_key_exists( $requestedLanguageCode, $serialization ) ) {
				$fallbackSerialization = $fallbackChain->extractPreferredValue( $serialization );
				if ( $fallbackSerialization !== null ) {
					if ( $fallbackSerialization['source'] !== null ) {
						$fallbackSerialization['source-language'] = $fallbackSerialization['source'];
					}
					unset( $fallbackSerialization['source'] );
					if ( $requestedLanguageCode !== $fallbackSerialization['language'] ) {
						$fallbackSerialization['for-language'] = $requestedLanguageCode;
					}
					$newSerialization[$requestedLanguageCode] = $fallbackSerialization;
				}
			}
		}
		return $newSerialization;
	}

	/**
	 * @param array $serialization
	 * @param string[] $langCodes
	 *
	 * @return array
	 */
	private function filterEntitySerializationUsingLangCodes(
		array $serialization,
		array $langCodes
	) {
		if ( !empty( $langCodes ) ) {
			if ( array_key_exists( 'labels', $serialization ) ) {
				foreach ( $serialization['labels'] as $langCode => $languageArray ) {
					if ( !in_array( $langCode, $langCodes ) ) {
						unset( $serialization['labels'][$langCode] );
					}
				}
			}
			if ( array_key_exists( 'descriptions', $serialization ) ) {
				foreach ( $serialization['descriptions'] as $langCode => $languageArray ) {
					if ( !in_array( $langCode, $langCodes ) ) {
						unset( $serialization['descriptions'][$langCode] );
					}
				}
			}
			if ( array_key_exists( 'aliases', $serialization ) ) {
				foreach ( $serialization['aliases'] as $langCode => $languageArray ) {
					if ( !in_array( $langCode, $langCodes ) ) {
						unset( $serialization['aliases'][$langCode] );
					}
				}
			}
		}
		return $serialization;
	}

	private function getEntitySerializationWithMetaData( array $serialization ) {
		$arrayTypes = array(
			'aliases' => 'id',
			'claims/*/*/references/*/snaks' => 'id',
			'claims/*/*/qualifiers' => 'id',
			'claims' => 'id',
			'descriptions' => 'language',
			'labels' => 'language',
			'sitelinks' => 'site',
		);
		foreach ( $arrayTypes as $path => $keyName ) {
			$serialization = $this->modifier->modifyUsingCallback(
				$serialization,
				$path,
				$this->callbackFactory->getCallbackToSetArrayType( 'kvp', $keyName )
			);
		}

		$kvpMergeArrays = array(
			'descriptions',
			'labels',
			'sitelinks',
		);
		foreach ( $kvpMergeArrays as $path ) {
			$serialization = $this->modifier->modifyUsingCallback(
				$serialization,
				$path,
				function( $array ) {
					if ( is_array( $array ) ) {
						$array[ApiResult::META_KVP_MERGE] = true;
					}
					return $array;
				}
			);
		}

		$indexTags = array(
			'labels' => 'label',
			'descriptions' => 'description',
			'aliases/*' => 'alias',
			'aliases' => 'language',
			'sitelinks/*/badges' => 'badge',
			'sitelinks' => 'sitelink',
			'claims/*/*/qualifiers/*' => 'qualifiers',
			'claims/*/*/qualifiers' => 'property',
			'claims/*/*/qualifiers-order' => 'property',
			'claims/*/*/references/*/snaks/*' => 'snak',
			'claims/*/*/references/*/snaks' => 'property',
			'claims/*/*/references/*/snaks-order' => 'property',
			'claims/*/*/references' => 'reference',
			'claims/*' => 'claim',
			'claims' => 'property',
		);
		foreach ( $indexTags as $path => $tag ) {
			$serialization = $this->modifier->modifyUsingCallback(
				$serialization,
				$path,
				$this->callbackFactory->getCallbackToIndexTags( $tag )
			);
		}

		return $serialization;
	}

	/**
	 * Get serialized information for the EntityId and add them to result
	 *
	 * @param EntityId $entityId
	 * @param string|array|null $path
	 *
	 * @since 0.5
	 */
	public function addBasicEntityInformation( EntityId $entityId, $path ) {
		$this->setValue( $path, 'id', $entityId->getSerialization() );
		$this->setValue( $path, 'type', $entityId->getEntityType() );
	}

	/**
	 * Get serialized labels and add them to result
	 *
	 * @since 0.5
	 *
	 * @param TermList $labels the labels to insert in the result
	 * @param array|string $path where the data is located
	 */
	public function addLabels( TermList $labels, $path ) {
		$this->addTermList( $labels, 'labels', 'label', $path );
	}

	/**
	 * Adds fake serialization to show a label has been removed
	 *
	 * @since 0.5
	 *
	 * @param string $language
	 * @param array|string $path where the data is located
	 */
	public function addRemovedLabel( $language, $path ) {
		$this->addRemovedTerm( $language, 'labels', 'label', $path );
	}

	/**
	 * Get serialized descriptions and add them to result
	 *
	 * @since 0.5
	 *
	 * @param TermList $descriptions the descriptions to insert in the result
	 * @param array|string $path where the data is located
	 */
	public function addDescriptions( TermList $descriptions, $path ) {
		$this->addTermList( $descriptions, 'descriptions', 'description', $path );
	}

	/**
	 * Adds fake serialization to show a label has been removed
	 *
	 * @since 0.5
	 *
	 * @param string $language
	 * @param array|string $path where the data is located
	 */
	public function addRemovedDescription( $language, $path ) {
		$this->addRemovedTerm( $language, 'descriptions', 'description', $path );
	}

	/**
	 * Get serialized TermList and add it to the result
	 *
	 * @param TermList $termList
	 * @param string $name
	 * @param string $tag
	 * @param array|string $path where the data is located
	 */
	private function addTermList( TermList $termList, $name, $tag, $path ) {
		$serializer = $this->serializerFactory->newTermListSerializer();
		$value = $serializer->serialize( $termList );
		if ( $this->addMetaData ) {
			ApiResult::setArrayType( $value, 'kvp', 'language' );
			$value[ApiResult::META_KVP_MERGE] = true;
		}
		$this->setList( $path, $name, $value, $tag );
	}

	/**
	 * Adds fake serialization to show a term has been removed
	 *
	 * @param string $language
	 * @param string $name
	 * @param string $tag
	 * @param array|string $path where the data is located
	 */
	private function addRemovedTerm( $language, $name, $tag, $path ) {
		$value = array(
			$language => array(
				'language' => $language,
				'removed' => '',
			)
		);
		if ( $this->addMetaData ) {
			ApiResult::setArrayType( $value, 'kvp', 'language' );
			$value[ApiResult::META_KVP_MERGE] = true;
		}
		$this->setList( $path, $name, $value, $tag );
	}

	/**
	 * Get serialized AliasGroupList and add it to result
	 *
	 * @since 0.5
	 *
	 * @param AliasGroupList $aliasGroupList the AliasGroupList to set in the result
	 * @param array|string $path where the data is located
	 */
	public function addAliasGroupList( AliasGroupList $aliasGroupList, $path ) {
		$serializer = $this->serializerFactory->newAliasGroupListSerializer();
		$values = $serializer->serialize( $aliasGroupList );

		if ( $this->addMetaData ) {
			$values = $this->modifier->modifyUsingCallback(
				$values,
				null,
				$this->callbackFactory->getCallbackToSetArrayType( 'kvp', 'id' )
			);
			$values = $this->modifier->modifyUsingCallback(
				$values,
				'*',
				$this->callbackFactory->getCallbackToIndexTags( 'alias' )
			);
		}

		$this->setList( $path, 'aliases', $values, 'language' );
		ApiResult::setArrayType( $values, 'kvp', 'id' );
	}

	/**
	 * Get serialized sitelinks and add them to result
	 *
	 * @since 0.5
	 *
	 * @todo use a SiteLinkListSerializer when created in DataModelSerialization here
	 *
	 * @param SiteLinkList $siteLinkList the site links to insert in the result
	 * @param array|string $path where the data is located
	 * @param bool $addUrl
	 */
	public function addSiteLinkList( SiteLinkList $siteLinkList, $path, $addUrl = false ) {
		$serializer = $this->serializerFactory->newSiteLinkSerializer();

		$values = array();
		foreach ( $siteLinkList->toArray() as $siteLink ) {
			$values[$siteLink->getSiteId()] = $serializer->serialize( $siteLink );
		}

		if ( $addUrl ) {
			$values = $this->getSiteLinkListArrayWithUrls( $values );
		}

		if ( $this->addMetaData ) {
			$values = $this->getSiteLinkListArrayWithMetaData( $values );
		}

		$this->setList( $path, 'sitelinks', $values, 'sitelink' );
	}

	private function getSiteLinkListArrayWithUrls( array $array ) {
		$siteStore = $this->siteStore;
		$addUrlCallback = function( $array ) use ( $siteStore ) {
			$site = $siteStore->getSite( $array['site'] );
			if ( $site !== null ) {
				$array['url'] = $site->getPageUrl( $array['title'] );
			}
			return $array;
		};
		return $this->modifier->modifyUsingCallback( $array, '*', $addUrlCallback );
	}

	private function getSiteLinkListArrayWithMetaData( array $array ) {
		$array = $this->modifier->modifyUsingCallback(
			$array,
			null,
			$this->callbackFactory->getCallbackToSetArrayType( 'kvp', 'site' )
		);
		$array[ApiResult::META_KVP_MERGE] = true;
		$array = $this->modifier->modifyUsingCallback(
			$array,
			'*/badges',
			$this->callbackFactory->getCallbackToIndexTags( 'badge' )
		);
		return $array;
	}

	/**
	 * Adds fake serialization to show a sitelink has been removed
	 *
	 * @since 0.5
	 *
	 * @param SiteLinkList $siteLinkList
	 * @param array|string $path where the data is located
	 */
	public function addRemovedSiteLinks( SiteLinkList $siteLinkList, $path ) {
		$serializer = $this->serializerFactory->newSiteLinkSerializer();
		$values = array();
		foreach ( $siteLinkList->toArray() as $siteLink ) {
			$value = $serializer->serialize( $siteLink );
			$value['removed'] = '';
			$values[$siteLink->getSiteId()] = $value;
		}
		if ( $this->addMetaData ) {
			$values = $this->modifier->modifyUsingCallback(
				$values,
				null,
				$this->callbackFactory->getCallbackToSetArrayType( 'kvp', 'site' )
			);
			$values[ApiResult::META_KVP_MERGE] = true;
		}
		$this->setList( $path, 'sitelinks', $values, 'sitelink' );
	}

	/**
	 * Get serialized claims and add them to result
	 *
	 * @since 0.5
	 *
	 * @param Statement[] $statements the labels to set in the result
	 * @param array|string $path where the data is located
	 * @param array|string $props a list of fields to include, or "all"
	 */
	public function addStatements( array $statements, $path, $props = 'all' ) {
		$serializer = $this->serializerFactory->newStatementListSerializer();

		$values = $serializer->serialize( new StatementList( $statements ) );

		if ( is_array( $props ) && !in_array( 'references', $props ) ) {
			$values = $this->modifier->modifyUsingCallback(
				$values,
				'*/*',
				function ( $array ) {
					unset( $array['references'] );
					return $array;
				}
			);
		}

		$values = $this->getArrayWithAlteredClaims( $values, '*/*/' );

		if ( $this->addMetaData ) {
			$values = $this->getClaimsArrayWithMetaData( $values, '*/*/' );
			$values = $this->modifier->modifyUsingCallback(
				$values,
				null,
				$this->callbackFactory->getCallbackToSetArrayType( 'kvp', 'id' )
			);
			$values = $this->modifier->modifyUsingCallback(
				$values,
				'*',
				$this->callbackFactory->getCallbackToIndexTags( 'claim' )
			);
		}

		$values = $this->modifier->modifyUsingCallback(
			$values,
			'*/*/mainsnak',
			$this->callbackFactory->getCallbackToAddDataTypeToSnak( $this->dataTypeLookup )
		);

		if ( $this->addMetaData ) {
			ApiResult::setArrayType( $values, 'kvp', 'id' );
		}

		$this->setList( $path, 'claims', $values, 'property' );
	}

	/**
	 * Get serialized claim and add it to result
	 *
	 * @param Statement $statement
	 *
	 * @since 0.5
	 */
	public function addStatement( Statement $statement ) {
		$serializer = $this->serializerFactory->newStatementSerializer();

		//TODO: this is currently only used to add a Claim as the top level structure,
		//      with a null path and a fixed name. Would be nice to also allow claims
		//      to be added to a list, using a path and a id key or index.

		$value = $serializer->serialize( $statement );

		$value = $this->getArrayWithAlteredClaims( $value );

		if ( $this->addMetaData ) {
			$value = $this->getClaimsArrayWithMetaData( $value );
		}

		$value = $this->modifier->modifyUsingCallback(
			$value,
			'mainsnak',
			$this->callbackFactory->getCallbackToAddDataTypeToSnak( $this->dataTypeLookup )
		);

		$this->setValue( null, 'claim', $value );
	}

	/**
	 * @param array $array
	 * @param string $claimPath to the claim array/arrays with trailing /
	 *
	 * @return array
	 */
	private function getArrayWithAlteredClaims(
		array $array,
		$claimPath = ''
	) {
		$array = $this->getArrayWithDataTypesInGroupedSnakListAtPath(
			$array,
			$claimPath . 'references/*/snaks'
		);
		$array = $this->getArrayWithDataTypesInGroupedSnakListAtPath(
			$array,
			$claimPath . 'qualifiers'
		);
		$array = $this->modifier->modifyUsingCallback(
			$array,
			$claimPath . 'mainsnak',
			$this->callbackFactory->getCallbackToAddDataTypeToSnak( $this->dataTypeLookup )
		);
		return $array;
	}

	/**
	 * @param array $array
	 * @param string $claimPath to the claim array/arrays with trailing /
	 *
	 * @return array
	 */
	private function getClaimsArrayWithMetaData( array $array, $claimPath = '' ) {
		$metaDataModifications = array(
			'references/*/snaks/*' => array(
				$this->callbackFactory->getCallbackToIndexTags( 'snak' ),
			),
			'references/*/snaks' => array(
				$this->callbackFactory->getCallbackToSetArrayType( 'kvp', 'id' ),
				$this->callbackFactory->getCallbackToIndexTags( 'property' ),
			),
			'references/*/snaks-order' => array(
				$this->callbackFactory->getCallbackToIndexTags( 'property' )
			),
			'references' => array(
				$this->callbackFactory->getCallbackToIndexTags( 'reference' ),
			),
			'qualifiers/*' => array(
				$this->callbackFactory->getCallbackToIndexTags( 'qualifiers' ),
			),
			'qualifiers' => array(
				$this->callbackFactory->getCallbackToSetArrayType( 'kvp', 'id' ),
				$this->callbackFactory->getCallbackToIndexTags( 'property' ),
			),
			'qualifiers-order' => array(
				$this->callbackFactory->getCallbackToIndexTags( 'property' )
			),
			'mainsnak' => array(
				$this->callbackFactory->getCallbackToAddDataTypeToSnak( $this->dataTypeLookup ),
			),
		);

		foreach ( $metaDataModifications as $path => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$array = $this->modifier->modifyUsingCallback( $array, $claimPath . $path, $callback );
			}
		}

		return $array;
	}

	/**
	 * Get serialized reference and add it to result
	 *
	 * @param Reference $reference
	 *
	 * @since 0.5
	 */
	public function addReference( Reference $reference ) {
		$serializer = $this->serializerFactory->newReferenceSerializer();

		//TODO: this is currently only used to add a Reference as the top level structure,
		//      with a null path and a fixed name. Would be nice to also allow references
		//      to be added to a list, using a path and a id key or index.

		$value = $serializer->serialize( $reference );

		$value = $this->getArrayWithDataTypesInGroupedSnakListAtPath( $value, 'snaks' );

		if ( $this->addMetaData ) {
			$value = $this->getReferenceArrayWithMetaData( $value );
		}

		$this->setValue( null, 'reference', $value );
	}

	/**
	 * @param array $array
	 * @param string $path
	 *
	 * @return array
	 */
	private function getArrayWithDataTypesInGroupedSnakListAtPath( array $array, $path ) {
		return $this->modifier->modifyUsingCallback(
			$array,
			$path,
			$this->callbackFactory->getCallbackToAddDataTypeToSnaksGroupedByProperty( $this->dataTypeLookup )
		);
	}

	private function getReferenceArrayWithMetaData( array $array ) {
		$array = $this->modifier->modifyUsingCallback( $array, 'snaks-order', function ( $array ) {
			ApiResult::setIndexedTagName( $array, 'property' );
			return $array;
		} );
		$array = $this->modifier->modifyUsingCallback( $array, 'snaks', function ( $array ) {
			foreach ( $array as &$snakGroup ) {
				if ( is_array( $snakGroup ) ) {
					ApiResult::setArrayType( $array, 'array' );
					ApiResult::setIndexedTagName( $snakGroup, 'snak' );
				}
			}
			ApiResult::setArrayType( $array, 'kvp', 'id' );
			ApiResult::setIndexedTagName( $array, 'property' );
			return $array;
		} );
		return $array;
	}

	/**
	 * Add an entry for a missing entity...
	 *
	 * @param string|null $key The key under which to place the missing entity in the 'entities'
	 *        structure. If null, defaults to the 'id' field in $missingDetails if that is set;
	 *        otherwise, it defaults to using a unique negative number.
	 * @param array $missingDetails array containing key value pair missing details
	 *
	 * @since 0.5
	 */
	public function addMissingEntity( $key, array $missingDetails ) {
		if ( $key === null && isset( $missingDetails['id'] ) ) {
			$key = $missingDetails['id'];
		}

		if ( $key === null ) {
			$key = $this->missingEntityCounter;
		}

		$this->appendValue(
			'entities',
			$key,
			array_merge( $missingDetails, array( 'missing' => "" ) ),
			'entity'
		);

		if ( $this->addMetaData ) {
			$this->result->addIndexedTagName( 'entities', 'entity' );
			$this->result->addArrayType( array( 'entities' ), 'kvp', 'id' );
			$this->result->addValue(
				array( 'entities' ),
				ApiResult::META_KVP_MERGE,
				true,
				ApiResult::OVERRIDE
			);
		}

		$this->missingEntityCounter--;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param string $name
	 *
	 * @since 0.5
	 */
	public function addNormalizedTitle( $from, $to, $name = 'n' ) {
		$this->setValue(
			'normalized',
			$name,
			array( 'from' => $from, 'to' => $to )
		);
	}

	/**
	 * Adds the ID of the new revision from the Status object to the API result structure.
	 * The status value is expected to be structured in the way that EditEntity::attemptSave()
	 * resp WikiPage::doEditContent() do it: as an array, with an EntityRevision or Revision
	 *  object in the 'revision' field. If $oldRevId is set and the latest edit was null,
	 * a 'nochange' flag is also added.
	 *
	 * If no revision is found the the Status object, this method does nothing.
	 *
	 * @see ApiResult::addValue()
	 *
	 * @since 0.5
	 *
	 * @param Status $status The status to get the revision ID from.
	 * @param string|null|array $path Where in the result to put the revision id
	 * @param int|null $oldRevId The id of the latest revision of the entity before
	 *        the last (possibly null) edit
	 */
	public function addRevisionIdFromStatusToResult( Status $status, $path, $oldRevId = null ) {
		$value = $status->getValue();

		if ( isset( $value['revision'] ) ) {
			$revisionId = $this->getRevisionId( $value['revision'] );

			$this->setValue( $path, 'lastrevid', $revisionId );

			if ( $oldRevId && $oldRevId === $revisionId ) {
				// like core's ApiEditPage
				$this->setValue( $path, 'nochange', true );
			}
		}
	}

	private function getRevisionId( $revision ) {
		if ( $revision instanceof Revision ) {
			$revisionId = $revision->getId();
		} elseif ( $revision instanceof EntityRevision ) {
			$revisionId = $revision->getRevisionId();
		}

		return empty( $revisionId ) ? 0 : $revisionId;
	}

}
