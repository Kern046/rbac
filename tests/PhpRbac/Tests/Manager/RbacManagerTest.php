<?php

namespace PhpRbac\Tests\Manager;

use PhpRbac\Manager\RbacManager;
use PhpRbac\Rbac;
use PhpRbac\Exception\RbacUserNotProvidedException;
use PhpRbac\Database\Jf;
use PhpRbac\Tests\RbacTestCase;

class RbacManagerTest extends RbacTestCase
{
    /** @var RbacManager **/
    private $manager;
    
    public function setUp()
    {
        Jf::loadConfig(static::getSQLConfig('pdo_mysql'));
        Jf::loadConnection();
        
        $rbac = Rbac::getInstance();
        
        $rbac->reset(true);
        
        $this->manager = new RbacManager();
    }
    
    public function testAssign()
    {
        $this->manager->assign(1, 1);
        
        $this->assertCount(1, $this->manager->getPermissionManager()->roles(1));
    }
    
    public function testCheckWithInexactData()
    {
        $this->assertFalse($this->manager->check(1, 2));
    }
    
    public function testCheckWithExactData()
    {
        $this->assertTrue($this->manager->check('/', 1));
    }
    
    public function testCheckWithPermissionTitle()
    {
        $this->assertTrue($this->manager->check('root', 1));
    }
    
    /**
     * @expectedException \PhpRbac\Exception\RbacUserNotProvidedException
     */
    public function testInvalidCheck()
    {
        $this->manager->check(1);
    }
    
    public function testEnforceWithExactData()
    {
        $this->assertTrue($this->manager->enforce(1, 1));
    }
    
    /**
     * @expectedException \PhpRbac\Exception\RbacUserNotProvidedException
     */
    public function testInvalidEnforce()
    {
        $this->manager->enforce(1);
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testEnforceWithInexactData()
    {
        $this->markTestIncomplete('Need to fix the PHPUnit_Framework_Error');
        $this->manager->enforce(1, 2);
    }
    
    /**
     * @expectedException Exception
     */
    public function testInvalidReset()
    {
        $this->manager->reset();
    }
}