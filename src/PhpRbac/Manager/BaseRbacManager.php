<?php
namespace PhpRbac\Manager;

use PhpRbac\Database\JModel;

use PhpRbac\Rbac;

/**
 * Rbac base class, it contains operations that are essentially the same for
 * permissions and roles
 * and is inherited by both
 *
 * @author abiusx
 * @version 1.0
 */
abstract class BaseRbacManager extends JModel
{
    function rootId()
    {
        return 1;
    }

    /**
     * Return type of current instance, e.g roles, permissions
     *
     * @return string
     */
    abstract protected function type();

    /**
     * Adds a new role or permission
     * Returns new entry's ID
     *
     * @param string $Title
     *        	Title of the new entry
     * @param string $Description
     *        	Description of the new entry
     * @param integer $ParentID
     *        	optional ID of the parent node in the hierarchy
     * @return integer ID of the new entry
     */
    function add($Title, $Description, $ParentID = null)
    {
        if ($ParentID === null)
        {
            $ParentID = $this->rootId();
        }
        return (int)$this->{$this->type()}->insertChildData([
            'Title' => $Title,
            'Description' => $Description
        ], 'ID=?', $ParentID);
    }

    /**
     * Adds a path and all its components.
     * Will not replace or create siblings if a component exists.
     *
     * @param string $Path
     *        	such as /some/role/some/where - Must begin with a / (slash)
     * @param array $Descriptions
     *        	array of descriptions (will add with empty description if not available)
     *
     * @return integer Number of nodes created (0 if none created)
     */
    function addPath($Path, array $Descriptions = null)
    {
        if ($Path[0] !== '/')
        {
            throw new \Exception ('The path supplied is not valid.');
        }
        $Path = substr($Path, 1);
        $Parts = explode('/', $Path);
        $Parent = 1;
        $index = 0;
        $CurrentPath = '';
        $NodesCreated = 0;

        foreach($Parts as $p)
        {
            $Description =
                (isset ($Descriptions[$index]))
                ? $Descriptions[$index]
                : ''
            ;
            $CurrentPath .= "/{$p}";
            $t = $this->pathId($CurrentPath);
            if(!$t)
            {
                $IID = $this->add($p, $Description, $Parent);
                $Parent = $IID;
                $NodesCreated++;
            }
            else
            {
                $Parent = $t;
            }
            ++$index;
        }
        return (int)$NodesCreated;
    }

