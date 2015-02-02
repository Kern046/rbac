<?php

namespace PhpRbac\Tests\Database\Installer;

use PhpRbac\Database\Installer\PdoMysqlInstaller;

use PhpRbac\Database\Jf;
use PhpRbac\Tests\RbacTestCase;
use PhpRbac\Rbac;

class PdoMysqlInstallerTest extends RbacTestCase
{
    public function setUp()
    {
        new Rbac();
        Jf::$Db->query('DROP DATABASE kilix_rbac_test');
    }
    
    /**
     * @dataProvider configurationProvider
     */
    public function test($config)
    {
        Jf::loadConfig($config);
        
        $installer = new PdoMysqlInstaller();
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
                static::getSQLConfig('pdo_mysql'),
            ]
        ];
    }
}