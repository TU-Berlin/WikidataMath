<?php

namespace Wikibase;

use Html;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Interactors\TermSearchResult;

/**
 * Class representing the disambiguation of a list of WikibaseItems.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author jeblad
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 * @authro Adam Shorland
 */
class ItemDisambiguation {

	/**
	 * @var EntityTitleLookup
	 */
	private $titleLookup;

	/**
	 * @var LanguageNameLookup
	 */
	private $languageNameLookup;

	/**
	 * @var string
	 */
	private $displayLanguageCode;

	/**
	 * @since 0.5
	 *
	 * @param EntityTitleLookup $titleLookup
	 * @param LanguageNameLookup $languageNameLookup
	 * @param string $displayLanguageCode
	 */
	public function __construct(
		EntityTitleLookup $titleLookup,
		LanguageNameLookup $languageNameLookup,
		$displayLanguageCode
	) {
		$this->titleLookup = $titleLookup;
		$this->languageNameLookup = $languageNameLookup;
		$this->displayLanguageCode = $displayLanguageCode;
	}

	/**
	 * Builds and returns the HTML to represent the WikibaseItem.
	 *
	 * @since 0.5
	 *
	 * @param TermSearchResult[] $searchResults
	 *
	 * @return string HTML
	 */
	public function getHTML( array $searchResults ) {
		return
			'<ul class="wikibase-disambiguation">' .
				implode( '', array_map(
					array( $this, 'getResultHtml' ),
					$searchResults
				) ).
			'</ul>';
	}

	/**
	 * @param TermSearchResult $searchResult
	 *
	 * @return string HTML
	 */
	public function getResultHtml( TermSearchResult $searchResult ) {
		$idHtml = $this->getIdHtml( $searchResult->getEntityId() );

		$displayLabel = $searchResult->getDisplayLabel();
		$displayDescription = $searchResult->getDisplayDescription();
		$matchedTerm = $searchResult->getMatchedTerm();

		$labelHtml = $this->getLabelHtml(
			$displayLabel
		);

		$descriptionHtml = $this->getDescriptionHtml(
			$displayDescription
		);

		$matchHtml = $this->getMatchHtml(
			$matchedTerm, $displayLabel
		);

		$result = $idHtml;

		if ( $labelHtml !== '' || $descriptionHtml !== '' || $matchHtml !== '' ) {
			$result .= wfMessage( 'colon-separator' )->escaped();
		}

		if ( $labelHtml !== '' ) {
			$result .= $labelHtml;
		}

		if ( $labelHtml !== '' && $descriptionHtml !== '' ) {
			$result .= wfMessage( 'comma-separator' )->escaped();
		}

		if ( $descriptionHtml !== '' ) {
			$result .= $descriptionHtml;
		}

		if ( $matchHtml !== '' ) {
			$result .= $matchHtml;
		}

		$result = Html::rawElement( 'li', array( 'class' => 'wikibase-disambiguation' ), $result );
		return $result;
	}

	/**
	 * Returns HTML representing the label in the display language (or an appropriate fallback).
	 *
	 * @param EntityId|null $entityId
	 *
	 * @return string HTML
	 */
	private function getIdHtml( EntityId $entityId = null ) {
		$title = $this->titleLookup->getTitleForId( $entityId );

		$idElement = Html::element(
			'a',
			array(
				'title' => $title ? $title->getPrefixedText() : '',
				'href' => $title ? $title->getLocalURL() : '',
				'class' => 'wb-itemlink-id'
			),
			$entityId->getSerialization()
		);

		return $idElement;
	}

	/**
	 * Returns HTML representing the label in the display language (or an appropriate fallback).
	 *
	 * @param Term|null $label
	 *
	 * @return string HTML
	 */
	private function getLabelHtml( Term $label = null ) {
		if ( !$label ) {
			return '';
		}

		//TODO: include actual language if $label is a FallbackTerm
		$labelElement = Html::element(
			'span',
			array( 'class' => 'wb-itemlink-label' ),
			$label->getText()
		);
		return $labelElement;
	}

	/**
	 * Returns HTML representing the description in the display language (or an appropriate fallback).
	 *
	 * @param Term|null $description
	 *
	 * @return string HTML
	 */
	private function getDescriptionHtml( Term $description = null ) {
		if ( !$description ) {
			return '';
		}

		//TODO: include actual language if $description is a FallbackTerm
		$descriptionElement = Html::element(
			'span',
			array( 'class' => 'wb-itemlink-description' ),
			$description->getText()
		);
		return $descriptionElement;
	}

	/**
	 * Returns HTML representing the matched term in the search language (or an appropriate fallback).
	 * The matched text and language are wrapped using the wikibase-itemlink-userlang-wrapper message.
	 * If the matched term has the same text as the display label, an empty string is returned.
	 *
	 * @param Term|null $match
	 * @param Term|null $label
	 *
	 * @return string HTML
	 */
	private function getMatchHtml( Term $match = null, Term $label = null ) {
		if ( !$match ) {
			return '';
		}

		if ( $label && $label->getText() == $match->getText() ) {
			return '';
		}

		$text = $match->getText();
		$language = $this->languageNameLookup->getName( $match->getLanguageCode() );

		$matchElement = $descriptionElement = Html::element(
			'span',
			array( 'class' => 'wb-itemlink-match' ),
			wfMessage( 'wikibase-itemlink-userlang-wrapper' )->params( $language, $text )->text()
		);

		return $matchElement;
	}

}
