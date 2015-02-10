<?php

namespace PhpRbac\Tests\Database;

use PhpRbac\Database\Jf;

use PhpRbac\Rbac;
use PhpRbac\Tests\RbacTestCase;

class JfTest extends RbacTestCase
{
    public static function setUpBeforeClass()
    {
        $config = self::configProvider()[0][0];
        $config['adapter'] = 'pdo_mysql';
        
        Jf::loadConfig($config);
        Jf::loadConnection();
    }
    
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
        $config['adapter'] = 'mysql';
        Jf::loadConfig($config);
        Jf::loadConnection();
        
        Rbac::getInstance();
        
        $query = Jf::sql('SELECT * FROM kilix_rbac_roles');
        
        $this->assertInternalType('array', $query);
        $this->assertCount(1, $query);
    }
    
    /**
     * @dataProvider configProvider
     */
    public function testSqlPrepareMysqlite($config)
    {
        $config['adapter'] = 'mysql';
        Jf::loadConfig($config);
        Jf::loadConnection();
        
        Rbac::getInstance();
        
        $query = Jf::sql('SELECT * FROM kilix_rbac_roles WHERE ID = ?;', 1);
        
        $this->assertInternalType('array', $query);
        $this->assertCount(1, $query);
    }
    
    public static function configProvider()
    {
        return [
            [
                static::getSQLConfig('fakeAdapter'),
            ]
        ];
    }
}