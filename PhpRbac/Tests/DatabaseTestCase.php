<?php

namespace PhpRbac\Tests;

use PhpRbac\Database\Jf;

abstract class DatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase
{
    // only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;

    final public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                if ((string) Jf::getConfig('adapter') === 'pdo_sqlite') {
                    self::$pdo = new \PDO('sqlite:' . dirname(dirname(__FILE__)) . '/database/' . Jf::getConfig('dbname'));

                    $sql = file_get_contents(dirname(dirname(__DIR__))."/database/sqlite.sql");
                    $sql = str_replace("PREFIX_", Jf::getConfig('table_prefix'), $sql);
                    $statements = explode(";", $sql);

                    if (is_array($statements))
                    foreach ($statements as $query)
                        self::$pdo->query($query);

                } else {
                    self::$pdo = new \PDO("mysql:host=".Jf::getConfig('host').";dbname=".Jf::getConfig('dbname'), Jf::getConfig('user'), Jf::getConfig('pass'));
                }
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, Jf::getConfig('dbname'));
        }
        return $this->conn;
    }
}
