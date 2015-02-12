<?php
namespace PhpRbac\NestedSet;

interface NestedSetInterface
{
    /**
     * Adds a child to the beginning of a node's children
     * 
     * @param integer $PID
     * @return integer
     */
    public function insertChild($PID = 0);
    
    /**
     * Adds a sibling after a node
     * 
     * @param integer $ID
     * @return integer
     */
    public function insertSibling($ID = 0);
    
    /**
     * Deletes a node and all its descendants
     *
     * @param integer $ID
     * @return integer
     */
    public function deleteSubtree($ID);
    
    /**
     * Deletes a node and shifts the children up
     *
     * @param integer $ID
     * @return integer
     */
    public function delete($ID);
    
    /**
     * Retrives the full tree including Depth field.
     *
     * @return array
     */
    public function fullTree();
    
    /**
     * Returns immediate children of a node
     * Note: this function performs the same as descendants but only returns results with Depth=1
     * 
     * @param integer $ID
     * @return array
     * @seealso descendants
     */
    public function children($ID);
    
    /**
     * Returns all descendants of a node
     * Set $AbsoluteDepths true to return Depth of sub-tree from zero
     * or absolutely from the whole tree  
     *
     * @param integer $ID
     * @param boolean $AbsoluteDepths
     * @return array
     * @seealso children
     */
    public function descendants($ID, $AbsoluteDepths = false);
    
    /**
     * Returns number of descendants 
     *
     * @param integer $ID
     * @return integer Count
     */
    public function descendantCount($ID);
    
    /**
     * Finds all leaves of a parent
     * Note: if you don' specify $PID, There would be one less AND in the SQL Query
     * 
     * @param integer $PID
     * @return array|boolean
     */
    public function leaves($PID = null);
    
    /**
     * Returns the path to a node, including the node
     *
     * @param integer $ID
     * @return array
     */
    public function path($ID);
    
    /**
     * Returns the depth of a node in the tree
     * Note: this uses path
     * $Depth is the depth from zero upwards
     * 
     * @param integer $ID
     * @return integer Depth
     * @seealso path
     */
    public function depth($ID);
    
    /**
     * Returns the parent of a node
     * Note: this uses path
     * 
     * @param integer $ID
     * @return array|null
     * @seealso path
     */
    public function parentNode($ID);
    
    /**
     * Returns a sibling of the current node
     * Note: You can't find siblings of roots 
     * Note: this is a heavy function on nested sets, uses both children (which is quite heavy) and path
     * $SiblingDistance is the distance from current node (negative or positive)
     * 
     * @param integer $ID
     * @param integer $SiblingDistance
     * @return array|null 
     */
    public function sibling($ID, $SiblingDistance = 1);
}