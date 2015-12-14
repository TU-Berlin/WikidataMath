<?php

namespace Wikibase\Test\Repo\Validators;

use Wikibase\Repo\Validators\RegexValidator;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;

/**
 * @covers Wikibase\Repo\Validators\RegexValidator
 *
 * @license GPL 2+
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @author Daniel Kinzler
 */
class RegexValidatorTest extends \PHPUnit_Framework_TestCase {

	public function provideValidate() {
		return array(
			array( '/^x/', false, 'xyz', true, "match" ),
			array( '/^x/', false, 'zyx', false, "mismatch" ),
			array( '/^x/', true, 'zyx', true, "inverse match" ),
			array( '/^x/', true, 'xyz', false, "inverse mismatch" ),
		);
	}

	/**
	 * @dataProvider provideValidate()
	 */
	public function testValidate( $regex, $inverse, $value, $expected, $message ) {
		$validator = new RegexValidator( $regex, $inverse );
		$result = $validator->validate( $value );

		$this->assertEquals( $expected, $result->isValid(), $message );

		if ( !$expected ) {
			$errors = $result->getErrors();
			$this->assertCount( 1, $errors, $message );
			$this->assertEquals( 'malformed-value', $errors[0]->getCode(), $message );

			$localizer = new ValidatorErrorLocalizer();
			$msg = $localizer->getErrorMessage( $errors[0] );
			$this->assertTrue( $msg->exists(), $msg );
		}
	}

}
