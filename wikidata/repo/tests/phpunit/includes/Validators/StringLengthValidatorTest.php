<?php

namespace Wikibase\Test\Repo\Validators;

use Wikibase\Repo\Validators\StringLengthValidator;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;

/**
 * @covers Wikibase\Repo\Validators\StringLengthValidator
 *
 * @license GPL 2+
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @author Daniel Kinzler
 */
class StringLengthValidatorTest extends \PHPUnit_Framework_TestCase {

	public function provideValidate() {
		return array(
			array( 1, 10, 'strlen', 'foo', true, "normal fit" ),
			array( 0, 10, 'strlen', '', true, "empty ok" ),
			array( 1, 10, 'strlen', '', false, "empty not allowed" ),
			array( 1, 2, 'strlen', 'foo', false, "too long" ),
			array( 1, 2, 'strlen', 'ää', false, "byte measure" ), // assumes utf-8, latin1 will fail
			array( 1, 2, 'mb_strlen', 'ää', true, "char measure" ),
		);
	}

	/**
	 * @dataProvider provideValidate()
	 */
	public function testValidate( $minLength, $maxLength, $measure, $value, $expected, $message ) {
		$validator = new StringLengthValidator( $minLength, $maxLength, $measure );
		$result = $validator->validate( $value );

		$this->assertEquals( $expected, $result->isValid(), $message );

		if ( !$expected ) {
			$errors = $result->getErrors();
			$this->assertCount( 1, $errors, $message );
			$this->assertTrue(
				in_array( $errors[0]->getCode(), array( 'too-long', 'too-short' ) ),
				$message . "\n" . $errors[0]->getCode()
			);

			$localizer = new ValidatorErrorLocalizer();
			$msg = $localizer->getErrorMessage( $errors[0] );
			$this->assertTrue( $msg->exists(), $msg );
		}
	}

}
