<?php

namespace Wikibase\Rdf;

use DataValues\IllegalValueException;
use DataValues\TimeValue;

/**
 * Clean datetime value to conform to RDF/XML standards
 * This class supports Julian->Gregorian conversion
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Stas Malyshev
 */
class JulianDateTimeValueCleaner extends DateTimeValueCleaner {

	/**
	 * Get standardized dateTime value, compatible with xsd:dateTime
	 * If the value cannot be converted to it, returns null
	 *
	 * @param TimeValue $value
	 *
	 * @return string|null
	 */
	public function getStandardValue( TimeValue $value ) {
		$calendar = $value->getCalendarModel();
		if ( $calendar == TimeValue::CALENDAR_GREGORIAN ) {
			return $this->cleanupGregorianValue( $value->getTime(), $value->getPrecision() );
		} elseif ( $calendar == TimeValue::CALENDAR_JULIAN ) {
			$precision = $value->getPrecision();
			// If we are less precise than a day, no point to convert
			// Julian to Gregorian since we don't have enough information to do it anyway
			if ( $precision >= TimeValue::PRECISION_DAY ) {
				return $this->julianDateValue( $value->getTime() );
			} else {
				return $this->cleanupGregorianValue( $value->getTime(), $precision );
			}
		}
		return null;
	}

	/**
	 * Get Julian date value and return it as Gregorian date
	 *
	 * @param string $dateValue
	 *
	 * @return string|null Value compatible with xsd:dateTime type, null if we failed to parse
	 */
	private function julianDateValue( $dateValue ) {
		try {
			list( $minus, $y, $m, $d, $time ) = $this->parseDateValue( $dateValue );
		} catch ( IllegalValueException $e ) {
			return null;
		}
		// We accept here certain precision loss since we will need to do calculations anyway,
		// and we can't calculate with dates that don't fit in int.
		$y = $minus ? -(int)$y : (int)$y;

		// cal_to_jd needs int year
		// If it's too small it's fine, we'll get 0
		// If it's too big, it doesn't make sense anyway,
		// since who uses Julian with day precision in year 2 billion?
		$jd = cal_to_jd( CAL_JULIAN, $m, $d, (int)$y );
		if ( $jd == 0 ) {
			// that means the date is broken
			return null;
		}
		// PHP API for Julian/Gregorian conversions is kind of awful
		list( $m, $d, $y ) = explode( '/', jdtogregorian( $jd ) );

		if ( $this->xsd11 && $y < 0 ) {
			// To make year match XSD 1.1 we need to bump up the negative years by 1
			// We know we have precision here since otherwise we wouldn't convert
			$y++;
		}

		// This is a bit weird since xsd:dateTime requires >=4 digit always,
		// and leading 0 is not allowed for 5 digits, but sprintf counts - as digit
		// See: http://www.w3.org/TR/xmlschema-2/#dateTime
		return sprintf( '%s%04d-%02d-%02dT%s', ( $y < 0 ) ? '-' : '', abs( $y ), $m, $d, $time );
	}

}
