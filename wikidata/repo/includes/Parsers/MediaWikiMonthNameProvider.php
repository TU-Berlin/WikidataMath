<?php

namespace Wikibase\Repo\Parsers;

use Language;

/**
 * A MonthNameProvider using MediaWiki's localization infrastructure.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class MediaWikiMonthNameProvider implements MonthNameProvider {

	/**
	 * @see MonthNameProvider::getLocalizedMonthNames
	 *
	 * @param string $languageCode
	 *
	 * @return string[] Array mapping the month's numbers 1 to 12 to localized month names.
	 */
	public function getLocalizedMonthNames( $languageCode ) {
		$language = Language::factory( $languageCode );

		$monthNames = array();

		for ( $i = 1; $i <= 12; $i++ ) {
			$monthNames[$i] = $language->getMonthName( $i );
		}

		return $monthNames;
	}

	/**
	 * Creates a replacements array using information retrieved via MediaWiki's Language object.
	 * Takes full month names, genitive names and abbreviations into account.
	 *
	 * @see MonthNameProvider::getMonthNameReplacements
	 *
	 * @param string $languageCode
	 * @param string $canonicalLanguageCode
	 *
	 * @return string[] Array mapping localized month names (including full month names, genitive
	 * names and abbreviations) to the same month names in a canonical language (usually English).
	 */
	public function getMonthNameReplacements( $languageCode, $canonicalLanguageCode = 'en' ) {
		$language = Language::factory( $languageCode );
		$baseLanguage = Language::factory( $canonicalLanguageCode );

		$replacements = array();

		for ( $i = 1; $i <= 12; $i++ ) {
			$canonical = $baseLanguage->getMonthName( $i );

			$replacements[$language->getMonthName( $i )] = $canonical;
			$replacements[$language->getMonthNameGen( $i )] = $canonical;
			$replacements[$language->getMonthAbbreviation( $i )] = $canonical;
		}

		return $replacements;
	}

}
