<?php

namespace PhpRbac\Database\Installer;

interface InstallerInterface
{
    /**
     * Try to set the database connection
     * 
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $dbname
     */
    public function init($host, $user, $pass, $dbname);
    
    /**
     * Init instance of DB connection
     * 
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $dbname
     */
    public function install($host, $user, $pass, $dbname);
    
    /**
     * This method will retrieve SQL queries from a file associated with a DBMS
     * 
     * @param string $dbms
     * @return array
     * @throws InvalidArgumentException
     */
    public function getSqlQueries($dbms);
}