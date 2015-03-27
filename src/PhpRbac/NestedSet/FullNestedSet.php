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
    	Rbac::getInstance()->getDatabaseManager()->request('UNLOCK TABLES');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getID($condition, $value)
    {
        $result = Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT {$this->id()} AS ID FROM {$this->table()} WHERE $condition LIMIT 1"
        , [$value]);
        
        if($result !== false)
        {
            return $result[0]['ID'];
        }
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRecord($id)
    {
        $Res = Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT * FROM {$this->table()} WHERE ID=?"
        , [$id]);
        
        if($Res !== false)
        {
            return $Res[0];
        }
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function depthConditional($conditionString, $arguments = [])
    {
        return count($this->pathConditional($conditionString, $arguments)) - 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function siblingConditional($siblingDistance = 1, $conditionString, $arguments = null)
    {
        $parent = $this->parentNodeConditional($conditionString, $arguments);
        
        if (!($siblings = $this->children($parent[$this->id()])))
        {
            return null;
        }
        
        $id = $this->getID($conditionString, $arguments);
        $n = 0;
        
        foreach ($siblings as &$sibling)
        {
            if ($sibling[$this->id()] == $id)
            {
                break;
            }
            ++$n;
        }
        return $siblings[$n + $siblingDistance];
    }
    
    /**
     * {@inheritdoc}
     */
    public function parentNodeConditional($conditionString, $arguments = [])
    {
        $path = $this->pathConditional($conditionString, $arguments);
        $nbPaths = count($path);
        
        if($nbPaths < 2)
        {
            return null;
        }
        return $path[$nbPaths - 2];
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteConditional($conditionString, $arguments = [])
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
    	$this->lock();
            
        $info = $databaseManager->request(
            "SELECT {$this->left()} AS `Left`,{$this->right()} AS `Right`
            FROM {$this->table()} WHERE $conditionString LIMIT 1"
        , $arguments);
            
        if(!$info)
        {
            $this->unlock();
            return false;
        }
        
        $count = $databaseManager->request(
            "DELETE FROM {$this->table()} WHERE {$this->left()} = ?",$info[0]["Left"]
        );
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - 1,
            {$this->left()} = {$this->left()} - 1 WHERE {$this->left()} BETWEEN ? AND ?"
        , [$info[0]['Left'], $info[0]['Right']]);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - 2
            WHERE {$this->right()} > ?"
        , [$info[0]['Right']]);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} - 2
            WHERE {$this->left()} > ?"
        , [$info[0]['Right']]);
        
        $this->unlock();
        
        return $count === 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteSubtreeConditional($conditionString, $arguments = [])
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $this->lock();
        
        $info = $databaseManager->request(
            "SELECT {$this->left()} AS `Left`,{$this->right()} AS `Right` ,{$this->right()}-{$this->left()}+ 1 AS Width
            FROM {$this->table()} WHERE $conditionString"
        , $arguments)[0];

        $count = $databaseManager->request(
            "DELETE FROM {$this->table()} WHERE {$this->left()} BETWEEN ? AND ?"
        , [$info['Left'], $info['Right']]);

        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - ? WHERE {$this->right()} > ?"
        , [$info['Width'], $info['Right']]);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} - ? WHERE {$this->left()} > ?" 
        , [$info['Width'], $info['Right']]);
            
        $this->unlock();
        
        return $count >= 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function descendantsConditional($isAbsoluteDepth = false, $conditionString, $arguments = [])
    {
        if ($isAbsoluteDepth === false)
        {
            $depthConcat = " - (sub_tree.innerDepth )";
        }
        
        return Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT node.*, (COUNT(parent.{$this->id()})-1$depthConcat) AS Depth
            FROM {$this->table()} AS node,
            	{$this->table()} AS parent,
            	{$this->table()} AS sub_parent,
            	(
                    SELECT node.{$this->id()}, (COUNT(parent.{$this->id()}) - 1) AS innerDepth
                    FROM {$this->table()} AS node,
                    {$this->table()} AS parent
                    WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
                    AND (node.$conditionString)
                    GROUP BY node.{$this->id()}
                    ORDER BY node.{$this->left()}
            	) AS sub_tree
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            	AND node.{$this->left()} BETWEEN sub_parent.{$this->left()} AND sub_parent.{$this->right()}
            	AND sub_parent.{$this->id()} = sub_tree.{$this->id()}
            GROUP BY node.{$this->id()}
            HAVING Depth > 0
            ORDER BY node.{$this->left()}"
        , $arguments);
    }
    
    /**
     * {@inheritdoc}
     */
    public function childrenConditional($conditionString, $arguments = [])
    {
        return Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT node.*, (COUNT(parent.{$this->id()})-1 - (sub_tree.innerDepth )) AS Depth
            FROM {$this->table()} AS node,
            	{$this->table()} AS parent,
            	{$this->table()} AS sub_parent,
           	(
                    SELECT node.{$this->id()}, (COUNT(parent.{$this->id()}) - 1) AS innerDepth
                    FROM {$this->table()} AS node,
                    {$this->table()} AS parent
                    WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
                    AND (node.$conditionString)
                    GROUP BY node.{$this->id()}
                    ORDER BY node.{$this->left()}
            ) AS sub_tree
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            	AND node.{$this->left()} BETWEEN sub_parent.{$this->left()} AND sub_parent.{$this->right()}
            	AND sub_parent.{$this->id()} = sub_tree.{$this->id()}
            GROUP BY node.{$this->id()}
            HAVING Depth = 1
            ORDER BY node.{$this->left()}"
        , $arguments);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pathConditional($conditionString, $arguments = [])
    {
        return Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT parent.*
            FROM {$this->table()} AS node,
            {$this->table()} AS parent
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            AND ( node.$conditionString )
            ORDER BY parent.{$this->left()}"
        , $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function leavesConditional($conditionString = '', $arguments = [])
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        if(!empty($conditionString))
        {
            return $databaseManager->request(
                "SELECT * FROM {$this->table()}
                WHERE {$this->right()} = {$this->left()} + 1
            	AND {$this->left()} BETWEEN
                (SELECT {$this->left()} FROM {$this->table()} WHERE $conditionString)
                    AND
                (SELECT {$this->right()} FROM {$this->table()} WHERE $conditionString)"
            , $arguments);
        }
        return $databaseManager->request(
            "SELECT * FROM {$this->table()} WHERE {$this->right()} = {$this->left()} + 1"
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function insertSiblingData($fieldValues = [], $conditionString = '', $arguments = [])
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $this->lock();
        
        if(!empty($conditionString))
        {
            $conditionString = "WHERE $conditionString";
        }
        
        $sibling = $databaseManager->request(
            "SELECT {$this->right()} AS `Right` FROM {$this->table()} $conditionString"
        , $arguments)[0];

        if($sibling === null)
        {
            $sibling['Left'] = $sibling['Right'] = 0;
        }
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} + 2 WHERE {$this->right()} > ?"
        , [$sibling['Right']]);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} + 2 WHERE {$this->left()} > ?"
        , [$sibling['Right']]);

        $fields = $values = '';
        
        $data = [];
        
        foreach($fieldValues as $k => $v)
        {
            $fields .= ", `$k`";
            $values .= ', ?';
            $data[] = $v;
        }
        
        array_unshift($data, $sibling['Right'] + 2);
        array_unshift($data, $sibling['Right'] + 1);

        $Res = $databaseManager->request(
            "INSERT INTO {$this->table()} ({$this->left()},{$this->right()} $fields)
            VALUES(?,? $values)"
        , $data);
        
        $this->unlock();
        
        return $Res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function insertChildData($fieldValues = [], $conditionString = '', $arguments = [])
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $this->lock();
        
        if(!empty($conditionString))
        {
            $conditionString = "WHERE $conditionString";
        }
        
        $parent = $databaseManager->request(
            "SELECT {$this->right()} AS `Right`, {$this->left()} AS `Left` FROM {$this->table()} $conditionString"
        , $arguments)[0];
            
        if ($parent==null)
        {
            $parent['Left'] = $parent['Right'] = 0;
        }
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} + 2 WHERE {$this->right()} >= ?"
        , $parent['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} + 2 WHERE {$this->left()} > ?"
        , $parent['Right']);

        $fields = $values = '';
        $data = [];
        
        foreach($fieldValues as $k => $v)
        {
            $fields .= ", `$k`";
            $values .= ', ?';
            $data[] = $v;
        }
        array_unshift($data, $parent['Right'] + 1);
        array_unshift($data, $parent['Right']);
        
        $Res = $databaseManager->request(
            "INSERT INTO {$this->table()} ({$this->left()},{$this->right()} $fields) " .
            "VALUES(?, ? $values)"
        , $data);
            
        $this->unlock();
        
        return $Res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function editData($fieldValues = [], $conditionString = '', $arguments = [])
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        if(!empty($conditionString))
        {
            $conditionString = "WHERE $conditionString";
        }

        $fields = '';
        $values = [];
        
        foreach($fieldValues as $k=>$v)
        {
            if (!empty($fields))
            {
                $fields .= ',';
            }
            $fields .= "`$k`=?";
            $values[] = $v;
        }

        return $databaseManager->request(
            "UPDATE {$this->table()} SET $fields $conditionString"
        , array_merge($values, $arguments));
    }
}