<?php

namespace PhpRbac\Tests;

use PhpRbac\Rbac;

class RbacTest extends RbacTestCase
{
    /** @var Rbac **/
    private $rbac;
    
    public function setUp()
    {
        $config = self::getSQLConfig('pdo_mysql');
        
        $dsn = "mysql:dbname={$config['dbname']};host={$config['host']}";

        $DBConnection = new \PDO($dsn, $config['user'], $config['pass']);
        
        $this->rbac = Rbac::getInstance();
        $this->rbac->init($DBConnection, 'kilix_rbac_');
        $this->rbac->reset(true);
    }
    
    public function testAssign()
    {
        $this->rbac->getRbacManager()->getRoleManager()->addPath('/role_01/role_02');
        $this->rbac->getRbacManager()->getPermissionManager()->addPath('/permission_01/permission_02');
        
        $this->assertTrue($this->rbac->assign('role_01', 'permission_02'));
    }
    
    public function testCheck()
    {
        $this->assertTrue($this->rbac->check(1, 1));
    }
    
    public function testEnforce()
    {
        $this->rbac->getRbacManager()->getPermissionManager()->add('read-article', 'Lire un article');
        
        $this->assertTrue($this->rbac->enforce('read-article', 1));
    }
}