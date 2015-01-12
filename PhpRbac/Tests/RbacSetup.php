<?php

namespace PhpRbac\Tests;

use PhpRbac\Rbac;

use PhpRbac\Database\Jf;
use PhpRbac\Tests\DatabaseTestCase;

/**
 * @file
 * Unit Tests for PhpRbac PSR Wrapper
 *
 * @defgroup phprbac_unit_test_wrapper_setup Unit Tests for Rbac Functionality
 * @ingroup phprbac_unit_tests
 * @{
 * Documentation for all Unit Tests regarding RbacSetup functionality.
 */

class RbacSetup extends DatabaseTestCase
{
    /*
     * Test Setup and Fixture
     */

	public static $rbac;

    public static function setUpBeforeClass()
    {
    	self::$rbac = new Rbac();

    	if (Jf::getConfig('adapter') === 'pdo_sqlite') {
    	    self::$rbac->reset(true);
    	}
    }

    protected function tearDown()
    {
        if (Jf::getConfig('adapter') === 'pdo_sqlite') {
            self::$rbac->reset(true);
        }
    }

    public function getDataSet()
    {
        return $this->createXMLDataSet(dirname(__FILE__) . '/datasets/database-seed.xml');
    }

    /*
     * Tests for proper object instantiation
     */

    public function testRbacInstance() {
        $this->assertInstanceOf('PhpRbac\Rbac', self::$rbac);
    }
}

/** @} */ // End group phprbac_unit_test_wrapper_setup */
