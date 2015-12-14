<?php

namespace Wikibase\Test\Repo\Validators;

use DataValues\StringValue;
use Wikibase\Repo\Validators\CompositeValidator;
use Wikibase\Repo\Validators\RegexValidator;
use Wikibase\Repo\Validators\StringLengthValidator;
use Wikibase\Repo\Validators\TypeValidator;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;

/**
 * @covers Wikibase\Repo\Validators\CompositeValidator
 *
 * @license GPL 2+
 *
 * @group WikibaseRepo
 * @group Wikibase
 * @group WikibaseValidators
 *
 * @author Daniel Kinzler
 */
class CompositeValidatorTest extends \PHPUnit_Framework_TestCase {

	public function provideValidate() {
		$validators = array(
			new TypeValidator( 'string' ),
			new StringLengthValidator( 1, 10 ),
			new RegexValidator( '/xxx/', true ),
		);

		return array(
			array( array(), true, 'foo', 0, "no validators" ),
			array( $validators, true, 'foo', 0, "pass validation" ),
			array( $validators, true, new StringValue( "foo" ), 1, "fail first validation" ),
			array( $validators, true, '', 1, "fail second validation" ),
			array( $validators, false, str_repeat( 'x', 20 ), 2, "fail second and third validation" ),
			array( $validators, false, str_repeat( 'x', 5 ), 1, "fail third validation" ),
		);
	}

	/**
	 * @dataProvider provideValidate()
	 */
	public function testValidate( $validators, $failFast, $value, $expectedErrorCount, $message ) {
		$validator = new CompositeValidator( $validators, $failFast );
		$result = $validator->validate( $value );
		$errors = $result->getErrors();

		$this->assertEquals( $expectedErrorCount === 0, $result->isValid(), $message );
		$this->assertCount( $expectedErrorCount, $errors, $message );

		$localizer = new ValidatorErrorLocalizer();

		foreach ( $errors as $error ) {
			$msg = $localizer->getErrorMessage( $error );
			$this->assertTrue( $msg->exists(), $msg );
		}
	}

}
