<?php
namespace PhpRbac\NestedSet;

interface ExtendedNestedSetInterface extends NestedSetInterface
{   
    /**
     * Returns the ID of a node based on a SQL conditional string
     * It accepts other params in the PreparedStatements format
     * $Condition equals the SQL condition, such as Title=?
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param string $Condition
     * @param string $Rest
     * @return integer ID
     */
    public function getID($ConditionString);
    
    
    /**
     * Returns the record of a node based on a SQL conditional string
     * It accepts other params in the PreparedStatements format
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param String $ConditionString
     * @param string $Rest
     * @return Array Record
     */
    public function getRecord($ConditionString, $Rest = null);

    /**
     * Adds a child to the beginning of a node's children
     * $FieldValueArray is key-paired field-values to insert
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     *
     * @param array $FieldValueArray
     * @param string $ConditionString
     * @param string $Rest
     * @return integer ChildID
     */
    public function insertChildData($FieldValueArray = [], $ConditionString = null, $Rest = null);
    
    /**
     * Adds a sibling after a node
     * $FieldValueArray is Pairs of Key/Value as Field/Value in the table
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     *
     * @param array $FieldValueArray
     * @param string $ConditionString
     * @param string $Rest
     * @return integer
     */
    public function insertSiblingData($FieldValueArray = [], $ConditionString = null, $Rest = null);

    
    /**
     * Deletes a node and all its descendants
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     *
     * @param String $ConditionString
     * @param string $Rest
     */
    public function deleteSubtreeConditional($ConditionString, $Rest = null);

    /**
     * Deletes a node and shifts the children up
     * Note: use a condition to support only 1 row, LIMIT 1 used.
     * Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param string $ConditionString
     * @param string $Rest
     * @return boolean
     */
    public function deleteConditional($ConditionString, $Rest = null);

    /**
     * Returns immediate children of a node
     * Note: this function performs the same as descendants but only returns results with Depth=1
     * Note: use only a single condition here
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param string $ConditionString
     * @param string $Rest
     * @return array
     * @seealso descendants
     */
    public function childrenConditional($ConditionString, $Rest = null);
    
    /**
     * Returns all descendants of a node
     * Note: use only a sinlge condition here
     * Set $AbsoluteDepths true to return Depth of sub-tree from zero or absolutely from the whole tree
     * $Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param boolean $AbsoluteDepths
     * @param string $ConditionString
     * @param string $Rest
     * @return array
     * @seealso children
     */
    public function descendantsConditional($AbsoluteDepths = false, $ConditionString, $Rest = null);
    
    /**
     * Finds all leaves of a parent
     * Note: if you don' specify $PID, There would be one less AND in the SQL Query
     * Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param string $ConditionString
     * @param string $Rest
     * @return array
     */
    public function leavesConditional($ConditionString = null, $Rest = null);

    /**
     * Returns the path to a node, including the node
     * Note: use a single condition, or supply "node." before condition fields.
     * Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param string $ConditionString
     * @param string $Rest
     * @return array
     */
    public function pathConditional($ConditionString, $Rest = null);

    /**
     * Returns the depth of a node in the tree
     * Note: this uses path
     * Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * Return Depth from zero upwards
     * 
     * @param string $ConditionString
     * @param string $Rest
     * @return integer
     * @seealso path
     */
    public function depthConditional($ConditionString, $Rest = null);

    /**
     * Returns the parent of a node
     * Note: this uses path
     * Rest is optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param string $ConditionString
     * @param string $Rest
     * @return array|null
     * @seealso path
     */
    public function parentNodeConditional($ConditionString, $Rest = null);
    
    /**
     * Returns a sibling of the current node
     * Note: You can't find siblings of roots
     * Note: this is a heavy function on nested sets, uses both children (which is quite heavy) and path
     * $SiblingDistance is the distance from current node (negative or positive)
     * $Rest is $Rest optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param integer $SiblingDistance
     * @param string $ConditionString
     * @param string $Rest
     * @return array|null
     */
    public function siblingConditional($SiblingDistance = 1, $ConditionString, $Rest = null);
    
    /**
     * Edits a node
     * $FieldValueArray is Pairs of Key/Value as Field/Value in the table to edit
     * $Rest is $Rest optional, rest of variables to fill in placeholders of condition string,
     * one variable for each ? in condition
     * 
     * @param array $FieldValueArray
     * @param string $ConditionString
     * @param string $Rest
     * @return integer
     */
    public function editData($FieldValueArray = [], $ConditionString = null, $Rest = null);
}
