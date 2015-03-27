<?php

namespace PhpRbac\Database;

class DatabaseManager
{
    /** @var object **/
    private $connection;
    /** @var boolean **/
    private $groupConcatLimitChanged = false;
    /** @var string **/
    private $tablePrefix;

    /**
     * Set the Database connection and the RBAC tables prefix
     * 
     * @param object $DBConnection
     * @param string $tablePrefix
     */
    public function __construct($DBConnection, $tablePrefix = 'phprbac_')
    {
        $this->connection = $DBConnection;
        $this->tablePrefix = $tablePrefix;
    }
    
    /**
     * Return the Database connection
     * 
     * @return object
     */
    public function getConnection()
    {
        return $this->connection;
    }
    
    /**
     * Return the RBAC tables prefix
     * 
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }
    
    /**
     * @return boolean
     */
    public function isSQLite()
    {
	return
            $this->connection instanceof \PDO &&
            $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ;
    }
    
    /**
     * @return boolean
     */
    public function isMySql()
    {
        return
            $this->connection instanceof \mysqli ||
            (
                $this->connection instanceof \PDO &&
                $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql'
            )
        ;
    }

    /**
     * The Jf::sql function. The behavior of this function is as follows:
     *
     * * On queries with no parameters, it should use query function and fetch all results (no prepared statement)
     * * On queries with parameters, parameters are provided as question marks (?) and then additional function arguments will be
     * 	 bound to question marks.
     * * On SELECT, it will return 2D array of results or NULL if no result.
     * * On DELETE, UPDATE it returns affected rows
     * * On INSERT, if auto-increment is available last insert id, otherwise affected rows
     *
     * @todo currently sqlite always returns sequence number for lastInsertId, so there's no way of knowing if insert worked instead of execute result. all instances of ==1 replaced with >=1 to check for insert
     *
     * @param string $query
     * @param array $arguments
     * @throws Exception
     * @return array|integer|null
     */
    public function request($query, $arguments = [])
    {
        if ($this->connection instanceof \PDO)
        {
            return $this->pdoRequest($query, $arguments);
        }
        elseif($this->connection instanceof \mysqli)
        {
            return $this->mysqliRequest($query, $arguments);
        }
        throw new \Exception('Unknown database interface type.');
    }

    /**
     * Execute a SQL query with a PDO connection
     * 
     * @param string $query
     * @param array $arguments
     * @return boolean
     */
    public function pdoRequest($query, $arguments = [])
    {
        $this->checkGroupConcatLimit();

        if(!$stmt = $this->connection->prepare($query))
        {
            return false;
        }
        
        $nbArguments = count($arguments);
        
        for($i = 0; $i < $nbArguments; ++$i)
        {
            $stmt->bindValue($i + 1, $arguments[$i]);
        }
            
        $success = $stmt->execute();

        $type = substr(trim(strtoupper($query)), 0, 6);
        
        if ($type === 'INSERT')
        {
            if($success === false)
            {
                return null;
            }
            $res = $this->connection->lastInsertId();
            if ($res == 0)
            {
                return $stmt->rowCount ();
            } 
            return $res;
        }
        elseif($type === 'DELETE' || $type === 'UPDATE' || $type === 'REPLACE')
        {
            return $stmt->rowCount();
        }  
        elseif ($type === 'SELECT')
        {
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($res) === 0)
            {
                return null;
            }
            return $res;
        }
    }

    /**
     * Execute a SQL query with a mysqli connection
     * 
     * @param string $query
     * @param array $arguments
     * @return boolean
     */
    public function mysqliRequest($query, $arguments = [])
    {
        $this->checkGroupConcatLimit();
        $nbArguments = count($arguments);
        
        if($nbArguments === 0)
        {
            if (($result = $this->connection->query($query)) === true)
            {
                return true;
            }
            elseif ($result !== false && $result->num_rows !== false)
            {
                $out = [];
                while($r = $result->fetch_array(MYSQLI_ASSOC))
                {
                    $out [] = $r;
                }    
                return $out;
            }
            return null;
        }
        
        if(($preparedStatement = $this->connection->prepare($query)) === false)
        {
            trigger_error("Unable to prepare statement: {$query}, reason: ".$this->connection->error);
        }
        
        $a = [];
        
        foreach($arguments as $k => &$v)
        {
            $a[$k] = &$v;
        }
        
        array_unshift($a, str_repeat('s', $nbArguments));
        
        call_user_func_array([$preparedStatement, 'bind_param'], $a);
        $preparedStatement->execute ();

        $type = substr(trim(strtoupper($query)), 0, 6);
        
        if ($type == 'INSERT')
        {
            $res = $this->connection->insert_id;
            if ($res == 0)
            {
                return $this->connection->affected_rows;
            }
            return $res;
        }
        elseif ($type == 'DELETE' or $type == 'UPDATE' or $type == 'REPLACE')
        {
            return $this->connection->affected_rows;
        }
        elseif ($type == 'SELECT')
        {
            // fetching all results in a 2D array
            $metadata = $preparedStatement->result_metadata ();
            $out = [];
            $fields = [];
            if ($metadata === false)
            {
                return null;
            }
            while($field = $metadata->fetch_field ())
            {
                $fields [] = &$out [$field->name];
            }
            call_user_func_array([$preparedStatement, 'bind_result'], $fields);
            
            $output = [];
            $count = 0;
            while($preparedStatement->fetch())
            {
                foreach($out as $k => $v)
                {
                    $output[$count][$k] = $v;
                }    
                ++$count;
            }
            $preparedStatement->free_result();
            return ($count == 0) ? null : $output;
        }
        return null;
    }
    
    /**
     * Fix duplication of the old library.
     * Still mysterious.
     */
    public function checkGroupConcatLimit()
    {
        $debug_backtrace = debug_backtrace();

        if(
            (isset($debug_backtrace[4])) &&
            ($debug_backtrace[4]['function'] == 'pathId') &&
            $this->groupConcatLimitChanged === false
        )
        {
            if($this->connection->query('SET SESSION group_concat_max_len = 1000000') !== false)
            {
                $this->groupConcatLimitChanged = true;
            }
        }
    }
}
