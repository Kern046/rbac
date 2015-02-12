<?php

namespace PhpRbac\Tests;

abstract class RbacTestCase extends \PHPUnit_Framework_TestCase
{
    protected static function getSQLConfig($adapter = 'pdo_mysql')
    {
        switch($adapter) {
            case 'pdo_sqlite':
                $config = [
                    "adapter"       => $adapter,
                    "host"          => $GLOBALS['SQLITE_DB_HOST'],
                    "user"          => $GLOBALS['SQLITE_DB_USER'],
                    "pass"          => $GLOBALS['SQLITE_DB_PASSWD'],
                    "dbname"        => $GLOBALS['SQLITE_DB_DBNAME'],
                ];
                break;
            case 'pdo_mysql':
            case 'mysql':
            default:
                $config = [
                    "adapter"       => $adapter,
                    "host"          => $GLOBALS['MYSQL_DB_HOST'],
                    "user"          => $GLOBALS['MYSQL_DB_USER'],
                    "pass"          => $GLOBALS['MYSQL_DB_PASSWD'],
                    "dbname"        => $GLOBALS['MYSQL_DB_DBNAME'],
                ];
        }
        $config['table_prefix'] = $GLOBALS['DB_TABLE_PREFIX'];
        return $config;
    }
}
