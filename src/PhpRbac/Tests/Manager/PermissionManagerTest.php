<?php

namespace PhpRbac\Tests\Manager;

use PhpRbac\Manager\PermissionManager;
use PhpRbac\Rbac;
use PhpRbac\Tests\RbacTestCase;

class PermissionManagerTest extends RbacTestCase
{
    /** @var PermissionManager **/
    private $manager;
    
    public function setUp()
    {
        $config = self::getSQLConfig('pdo_mysql');
        
        $dsn = "mysql:dbname={$config['dbname']};host={$config['host']}";

        $DBConnection = new \PDO($dsn, $config['user'], $config['pass']);
        
        $rbac = Rbac::getInstance();
        $rbac->init($DBConnection, 'kilix_rbac_');
        
        $rbac->reset(true);
        
        $this->manager = new PermissionManager();
    }
    
    public function testAdd()
    {
        $this->manager->add('read-article', 'Lire un article');
        
        $nbPermissions = $this->manager->count();
        
        $this->assertEquals(2, $nbPermissions);
    }
    
    public function testAddPath()
    {
        $this->manager->addPath('/connect-admin');
        
        $permissions = $this->manager->children(1);
        
        $this->assertCount(1, $permissions);
        $this->assertEquals('connect-admin', $permissions[0]['Title']);
    }
    
    public function testAssign()
    {
        $this->manager->addPath('/connect-admin');
        $this->manager->assign(1, 'connect-admin');
        
        $this->assertCount(1, $this->manager->roles('connect-admin'));
    }
    
    public function testRemove()
    {
        $this->manager->addPath('/connect-admin');
        $this->manager->assign(1, 'connect-admin');
        
        $return = $this->manager->remove($this->manager->titleId('connect-admin'));
        
        $this->assertTrue($return);
        $this->assertEquals(1, $this->manager->count());
    }
    
    public function testRoles()
    {
        $this->manager->addPath('/connect-admin');
        $this->manager->assign(1, 'connect-admin');
        
        $roles = $this->manager->roles('connect-admin', false);
        
        $this->assertCount(1, $roles);
        $this->assertEquals('root', $roles[0]['Title']);
    }
    
    public function testDepth()
    {
        $this->manager->addPath('/register-admin/connect-admin');
        $this->manager->depth($this->manager->titleId('register-admin'));
    }
    
    public function testGetTitle()
    {
        $this->assertEquals('root', $this->manager->getTitle(1));
    }
    
    public function testGetDescription()
    {
        $this->assertEquals('root', $this->manager->getDescription(1));
    }
    
    public function testGetPath()
    {
        $this->assertEquals('/', $this->manager->getPath(1));
    }
    
    public function testUnassign()
    {
        $this->assertEquals(1, $this->manager->unassignRoles(1));
        $this->assertNull($this->manager->roles(1));
    }
    
    public function testEdit()
    {
        $this->manager->addPath('/log');
        
        $this->manager->edit($this->manager->titleId('log'), 'file-log', 'Log into files');
        
        $this->assertEquals('file-log', $this->manager->descendants(1)['file-log']['Title']);
    }
}