    /**
     * Return count of the entity
     *
     * @return integer
     */
    function count()
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $Res = $databaseManager->request(
            "SELECT COUNT(*) FROM {$databaseManager->getTablePrefix()}{$this->type()}"
        );
        return (int)$Res[0]['COUNT(*)'];
    }
        
    /**
     * Get ID from a path or a title
     * 
     * @param string $item
     * @return integer
     */
    public function getId($item)
    {
        return
            (is_numeric($item))
            ? $item
            : (
                (substr($item, 0, 1) == '/')
                ? $this->pathId($item)
                : $this->titleId($item)
            ) 
        ;
    }
    
    /**
     * Returns ID of entity
     *
     * @param string $entity (Path or Title)
     *
     * @return mixed ID of entity or null
     */
    public function returnId($entity = null)
    {
        return 
            (substr($entity, 0, 1) === '/')
            ? $this->pathId($entity)
            : $this->titleId($entity)
        ;
    }

    /**
     * Returns ID of a path
     *
     * @todo this has a limit of 1000 characters on $Path
     * @param string $Path
     *        	such as /role1/role2/role3 ( a single slash is root)
     * @return integer NULL
     */
    public function pathId($Path)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $databaseConnection = $databaseManager->getConnection();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        $Path = "root{$Path}";

        if($Path[strlen($Path) - 1] === '/')
        {
            $Path = substr($Path, 0, strlen($Path) - 1);
        }
        $Parts = explode('/', $Path);
        
        $Adapter = get_class($databaseConnection);
        
        if(
            $Adapter === 'mysqli' ||
            (
                $Adapter == "PDO" &&
                $databaseConnection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql'
            )
        )
        {
            $GroupConcat="GROUP_CONCAT(parent.Title ORDER BY parent.Lft SEPARATOR '/')";
        }
        elseif(
            $Adapter === 'PDO' &&
            $databaseConnection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
        )
        {
            $GroupConcat="GROUP_CONCAT(parent.Title,'/')";
        }
        else
        {
            throw new \Exception("Unknown Group_Concat on this type of database: {$Adapter}");
        }

        $res = $databaseManager->request(
            "SELECT node.ID,{$GroupConcat} AS Path
            FROM {$tablePrefix}{$this->type()} AS node,
            {$tablePrefix}{$this->type()} AS parent
            WHERE node.Lft BETWEEN parent.Lft AND parent.Rght
            AND  node.Title=?
            GROUP BY node.ID
            HAVING Path = ?"
        , $Parts[count($Parts) - 1], $Path);

        if($res !== false)
        {
            return $res[0]['ID'];
        }
        return null;
        // TODO: make the below SQL work, so that 1024 limit is over
        /**
        $QueryBase = ("SELECT n0.ID  \nFROM {$tablePrefix}{$this->type()} AS n0");
        $QueryCondition = "\nWHERE 	n0.Title=?";

        for($i = 1; $i < count ( $Parts ); ++ $i)
        {
                $j = $i - 1;
                $QueryBase .= "\nJOIN 		{$tablePrefix}{$this->type()} AS n{$i} ON (n{$j}.Lft BETWEEN n{$i}.Lft+1 AND n{$i}.Rght)";
                $QueryCondition .= "\nAND 	n{$i}.Title=?";
                // Forcing middle elements
                $QueryBase .= "\nLEFT JOIN 	{$tablePrefix}{$this->type()} AS nn{$i} ON (nn{$i}.Lft BETWEEN n{$i}.Lft+1 AND n{$j}.Lft-1)";
                $QueryCondition .= "\nAND 	nn{$i}.Lft IS NULL";
        }
        $Query = $QueryBase . $QueryCondition;
        $PartsRev = array_reverse ( $Parts );
        array_unshift ( $PartsRev, $Query );

        print_ ( $PartsRev );
        $res = call_user_func_array ( "Jf::sql", $PartsRev );

        if ($res)
        {
            return $res[0]['ID'];
        } 
        return null;
        */
    }

    /**
     * Returns ID belonging to a title, and the first one on that
     *
     * @param string $Title
     * @return integer Id of specified Title
     */
    public function titleId($Title)
    {
        return $this->{$this->type()}->getID('Title=?', $Title);
    }

    /**
     * Return the whole record of a single entry (including Rght and Lft fields)
     *
     * @param integer $ID
     */
    protected function getRecord($ID)
    {
        return call_user_func_array(
            [$this->{$this->type ()}, 'getRecord']
        , func_get_args ());
    }

    /**
     * Returns title of entity
     *
     * @param integer $ID
     * @return string NULL
     */
    function getTitle($ID)
    {
        if(($r = $this->getRecord('ID=?', $ID)) !== null)
        {
            return $r['Title'];
        }
        return null;
    }

    /**
     * Returns path of a node
     *
     * @param integer $ID
     * @return string path
     */
    function getPath($ID)
    {
        $res = $this->{$this->type ()}->pathConditional('ID=?', $ID);
        $out = null;
        if(is_array($res))
        {
            foreach ($res as $r)
            {
                $out =
                    ($r['ID'] == 1)
                    ? '/'
                    : $out . '/' . $r['Title']
                ;
            }
        }
            
        if(strlen($out) > 1)
        {
            return substr($out, 1);
        }
        return $out;
    }

    /**
     * Return description of entity
     *
     * @param integer $ID
     * @return string NULL
     */
    function getDescription($ID)
    {
        if(($r = $this->getRecord("ID=?", $ID)) !== null)
        {
            return $r['Description'];
        }
        return null;
    }

    /**
     * Edits an entity, changing title and/or description. Maintains Id.
     *
     * @param integer $ID
     * @param string $NewTitle
     * @param string $NewDescription
     *
     */
    function edit($ID, $NewTitle = null, $NewDescription = null)
    {
        $Data = [];

        if($NewTitle !== null)
        {
            $Data['Title'] = $NewTitle;
        }

        if($NewDescription !== null)
        {
            $Data['Description'] = $NewDescription;
        }           
        return $this->{$this->type()}->editData($Data, 'ID=?', $ID) == 1;
    }

    /**
     * Returns children of an entity
     *
     * @param integer $ID
     * @return array
     *
     */
    function children($ID)
    {
        return $this->{$this->type()}->childrenConditional('ID=?', $ID);
    }

    /**
     * Returns descendants of a node, with their depths in integer
     *
     * @param integer $ID
     * @return array with keys as titles and Title,ID, Depth and Description
     *
     */
    function descendants($ID)
    {
        $res = $this->{$this->type ()}->descendantsConditional(false, 'ID=?', $ID);
        $out = [];
        if(is_array($res))
        {
            foreach($res as $v)
            {
                $out[$v['Title']] = $v;
            }
        }    
        return $out;
    }

    /**
     * Return depth of a node
     *
     * @param integer $ID
     */
    function depth($ID)
    {
        return $this->{$this->type()}->depthConditional('ID=?', $ID);
    }

    /**
     * Returns parent of a node
     *
     * @param integer $ID
     * @return array including Title, Description and ID
     *
     */
    function parentNode($ID)
    {
        return $this->{$this->type()}->parentNodeConditional('ID=?', $ID);
    }

    /**
     * Reset the table back to its initial state
     * Keep in mind that this will not touch relations
     *
     * @param boolean $Ensure
     *        	must be true to work, otherwise an \Exception is thrown
     * @throws \Exception
     * @return integer number of deleted entries
     *
     */
    function reset($Ensure = false)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        if($Ensure !== true)
        {
            throw new \Exception ('You must pass true to this function, otherwise it won\'t work.');
        }
        
        $res = $databaseManager->request("DELETE FROM {$tablePrefix}{$this->type()}");
        
        $Adapter = get_class($databaseManager->getConnection());
        
        if($this->isMySql())
        {
            $databaseManager->request("ALTER TABLE {$tablePrefix}{$this->type()} AUTO_INCREMENT=1");
        }    
        elseif($this->isSQLite())
        {
            $databaseManager->request('delete from sqlite_sequence where name=? ', "{$tablePrefix}{$this->type()}");
        }
        else
        {
            throw new \Exception("Rbac can not reset table on this type of database: {$Adapter}");
        }     
        $databaseManager->request(
            "INSERT INTO {$tablePrefix}{$this->type()} (Title,Description,Lft,Rght) VALUES (?,?,?,?)",
            "root",
            "root",
            0,
            1
        );
        return (int) $res;
    }

    /**
     * Assigns a role to a permission (or vice-verse)
     *
     * @param mixed $Role
     *         Id, Title and Path
     * @param mixed $Permission
     *         Id, Title and Path
     * @return boolean inserted or existing
     *
     * @todo: Check for valid permissions/roles
     * @todo: Implement custom error handler
     */
    function assign($Role, $Permission)
    {
        $rbac = Rbac::getInstance();
        
        $manager = $rbac->getRbacManager();
        $databaseManager = $rbac->getDatabaseManager();
        
        $RoleID = $manager->getRoleManager()->getId($Role);
        $PermissionID = $manager->getPermissionManager()->getId($Permission);

        return $databaseManager->request(
            'INSERT INTO ' . $databaseManager->getTablePrefix() . 'rolepermissions
            (RoleID,PermissionID,AssignmentDate)
            VALUES (?,?,?)', $RoleID, $PermissionID, time()
        ) >= 1;
    }

    /**
     * Unassigns a role-permission relation
     *
     * @param mixed $Role
     *         Id, Title and Path
     * @param mixed $Permission:
     *         Id, Title and Path
     * @return boolean
     */
    function unassign($Role, $Permission)
    {
        $rbac = Rbac::getInstance();
        
        $manager = $rbac->getRbacManager();
        $databaseManager = $rbac->getDatabaseManager();
        
        $RoleID = $manager->getRoleManager()->getId($Role);
        $PermissionID = $manager->getPermissionManager()->getId($Permission);
        
        return $databaseManager->request(
            'DELETE FROM ' . $databaseManager->getTablePrefix() . 'rolepermissions WHERE
            RoleID=? AND PermissionID=?'
        , $RoleID, $PermissionID) == 1;
    }

    /**
     * Remove all role-permission relations
     * mostly used for testing
     *
     * @param boolean $Ensure
     *        	must be set to true or throws an \Exception
     * @return number of deleted assignments
     */
    function resetAssignments($Ensure = false)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        if($Ensure !== true)
        {
            throw new \Exception ("You must pass true to this function, otherwise it won't work.");
            return;
        }
        
        $res = $databaseManager->request("DELETE FROM {$tablePrefix}rolepermissions");

        $Adapter = get_class($databaseManager->getConnection());
        if($this->isMySql())
        {
            $databaseManager->request("ALTER TABLE {$tablePrefix}rolepermissions AUTO_INCREMENT =1 ");
        }
        elseif($this->isSQLite())
        {
            $databaseManager->request("delete from sqlite_sequence where name=? ", "{$tablePrefix}_rolepermissions");
        }
        else
        {
            throw new \Exception ("Rbac can not reset table on this type of database: {$Adapter}");
        }
        $this->assign($this->rootId(), $this->rootId());
        return $res;
    }
}