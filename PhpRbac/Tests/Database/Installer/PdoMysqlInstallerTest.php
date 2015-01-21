<?php

namespace PhpRbac\Tests\Database\Installer;

use PhpRbac\Database\Installer\PdoMysqlInstaller;

use PhpRbac\Database\Jf;

class PdoMysqlInstallerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
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
                [
                    'host' => 'localhost',
                    'user' => 'root',
                    'pass' => 'vagrant',
                    'dbname' => 'kilix_rbac_test',
                    'table_prefix' => 'kilix_rbac_'
                ]
            ]
        ];
    }
}