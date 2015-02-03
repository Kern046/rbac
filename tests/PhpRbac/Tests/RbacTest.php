<?php

namespace PhpRbac\Tests;

use PhpRbac\Rbac;
use PhpRbac\Manager\RbacManager;
use PhpRbac\Database\Jf;

class RbacTest extends RbacTestCase
{
    /** @var RbacManager **/
    private $rbacManager;
    
    public function setUp()
    {
        Jf::loadConfig(static::getSQLConfig('pdo_mysql'));
        Jf::loadConnection();
        
        $this->rbacManager = Rbac::getInstance()->getManager();
        $this->rbacManager->reset(true);
    }
    
    public function testAssign()
    {
        $this->rbacManager->getRoleManager()->addPath('/role_01/role_02');
        $this->rbacManager->getPermissionManager()->addPath('/permission_01/permission_02');
        
        $this->assertTrue($this->rbacManager->assign('role_01', 'permission_02'));
    }
    
    public function testCheck()
    {
        $this->assertTrue($this->rbacManager->check(1, 1));
    }
    
    public function testEnforce()
    {
        $this->rbacManager->getPermissionManager()->add('read-article', 'Lire un article');
        
        $this->assertTrue($this->rbacManager->enforce('read-article', 1));
    }
    
    public function testTablePrefix()
    {
        $this->assertInternalType('string', Rbac::getInstance()->tablePrefix());
    }
}