<?php

namespace PhpRbac\NestedSet;

use PhpRbac\Rbac;

/**
 * BaseNestedSet Class
 * This class provides a means to implement Hierarchical data in flat SQL tables.
 * Queries extracted from http://dev.mysql.com/tech-resources/articles/hierarchical-data.html
 *
 * Tested and working properly
 * 
 * Usage:
 * have a table with at least 3 INT fields for ID,Left and Right.
 * Create a new instance of this class and pass the name of table and name of the 3 fields above
  */
//FIXME: many of these operations should be done in a transaction
class BaseNestedSet implements NestedSetInterface
{
    /** @var string **/
    private $Table;
    /** @var string **/
    private $Left;
    /** @var string **/
    private $Right;
    /** @var string **/
    private $ID;
    
    /**
     * @param string $Table
     * @param string $IDField
     * @param string $LeftField
     * @param string $RightField
     */
    public function __construct($Table, $IDField = 'ID', $LeftField = 'Left', $RightField = 'Right')
    {
        $this->Table = $Table;
        $this->ID = $IDField;
        $this->Left = $LeftField;
        $this->Right = $RightField;
    }
    
    /**
     * @return string
     */
    protected function id()
    {
    	return $this->ID;
    }
    
    /**
     * @return string
     */
    protected function table()
    {
    	return $this->Table;
    }
    
    /**
     * @return string
     */
    protected function left()
    {
    	return $this->Left;
    }
    
    /**
     * @return string
     */
    protected function right()
    {
    	return $this->Right;
    }
    
    /**
     * {@inheritdoc}
     */
    public function descendantCount($ID)
    {
        $Res = Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT ({$this->right()}-{$this->left()}-1)/2 AS `Count` FROM
            {$this->table()} WHERE {$this->id()}=?"
        , $ID);
        return sprintf('%d', $Res[0]['Count']) * 1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function depth($ID)
    {
        return count($this->path($ID))-1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function sibling($ID, $SiblingDistance = 1)
    {
        $n = 0;
        $Parent = $this->parentNode($ID);
        
        if(($Siblings = $this->children($Parent[$this->id()])) === null)
        {
            return null;
        }
        foreach ($Siblings as &$Sibling)
        {
            if($Sibling[$this->id()]==$ID)
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
    public function parentNode($ID)
    {
        $Path = $this->path($ID);
        if(count($Path) < 2)
        {
            return null;
        }
        return $Path[count($Path) - 2];        
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete($ID)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $Info = $databaseManager->request(
            "SELECT {$this->left()} AS `Left`,{$this->right()} AS `Right` 
            FROM {$this->table()} WHERE {$this->id()} = ?;"
        , $ID)[0];

        $count = $databaseManager->request(
            "DELETE FROM {$this->table()} WHERE {$this->left()} = ?"
        , $Info['Left']);

        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - 1, `".
            $this->left."` = {$this->left()} - 1 WHERE {$this->left()} BETWEEN ? AND ?"
        , $Info['Left'], $Info['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - 2 WHERE `".
            $this->Right."` > ?"
        , $Info['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} - 2 WHERE `".
            $this->left."` > ?"
        , $Info['Right']);
        
        return $count;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteSubtree($ID)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $Info = $databaseManager->request(
            "SELECT {$this->left()} AS `Left`,{$this->right()} AS `Right`, {$this->right()}-{$this->left()} + 1 AS Width
            FROM {$this->table()} WHERE {$this->id()} = ?;"
        , $ID)[0];
        
        $count = $databaseManager->request(
            "DELETE FROM {$this->table()} WHERE {$this->left()} BETWEEN ? AND ?"
        , $Info['Left'], $Info['Right']);
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} - ? WHERE {$this->right()} > ?"
        , $Info['Width'], $Info['Right']);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} - ? WHERE {$this->left()} > ?"
        ,$Info['Width'],$Info['Right']);
            
        return $count;
    }
    
    /**
     * {@inheritdoc}
     */
    public function descendants($id, $absoluteDepths = false)
    {
        $depthConcat =
            ($absoluteDepths === false)
            ? ' - (sub_tree.depth)'
            : ''
        ;
        return $this->getSubtreePart($id, $depthConcat, '> 0', "{$this->id()} = ?");
    }
    
