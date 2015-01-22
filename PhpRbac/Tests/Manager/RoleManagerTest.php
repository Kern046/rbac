<?php

namespace PhpRbac\Tests\Manager;

use PhpRbac\Manager\RoleManager;
use PhpRbac\Rbac;
use PhpRbac\Database\Jf;

class RoleManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var RoleManager **/
    private $manager;
    
    public function setUp()
    {
        $rbac = new Rbac();
        
        Jf::loadConfig($this->configurationProvider()[0][0]);
        Jf::loadConnection();
        
        $rbac->reset(true);
        
        $this->manager = new RoleManager();
    }
    
    public function testRecursiveRemove()
    {
        $this->manager->addPath('/administrator/moderator');
        
        $this->manager->remove($this->manager->titleId('administrator'), true);
        
        $this->assertEquals(1, $this->manager->count());
    }
    
    public function testRemove()
    {
        $this->manager->addPath('/administrator/moderator', ['Admin', 'Modo']);
        
        $this->manager->remove($this->manager->titleId('administrator'));
        
        $this->assertEquals(2, $this->manager->count());
        $this->assertEquals('moderator', $this->manager->children(1)[0]['Title']);
        $this->assertEquals('Modo', $this->manager->children(1)[0]['Description']);
    }
    
    public function testHasPermission()
    {
        $this->assertTrue($this->manager->hasPermission(1, 1));
    }
    
    public function testHasNoPermission()
    {
        $this->assertFalse($this->manager->hasPermission(1, 2));
    }
    
    public function testPermissions()
    {
        $permissions = $this->manager->permissions(1);
        
        $this->assertCount(1, $permissions);
    }
    
    public function testPermissionsWithRoleTitle()
    {
        $permissions = $this->manager->permissions('root');
        
        $this->assertCount(1, $permissions);
    }
    
    public function testCompletePermissions()
    {
        $permissions = $this->manager->permissions(1, false);
        
        $this->assertCount(1, $permissions);
        $this->assertEquals('root', $permissions[0]['Title']);
    }
    
    public function testUnassign()
    {
        $this->assertTrue($this->manager->unassign(1, 1));
    }
    
    public function testUnassignWithTitle()
    {
        $this->assertTrue($this->manager->unassign('root', 'root'));
    }
    
    public function testUnassignWithPath()
    {
        $this->assertTrue($this->manager->unassign('/', '/'));
    }
    
    public function testUnassignUsers()
    {
        $this->assertEquals(1, $this->manager->unassignPermissions(1));
    }
    
    public function testUnassignPermissions()
    {
        $this->assertEquals(1, $this->manager->unassignUsers(1));
    }
    
    public function testParentNode()
    {
        $this->manager->addPath('/admin');
        
        $this->assertEquals('root', $this->manager->parentNode($this->manager->titleId('admin'))['Title']);
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