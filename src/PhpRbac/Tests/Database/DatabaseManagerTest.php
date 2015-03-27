<?php

namespace PhpRbac\Tests\Database;

use PhpRbac\Database\DatabaseManager;

use PhpRbac\Rbac;
use PhpRbac\Tests\RbacTestCase;

class JfTest extends RbacTestCase
{
    /** @var DatabaseManager **/
    private static $databaseManager;
    
    public static function setUpBeforeClass()
    {
        $config = self::getSQLConfig('pdo_mysql');
        
        $dsn = "mysql:dbname={$config['dbname']};host={$config['host']}";

        $DBConnection = new \PDO($dsn, $config['user'], $config['pass']);

        $rbac = Rbac::getInstance();
        $rbac->init($DBConnection, 'kilix_rbac_');
        
        self::$databaseManager = $rbac->getDatabaseManager();
    }
    
    public function testPdoQuery()
    {
        $query = self::$databaseManager->request('SELECT table_name, table_type, engine FROM information_schema.tables LIMIT 3');
        
        $this->assertInternalType('array', $query);
        $this->assertCount(3, $query);
    }
    
    public function testPdoPrepare()
    {
        $query = self::$databaseManager->request('SELECT table_name, ?, engine FROM information_schema.tables LIMIT 3', ['table_type']);
        
        $this->assertInternalType('array', $query);
        $this->assertCount(3, $query);
    }
    
    public function testMysqliQuery()
    {
        $config = self::getSQLConfig('pdo_mysql');

        $DBConnection = new \mysqli($config['host'], $config['user'], $config['pass'], $config['dbname']);

        $rbac = Rbac::getInstance();
        $rbac->init($DBConnection, 'kilix_rbac_');
        $query = $rbac->getDatabaseManager()->request('SELECT table_name, table_type, engine FROM information_schema.tables LIMIT 3');
        
        $this->assertInternalType('array', $query);
        $this->assertCount(3, $query);
    }
    
    public function testMysqliPrepare()
    {
        $config = self::getSQLConfig('pdo_mysql');

        $DBConnection = new \mysqli($config['host'], $config['user'], $config['pass'], $config['dbname']);

        $rbac = Rbac::getInstance();
        $rbac->init($DBConnection, 'kilix_rbac_');
        $query = $rbac->getDatabaseManager()->request('SELECT table_name, ?, engine FROM information_schema.tables LIMIT 3', ['table_type']);
        
        $this->assertInternalType('array', $query);
        $this->assertCount(3, $query);
    }
}