<?php

namespace PhpRbac\Tests\Database\Installer;

use PhpRbac\Database\Installer\MysqliInstaller;

use PhpRbac\Database\Jf;
use PhpRbac\Tests\RbacTestCase;
use PhpRbac\Rbac;

class MysqliInstallerTest extends RbacTestCase
{
    public function setUp()
    {
        Jf::loadConfig(static::getSQLConfig('pdo_mysql'));
        Jf::loadConnection();
        
        Rbac::getInstance();
        
        Jf::$Db->query('DROP DATABASE kilix_rbac_test');
    }
    
    /**
     * @dataProvider configurationProvider
     */
    public function test($config)
    {
        Jf::loadConfig($config);
        
        $installer = new MysqliInstaller();
        $installationSuccess = $installer->init(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['dbname']
        );
        
        $this->assertTrue($installationSuccess);
    }

    public function configurationProvider()
    {
        return [
            [
                static::getSQLConfig('mysql'),
            ]
        ];
    }
}