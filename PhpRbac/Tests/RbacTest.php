<?php

namespace PhpRbac\Tests;

use PhpRbac\Rbac;
use PhpRbac\Database\Jf;

class RbacTest extends \PHPUnit_Framework_TestCase
{
    /** @var Rbac **/
    private $rbac;
    
    public function setUp()
    {
        $this->rbac = new Rbac();
        
        Jf::loadConfig($this->configurationProvider()[0][0]);
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