<?php
namespace PhpRbac\Manager;

use PhpRbac\Rbac;

use PhpRbac\NestedSet\FullNestedSet;

/**
 * Rbac base class, it contains operations that are essentially the same for
 * permissions and roles
 * and is inherited by both
 *
 * @author abiusx
 * @version 1.0
 */
abstract class BaseRbacManager implements BaseRbacManagerInterface
{
    /** @var FullNestedSet */
    protected $nestedSet;
    /** @var string **/
    protected $type;
    /** @var integer **/
    protected $rootId = 1;
    
    /**
     * {@inheritdoc}
     */
    public function add($title, $description, $parentId = null)
    {
        if($parentId === null)
        {
            $parentId = $this->rootId;
        }
        return $this->nestedSet->insertChildData([
            'Title' => $title,
            'Description' => $description
        ], 'ID=?', $parentId);
    }

    /**
     * {@inheritdoc}
     */
    public function addPath($path, array $descriptions = null)
    {
        if ($path[0] !== '/')
        {
            throw new \Exception ('The path supplied is not valid.');
        }
        $path = substr($path, 1);
        $parts = explode('/', $path);
        $nbParts = count($parts);
        $parent = 1;
        $currentPath = '';
        $nodesCreated = 0;

        for($i = 0; $i < $nbParts; ++$i)
        {
            $description =
                (isset($descriptions[$i]))
                ? $descriptions[$i]
                : ''
            ;
            $currentPath .= "/{$parts[$i]}";
            if(!($t = $this->pathId($currentPath)))
            {
                $parent = $this->add($parts[$i], $description, $parent);
                ++$nodesCreated;
                continue;
            }
            $parent = $t;
        }
        return $nodesCreated;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $Res = $databaseManager->request(
            "SELECT COUNT(*) FROM {$databaseManager->getTablePrefix()}{$this->type}"
        );
        return (int)$Res[0]['COUNT(*)'];
    }
        
    /**
     * {@inheritdoc}
     */
    public function getId($item)
    {
        return
            (is_numeric($item))
            ? $item
            : $this->returnId($item)
        ;
    }
    
    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function pathId($path)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $databaseConnection = $databaseManager->getConnection();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        $path = "root{$path}";

