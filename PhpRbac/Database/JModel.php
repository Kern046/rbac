<?php

namespace PhpRbac\Database;

class JModel
{
    protected function isSQLite()
    {
	return Jf::$Db instanceof \PDO && Jf::$Db->getAttribute(\PDO::ATTR_DRIVER_NAME)=="sqlite";
    }
    protected function isMySql()
    {
        return Jf::$Db instanceof \mysqli || (Jf::$Db instanceof \PDO && Jf::$Db->getAttribute(\PDO::ATTR_DRIVER_NAME)=="mysql");
    }
}