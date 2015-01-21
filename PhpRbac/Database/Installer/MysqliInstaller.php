<?php

namespace PhpRbac\Database\Installer;

use PhpRbac\Database\Jf;

class MysqliInstaller extends BasicInstaller
{
    /**
     * {@inheritdoc}
     */
    public function init($host, $user, $pass, $dbname)
    {
        try
        {
            Jf::$Db = new \mysqli($host, $user, $pass, $dbname);
        }
        catch (Exception $ex)
        {
            if ($e->getCode()==1049)
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
        
	$db = new \mysqli($host, $user, $pass);
	$db->query("CREATE DATABASE $dbname");
	$db->select_db($dbname);
        
	if (is_array($queries))
        {
            foreach ($queries as $query)
            {
                $db->query($query);
            }
        }
	Jf::$Db = new \mysqli($host, $user, $pass, $dbname);
	Jf::$Rbac->reset(true);
    }
}