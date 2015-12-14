<?php

namespace Wikibase\Repo\Specials;

use Exception;
use HTMLForm;
use Html;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Lib\UserInputException;
use Wikibase\Repo\Interactors\RedirectCreationInteractor;
use Wikibase\Repo\Interactors\TokenCheckInteractor;
use Wikibase\Repo\Localizer\ExceptionLocalizer;
use Wikibase\Repo\WikibaseRepo;

/**
 * Special page for creating redirects between entities
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Addshore
 */
class SpecialRedirectEntity extends SpecialWikibasePage {

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var ExceptionLocalizer
	 */
	private $exceptionLocalizer;

	/**
	 * @var RedirectCreationInteractor
	 */
	private $interactor;

	/**
	 * @var TokenCheckInteractor
	 */
	private $tokenCheck;

	/**
	 * @since 0.5
	 */
	public function __construct() {
		parent::__construct( 'RedirectEntity' );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$this->initServices(
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getExceptionLocalizer(),
			new TokenCheckInteractor(
				$this->getUser()
			),
			$wikibaseRepo->newRedirectCreationInteractor(
				$this->getUser(),
				$this->getContext()
			)
		);
	}

	public function initServices(
		EntityIdParser $idParser,
		ExceptionLocalizer $exceptionLocalizer,
		TokenCheckInteractor $tokenCheck,
		RedirectCreationInteractor $interactor
	) {
		$this->idParser = $idParser;
		$this->exceptionLocalizer = $exceptionLocalizer;
		$this->tokenCheck = $tokenCheck;
		$this->interactor = $interactor;
	}

	/**
	 * @param string $name
	 *
	 * @return EntityId|null
	 * @throws UserInputException
	 */
	private function getEntityIdParam( $name ) {
		$rawId = $this->getTextParam( $name );

		if ( $rawId === '' ) {
			return null;
		}

		try {
			return $this->idParser->parse( $rawId );
		} catch ( EntityIdParsingException $ex ) {
			throw new UserInputException(
				'wikibase-wikibaserepopage-invalid-id',
				array( $rawId ),
				'Entity id is not valid'
			);
		}
	}

	private function getTextParam( $name ) {
		$value = $this->getRequest()->getText( $name, '' );
		return trim( $value );
	}

	/**
	 * @see SpecialWikibasePage::execute
	 *
	 * @since 0.5
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->checkReadOnly();

		$this->setHeaders();
		$this->outputHeader();

		try {
			$fromId = $this->getEntityIdParam( 'fromid' );
			$toId = $this->getEntityIdParam( 'toid' );

			if ( $fromId && $toId ) {
				$this->redirectEntity( $fromId, $toId );
			}
		} catch ( Exception $ex ) {
			$this->showExceptionMessage( $ex );
		}

		$this->createForm();
	}

	protected function showExceptionMessage( Exception $ex ) {
		$msg = $this->exceptionLocalizer->getExceptionMessage( $ex );

		$this->showErrorHTML( $msg->parse(), 'error' );

		// Report chained exceptions recursively
		if ( $ex->getPrevious() ) {
			$this->showExceptionMessage( $ex->getPrevious() );
		}
	}

	/**
	 * @param EntityId $fromId
	 * @param EntityId $toId
	 */
	private function redirectEntity( EntityId $fromId, EntityId $toId ) {
		$this->tokenCheck->checkRequestToken( $this->getRequest(), 'wpEditToken' );

		$this->interactor->createRedirect( $fromId, $toId, false );

		$this->getOutput()->addWikiMsg(
			'wikibase-redirectentity-success',
			$fromId->getSerialization(),
			$toId->getSerialization()
		);
	}

	/**
	 * Creates the HTML form for redirecting an entity
	 */
	protected function createForm() {
		$pre = '';
		if ( $this->getUser()->isAnon() ) {
			$pre = Html::rawElement(
				'p',
				array( 'class' => 'warning' ),
				$this->msg(
					'wikibase-anonymouseditwarning',
					$this->msg( 'wikibase-entity' )->text()
				)->parse()
			);
		}

		HTMLForm::factory( 'ooui', $this->getFormElements(), $this->getContext() )
			->setId( 'wb-redirectentity-form1' )
			->setPreText( $pre )
			->setSubmitID( 'wb-redirectentity-submit' )
			->setSubmitName( 'wikibase-redirectentity-submit' )
			->setSubmitTextMsg( 'wikibase-redirectentity-submit' )
			->setWrapperLegendMsg( 'special-redirectentity' )
			->setSubmitCallback( function () {// no-op
			} )->show();
	}

	/**
	 * Returns the form elements.
	 *
	 * @return string
	 */
	protected function getFormElements() {
		return array(
			'fromid' => array(
				'name' => 'fromid',
				'default' => $this->getRequest()->getVal( 'fromid' ),
				'type' => 'text',
				'id' => 'wb-redirectentity-fromid',
				'label-message' => 'wikibase-redirectentity-fromid'
			),
			'toid' => array(
				'name' => 'toid',
				'default' => $this->getRequest()->getVal( 'toid' ),
				'type' => 'text',
				'id' => 'wb-redirectentity-toid',
				'label-message' => 'wikibase-redirectentity-toid'
			)
		);
	}

}