        if($path[strlen($path) - 1] === '/')
        {
            $path = substr($path, 0, strlen($path) - 1);
        }
        $Parts = explode('/', $path);
        
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
            FROM {$tablePrefix}{$this->type} AS node,
            {$tablePrefix}{$this->type} AS parent
            WHERE node.Lft BETWEEN parent.Lft AND parent.Rght
            AND  node.Title=?
            GROUP BY node.ID
            HAVING Path = ?"
        , $Parts[count($Parts) - 1], $path);

        if($res !== false)
        {
            return $res[0]['ID'];
        }
        return null;
        // TODO: make the below SQL work, so that 1024 limit is over
        /**
        $QueryBase = ("SELECT n0.ID  \nFROM {$tablePrefix}{$this->type} AS n0");
        $QueryCondition = "\nWHERE 	n0.Title=?";

        for($i = 1; $i < count ( $Parts ); ++ $i)
        {
                $j = $i - 1;
                $QueryBase .= "\nJOIN 		{$tablePrefix}{$this->type} AS n{$i} ON (n{$j}.Lft BETWEEN n{$i}.Lft+1 AND n{$i}.Rght)";
                $QueryCondition .= "\nAND 	n{$i}.Title=?";
                // Forcing middle elements
                $QueryBase .= "\nLEFT JOIN 	{$tablePrefix}{$this->type} AS nn{$i} ON (nn{$i}.Lft BETWEEN n{$i}.Lft+1 AND n{$j}.Lft-1)";
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
     * {@inheritdoc}
     */
    public function titleId($title)
    {
        return $this->nestedSet->getID('Title=?', $title);
    }

    /**
     * {@inheritdoc}
     */
    public function getRecord($id)
    {
        return call_user_func_array(
            [$this->nestedSet, 'getRecord']
        , func_get_args ());
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle($id)
    {
        if(($r = $this->getRecord('ID=?', $id)) !== null)
        {
            return $r['Title'];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath($id)
    {
        $res = $this->nestedSet->pathConditional('ID=?', $id);
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
     * {@inheritdoc}
     */
    public function getDescription($id)
    {
        if(($r = $this->getRecord("ID=?", $id)) !== null)
        {
            return $r['Description'];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function edit($id, $newTitle = null, $newDescription = null)
    {
        $Data = [];

        if($newTitle !== null)
        {
            $Data['Title'] = $newTitle;
        }

        if($newDescription !== null)
        {
            $Data['Description'] = $newDescription;
        }           
        return $this->nestedSet->editData($Data, 'ID=?', $id) == 1;
    }

    /**
     * {@inheritdoc}
     */
    public function children($id)
    {
        return $this->nestedSet->childrenConditional('ID=?', $id);
    }

    /**
     * {@inheritdoc}
     */
    public function descendants($id)
    {
        $res = $this->nestedSet->descendantsConditional(false, 'ID=?', $id);
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
     * {@inheritdoc}
     */
    public function depth($id)
    {
        return $this->nestedSet->depthConditional('ID=?', $id);
    }

    /**
     * {@inheritdoc}
     */
    public function parentNode($id)
    {
        return $this->nestedSet->parentNodeConditional('ID=?', $id);
    }

    /**
     * {@inheritdoc}
     */
    public function reset($ensure = false)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        if($ensure !== true)
        {
            throw new \Exception ('You must pass true to this function, otherwise it won\'t work.');
        }
        
        $res = $databaseManager->request("DELETE FROM {$tablePrefix}{$this->type}");
        
        $Adapter = get_class($databaseManager->getConnection());
        
        if($databaseManager->isMySql())
        {
            $databaseManager->request("ALTER TABLE {$tablePrefix}{$this->type} AUTO_INCREMENT=1");
        }    
        elseif($databaseManager->isSQLite())
        {
            $databaseManager->request('delete from sqlite_sequence where name=? ', "{$tablePrefix}{$this->type}");
        }
        else
        {
            throw new \Exception("Rbac can not reset table on this type of database: {$Adapter}");
        }     
        $databaseManager->request(
            "INSERT INTO {$tablePrefix}{$this->type} (Title,Description,Lft,Rght) VALUES (?,?,?,?)",
            "root",
            "root",
            0,
            1
        );
        return (int) $res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function remove($id, $recursive = false)
    {
        if($recursive === true)
        {
            return $this->nestedSet->deleteSubtreeConditional('ID=?', $id);
        }
        return $this->nestedSet->deleteConditional('ID=?', $id);
    }

    /**
     * {@inheritdoc}
     */
    public function assign($role, $permission)
    {
        $rbac = Rbac::getInstance();
        
        $manager = $rbac->getRbacManager();
        $databaseManager = $rbac->getDatabaseManager();
        
        $roleId = $manager->getRoleManager()->getId($role);
        $permissionId = $manager->getPermissionManager()->getId($permission);

        return $databaseManager->request(
            'INSERT INTO ' . $databaseManager->getTablePrefix() . 'rolepermissions
            (RoleID,PermissionID,AssignmentDate)
            VALUES (?,?,?)', $roleId, $permissionId, time()
        ) >= 1;
    }

    /**
     * {@inheritdoc}
     */
    public function unassign($role, $permission)
    {
        $rbac = Rbac::getInstance();
        
        $manager = $rbac->getRbacManager();
        $databaseManager = $rbac->getDatabaseManager();
        
        $roleId = $manager->getRoleManager()->getId($role);
        $permissionId = $manager->getPermissionManager()->getId($permission);
        
        return $databaseManager->request(
            'DELETE FROM ' . $databaseManager->getTablePrefix() . 'rolepermissions WHERE
            RoleID=? AND PermissionID=?'
        , $roleId, $permissionId) == 1;
    }

    /**
     * {@inheritdoc}
     */
    public function resetAssignments($ensure = false)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        if($ensure !== true)
        {
            throw new \Exception ('You must pass true to this function, otherwise it won\'t work.');
        }
        
        $res = $databaseManager->request("DELETE FROM {$tablePrefix}rolepermissions");

        $Adapter = get_class($databaseManager->getConnection());
        if($databaseManager->isMySql())
        {
            $databaseManager->request("ALTER TABLE {$tablePrefix}rolepermissions AUTO_INCREMENT =1 ");
        }
        elseif($databaseManager->isSQLite())
        {
            $databaseManager->request("delete from sqlite_sequence where name=? ", "{$tablePrefix}_rolepermissions");
        }
        else
        {
            throw new \Exception ("Rbac can not reset table on this type of database: {$Adapter}");
        }
        $this->assign($this->rootId, $this->rootId);
        return $res;
    }
}