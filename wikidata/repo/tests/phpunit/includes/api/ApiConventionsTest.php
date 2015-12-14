<?php

namespace Wikibase\Test\Repo\Api;

use ApiBase;
use ApiMain;
use FauxRequest;
use ReflectionMethod;

/**
 * @group Wikibase
 * @group WikibaseRepo
 * @group WikibaseAPI
 * @group Database
 *
 * @group medium
 *
 * @licence GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 */
class ApiConventionsTest extends \MediaWikiTestCase {

	public function wikibaseApiModuleProvider() {
		$argList = array();

		foreach ( $GLOBALS['wgAPIModules'] as $moduleClass ) {
			// Make sure to only test Wikibase Api modules
			// This works as long as Wikibase modules are always defined as a class name string.
			// @todo adjust this if we ever define our api modules differently.
			if ( is_string( $moduleClass ) && strpos( $moduleClass, 'Wikibase' ) !== false ) {
				$argList[] = array( $moduleClass );
			}
		}

		return $argList;
	}

	/**
	 * Connects the assertions for the different methods and iterates through the api modules
	 *
	 * @dataProvider wikibaseApiModuleProvider
	*/
	public function testApiConventions( $moduleClass ) {
		$params = array();
		$user = $GLOBALS['wgUser'];

		$request = new FauxRequest( $params, true );
		$main = new ApiMain( $request );
		$main->getContext()->setUser( $user );
		$module = new $moduleClass( $main, 'moduleClass' );

		$this->assertGetFinalParamDescription( $moduleClass, $module );
		$this->assertGetFinalDescription( $moduleClass, $module );
		$this->assertGetExamplesMessages( $moduleClass, $module );
	}

	/**
	 * This method is for the assertions in particular for getFinalDescription as defined in ApiBase
	 *
	 * @param string $moduleClass One of the modules in $GLOBALS['wgAPIModules'], only in this
	 *  function for the error messages.
	 * @param ApiBase $module is an instance of $moduleClass
	 **/
	private function assertGetFinalDescription( $moduleClass, ApiBase $module ) {
		$method = 'getFinalDescription';
		$descArray = $module->$method();

		$rMethod = new ReflectionMethod( $module, $method );
		$this->assertTrue( $rMethod->isPublic(), 'the method ' . $method . ' of module '
			. $moduleClass . ' is not public' );

		$this->assertNotEmpty( $module->$method(), 'the Module ' . $moduleClass
			. ' does not have the method ' . $method );
		$this->assertNotEmpty( $descArray, 'the array returned by the method ' . $method
			. ' of module ' . $moduleClass . ' is empty' );

		foreach ( $descArray as $desc ) {
			$this->assertInstanceOf( 'Message', $desc, 'the value returned by the method '
				. $method . ' of the module ' . $moduleClass . ' is not a Message object' );
		}
	}

	/**
	 * This method is for the assertions for getFinalParamDescription as defined in ApiBase,
	 * depending on getFinalParams.
	 *
	 * @param string $moduleClass One of the modules in $GLOBALS['wgAPIModules'], only in this
	 *  function for the error messages.
	 * @param ApiBase $module is an instance of $moduleClass
	 **/
	private function assertGetFinalParamDescription( $moduleClass, ApiBase $module ) {
		$parameters = $module->getFinalParams();

		if ( !empty( $parameters ) ) {
			$descriptions = $module->getFinalParamDescription();

			$this->assertNotEmpty(
				$descriptions,
				'the array returned by the method getFinalParamDescription of module '
				. $moduleClass . ' is empty'
			);

			// Comparing the keys of the arrays of getParamDescription and getParams
			$arrayKeysMatch = !array_diff_key( $descriptions, $parameters )
				&& !array_diff_key( $parameters, $descriptions );
			$this->assertTrue( $arrayKeysMatch, 'keys different at ' . $moduleClass );
		}
	}

	/**
	 * This method is for the assertions of getExamplesMessages/ getExamples as defined in ApiBase
	 *
	 * @param string $moduleClass One of the modules in $GLOBALS['wgAPIModules'], only in this
	 *  function for the error messages.
	 * @param ApiBase $module is an instance of $moduleClass
	 **/
	private function assertGetExamplesMessages( $moduleClass, ApiBase $module ) {
		$method = new ReflectionMethod( $moduleClass, 'getExamplesMessages' );
		$method->setAccessible( true );
		$examples = $method->invoke( $module );

		$this->assertNotEmpty( $examples, 'there are no examples for ' . $moduleClass );

		foreach ( $examples as $url => $example ) {
			$this->assertRegExp(
				'/^action=\w/',
				$url,
				'the key ' . $url . ' is not an url at ' . $moduleClass
			);

			if ( is_string( $example ) ) {
				$this->assertTrue(
					wfMessage( $example )->exists(),
					"message ($example) for $url doesn't exist"
				);
			} else {
				$this->assertInstanceOf(
					'Message',
					$example,
					'the value of the example for ' . $url . ' in ' . $moduleClass
					. ' is not a Message'
				);
			}
		}

	}

}
