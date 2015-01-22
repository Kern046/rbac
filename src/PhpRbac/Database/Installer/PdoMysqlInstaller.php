<?php

namespace PhpRbac\Database\Installer;

use PhpRbac\Database\Jf;

class PdoMysqlInstaller extends BasicInstaller
{
    /**
     * {@inheritdoc}
     */
    public function init($host, $user, $pass, $dbname)
    {
	try
        {
            Jf::$Db = new \PDO("mysql:host={$host};dbname={$dbname}",$user,$pass);
	}
	catch (\PDOException $e)
	{
            if ($e->getCode() === 1049)
            {
                $this->install($host, $user, $pass, $dbname);
                return true;
            }
            throw $e;
	}
    }
    
    /**
     * {@inheritdoc}
     */
    public function install($host, $user, $pass, $dbname)
    {
	$queries = $this->getSqlQueries('mysql');
        
	$db = new \PDO("mysql:host={$host};", $user, $pass);
        
	$db->query("CREATE DATABASE {$dbname}");
	$db->query("USE {$dbname}");
        
	if (is_array($queries))
        {
            foreach ($queries as $query)
            {
                $db->query($query);
            }
        }
	Jf::$Db = new \PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
	Jf::$Rbac->reset(true);
    }
}