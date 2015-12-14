<?php

namespace Wikibase\Repo;

use DataTypes\DataTypeFactory;
use DataValues\DataValueFactory;
use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Serializers\DataValueSerializer;
use Deserializers\Deserializer;
use Hooks;
use IContextSource;
use Language;
use Serializers\Serializer;
use SiteSQLStore;
use SiteStore;
use StubObject;
use User;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\ChangeOp\ChangeOpFactoryProvider;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\EntityId\SuffixEntityIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\TermLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\EditEntityFactory;
use Wikibase\EntityFactory;
use Wikibase\Repo\ParserOutput\EntityParserOutputGeneratorFactory;
use Wikibase\InternalSerialization\DeserializerFactory as InternalDeserializerFactory;
use Wikibase\InternalSerialization\SerializerFactory as InternalSerializerFactory;
use Wikibase\LabelDescriptionDuplicateDetector;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\EntityIdLinkFormatter;
use Wikibase\Lib\EntityIdPlainLinkFormatter;
use Wikibase\Lib\EntityIdValueFormatter;
use Wikibase\Lib\FormatterLabelDescriptionLookupFactory;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\OutputFormatValueFormatterFactory;
use Wikibase\Lib\PropertyInfoDataTypeLookup;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\MediaWikiContentLanguages;
use Wikibase\Lib\WikibaseValueFormatterBuilders;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Rdf\ValueSnakRdfBuilderFactory;
use Wikibase\PropertyInfoBuilder;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\Content\ItemHandler;
use Wikibase\Repo\Content\PropertyHandler;
use Wikibase\Repo\Hooks\EditFilterHookRunner;
use Wikibase\Repo\Interactors\RedirectCreationInteractor;
use Wikibase\Repo\LinkedData\EntityDataFormatProvider;
use Wikibase\Repo\Localizer\ChangeOpValidationExceptionLocalizer;
use Wikibase\Repo\Localizer\DispatchingExceptionLocalizer;
use Wikibase\Repo\Localizer\ExceptionLocalizer;
use Wikibase\Repo\Localizer\GenericExceptionLocalizer;
use Wikibase\Repo\Localizer\MessageExceptionLocalizer;
use Wikibase\Repo\Localizer\MessageParameterFormatter;
use Wikibase\Repo\Localizer\ParseExceptionLocalizer;
use Wikibase\Repo\Notifications\ChangeNotifier;
use Wikibase\Repo\Notifications\ChangeTransmitter;
use Wikibase\Repo\Notifications\DatabaseChangeTransmitter;
use Wikibase\Repo\Notifications\HookChangeTransmitter;
use Wikibase\Repo\Store\EntityPermissionChecker;
use Wikibase\Repo\Validators\EntityConstraintProvider;
use Wikibase\Repo\Validators\SnakValidator;
use Wikibase\Repo\Validators\TermValidatorFactory;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;
use Wikibase\SettingsArray;
use Wikibase\SnakFactory;
use Wikibase\SqlStore;
use Wikibase\Store;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\Store\EntityIdLookup;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\StringNormalizer;
use Wikibase\SummaryFormatter;
use Wikibase\View\EntityViewFactory;
use Wikibase\View\Template\TemplateFactory;

