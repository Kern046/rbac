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
            return true;
        }
        catch (\Exception $ex)
        {
            if ($ex->getCode() === 2)
            {
                $this->install($host, $user, $pass, $dbname);
                return true;
            }
            throw $ex;	
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function install($host, $user, $pass, $dbname)
    {
        $queries = $this->getSqlQueries('mysql');

        $db = new \mysqli($host, $user, $pass);
        $db->query("CREATE DATABASE IF NOT EXISTS $dbname");
        $db->select_db($dbname);

        if (is_array($queries)) {
            foreach ($queries as $query) {
                $db->query($query);
            }
        }
        Jf::$Db = new \mysqli($host, $user, $pass, $dbname);
        Jf::$Rbac->reset(true);
    }
}