<?php
namespace PhpRbac\NestedSet;

use PhpRbac\Rbac;

/**
 * FullNestedSet Class
 * This class provides a means to implement Hierarchical data in flat SQL tables.
 * Queries extracted from http://dev.mysql.com/tech-resources/articles/hierarchical-data.html
 * Tested and working properly.
 *
 * Usage:
 * have a table with at least 3 INT fields for ID,Left and Right.
 * Create a new instance of this class and pass the name of table and name of the 3 fields above
 */
class FullNestedSet extends BaseNestedSet implements ExtendedNestedSetInterface
{
    /**
     * {@inheritdoc}
     */
    protected function lock()
    {
    	Rbac::getInstance()->getDatabaseManager()->request("LOCK TABLE {$this->table()} WRITE");
    }
    
    /**
     * {@inheritdoc}
     */
    protected function unlock()
    {
    	Rbac::getInstance()->getDatabaseManager()->request("UNLOCK TABLES");
    }
    
    /**
     * {@inheritdoc}
     */
    public function getID($ConditionString, $Rest = null)
    {
        $args = func_get_args();
        
        array_shift($args);
        
        $Query = "SELECT {$this->id()} AS ID FROM {$this->table()} WHERE $ConditionString LIMIT 1";
        
        array_unshift($args, $Query);
        
        $Res = call_user_func_array([Rbac::getInstance()->getDatabaseManager(), 'request'], $args);
        
        if($Res !== false)
        {
            return $Res[0]['ID'];
        }
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRecord($ConditionString, $Rest = null)
    {
        $args = func_get_args();
        
        array_shift($args);
        
        $Query = "SELECT * FROM {$this->table()} WHERE $ConditionString";
        
        array_unshift($args, $Query);
        
        $Res = call_user_func_array([Rbac::getInstance()->getDatabaseManager(), 'request'],$args);
        
        if($Res !== false)
        {
            return $Res[0];
        }
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function depthConditional($ConditionString, $Rest=null)
    {
        return count(call_user_func_array([$this, 'pathConditional'], func_get_args())) - 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function siblingConditional($SiblingDistance = 1, $ConditionString, $Rest = null)
    {
        $Arguments = func_get_args();
        
        array_shift($Arguments);
        
        $Parent = call_user_func_array([$this, 'parentNodeConditional'], $Arguments);
        
        $Siblings = $this->children($Parent[$this->id()]);
        
        if (!$Siblings)
        {
            return null;
        }
        
        $ID = call_user_func_array([$this, 'getID'], $Arguments);
        $n = 0;
        
        foreach ($Siblings as &$Sibling)
        {
            if ($Sibling[$this->id()] == $ID)
            {
                break;
            }
            ++$n;
        }
        return $Siblings[$n + $SiblingDistance];
    }
    
    /**
     * {@inheritdoc}
     */
    public function parentNodeConditional($ConditionString, $Rest = null)
    {
        $Path = call_user_func_array([$this, 'pathConditional'], func_get_args());
        $nbPaths = count($Path);
        
        if($nbPaths < 2)
        {
            return null;
        }
        return $Path[$nbPaths - 2];
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteConditional($ConditionString, $Rest = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
    	$this->lock();
        
    	$Arguments=func_get_args();
        
        array_shift($Arguments);
        
        $Query=
            "SELECT {$this->left()} AS `Left`,{$this->right()} AS `Right`
            FROM {$this->table()} WHERE $ConditionString LIMIT 1"
        ;

        array_unshift($Arguments, $Query);
        
        if(!($Info = call_user_func_array([$databaseManager, 'request'], $Arguments)))
        {
            $this->unlock();
            return false;
        }
        
        $Info = $Info[0];

        $count = $databaseManager->request("DELETE FROM {$this->table()} WHERE {$this->left()} = ?",$Info["Left"]);
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - 1,
            {$this->left()} = {$this->left()} - 1 WHERE {$this->left()} BETWEEN ? AND ?"
        , $Info['Left'], $Info['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - 2
            WHERE {$this->right()} > ?"
        , $Info['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} - 2
            WHERE {$this->left()} > ?"
        , $Info['Right']);
        
        $this->unlock();
        
        return $count === 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteSubtreeConditional($ConditionString, $Rest = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $this->lock();
        
    	$Arguments=func_get_args();
        
        array_shift($Arguments);
        
        $Query=
            "SELECT {$this->left()} AS `Left`,{$this->right()} AS `Right` ,{$this->right()}-{$this->left()}+ 1 AS Width
            FROM {$this->table()} WHERE $ConditionString"
        ;

        array_unshift($Arguments,$Query);
        
        $Info = call_user_func_array([$databaseManager, 'request'], $Arguments)[0];

        $count = $databaseManager->request(
            "DELETE FROM {$this->table()} WHERE {$this->left()} BETWEEN ? AND ?"
        , $Info['Left'], $Info['Right']);

        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - ? WHERE {$this->right()} > ?"
        , $Info['Width'], $Info['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} - ? WHERE {$this->left()} > ?" 
        , $Info['Width'], $Info['Right']);
            
        $this->unlock();
        
        return $count >= 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function descendantsConditional($AbsoluteDepths = false, $ConditionString, $Rest = null)
    {
        if ($AbsoluteDepths === false)
        {
            $DepthConcat = " - (sub_tree.innerDepth )";
        }
            
        $Arguments = func_get_args();
        
        array_shift($Arguments);
        array_shift($Arguments); //second argument, $AbsoluteDepths
        
        $Query=
            "SELECT node.*, (COUNT(parent.{$this->id()})-1$DepthConcat) AS Depth
            FROM {$this->table()} AS node,
            	{$this->table()} AS parent,
            	{$this->table()} AS sub_parent,
            	(
                    SELECT node.{$this->id()}, (COUNT(parent.{$this->id()}) - 1) AS innerDepth
                    FROM {$this->table()} AS node,
                    {$this->table()} AS parent
                    WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
                    AND (node.$ConditionString)
                    GROUP BY node.{$this->id()}
                    ORDER BY node.{$this->left()}
            	) AS sub_tree
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            	AND node.{$this->left()} BETWEEN sub_parent.{$this->left()} AND sub_parent.{$this->right()}
            	AND sub_parent.{$this->id()} = sub_tree.{$this->id()}
            GROUP BY node.{$this->id()}
            HAVING Depth > 0
            ORDER BY node.{$this->left()}"
        ;

        array_unshift($Arguments, $Query);
        
        return call_user_func_array([Rbac::getInstance()->getDatabaseManager(), 'request'], $Arguments);
    }
    
    /**
     * {@inheritdoc}
     */
    public function childrenConditional($ConditionString, $Rest = null)
    {
        $Arguments=func_get_args();
        
        array_shift($Arguments);
        
        $Query=
            "SELECT node.*, (COUNT(parent.{$this->id()})-1 - (sub_tree.innerDepth )) AS Depth
            FROM {$this->table()} AS node,
            	{$this->table()} AS parent,
            	{$this->table()} AS sub_parent,
           	(
                    SELECT node.{$this->id()}, (COUNT(parent.{$this->id()}) - 1) AS innerDepth
                    FROM {$this->table()} AS node,
                    {$this->table()} AS parent
                    WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
                    AND (node.$ConditionString)
                    GROUP BY node.{$this->id()}
                    ORDER BY node.{$this->left()}
            ) AS sub_tree
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            	AND node.{$this->left()} BETWEEN sub_parent.{$this->left()} AND sub_parent.{$this->right()}
            	AND sub_parent.{$this->id()} = sub_tree.{$this->id()}
            GROUP BY node.{$this->id()}
            HAVING Depth = 1
            ORDER BY node.{$this->left()}"
        ;

        array_unshift($Arguments,$Query);
        
        $Res = call_user_func_array([Rbac::getInstance()->getDatabaseManager(), 'request'], $Arguments);
        
        if($Res !== false)
        {
            foreach ($Res as &$v)
            {
                unset($v['Depth']);
            }
        }
        return $Res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function pathConditional($ConditionString, $Rest = null)
    {
        $Arguments=func_get_args();
        
        array_shift($Arguments);
        
        $Query=
            "SELECT parent.*
            FROM {$this->table()} AS node,
            {$this->table()} AS parent
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            AND ( node.$ConditionString )
            ORDER BY parent.{$this->left()}"
        ;

        array_unshift($Arguments,$Query);
        
        return call_user_func_array([Rbac::getInstance()->getDatabaseManager(), 'request'], $Arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function leavesConditional($ConditionString = null, $Rest = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        if($ConditionString !== null)
        {
            $Arguments = func_get_args();
            
            array_shift($Arguments);

            $Query="SELECT *
                FROM {$this->table()}
                WHERE {$this->right()} = {$this->left()} + 1
            	AND {$this->left()} BETWEEN
                (SELECT {$this->left()} FROM {$this->table()} WHERE $ConditionString)
                	AND
                (SELECT {$this->right()} FROM {$this->table()} WHERE $ConditionString)";

            $Arguments = array_merge($Arguments, $Arguments);
            
            array_unshift($Arguments, $Query);
            
            return call_user_func_array([$databaseManager, 'request'], $Arguments);
        }
        return $databaseManager->request(
            "SELECT * FROM {$this->table()} WHERE {$this->right()} = {$this->left()} + 1"
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function insertSiblingData($FieldValueArray = [], $ConditionString = null, $Rest = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $this->lock();
    	//Find the Sibling
        $Arguments = func_get_args();
        
        array_shift($Arguments); //first argument, the array
        array_shift($Arguments);
        
        if ($ConditionString !== null)
        {
            $ConditionString = "WHERE $ConditionString";
        }
        
        $Query = "SELECT {$this->right()} AS `Right` FROM {$this->table()} $ConditionString";

        array_unshift($Arguments, $Query);
        
        $Sibl = call_user_func_array([$databaseManager, 'request'], $Arguments)[0];

        if($Sibl === null)
        {
            $Sibl['Left'] = $Sibl['Right'] = 0;
        }
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} + 2 WHERE {$this->right()} > ?"
        ,$Sibl['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} + 2 WHERE {$this->left()} > ?"
        ,$Sibl['Right']);

        $FieldsString = $ValuesString = '';
        
        $Values = [];
        
        if($FieldValueArray)
        {
            foreach($FieldValueArray as $k => $v)
            {
                $FieldsString .= ", `$k`";
                $ValuesString .= ', ?';
                $Values[] = $v;
            }
        }
        $Query = 
            "INSERT INTO {$this->table()} ({$this->left()},{$this->right()} $FieldsString)
            VALUES(?,? $ValuesString)"
        ;
        
        array_unshift($Values, $Sibl['Right'] + 2);
        array_unshift($Values, $Sibl['Right'] + 1);
        array_unshift($Values, $Query);

        $Res = call_user_func_array([$databaseManager, 'request'], $Values);
        
        $this->unlock();
        
        return $Res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function insertChildData($FieldValueArray=array(),$ConditionString=null,$Rest=null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $this->lock();
    	//Find the Sibling
        $Arguments=func_get_args();
        
        array_shift($Arguments); //first argument, the array
        array_shift($Arguments);
        
        if($ConditionString !== null)
        {
            $ConditionString = "WHERE $ConditionString";
        }
        
        $Query="SELECT {$this->right()} AS `Right`, {$this->left()} AS `Left` FROM {$this->table()} $ConditionString";
        
        array_unshift($Arguments,$Query);
        
        $Parent = call_user_func_array([$databaseManager, 'request'], $Arguments)[0];
        
        if ($Parent==null)
        {
            $Parent['Left'] = $Parent['Right'] = 0;
        }
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} + 2 WHERE {$this->right()} >= ?"
        , $Parent['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} + 2 WHERE {$this->left()} > ?"
        , $Parent['Right']);

        $FieldsString=$ValuesString = '';
        $Values = [];
        
        if($FieldValueArray)
        {
            foreach($FieldValueArray as $k => $v)
            {
                $FieldsString .= ", `$k`";
                $ValuesString .= ', ?';
                $Values[] = $v;
            }
        }
            
        $Query=
            "INSERT INTO {$this->table()} ({$this->left()},{$this->right()} $FieldsString) " .
            "VALUES(?, ? $ValuesString)";
        
        array_unshift($Values, $Parent['Right'] + 1);
        array_unshift($Values, $Parent['Right']);
        array_unshift($Values, $Query);
        
        $Res = call_user_func_array([$databaseManager, 'request'], $Values);
        
        $this->unlock();
        
        return $Res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function editData($FieldValueArray = array(), $ConditionString = null, $Rest = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        //Find the Sibling
        $Arguments = func_get_args();
        
        array_shift($Arguments); //first argument, the array
        array_shift($Arguments);
        
        if($ConditionString !== null)
        {
            $ConditionString = "WHERE $ConditionString";
        }

        $FieldsString = '';
        $Values = [];
        
        if ($FieldValueArray)
        {
            foreach($FieldValueArray as $k=>$v)
            {
                if (!empty($FieldsString))
                {
                    $FieldsString .= ',';
                }
                $FieldsString .= "`$k`=?";
                $Values[] = $v;
            }
        } 
        $Query = "UPDATE {$this->table()} SET $FieldsString $ConditionString";

        array_unshift($Values,$Query);
        
        $Arguments = array_merge($Values, $Arguments);

        return call_user_func_array([$databaseManager, 'request'], $Arguments);
    }
}