/**
 * Top level factory for the WikibaseRepo extension.
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class WikibaseRepo {

	/**
	 * @var SettingsArray
	 */
	private $settings;

	/**
	 * @var DataTypeFactory|null
	 */
	private $dataTypeFactory = null;

	/**
	 * @var ValueParserFactory|null
	 */
	private $valueParserFactory = null;

	/**
	 * @var SnakConstructionService|null
	 */
	private $snakConstructionService = null;

	/**
	 * @var PropertyDataTypeLookup|null
	 */
	private $propertyDataTypeLookup = null;

	/**
	 * @var LanguageFallbackChainFactory|null
	 */
	private $languageFallbackChainFactory = null;

	/**
	 * @var StatementGuidValidator|null
	 */
	private $statementGuidValidator = null;

	/**
	 * @var EntityIdParser|null
	 */
	private $entityIdParser = null;

	/**
	 * @var StringNormalizer|null
	 */
	private $stringNormalizer = null;

	/**
	 * @var OutputFormatSnakFormatterFactory|null
	 */
	private $snakFormatterFactory = null;

	/**
	 * @var OutputFormatValueFormatterFactory|null
	 */
	private $valueFormatterFactory = null;

	/**
	 * @var SummaryFormatter|null
	 */
	private $summaryFormatter = null;

	/**
	 * @var ExceptionLocalizer|null
	 */
	private $exceptionLocalizer = null;

	/**
	 * @var SiteStore|null
	 */
	private $siteStore = null;

	/**
	 * @var Store|null
	 */
	private $store = null;

	/**
	 * @var EntityNamespaceLookup|null
	 */
	private $entityNamespaceLookup = null;

	/**
	 * @var TermLookup|null
	 */
	private $termLookup = null;

	/**
	 * @var ContentLanguages|null
	 */
	private $monolingualTextLanguages = null;

	/**
	 * @var DataTypeDefinitions
	 */
	private $dataTypeDefinitions;

	/**
	 * @var Language
	 */
	private $defaultLanguage;

	/**
	 * @var ValueSnakRdfBuilderFactory
	 */
	private $valueSnakRdfBuilderFactory;

	/**
	 * Returns the default instance constructed using newInstance().
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @since 0.4
	 *
	 * @return WikibaseRepo
	 */
	public static function getDefaultInstance() {
		global $wgWBRepoDataTypes, $wgWBRepoSettings, $wgContLang;
		static $instance = null;

		$dataTypeDefinitions = $wgWBRepoDataTypes;
		Hooks::run( 'WikibaseRepoDataTypes', array( &$dataTypeDefinitions ) );

		if ( $instance === null ) {
			$instance = new self(
				new SettingsArray( $wgWBRepoSettings ),
				new DataTypeDefinitions( $dataTypeDefinitions ),
				$wgContLang
			);
		}

		return $instance;
	}

	/**
	 * Returns the default ValidatorBuilders instance.
	 * @warning This is for use with bootstrap code in WikibaseRepo.datatypes.php only!
	 * Program logic should use WikibaseRepo::getDataTypeValidatorFactory() instead!
	 *
	 * @since 0.5
	 *
	 * @param string $reset Flag: Pass "reset" to reset the default instance
	 *
	 * @return ValidatorBuilders
	 */
	public static function getDefaultValidatorBuilders( $reset = 'noreset' ) {
		static $builders;

		if ( $builders === null || $reset === 'reset' ) {
			$wikibaseRepo = self::getDefaultInstance();
			$builders = $wikibaseRepo->newValidatorBuilders();
		}

		return $builders;
	}

	/**
	 * Returns a low level factory object for creating validators for well known data types.
	 * @warning This is for use with getDefaultValidatorBuilders() during bootstrap only!
	 * Program logic should use WikibaseRepo::getDataTypeValidatorFactory() instead!
	 *
	 * @return ValidatorBuilders
	 */
	private function newValidatorBuilders() {
		$urlSchemes = $this->settings->getSetting( 'urlSchemes' );

		return new ValidatorBuilders(
			$this->getEntityLookup(),
			$this->getEntityIdParser(),
			$urlSchemes,
			$this->getVocabularyBaseUri(),
			$this->getMonolingualTextLanguages()
		);
	}

	/**
	 * Returns the default WikibaseValueFormatterBuilders instance.
	 * @warning This is for use with bootstrap code in WikibaseRepo.datatypes.php only!
	 * Program logic should use WikibaseRepo::getSnakFormatterFactory() instead!
	 *
	 * @since 0.5
	 *
	 * @param string $reset Flag: Pass "reset" to reset the default instance
	 *
	 * @return WikibaseValueFormatterBuilders
	 */
	public static function getDefaultFormatterBuilders( $reset = 'noreset' ) {
		static $builders;

		if ( $builders === null || $reset === 'reset' ) {
			$wikibaseRepo = self::getDefaultInstance();
			$builders = $wikibaseRepo->newWikibaseValueFormatterBuilders();
		}

		return $builders;
	}

	/**
	 * Returns a low level factory object for creating formatters for well known data types.
	 *
	 * @warning This is for use with getDefaultFormatterBuilders() during bootstrap only!
	 * Program logic should use WikibaseRepo::getSnakFormatterFactory() instead!
	 *
	 * @return WikibaseValueFormatterBuilders
	 */
	private function newWikibaseValueFormatterBuilders() {
		return new WikibaseValueFormatterBuilders(
			$this->getDefaultLanguage(),
			new FormatterLabelDescriptionLookupFactory( $this->getTermLookup() ),
			new LanguageNameLookup(),
			$this->getLocalEntityUriParser(),
			$this->getEntityTitleLookup()
		);
	}

	/**
	 * @since 0.4
	 *
	 * @param SettingsArray $settings
	 * @param DataTypeDefinitions $dataTypeDefinitions
	 * @param Language|null $defaultLanguage
	 */
	public function __construct(
		SettingsArray $settings,
		DataTypeDefinitions $dataTypeDefinitions,
		Language $defaultLanguage = null
	) {
		$this->settings = $settings;
		$this->dataTypeDefinitions = $dataTypeDefinitions;
		$this->defaultLanguage = $defaultLanguage;
	}

	/**
	 * @return Language
	 */
	private function getDefaultLanguage() {
		global $wgContLang;
		return $this->defaultLanguage ?: $wgContLang;
	}

	/**
	 * @since 0.4
	 *
	 * @return DataTypeFactory
	 */
	public function getDataTypeFactory() {
		if ( $this->dataTypeFactory === null ) {
			$this->dataTypeFactory = new DataTypeFactory( $this->dataTypeDefinitions->getValueTypes() );
		}

		return $this->dataTypeFactory;
	}

	/**
	 * @since 0.5
	 *
	 * @return ValueParserFactory
	 */
	public function getValueParserFactory() {
		global $wgValueParsers;

		if ( $this->valueParserFactory === null ) {
			$callbacks = $this->dataTypeDefinitions->getParserFactoryCallbacks();

			// For backwards-compatibility, also register parsers under legacy names.
			$callbacks = array_merge( $wgValueParsers, $callbacks );

			$this->valueParserFactory = new ValueParserFactory( $callbacks );
		}

		return $this->valueParserFactory;
	}

	/**
	 * @since 0.4
	 *
	 * @return DataValueFactory
	 */
	public function getDataValueFactory() {
		return new DataValueFactory( $this->getDataValueDeserializer() );
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityContentFactory
	 */
	public function getEntityContentFactory() {
		return new EntityContentFactory( $this->getContentModelMappings() );
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityChangeFactory
	 */
	public function getEntityChangeFactory() {
		//TODO: take this from a setting or registry.
		$changeClasses = array(
			Item::ENTITY_TYPE => 'Wikibase\ItemChange',
			// Other types of entities will use EntityChange
		);

		return new EntityChangeFactory(
			$this->getEntityFactory(),
			new EntityDiffer(),
			$changeClasses
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher() {
		return $this->getStore()->getEntityStoreWatcher();
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityTitleLookup
	 */
	public function getEntityTitleLookup() {
		return $this->getEntityContentFactory();
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityIdLookup
	 */
	public function getEntityIdLookup() {
		return $this->getEntityContentFactory();
	}

	/**
	 * @since 0.5
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $uncached = '' ) {
		return $this->getStore()->getEntityRevisionLookup( $uncached );
	}

	/**
	 * @since 0.5
	 *
	 * @param User $user
	 * @param IContextSource $context
	 *
	 * @return RedirectCreationInteractor
	 */
	public function newRedirectCreationInteractor( User $user, IContextSource $context ) {
		return new RedirectCreationInteractor(
			$this->getEntityRevisionLookup( 'uncached' ),
			$this->getEntityStore(),
			$this->getEntityPermissionChecker(),
			$this->getSummaryFormatter(),
			$user,
			$this->newEditFilterHookRunner( $context ),
			$this->getStore()->getEntityRedirectLookup()
		);
	}

	/**
	 * @param IContextSource|null $context
	 *
	 * @return EditFilterHookRunner
	 */
	private function newEditFilterHookRunner( IContextSource $context = null ) {
		return new EditFilterHookRunner(
			$this->getEntityTitleLookup(),
			$this->getEntityContentFactory(),
			$context
		);
	}

	/**
	 * @since 0.5
	 *
	 * @param string $displayLanguageCode
	 *
	 * @return TermIndexSearchInteractor
	 */
	public function newTermSearchInteractor( $displayLanguageCode ) {
		return new TermIndexSearchInteractor(
			$this->getStore()->getTermIndex(),
			$this->getLanguageFallbackChainFactory(),
			$this->getBufferingTermLookup(),
			$displayLanguageCode
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return EntityStore
	 */
	public function getEntityStore() {
		return $this->getStore()->getEntityStore();
	}

	/**
	 * @since 0.4
	 *
	 * @return PropertyDataTypeLookup
	 */
	public function getPropertyDataTypeLookup() {
		if ( $this->propertyDataTypeLookup === null ) {
			$infoStore = $this->getStore()->getPropertyInfoStore();
			$retrievingLookup = new EntityRetrievingDataTypeLookup( $this->getEntityLookup() );
			$this->propertyDataTypeLookup = new PropertyInfoDataTypeLookup(
				$infoStore,
				$retrievingLookup
			);
		}

		return $this->propertyDataTypeLookup;
	}

	/**
	 * @since 0.4
	 *
	 * @return StringNormalizer
	 */
	public function getStringNormalizer() {
		if ( $this->stringNormalizer === null ) {
			$this->stringNormalizer = new StringNormalizer();
		}

		return $this->stringNormalizer;
	}

	/**
	 * @since 0.4
	 *
	 * @param string $uncached Flag string, set to 'uncached' to get an uncached direct lookup service.
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $uncached = '' ) {
		return $this->getStore()->getEntityLookup( $uncached );
	}

	/**
	 * @since 0.4
	 *
	 * @return SnakConstructionService
	 */
	public function getSnakConstructionService() {
		if ( $this->snakConstructionService === null ) {
			$snakFactory = new SnakFactory();
			$dataTypeLookup = $this->getPropertyDataTypeLookup();
			$dataTypeFactory = $this->getDataTypeFactory();
			$dataValueFactory = $this->getDataValueFactory();

			$this->snakConstructionService = new SnakConstructionService(
				$snakFactory,
				$dataTypeLookup,
				$dataTypeFactory,
				$dataValueFactory );
		}

		return $this->snakConstructionService;
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityIdParser
	 */
	public function getEntityIdParser() {
		if ( $this->entityIdParser === null ) {
			//TODO: make the ID builders configurable
			$this->entityIdParser = new DispatchingEntityIdParser( BasicEntityIdParser::getBuilders() );
		}

		return $this->entityIdParser;
	}

	/**
	 * @since 0.5
	 *
	 * @return StatementGuidParser
	 */
	public function getStatementGuidParser() {
		return new StatementGuidParser( $this->getEntityIdParser() );
	}

	/**
	 * @since 0.5
	 *
	 * @return ChangeOpFactoryProvider
	 */
	public function getChangeOpFactoryProvider() {
		return new ChangeOpFactoryProvider(
			$this->getEntityConstraintProvider(),
			new GuidGenerator(),
			$this->getStatementGuidValidator(),
			$this->getStatementGuidParser(),
			$this->getSnakValidator(),
			$this->getTermValidatorFactory(),
			$this->getSiteStore()
		);
	}

	/**
	 * @since 0.5
	 *
	 * @return SnakValidator
	 */
	public function getSnakValidator() {
		return new SnakValidator(
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory(),
			$this->getDataTypeValidatorFactory()
		);
	}

	/**
	 * @since 0.4
	 *
	 * @return LanguageFallbackChainFactory
	 */
	public function getLanguageFallbackChainFactory() {
		if ( $this->languageFallbackChainFactory === null ) {
			global $wgUseSquid;

			// The argument is about whether full page output (OutputPage, specifically JS vars in
			// it currently) is cached for anons, where the only caching mechanism in use now is
			// Squid.
			$anonymousPageViewCached = $wgUseSquid;

			$this->languageFallbackChainFactory = new LanguageFallbackChainFactory( $anonymousPageViewCached );
		}

		return $this->languageFallbackChainFactory;
	}

	/**
	 * @since 0.5
	 *
	 * @return LanguageFallbackLabelDescriptionLookupFactory
	 */
	public function getLanguageFallbackLabelDescriptionLookupFactory() {
		return new LanguageFallbackLabelDescriptionLookupFactory(
			$this->getLanguageFallbackChainFactory(),
			$this->getTermLookup(),
			$this->getTermBuffer()
		);
	}

	/**
	 * @since 0.4
	 *
	 * @return StatementGuidValidator
	 */
	public function getStatementGuidValidator() {
		if ( $this->statementGuidValidator === null ) {
			$this->statementGuidValidator = new StatementGuidValidator( $this->getEntityIdParser() );
		}

		return $this->statementGuidValidator;
	}

	/**
	 * @since 0.4
	 *
	 * @return SettingsArray
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * @since 0.4
	 *
	 * @return Store
	 */
	public function getStore() {
		if ( $this->store === null ) {
			$this->store = new SqlStore(
				$this->getEntityContentDataCodec(),
				$this->getEntityIdParser(),
				$this->getEntityIdLookup(),
				$this->getEntityTitleLookup()
			);
		}

		return $this->store;
	}

	/**
	 * Returns a OutputFormatSnakFormatterFactory the provides SnakFormatters
	 * for different output formats.
	 *
	 * @return OutputFormatSnakFormatterFactory
	 */
	public function getSnakFormatterFactory() {
		if ( $this->snakFormatterFactory === null ) {
			$this->snakFormatterFactory = $this->newSnakFormatterFactory();
		}

		return $this->snakFormatterFactory;
	}

	/**
	 * @return TermBuffer
	 */
	public function getTermBuffer() {
		return $this->getBufferingTermLookup();
	}

	/**
	 * @return TermLookup
	 */
	public function getTermLookup() {
		return $this->getBufferingTermLookup();
	}

	/**
	 * @return BufferingTermLookup
	 */
	public function getBufferingTermLookup() {
		if ( !$this->termLookup ) {
			$this->termLookup = new BufferingTermLookup(
				$this->getStore()->getTermIndex(),
				1000 // @todo: configure buffer size
			);
		}

		return $this->termLookup;
	}

	/**
	 * @return EntityIdParser
	 */
	private function getLocalEntityUriParser() {
		return new SuffixEntityIdParser(
			$this->getSettings()->getSetting( 'conceptBaseUri' ),
			$this->getEntityIdParser()
		);
	}

	/**
	 * @return string
	 */
	private function getVocabularyBaseUri() {
		//@todo: We currently use the local repo concept URI here. This should be configurable,
		// to e.g. allow 3rd parties to use Wikidata as their vocabulary repo.
		return $this->getSettings()->getSetting( 'conceptBaseUri' );
	}

	/**
	 * @return OutputFormatSnakFormatterFactory
	 */
	protected function newSnakFormatterFactory() {
		$factory = new OutputFormatSnakFormatterFactory(
			$this->dataTypeDefinitions->getSnakFormatterFactoryCallbacks(),
			$this->getValueFormatterFactory(),
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
		);

		return $factory;
	}

	/**
	 * Returns a OutputFormatValueFormatterFactory the provides ValueFormatters
	 * for different output formats.
	 *
	 * @return OutputFormatValueFormatterFactory
	 */
	public function getValueFormatterFactory() {
		if ( $this->valueFormatterFactory === null ) {
			$this->valueFormatterFactory = $this->newValueFormatterFactory();
		}

		return $this->valueFormatterFactory;
	}

	/**
	 * @return OutputFormatValueFormatterFactory
	 */
	protected function newValueFormatterFactory() {
		return new OutputFormatValueFormatterFactory(
			$this->dataTypeDefinitions->getFormatterFactoryCallbacks( DataTypeDefinitions::PREFIXED_MODE ),
			$this->getDefaultLanguage(),
			new LanguageFallbackChainFactory()
		);
	}

	/**
	 * @return ValueSnakRdfBuilderFactory
	 */
	public function getValueSnakRdfBuilderFactory() {
		if ( $this->valueSnakRdfBuilderFactory === null ) {
			$this->valueSnakRdfBuilderFactory = new ValueSnakRdfBuilderFactory(
				$this->dataTypeDefinitions->getRdfBuilderFactoryCallbacks( DataTypeDefinitions::PREFIXED_MODE )
			);
		}

		return $this->valueSnakRdfBuilderFactory;
	}

	/**
	 * @return ExceptionLocalizer
	 */
	public function getExceptionLocalizer() {
		if ( $this->exceptionLocalizer === null ) {
			$formatter = $this->getMessageParameterFormatter();
			$localizers = $this->getExceptionLocalizers( $formatter );

			$this->exceptionLocalizer = new DispatchingExceptionLocalizer( $localizers, $formatter );
		}

		return $this->exceptionLocalizer;
	}

	/**
	 * @param ValueFormatter $formatter
	 *
	 * @return ExceptionLocalizer[]
	 */
	private function getExceptionLocalizers( ValueFormatter $formatter ) {
		return array(
			'MessageException' => new MessageExceptionLocalizer(),
			'ParseException' => new ParseExceptionLocalizer(),
			'ChangeOpValidationException' => new ChangeOpValidationExceptionLocalizer( $formatter ),
			'Exception' => new GenericExceptionLocalizer()
		);
	}

	/**
	 * Returns a SummaryFormatter.
	 *
	 * @return SummaryFormatter
	 */
	public function getSummaryFormatter() {
		if ( $this->summaryFormatter === null ) {
			$this->summaryFormatter = $this->newSummaryFormatter();
		}

		return $this->summaryFormatter;
	}

	/**
	 * @return SummaryFormatter
	 */
	protected function newSummaryFormatter() {
		global $wgContLang;

		// This needs to use an EntityIdPlainLinkFormatter as we want to mangle
		// the links created in LinkBeginHookHandler afterwards (the links must not
		// contain a display text: [[Item:Q1]] is fine but [[Item:Q1|Q1]] isn't).
		$idFormatter = new EntityIdPlainLinkFormatter( $this->getEntityContentFactory() );

		// Create a new ValueFormatterFactory, and override the formatter for entity IDs.
		$valueFormatterFactory = $this->newValueFormatterFactory();
		foreach ( $this->getEntityFactory()->getEntityTypes() as $entityType ) {
			$valueFormatterFactory->setFormatterFactoryCallback(
				"PT:wikibase-$entityType",
				function ( $format, FormatterOptions $options ) use ( $idFormatter ) {
					if ( $format === SnakFormatter::FORMAT_PLAIN ) {
						return new EntityIdValueFormatter( $idFormatter );
					} else {
						return null;
					}
				}
			);
		}

		// Create a new SnakFormatterFactory based on the specialized ValueFormatterFactory.
		$snakFormatterFactory = new OutputFormatSnakFormatterFactory(
			array(), // XXX: do we want $this->dataTypeDefinitions->getSnakFormatterFactoryCallbacks()
			$valueFormatterFactory,
			$this->getPropertyDataTypeLookup(),
			$this->getDataTypeFactory()
		);

		$options = new FormatterOptions();
		$snakFormatter = $snakFormatterFactory->getSnakFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$options
		);
		$valueFormatter = $valueFormatterFactory->getValueFormatter(
			SnakFormatter::FORMAT_PLAIN,
			$options
		);

		$formatter = new SummaryFormatter(
			$idFormatter,
			$valueFormatter,
			$snakFormatter,
			$wgContLang,
			$this->getEntityIdParser()
		);

		return $formatter;
	}

	/**
	 * @return EntityPermissionChecker
	 */
	public function getEntityPermissionChecker() {
		return $this->getEntityContentFactory();
	}

	/**
	 * @return TermValidatorFactory
	 */
	protected function getTermValidatorFactory() {
		$constraints = $this->settings->getSetting( 'multilang-limits' );
		$maxLength = $constraints['length'];

		$languages = $this->getTermsLanguages()->getLanguages();

		return new TermValidatorFactory(
			$maxLength,
			$languages,
			$this->getEntityIdParser(),
			$this->getLabelDescriptionDuplicateDetector()
		);
	}

	/**
	 * @return EntityConstraintProvider
	 */
	public function getEntityConstraintProvider() {
		return new EntityConstraintProvider(
			$this->getLabelDescriptionDuplicateDetector(),
			$this->getStore()->getSiteLinkConflictLookup()
		);
	}

	/**
	 * @return ValidatorErrorLocalizer
	 */
	public function getValidatorErrorLocalizer() {
		return new ValidatorErrorLocalizer( $this->getMessageParameterFormatter() );
	}

	/**
	 * @return LabelDescriptionDuplicateDetector
	 */
	public function getLabelDescriptionDuplicateDetector() {
		return new LabelDescriptionDuplicateDetector( $this->getStore()->getLabelConflictFinder() );
	}

	/**
	 * @return SiteStore
	 */
	public function getSiteStore() {
		if ( $this->siteStore === null ) {
			$this->siteStore = SiteSQLStore::newInstance();
		}

		return $this->siteStore;
	}

	/**
	 * Returns a ValueFormatter suitable for converting message parameters to wikitext.
	 * The formatter is most likely implemented to dispatch to different formatters internally,
	 * based on the type of the parameter.
	 *
	 * @return ValueFormatter
	 */
	protected function getMessageParameterFormatter() {
		global $wgLang;
		StubObject::unstub( $wgLang );

		$formatterOptions = new FormatterOptions();
		$valueFormatterFactory = $this->getValueFormatterFactory();

		return new MessageParameterFormatter(
			$valueFormatterFactory->getValueFormatter( SnakFormatter::FORMAT_WIKI, $formatterOptions ),
			new EntityIdLinkFormatter( $this->getEntityTitleLookup() ),
			$this->getSiteStore(),
			$wgLang
		);
	}

	/**
	 * @return ChangeTransmitter[]
	 */
	private function getChangeTransmitters() {
		$transmitters = array();

		$transmitters[] = new HookChangeTransmitter( 'WikibaseChangeNotification' );

		if ( $this->settings->getSetting( 'useChangesTable' ) ) {
			$transmitters[] = new DatabaseChangeTransmitter(
				$this->getStore()->getChangeStore()
			);
		}

		return $transmitters;
	}

	/**
	 * @return ChangeNotifier
	 */
	public function getChangeNotifier() {
		return new ChangeNotifier(
			$this->getEntityChangeFactory(),
			$this->getChangeTransmitters()
		);
	}

	/**
	 * Get the mapping of entity types => content models
	 *
	 * @since 0.5
	 *
	 * @return array
	 */
	public function getContentModelMappings() {
		// @TODO: We should have smth. like this for namespaces too
		$map = array(
			Item::ENTITY_TYPE => CONTENT_MODEL_WIKIBASE_ITEM,
			Property::ENTITY_TYPE => CONTENT_MODEL_WIKIBASE_PROPERTY
		);

		Hooks::run( 'WikibaseContentModelMapping', array( &$map ) );

		return $map;
	}

	/**
	 * @return EntityFactory
	 */
	public function getEntityFactory() {
		$entityClasses = array(
			Item::ENTITY_TYPE => 'Wikibase\DataModel\Entity\Item',
			Property::ENTITY_TYPE => 'Wikibase\DataModel\Entity\Property',
		);

		//TODO: provide a hook or registry for adding more.

		return new EntityFactory( $entityClasses );
	}

	/**
	 * @return EntityContentDataCodec
	 */
	public function getEntityContentDataCodec() {
		return new EntityContentDataCodec(
			$this->getEntityIdParser(),
			$this->getInternalEntitySerializer(),
			$this->getInternalEntityDeserializer(),
			$this->getSettings()->getSetting( 'maxSerializedEntitySize' ) * 1024
		);
	}

	/**
	 * @return Deserializer
	 */
	public function getInternalEntityDeserializer() {
		return $this->getInternalDeserializerFactory()->newEntityDeserializer();
	}

	/**
	 * @return Serializer
	 */
	public function getInternalEntitySerializer() {
		return $this->getInternalSerializerFactory()->newEntitySerializer();
	}

	/**
	 * @return Serializer
	 */
	public function getInternalStatementSerializer() {
		return $this->getInternalSerializerFactory()->newStatementSerializer();
	}

	/**
	 * @return Deserializer
	 */
	public function getInternalStatementDeserializer() {
		return $this->getInternalDeserializerFactory()->newStatementDeserializer();
	}

	/**
	 * @return InternalDeserializerFactory
	 */
	protected function getInternalDeserializerFactory() {
		return new InternalDeserializerFactory(
			$this->getDataValueDeserializer(),
			$this->getEntityIdParser()
		);
	}

	/**
	 * @return DeserializerFactory
	 */
	protected function getDeserializerFactory() {
		return new DeserializerFactory(
			$this->getDataValueDeserializer(),
			$this->getEntityIdParser()
		);
	}

	/**
	 * @return Deserializer
	 */
	public function getEntityDeserializer() {
		return $this->getDeserializerFactory()->newEntityDeserializer();
	}

	/**
	 * @return Deserializer
	 */
	public function getStatementDeserializer() {
		return $this->getDeserializerFactory()->newStatementDeserializer();
	}

	/**
	 * @return Deserializer
	 */
	public function getDataValueDeserializer() {
		return new DataValueDeserializer( array(
			'boolean' => 'DataValues\BooleanValue',
			'number' => 'DataValues\NumberValue',
			'string' => 'DataValues\StringValue',
			'unknown' => 'DataValues\UnknownValue',
			'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
			'monolingualtext' => 'DataValues\MonolingualTextValue',
			'multilingualtext' => 'DataValues\MultilingualTextValue',
			'quantity' => 'DataValues\QuantityValue',
			'time' => 'DataValues\TimeValue',
			'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
		) );
	}

	/**
	 * @return InternalSerializerFactory
	 */
	protected function getInternalSerializerFactory() {
		return new InternalSerializerFactory( new DataValueSerializer() );
	}

	/**
	 * @return ItemHandler
	 */
	public function newItemHandler() {
		$entityPerPage = $this->getStore()->newEntityPerPage();
		$termIndex = $this->getStore()->getTermIndex();
		$codec = $this->getEntityContentDataCodec();
		$constraintProvider = $this->getEntityConstraintProvider();
		$errorLocalizer = $this->getValidatorErrorLocalizer();
		$siteLinkStore = $this->getStore()->newSiteLinkStore();
		$legacyFormatDetector = $this->getLegacyFormatDetectorCallback();

		$handler = new ItemHandler(
			$entityPerPage,
			$termIndex,
			$codec,
			$constraintProvider,
			$errorLocalizer,
			$this->getEntityIdParser(),
			$siteLinkStore,
			$legacyFormatDetector
		);

		return $handler;
	}

	/**
	 * @return PropertyHandler
	 */
	public function newPropertyHandler() {
		$entityPerPage = $this->getStore()->newEntityPerPage();
		$termIndex = $this->getStore()->getTermIndex();
		$codec = $this->getEntityContentDataCodec();
		$constraintProvider = $this->getEntityConstraintProvider();
		$errorLocalizer = $this->getValidatorErrorLocalizer();
		$propertyInfoStore = $this->getStore()->getPropertyInfoStore();
		$propertyInfoBuilder = $this->newPropertyInfoBuilder();
		$legacyFormatDetector = $this->getLegacyFormatDetectorCallback();

		$handler = new PropertyHandler(
			$entityPerPage,
			$termIndex,
			$codec,
			$constraintProvider,
			$errorLocalizer,
			$this->getEntityIdParser(),
			$propertyInfoStore,
			$propertyInfoBuilder,
			$legacyFormatDetector
		);

		return $handler;
	}

	/**
	 * @return PropertyInfoBuilder
	 */
	public function newPropertyInfoBuilder() {
		$formatterUrlProperty = $this->getSettings()->getSetting( 'formatterUrlProperty' );

		if ( $formatterUrlProperty !== null ) {
			$formatterUrlProperty = new PropertyId( $formatterUrlProperty );
		}

		return new PropertyInfoBuilder( $formatterUrlProperty );
	}

	private function getLegacyFormatDetectorCallback() {
		$transformOnExport = $this->settings->getSetting( 'transformLegacyFormatOnExport' );

		if ( !$transformOnExport ) {
			return null;
		}

		/**
		 * Detects blobs that may be using a legacy serialization format.
		 * WikibaseRepo uses this for the $legacyExportFormatDetector parameter
		 * when constructing EntityHandlers.
		 *
		 * @see WikibaseRepo::newItemHandler
		 * @see WikibaseRepo::newPropertyHandler
		 * @see EntityHandler::__construct
		 *
		 * @note: False positives (detecting a legacy format when really no legacy format was used)
		 * are acceptable, false negatives (failing to detect a legacy format when one was used)
		 * are not acceptable.
		 *
		 * @param string $blob
		 * @param string $format
		 *
		 * @return bool True if $blob seems to be using a legacy serialization format.
		 */
		return function( $blob, $format ) {
			// The legacy serialization uses something like "entity":["item",21] or
			// even "entity":"p21" for the entity ID.
			return preg_match( '/"entity"\s*:/', $blob ) > 0;
		};
	}

	/**
	 * @param IContextSource|null $context
	 *
	 * @return ApiHelperFactory
	 */
	public function getApiHelperFactory( IContextSource $context = null ) {
		return new ApiHelperFactory(
			$this->getEntityTitleLookup(),
			$this->getExceptionLocalizer(),
			$this->getPropertyDataTypeLookup(),
			$this->getEntityFactory(),
			$this->getSiteStore(),
			$this->getSummaryFormatter(),
			$this->getEntityRevisionLookup( 'uncached' ),
			$this->newEditEntityFactory( $context )
		);
	}

	/**
	 * @param IContextSource|null $context
	 *
	 * @return EditEntityFactory
	 */
	public function newEditEntityFactory( IContextSource $context = null ) {
		return new EditEntityFactory(
			$this->getEntityTitleLookup(),
			$this->getEntityRevisionLookup( 'uncached' ),
			$this->getEntityStore(),
			$this->getEntityPermissionChecker(),
			$this->newEditFilterHookRunner( $context ),
			$context
		);
	}

	/**
	 * @return EntityNamespaceLookup
	 */
	public function getEntityNamespaceLookup() {
		if ( $this->entityNamespaceLookup === null ) {
			$this->entityNamespaceLookup = new EntityNamespaceLookup(
				$this->settings->getSetting( 'entityNamespaces' )
			);
		}

		return $this->entityNamespaceLookup;
	}

	/**
	 * @return EntityIdHtmlLinkFormatterFactory
	 */
	public function getEntityIdHtmlLinkFormatterFactory() {
		return new EntityIdHtmlLinkFormatterFactory(
			$this->getEntityTitleLookup(),
			new LanguageNameLookup()
		);
	}

	/**
	 * @return EntityParserOutputGeneratorFactory
	 */
	public function getEntityParserOutputGeneratorFactory() {
		$templateFactory = TemplateFactory::getDefaultInstance();
		$dataTypeLookup = $this->getPropertyDataTypeLookup();

		$statementGrouperBuilder = new StatementGrouperBuilder(
			$this->settings->getSetting( 'statementSections' ),
			$dataTypeLookup
		);

		$entityViewFactory = new EntityViewFactory(
			$this->getEntityIdHtmlLinkFormatterFactory(),
			new EntityIdLabelFormatterFactory(),
			$this->getHtmlSnakFormatterFactory(),
			$statementGrouperBuilder->getStatementGrouper(),
			$this->getSiteStore(),
			$this->getDataTypeFactory(),
			$templateFactory,
			new LanguageNameLookup(),
			$this->settings->getSetting( 'siteLinkGroups' ),
			$this->settings->getSetting( 'specialSiteLinkGroups' ),
			$this->settings->getSetting( 'badgeItems' )
		);

		$entityDataFormatProvider = new EntityDataFormatProvider();
		$formats = $this->getSettings()->getSetting( 'entityDataFormats' );
		$entityDataFormatProvider->setFormatWhiteList( $formats );

		return new EntityParserOutputGeneratorFactory(
			$entityViewFactory,
			$this->getStore()->getEntityInfoBuilderFactory(),
			$this->getEntityContentFactory(),
			$this->getLanguageFallbackChainFactory(),
			$templateFactory,
			$entityDataFormatProvider,
			$dataTypeLookup,
			$this->getLocalEntityUriParser(),
			$this->settings->getSetting( 'preferredGeoDataProperties' ),
			$this->settings->getSetting( 'preferredPageImagesProperties' ),
			$this->settings->getSetting( 'globeUris' )
		);
	}

	/**
	 * @return DataTypeValidatorFactory
	 */
	public function getDataTypeValidatorFactory() {

		return new BuilderBasedDataTypeValidatorFactory(
			$this->dataTypeDefinitions->getValidatorFactoryCallbacks()
		);
	}

	/**
	 * @return DataTypeDefinitions
	 */
	public function getDataTypeDefinitions() {
		return $this->dataTypeDefinitions;
	}

	private function getMonolingualTextLanguages() {
		if ( $this->monolingualTextLanguages === null ) {
			$this->monolingualTextLanguages = new MediaWikiContentLanguages();
		}
		return $this->monolingualTextLanguages;
	}

	/**
	 * Get a ContentLanguages object holding the languages available for labels, descriptions and aliases.
	 *
	 * @return ContentLanguages
	 */
	public function getTermsLanguages() {
		return new MediaWikiContentLanguages();
	}

	private function getHtmlSnakFormatterFactory() {
		return new WikibaseHtmlSnakFormatterFactory( $this->getSnakFormatterFactory() );
	}

}
