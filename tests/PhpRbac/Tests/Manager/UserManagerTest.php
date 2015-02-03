<?php

namespace PhpRbac\Tests\Manager;

use PhpRbac\Manager\UserManager;
use PhpRbac\Rbac;
use PhpRbac\Database\Jf;
use PhpRbac\Tests\RbacTestCase;

class UserManagerTest extends RbacTestCase
{
    /** @var RoleManager **/
    private $manager;
    
    public function setUp()
    {
        Jf::loadConfig(static::getSQLConfig('pdo_mysql'));
        Jf::loadConnection();
        
        $rbac = Rbac::getInstance();
        
        $rbac->reset(true);
        
        $this->manager = new UserManager();
    }
    
    public function testHasRole()
    {
        $this->assertTrue($this->manager->hasRole(1, 1));
    }
    
    public function testHasRoleWithRolePath()
    {
        $this->assertTrue($this->manager->hasRole('/', 1));
    }
    
    public function testHasRoleWithRoleTitle()
    {
        $this->assertTrue($this->manager->hasRole('root', 1));
    }
    
    public function testAssign()
    {
        $this->assertTrue($this->manager->assign(1, 2));
    }
    
    public function testUnassign()
    {
        $this->assertTrue($this->manager->unassign(1, 1));
    }
    
    public function testUnassignWithRolePath()
    {
        $this->assertTrue($this->manager->unassign('/', 1));
    }
    
    public function testUnassignWithRoleTitle()
    {
        $this->assertTrue($this->manager->unassign('root', 1));
    }
    
    public function testAllRoles()
    {
        $this->assertCount(1, $this->manager->allRoles(1));
    }
    
    public function testRoleCount()
    {
        $this->assertequals(1, $this->manager->roleCount(1));
    }
}