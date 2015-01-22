<?php

namespace PhpRbac\Tests;

use PhpRbac\Rbac;
use PhpRbac\Database\Jf;

class RbacTest extends RbacTestCase
{
    /** @var Rbac **/
    private $rbac;
    
    public function setUp()
    {
        $this->rbac = new Rbac();

        Jf::loadConfig(static::getSQLConfig('pdo_mysql'));
        Jf::loadConnection();
        
        $this->rbac->reset(true);
    }
    
    public function testAssign()
    {
        $this->rbac->Roles->addPath('/role_01/role_02');
        $this->rbac->Permissions->addPath('/permission_01/permission_02');
        
        $this->assertTrue($this->rbac->assign('role_01', 'permission_02'));
    }
    
    public function testCheck()
    {
        $this->assertTrue($this->rbac->check(1, 1));
    }
    
    public function testEnforce()
    {
        $this->rbac->Permissions->add('read-article', 'Lire un article');
        
        $this->assertTrue($this->rbac->enforce('read-article', 1));
    }
    
    public function testTablePrefix()
    {
        $this->assertInternalType('string', $this->rbac->tablePrefix());
    }
}