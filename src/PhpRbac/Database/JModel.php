<?php

namespace PhpRbac\Database;

use PhpRbac\Rbac;

class JModel
{
    protected function isSQLite()
    {
        $databaseConnection =
            Rbac::getInstance()
            ->getDatabaseManager()
            ->getConnection()
        ;
        
	return
            $databaseConnection instanceof \PDO &&
            $databaseConnection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ;
    }
    protected function isMySql()
    {
        $databaseConnection =
            Rbac::getInstance()
            ->getDatabaseManager()
            ->getConnection()
        ;
        
        return
            $databaseConnection instanceof \mysqli ||
            (
                $databaseConnection instanceof \PDO &&
                $databaseConnection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql'
            )
        ;
    }
}