<?php

namespace PhpRbac\Database\Installer;

abstract class BasicInstaller implements InstallerInterface
{
    /**
     * {@inheritdoc}
     */
    abstract public function init($host, $user, $pass, $dbname);
    
    /**
     * {@inheritdoc}
     */
    abstract public function install($host, $user, $pass, $dbname);
    
    /**
     * {@inheritdoc}
     */
    function getSqlQueries($dbms)
    {
        $file = dirname(dirname(dirname(__DIR__))) . "/{$dbms}.sql";
        
        if(!is_file($file))
        {
            throw new \InvalidArgumentException("$dbms has no associated SQL file.");
        }
        return explode(';', str_replace(
            'PREFIX_',
            Jf::getConfig('table_prefix'),
            file_get_contents($file)
        ));
    }
}