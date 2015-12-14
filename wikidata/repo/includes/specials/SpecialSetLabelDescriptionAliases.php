<?php

namespace Wikibase\Repo\Specials;

use Html;
use Language;
use SiteStore;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\FingerprintChangeOpFactory;
use Wikibase\DataModel\Entity\Entity;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\EditEntityFactory;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Summary;
use Wikibase\SummaryFormatter;

/**
 * Special page for setting label, description and aliases of a Wikibase entity that features
 * labels, descriptions and aliases.
 *
 * @since 0.5
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class SpecialSetLabelDescriptionAliases extends SpecialModifyEntity {

	/**
	 * @var FingerprintChangeOpFactory
	 */
	private $changeOpFactory;

	/**
	 * @var ContentLanguages
	 */
	private $termsLanguages;

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var string
	 */
	private $label = '';

	/**
	 * @var string
	 */
	private $description = '';

	/**
	 * @var string[]
	 */
	private $aliases = array();

	public function __construct() {
		parent::__construct( 'SetLabelDescriptionAliases', 'edit' );

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$this->changeOpFactory = $wikibaseRepo->getChangeOpFactoryProvider()
			->getFingerprintChangeOpFactory();
		$this->termsLanguages = $wikibaseRepo->getTermsLanguages();
	}

	/**
	 * @see SpecialWikibaseRepoPage::setSpecialWikibaseRepoPageServices
	 *
	 * @param SummaryFormatter $summaryFormatter
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param EntityTitleLookup $entityTitleLookup
	 * @param SiteStore $siteStore
	 * @param FingerprintChangeOpFactory $changeOpFactory
	 * @param ContentLanguages $termsLanguages
	 * @param EditEntityFactory $editEntityFactory
	 */
	public function setServices(
		SummaryFormatter $summaryFormatter,
		EntityRevisionLookup $entityRevisionLookup,
		EntityTitleLookup $entityTitleLookup,
		SiteStore $siteStore,
		FingerprintChangeOpFactory $changeOpFactory,
		ContentLanguages $termsLanguages,
		EditEntityFactory $editEntityFactory
	) {
		$this->setSpecialWikibaseRepoPageServices(
			$summaryFormatter,
			$entityRevisionLookup,
			$entityTitleLookup,
			$siteStore,
			$editEntityFactory
		);

		$this->changeOpFactory = $changeOpFactory;
		$this->termsLanguages = $termsLanguages;
	}

	/**
	 * @see SpecialModifyEntity::validateInput
	 *
	 * @return bool
	 */
	protected function validateInput() {
		return parent::validateInput()
			&& $this->entityRevision->getEntity() instanceof FingerprintProvider
			&& $this->isValidLanguageCode( $this->languageCode )
			&& $this->wasPostedWithLabelDescriptionOrAliases()
			&& $this->isAllowedToChangeTerms( $this->entityRevision->getEntity() );
	}

	/**
	 * @return bool
	 */
	private function wasPostedWithLabelDescriptionOrAliases() {
		$request = $this->getRequest();

		return $request->wasPosted() && (
			$request->getCheck( 'label' )
			|| $request->getCheck( 'description' )
			|| $request->getCheck( 'aliases' )
		);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	private function isAllowedToChangeTerms( Entity $entity ) {
		$action = $entity->getType() . '-term';

		if ( !$this->getUser()->isAllowed( $action ) ) {
			$this->showErrorHTML( $this->msg( 'permissionserrors' ) . ': ' . $action );
			return false;
		}

		return true;
	}

	/**
	 * @see SpecialModifyEntity::getFormElements
	 *
	 * @param Entity $entity
	 *
	 * @return string HTML
	 */
	protected function getFormElements( Entity $entity = null ) {
		if ( $entity !== null && $this->languageCode !== null ) {
			$languageName = Language::fetchLanguageName(
				$this->languageCode, $this->getLanguage()->getCode()
			);
			$intro = $this->msg(
				'wikibase-setlabeldescriptionaliases-introfull',
				$this->getEntityTitle( $entity->getId() )->getPrefixedText(),
				$languageName
			);

			$html = Html::hidden(
					'id',
					$entity->getId()->getSerialization()
				)
				. Html::hidden(
					'language',
					$this->languageCode
				)
				. $this->getLabeledInputField( 'label', $this->label )
				. Html::element( 'br' )
				. $this->getLabeledInputField( 'description', $this->description )
				. Html::element( 'br' )
				. $this->getLabeledInputField( 'aliases', implode( '|', $this->aliases ) );
		} else {
			$intro = $this->msg( 'wikibase-setlabeldescriptionaliases-intro' );
			$fieldId = 'wikibase-setlabeldescriptionaliases-language';
			$languageCode = $this->languageCode ? : $this->getLanguage()->getCode();

			$html = parent::getFormElements( $entity )
				. Html::element( 'br' )
				. Html::label(
					$this->msg( 'wikibase-modifyterm-language' )->text(),
					$fieldId,
					array(
						'class' => 'wb-label',
					)
				)
				. Html::input(
					'language',
					$languageCode,
					'text',
					array(
						'class' => 'wb-input',
						'id' => $fieldId,
					)
				);
		}

		return Html::rawElement(
			'p',
			array(),
			$intro->parse()
		)
		. $html
		. Html::element( 'br' );
	}

	/**
	 * Returns an HTML label and text input element for a specific term.
	 *
	 * @param string $termType Either 'label', 'description' or 'aliases'.
	 * @param string $value Text to fill the input element with
	 *
	 * @return string HTML
	 */
	private function getLabeledInputField( $termType, $value ) {
		$fieldId = 'wikibase-setlabeldescriptionaliases-' . $termType;

		// Messages:
		// wikibase-setlabeldescriptionaliases-label-label
		// wikibase-setlabeldescriptionaliases-description-label
		// wikibase-setlabeldescriptionaliases-aliases-label
		return Html::label(
			$this->msg( $fieldId . '-label' )->text(),
			$fieldId,
			array(
				'class' => 'wb-label',
			)
		)
		. Html::input(
			$termType,
			$value,
			'text',
			array(
				'class' => 'wb-input',
				'id' => $fieldId,
				'placeholder' => $value,
			)
		);
	}

	/**
	 * @see SpecialModifyEntity::prepareArguments
	 *
	 * @param string $subPage
	 */
	protected function prepareArguments( $subPage ) {
		$this->extractInput( $subPage );

		// Parse the 'id' parameter and throw an exception if the entity cannot be loaded
		parent::prepareArguments( $subPage );

		if ( $this->languageCode === '' ) {
			$this->languageCode = $this->getLanguage()->getCode();
		} elseif ( !$this->isValidLanguageCode( $this->languageCode ) ) {
			$msg = $this->msg( 'wikibase-wikibaserepopage-invalid-langcode', $this->languageCode );
			$this->showErrorHTML( $msg->parse() );
			$this->languageCode = null;
		}

		if ( $this->languageCode !== null && $this->entityRevision !== null ) {
			$entity = $this->entityRevision->getEntity();

			if ( $entity instanceof FingerprintProvider ) {
				$this->setFingerprintFields( $entity->getFingerprint() );
			}
		}
	}

	/**
	 * @param string $subPage
	 */
	private function extractInput( $subPage ) {
		$request = $this->getRequest();

		$parts = $subPage === '' ? array() : explode( '/', $subPage, 2 );
		$this->languageCode = $request->getVal( 'language', isset( $parts[1] ) ? $parts[1] : '' );

		$label = $request->getVal( 'label', '' );
		$this->label = $this->stringNormalizer->trimToNFC( $label );

		$description = $request->getVal( 'description', '' );
		$this->description = $this->stringNormalizer->trimToNFC( $description );

		$aliases = $request->getVal( 'aliases', '' );
		$aliases = $this->stringNormalizer->trimToNFC( $aliases );
		$this->aliases = $aliases === '' ? array() : explode( '|', $aliases );
		foreach ( $this->aliases as &$alias ) {
			$alias = $this->stringNormalizer->trimToNFC( $alias );
		}
	}

	private function setFingerprintFields( Fingerprint $fingerprint ) {
		if ( !$this->getRequest()->getCheck( 'label' )
			&& $fingerprint->hasLabel( $this->languageCode )
		) {
			$this->label = $fingerprint->getLabel( $this->languageCode )->getText();
		}

		if ( !$this->getRequest()->getCheck( 'description' )
			&& $fingerprint->hasDescription( $this->languageCode )
		) {
			$this->description = $fingerprint->getDescription( $this->languageCode )->getText();
		}

		if ( !$this->getRequest()->getCheck( 'aliases' )
			&& $fingerprint->hasAliasGroup( $this->languageCode )
		) {
			$this->aliases = $fingerprint->getAliasGroup( $this->languageCode )->getAliases();
		}
	}

	/**
	 * @param string|null $languageCode
	 *
	 * @return bool
	 */
	private function isValidLanguageCode( $languageCode ) {
		return $languageCode !== null && $this->termsLanguages->hasLanguage( $languageCode );
	}

	/**
	 * @see SpecialModifyEntity::modifyEntity
	 *
	 * @param Entity $entity
	 *
	 * @return Summary|bool
	 */
	protected function modifyEntity( Entity $entity ) {
		$changeOps = $this->getChangeOps( $entity->getFingerprint() );

		$summary = false;
		$success = true;

		foreach ( $changeOps as $module => $changeOp ) {
			$summary = new Summary( $module );

			try {
				$this->applyChangeOp( $changeOp, $entity, $summary );
			} catch ( ChangeOpException $ex ) {
				$this->showErrorHTML( $ex->getMessage() );
				$success = false;
			}
		}

		if ( !$success ) {
			return false;
		} elseif ( count( $changeOps ) === 1 ) {
			return $summary;
		}

		return $this->getSummaryForLabelDescriptionAliases();
	}

	/**
	 * @param Fingerprint $fingerprint
	 *
	 * @return ChangeOp[]
	 */
	private function getChangeOps( Fingerprint $fingerprint ) {
		$changeOpFactory = $this->changeOpFactory;
		$changeOps = array();

		if ( $this->label !== '' ) {
			if ( !$fingerprint->hasLabel( $this->languageCode )
				|| $fingerprint->getLabel( $this->languageCode )->getText() !== $this->label
			) {
				$changeOps['wbsetlabel'] = $changeOpFactory->newSetLabelOp(
					$this->languageCode,
					$this->label
				);
			}
		} elseif ( $fingerprint->hasLabel( $this->languageCode ) ) {
			$changeOps['wbsetlabel'] = $changeOpFactory->newRemoveLabelOp(
				$this->languageCode
			);
		}

		if ( $this->description !== '' ) {
			if ( !$fingerprint->hasDescription( $this->languageCode )
				|| $fingerprint->getDescription( $this->languageCode )->getText() !== $this->description
			) {
				$changeOps['wbsetdescription'] = $changeOpFactory->newSetDescriptionOp(
					$this->languageCode,
					$this->description
				);
			}
		} elseif ( $fingerprint->hasDescription( $this->languageCode ) ) {
			$changeOps['wbsetdescription'] = $changeOpFactory->newRemoveDescriptionOp(
				$this->languageCode
			);
		}

		if ( !empty( $this->aliases ) ) {
			if ( !$fingerprint->hasAliasGroup( $this->languageCode )
				|| $fingerprint->getAliasGroup( $this->languageCode )->getAliases() !== $this->aliases
			) {
				$changeOps['wbsetaliases'] = $changeOpFactory->newSetAliasesOp(
					$this->languageCode,
					$this->aliases
				);
			}
		} elseif ( $fingerprint->hasAliasGroup( $this->languageCode ) ) {
			$changeOps['wbsetaliases'] = $changeOpFactory->newRemoveAliasesOp(
				$this->languageCode,
				$fingerprint->getAliasGroup( $this->languageCode )->getAliases()
			);
		}

		return $changeOps;
	}

	/**
	 * @return Summary
	 */
	private function getSummaryForLabelDescriptionAliases() {
		// FIXME: Introduce more specific messages if only 2 of the 3 fields changed.
		$summary = new Summary( 'wbsetlabeldescriptionaliases' );
		$summary->addAutoSummaryArgs( $this->label, $this->description, $this->aliases );

		$summary->setLanguage( $this->languageCode );
		return $summary;
	}

}
