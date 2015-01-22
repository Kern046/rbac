<?php

namespace PhpRbac\Database\Installer;

use PhpRbac\Database\Jf;

class PdoSqliteInstaller extends BasicInstaller
{
    /**
     * {@inheritdoc}
     */
    public function init($host, $user, $pass, $dbname)
    {
	if (!file_exists($dbname))
        {
            $this->install($host, $user, $pass, $dbname);
            return true;
        }
        Jf::$Db = new \PDO("sqlite:{$dbname}", $user, $pass);
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function install($host, $user, $pass, $dbname)
    {
	Jf::$Db=new \PDO("sqlite:{$dbname}", $user, $pass);
        
	$queries = $this->getSqlQueries('sqlite');
        
	if (is_array($queries))
        {
            foreach ($queries as $query)
            {
                Jf::$Db->query($query);
            }
        }
        
	Jf::$Rbac->reset(true);
    }
}