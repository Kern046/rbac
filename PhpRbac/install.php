<?php

    use PhpRbac\Rbac;
    /**
     * We check if the script is really executed by a shell user
     * If he doesn't, we redirect the user to the website root
     */
    if(!isset($argv))
    {
        header('Location: /');
    }
    
    require('autoload.php');
    
    $database = [
        'adapter'       => null,
        'host'          => null,
        'name'          => null,
        'table_prefix'  => null,
        'user'          => null,
        'password'      => null
    ];
    
    $adapters = [
        'pdo_mysql' => '"%s"',
        'mysqli' => '"%s"',
        'pdo_sqlite' => '__DIR__ . "/%s.sqlite3"' 
    ];
    
    do{
        $retry = true;
        
        setAdapters($database, $adapters);
        setHost($database);
        setName($database);
        setTablePrefix($database);
        setUser($database);
        setPassword($database);
        
        if(confirm($database) === false)
        {
            fputs(STDOUT, 'Cleaning the previous data..' . PHP_EOL);
            continue;
        }
        
        generateConfig($database, $adapters);
        generateDatabase();
        
        if(tryConnection($database) === false)
        {
            $retry = true;
            continue;
        }
        
        $retry = false;
    }while($retry === true);
    
    function setAdapters(&$database, $adapters)
    {
        fputs(STDOUT, 'Please select one DB adapter' . PHP_EOL);
        fputs(STDOUT, 'Available adapters' . PHP_EOL);
        
        reset($adapters);
        while($key = key($adapters))
        {
            fputs(STDOUT, "- $key".PHP_EOL);
            next($adapters);
        }
        
        fputs(STDOUT, 'Database adapter [pdo_mysql] : ');
        $adapter = trim(fgets(STDIN));
        
        $database['adapter'] = (!empty($adapter)) ? $adapter : 'pdo_mysql';
        
        if(!isset($adapters[$database['adapter']]))
        {
            fputs(STDOUT, 'A wrong adapter has been chosen. Please correct it.' . PHP_EOL);
            setAdapters($database, $adapters);
            return true;
        }
    }
    
    function setHost(&$database)
    {
        fputs(STDOUT, 'Database host (ex. 127.0.0.1) : ');

        $database['host'] = trim(fgets(STDIN));
    }
    
    function setName(&$database)
    {
        fputs(STDOUT, 'Database name : ');

        $database['name'] = trim(fgets(STDIN));
    }
    
    function setTablePrefix(&$database)
    {
        fputs(STDOUT, 'Table prefix [phprbac_] : ');
        
        $prefix = trim(fgets(STDIN));
        $database['table_prefix'] = (!empty($prefix)) ? $prefix : 'phprbac_';
    }
    
    function setUser(&$database)
    {
        fputs(STDOUT, 'Database user : ');

        $database['user'] = trim(fgets(STDIN));
    }
    
    function setPassword(&$database)
    {
        fputs(STDOUT, 'Database password : ');

        $database['password'] = trim(fgets(STDIN));
    }
    
    function confirm($database)
    {
        fputs(STDOUT, PHP_EOL);
        fputs(STDOUT, "The host is {$database['host']}" . PHP_EOL);
        fputs(STDOUT, "The database name is {$database['name']} with {$database['adapter']} driver" . PHP_EOL);
        fputs(STDOUT, "All the RBAC tables will be prefixed with {$database['table_prefix']}" . PHP_EOL);
        fputs(STDOUT, "The credentials are {$database['user']}:{$database['password']}" . PHP_EOL);
        fputs(STDOUT, "Do you confirm this is the expected configuration [Y/n] (Y) ?" . PHP_EOL);
        
        return (trim(fgets(STDIN)) !== 'n');
    }
    
    function tryConnection($database)
    {
        try{
            new PDO(
                "mysql:host={$database['host']}; dbname={$database['name']}",
                $database['user'],
                $database['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
            fputs(STDOUT, 'Test connection success.' . PHP_EOL);
            return true;
        }
        catch (PDOException $ex)
        {
            fputs(STDOUT, 'The connection failed. Please try again.' . PHP_EOL);
            return false;
        }
    }
    
    function generateConfig($database, $adapters)
    {
        $data =	 
            '<?php' . PHP_EOL .
            '$adapter="' . $database['adapter'] . '";' . PHP_EOL . 
            '$host="' . $database['host'] . '";'. PHP_EOL .
            '$dbname=' . sprintf($adapters[$database['adapter']], $database['name']) . ';' . PHP_EOL .
            '$tablePrefix = "' . $database['table_prefix'] . '";' . PHP_EOL .
            '$user="' . $database['user'] . '";' . PHP_EOL .
            '$pass="' . $database['password'] . '";' . PHP_EOL
        ;

        $dbConnFile = 'database' . DIRECTORY_SEPARATOR . 'database.config';

        file_put_contents($dbConnFile, $data);

        $currentOS = strtoupper(substr(PHP_OS, 0, 3));

        if ($currentOS != 'WIN') {
            chmod($dbConnFile, 0644);
        }
    }
    
    function generateDatabase()
    {
        $rbac = new Rbac();
        $rbac->reset(true);
        
        fputs(STDOUT, 'Database is generated.' . PHP_EOL);
    }