    /**
     * {@inheritdoc}
     */
    public function children($id)
    {
        $Res = $this->getSubtreePart($id, ' - (sub_tree.depth )', '= 1', "{$this->id()} = ?");
            
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
     * 
     * @param integer $id
     * @param string $depthConcat
     * @param string $depthLevel
     * @param string $condition
     * @return array
     */
    protected function getSubtreePart($id, $depthConcat, $depthLevel, $condition, $arguments = array())
    {
        return Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT node.*, (COUNT(parent.{$this->id()})-1$depthConcat) AS Depth
            FROM {$this->table()} AS node,
            	{$this->table()} AS parent,
            	{$this->table()} AS sub_parent,
           	(
            		SELECT node.{$this->id()}, (COUNT(parent.{$this->id()}) - 1) AS depth
            		FROM {$this->table()} AS node,
            		{$this->table()} AS parent
            		WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            		AND node.$condition
            		GROUP BY node.{$this->id()}
            		ORDER BY node.{$this->left()}
            ) AS sub_tree
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            	AND node.{$this->left()} BETWEEN sub_parent.{$this->left()} AND sub_parent.{$this->right()}
            	AND sub_parent.{$this->id()} = sub_tree.{$this->id()}
            GROUP BY node.{$this->id()}
            HAVING Depth $depthLevel
            ORDER BY node.{$this->left()};"
        , $id);
    }
    
    /**
     * {@inheritdoc}
     */
    public function path($id)
    {
        return Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT parent.* FROM {$this->table()} AS node, {$this->table} AS parent
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()} AND parent.{$this->right()}
            AND node.{$this->id()} = ? ORDER BY parent.{$this->left()}"
        , [$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function leaves($pid = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        return
            ($pid === null)
            ? $databaseManager->request("SELECT * FROM {$this->table()} "
            . "WHERE {$this->right()} = {$this->left()} + 1")
            : $databaseManager->request(
                "SELECT * FROM {$this->table()} WHERE {$this->right()} = {$this->left()} + 1 
                    AND {$this->left()} BETWEEN 
                (SELECT {$this->left()} FROM {$this->table()} WHERE {$this->id()}=?)
                    AND 
                (SELECT {$this->right()} FROM {$this->table()} WHERE {$this->id()}=?)"
            , [$pid, $pid])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function insertSibling($id = 0)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $sibling = $databaseManager->request(
            "SELECT {$this->right()} AS `Right` FROM {$this->table()} WHERE {$this->id()} = ?"
        , [$id])[0];
            
        if($sibling === null)
        {
            $sibling['Right']=0;
        }
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} + 2 "
            . "WHERE {$this->right()} > ?"
        , [$sibling['Right']]);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} + 2 WHERE {$this->left()} > ?"
        , [$sibling['Right']]);
            
        return $databaseManager->request(
            "INSERT INTO {$this->table()} ({$this->left()},{$this->right()}) VALUES(?,?)"
        , [$sibling['Right'] + 1, $sibling['Right'] + 2]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function insertChild($pid = 0)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        $sibling = $databaseManager->request(
            "SELECT {$this->left()} AS `Left` FROM {$this->table()} WHERE {$this->id()} = ?"
        , [$pid])[0];
        
        if($sibling === null)
        {
            $sibling['Left'] = 0;
        }
        
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->right()} = {$this->right()} + 2 WHERE {$this->right()} > ?"
        , [$sibling['Left']]);
            
        $databaseManager->request(
            "UPDATE {$this->table()} SET {$this->left()} = {$this->left()} + 2 WHERE {$this->left()} > ?"
        , [$sibling['Left']]);
            
        return $databaseManager->request(
            "INSERT INTO {$this->table()} ({$this->left()},{$this->right()}) VALUES(?,?)"
        , [$sibling['Left'] + 1, $sibling['Left'] + 2]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fullTree()
    {
        return Rbac::getInstance()->getDatabaseManager()->request(
            "SELECT node.*, (COUNT(parent.{$this->id()}) - 1) AS Depth
            FROM {$this->table()} AS node,
            {$this->table()} AS parent
            WHERE node.{$this->left()} BETWEEN parent.{$this->left()}
            AND parent.{$this->right()}
            GROUP BY node.{$this->id()}
            ORDER BY node.{$this->left()}"
        );
    }
    
    /**
     * This function converts a 2D array with Depth fields into a multidimensional tree in an associative array
     *
     * @param Array $Result
     * @return Array Tree
     */
    #FIXME: think how to implement this!
    /**
    function Result2Tree($Result)
    {
        $out=array();
        $stack=array();
        $cur=&$out;
        foreach($Result as $R)
        {
            if ($cur[$LastKey]['Depth']==$R['Depth'])
            {
                echo "adding 0 ".$R['Title'].BR;
                $cur[$R[$this->id()]]=$R;
                $LastKey=$R[$this->id()];
            }
            elseif ($cur[$LastKey]['Depth']<$R['Depth'])
            {
                echo "adding 1 ".$R['Title'].BR;
                array_push($stack,$cur);
                $cur=&$cur[$LastKey];
                $cur[$R[$this->id()]]=$R;
                $LastKey=$R[$this->id()];
            }
            elseif ($cur[$LastKey]['Depth']>$R['Depth'])
            {
                echo "adding 2 ".$R['Title'].BR;
                $cur=array_pop($stack);
                $cur[$R[$this->id()]]=$R;
                $LastKey=$R[$this->id()];
            }
            
        }
        return $out;
    }
	/**/
}