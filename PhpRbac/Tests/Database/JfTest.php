<?php

namespace PhpRbac\Tests\Database;

use PhpRbac\Database\Jf;

use PhpRbac\Rbac;

class JfTest extends \PHPUnit_Framework_TestCase
{
    public function testSetTablePrefix()
    {
        $this->assertNull(Jf::$TABLE_PREFIX);
        
        Jf::setTablePrefix('test_table_prefix');
        
        $this->assertEquals('test_table_prefix', Jf::$TABLE_PREFIX);
    }
    
    /**
     * @dataProvider configProvider
     */
    public function testLoadConfigWithArray($config)
    {
        Jf::loadConfig($config);
        
        $this->assertEquals('localhost', Jf::getConfig('host'));
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidLoadconfig()
    {
        Jf::loadConfig('configuration');
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidGetConfig()
    {
        Jf::getConfig('false_key');
    }
    
    /**
     * @expectedException ErrorException
     */
    public function testInvalidLoadConnection()
    {
        Jf::loadConnection();
    }
    
    public function testSqlQuery()
    {
        $query = Jf::sql('SELECT table_name, table_type, engine FROM information_schema.tables LIMIT 3');
        
        $this->assertInternalType('array', $query);
        $this->assertCount(3, $query);
    }
    
    public function testSqlPrepare()
    {
        $query = Jf::sql('SELECT table_name, ?, engine FROM information_schema.tables LIMIT 3', 'table_type');
        
        $this->assertInternalType('array', $query);
        $this->assertCount(3, $query);
    }
    
    public function testGetTime()
    {
        $time = Jf::time();
        
        $this->assertEquals(date('G:m'), date('G:m', $time));
    }
    
    /**
     * @dataProvider configProvider
     */
    public function testSqlQueryMysqlite($config)
    {
        $rbac = new Rbac();
        
        $config['adapter'] = 'mysql';
        Jf::loadConfig($config);
        Jf::loadConnection();
        
        $query = Jf::sql('SELECT * FROM kilix_rbac_roles');
        
        $this->assertInternalType('array', $query);
        $this->assertCount(1, $query);
    }
    
    /**
     * @dataProvider configProvider
     */
    public function testSqlPrepareMysqlite($config)
    {
        $rbac = new Rbac();
        
        $config['adapter'] = 'mysql';
        Jf::loadConfig($config);
        Jf::loadConnection();
        
        $query = Jf::sql('SELECT * FROM kilix_rbac_roles WHERE ID = ?;', 1);
        
        $this->assertInternalType('array', $query);
        $this->assertCount(1, $query);
    }
    
    public function configProvider()
    {
        return [
            [
                [
                    'adapter' => 'fakeAdapter',
                    'host' => 'localhost',
                    'user' => 'root',
                    'pass' => 'vagrant',
                    'dbname'=> 'kilix_rbac_test',
                    'table_prefix' => 'kilix_rbac_'
                ]
            ]
        ];
    }
}