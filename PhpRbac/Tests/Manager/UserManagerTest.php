<?php

namespace PhpRbac\Tests\Manager;

use PhpRbac\Manager\UserManager;
use PhpRbac\Rbac;
use PhpRbac\Database\Jf;

class UserManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var RoleManager **/
    private $manager;
    
    public function setUp()
    {
        $rbac = new Rbac();
        
        Jf::loadConfig($this->configurationProvider()[0][0]);
        Jf::loadConnection();
        
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
    
    public function configurationProvider()
    {
        return [
            [
                [
                    "adapter"       => "pdo_mysql",
                    "host"          => "localhost",
                    "user"          => "root",
                    "pass"          => "vagrant",
                    "dbname"        => "kilix_rbac_test",
                    "table_prefix"  => "kilix_rbac_"
                ]
            ]
        ];
    }
}