<?php

namespace Wikibase\Repo\Api;

use ApiMain;
use Deserializers\Exceptions\DeserializationException;
use Wikibase\ChangeOp\ChangeOpReference;
use Wikibase\ChangeOp\StatementChangeOpFactory;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\WikibaseRepo;

/**
 * API module for creating a reference or setting the value of an existing one.
 *
 * @since 0.3
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetReference extends ModifyClaim {

	/**
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var DeserializerFactory
	 */
	private $deserializerFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $this->getContext() );
		$changeOpFactoryProvider = $wikibaseRepo->getChangeOpFactoryProvider();

		$this->statementChangeOpFactory = $changeOpFactoryProvider->getStatementChangeOpFactory();
		$this->errorReporter = $apiHelperFactory->getErrorReporter( $this );
		$this->deserializerFactory = new DeserializerFactory(
			$wikibaseRepo->getDataValueDeserializer(),
			$wikibaseRepo->getEntityIdParser()
		);
	}

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.3
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		$entityId = $this->guidParser->parse( $params['statement'] )->getEntityId();
		if ( isset( $params['baserevid'] ) ) {
			$entityRevision = $this->loadEntityRevision( $entityId, (int)$params['baserevid'] );
		} else {
			$entityRevision = $this->loadEntityRevision( $entityId );
		}
		$entity = $entityRevision->getEntity();

		$summary = $this->modificationHelper->createSummary( $params, $this );

		$claim = $this->modificationHelper->getStatementFromEntity( $params['statement'], $entity );

		if ( isset( $params['reference'] ) ) {
			$this->validateReferenceHash( $claim, $params['reference'] );
		}

		if ( isset( $params['snaks-order' ] ) ) {
			$snaksOrder = $this->getArrayFromParam( $params['snaks-order'] );
		} else {
			$snaksOrder = array();
		}

		$deserializer = $this->deserializerFactory->newSnakListDeserializer();
		/** @var SnakList $snakList */
		try {
			$snakList = $deserializer->deserialize( $this->getArrayFromParam( $params['snaks'] ) );
		} catch ( DeserializationException $e ) {
			$this->errorReporter->dieError(
				'Failed to get reference from reference Serialization ' . $e->getMessage(),
				'snak-instantiation-failure'
			);
		}
		$snakList->orderByProperty( $snaksOrder );

		$newReference = new Reference( $snakList );

		$changeOp = $this->getChangeOp( $newReference );
		$this->modificationHelper->applyChangeOp( $changeOp, $entity, $summary );

		$status = $this->saveChanges( $entity, $summary );
		$resultBuilder = $this->getResultBuilder();
		$resultBuilder->addRevisionIdFromStatusToResult( $status, 'pageinfo' );
		$resultBuilder->markSuccess();
		$resultBuilder->addReference( $newReference );
	}

	/**
	 * Check the provided parameters
	 */
	private function validateParameters( array $params ) {
		if ( !( $this->modificationHelper->validateStatementGuid( $params['statement'] ) ) ) {
			$this->errorReporter->dieError( 'Invalid claim guid', 'invalid-guid' );
		}
	}

	/**
	 * @param Statement $statement
	 * @param string $referenceHash
	 */
	private function validateReferenceHash( Statement $statement, $referenceHash ) {
		if ( !$statement->getReferences()->hasReferenceHash( $referenceHash ) ) {
			$this->errorReporter->dieError(
				'Statement does not have a reference with the given hash',
				'no-such-reference'
			);
		}
	}

	/**
	 * @param string $arrayParam
	 *
	 * @return array
	 */
	private function getArrayFromParam( $arrayParam ) {
		$rawArray = json_decode( $arrayParam, true );

		if ( !is_array( $rawArray ) || !count( $rawArray ) ) {
			$this->errorReporter->dieError( 'No array or invalid JSON given', 'invalid-json' );
		}

		return $rawArray;
	}

	/**
	 * @param Reference $reference
	 *
	 * @return ChangeOpReference
	 */
	private function getChangeOp( Reference $reference ) {
		$params = $this->extractRequestParams();

		$guid = $params['statement'];
		$hash = isset( $params['reference'] ) ? $params['reference'] : '';
		$index = isset( $params['index'] ) ? $params['index'] : null;

		return $this->statementChangeOpFactory->newSetReferenceOp( $guid, $reference, $hash, $index );
	}

	/**
	 * @see ApiBase::isWriteMode
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::needsToken
	 *
	 * @return string
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array_merge(
			array(
				'statement' => array(
					self::PARAM_TYPE => 'string',
					self::PARAM_REQUIRED => true,
				),
				'snaks' => array(
					self::PARAM_TYPE => 'text',
					self::PARAM_REQUIRED => true,
				),
				'snaks-order' => array(
					self::PARAM_TYPE => 'string',
				),
				'reference' => array(
					self::PARAM_TYPE => 'string',
				),
				'index' => array(
					self::PARAM_TYPE => 'integer',
				),
			),
			parent::getAllowedParams()
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&snaks='
				. '{"P212":[{"snaktype":"value","property":"P212","datavalue":{"type":"string",'
				. '"value":"foo"}}]}&baserevid=7201010&token=foobar'
				=> 'apihelp-wbsetreference-example-1',
			'action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF'
				. '&reference=1eb8793c002b1d9820c833d234a1b54c8e94187e&snaks='
				. '{"P212":[{"snaktype":"value","property":"P212","datavalue":{"type":"string",'
				. '"value":"bar"}}]}&baserevid=7201010&token=foobar'
				=> 'apihelp-wbsetreference-example-2',
			'action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&snaks='
				. '{"P212":[{"snaktype":"novalue","property":"P212"}]}'
				. '&index=0&baserevid=7201010&token=foobar'
				=> 'apihelp-wbsetreference-example-3',
		);
	}

}
