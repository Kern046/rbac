<?php

namespace PhpRbac\Tests\Database\Installer;

use PhpRbac\Database\Installer\PdoSqliteInstaller;

use PhpRbac\Database\Jf;
use PhpRbac\Tests\RbacTestCase;
use PhpRbac\Rbac;

class PdoSqliteInstallerTest extends RbacTestCase
{
    public function setUp()
    {
        new Rbac();
        $config = static::getSQLConfig('pdo_sqlite');

        Jf::loadConfig($config);
        Jf::loadConnection();

        Jf::$Db->query('DROP DATABASE kilix_rbac_test');
        if ($config['dbname'] != ':memory:') {
            unlink($config['dbname']);
        }

    }
    
    /**
     * @dataProvider configurationProvider
     */
    public function test($config)
    {
        Jf::loadConfig($config);
        
        $installer = new PdoSqliteInstaller();
        // DB file will be created
        $installationSuccess = $installer->init(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['dbname']
        );
        
        $this->assertTrue($installationSuccess);
        // File exists
        $installationSuccess02 = $installer->init(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['dbname']
        );
        
        $this->assertTrue($installationSuccess02);
    }
    
    public function configurationProvider()
    {
        return [
            [
                static::getSQLConfig('pdo_sqlite'),
            ]
        ];
    }
}