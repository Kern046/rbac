<?php
namespace PhpRbac\Manager;

use PhpRbac\NestedSet\FullNestedSet;

use PhpRbac\Database\Jf;

use PhpRbac\Rbac;

/**
 * @defgroup phprbac_role_manager Documentation regarding Role Manager Functionality
 * @ingroup phprbac
 * @{
 *
 * Documentation regarding Role Manager functionality.
 *
 * Role Manager: Contains functionality specific to Roles
 *
 * @author abiusx
 * @version 1.0
 */
class RoleManager extends BaseRbacManager
{
    /**
     * Roles Nested Set
     *
     * @var FullNestedSet
     */
    protected $roles = null;

    protected function type()
    {
            return "roles";
    }

    function __construct()
    {
        $this->type = "roles";
        $this->roles = new FullNestedSet(Jf::getConfig('table_prefix') . "roles", "ID", "Lft", "Rght" );
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
            : 
                (substr($item, 0, 1) == '/')
                ? Rbac::getInstance()->getManager()->getRoleManager()->pathId($item)
                : Rbac::getInstance()->getManager()->getRoleManager()->titleId($item)
        ;
    }

    /**
     * Remove roles from system
     *
     * @param integer $ID
     *        	role id
     * @param boolean $Recursive
     *        	delete all descendants
     *
     */
    function remove($ID, $Recursive = false)
    {
        $this->unassignPermissions($ID);
        $this->unassignUsers($ID);
        if (!$Recursive)
        {
            return $this->roles->deleteConditional('ID=?', $ID);
        }
        return $this->roles->deleteSubtreeConditional('ID=?', $ID);
    }

    /**
     * Unassigns all permissions belonging to a role
     *
     * @param integer $ID
     *        	role ID
     * @return integer number of assignments deleted
     */
    function unassignPermissions($ID)
    {
        return Jf::sql(
            'DELETE FROM ' . Jf::getConfig('table_prefix') . 'rolepermissions
            WHERE RoleID=?'
        , $ID);
    }

    /**
     * Unassign all users that have a certain role
     *
     * @param integer $ID
     *        	role ID
     * @return integer number of deleted assignments
     */
    function unassignUsers($ID)
    {
        return Jf::sql(
            'DELETE FROM ' . Jf::getConfig('table_prefix')
            . 'userroles WHERE RoleID=?'
        , $ID);
    }

    /**
     * Checks to see if a role has a permission or not
     *
     * @param integer $Role
     *        	ID
     * @param integer $Permission
     *        	ID
     * @return boolean
     *
     * @todo: If we pass a Role that doesn't exist the method just returns false. We may want to check for a valid Role.
     */
    function hasPermission($Role, $Permission)
    {
        $tablePrefix = Jf::getConfig('table_prefix');
        $Res = Jf::sql (
            "SELECT COUNT(*) AS Result
            FROM {$tablePrefix}rolepermissions AS TRel
            JOIN {$tablePrefix}permissions AS TP ON ( TP.ID= TRel.PermissionID)
            JOIN {$tablePrefix}roles AS TR ON ( TR.ID = TRel.RoleID)
            WHERE TR.Lft BETWEEN
            (SELECT Lft FROM {$tablePrefix}roles WHERE ID=?)
            AND
            (SELECT Rght FROM {$tablePrefix}roles WHERE ID=?)
            /* the above section means any row that is a descendants of our role (if descendant roles have some permission, then our role has it two) */
            AND TP.ID IN (
            SELECT parent.ID
            FROM {$tablePrefix}permissions AS node,
            {$tablePrefix}permissions AS parent
            WHERE node.Lft BETWEEN parent.Lft AND parent.Rght
            AND ( node.ID=? )
            ORDER BY parent.Lft
            );
            /*
            the above section returns all the parents of (the path to) our permission, so if one of our role or its descendants
            has an assignment to any of them, we're good.
            */"
        , $Role, $Role, $Permission);
        return $Res [0] ['Result'] >= 1;
    }

    /**
     * Returns all permissions assigned to a role
     *
     * @param integer $Role
     *        	ID
     * @param boolean $OnlyIDs
     *        	if true, result would be a 1D array of IDs
     * @return Array 2D or 1D or null
     *         the two dimensional array would have ID,Title and Description of permissions
     */
    function permissions($Role, $OnlyIDs = true)
    {
        if (! is_numeric ($Role))
        {
            $Role = $this->returnId($Role);
        }

        $tablePrefix = Jf::getConfig('table_prefix');
        if ($OnlyIDs)
        {
            $Res = Jf::sql ( "SELECT PermissionID AS `ID` FROM {$tablePrefix}rolepermissions WHERE RoleID=? ORDER BY PermissionID", $Role );
            if (is_array ( $Res ))
            {
                    $out = array ();
                    foreach ( $Res as $R )
                            $out [] = $R ['ID'];
                    return $out;
            }
            else
                    return null;
        } else {
        return Jf::sql ( "SELECT `TP`.ID, `TP`.Title, `TP`.Description FROM {$tablePrefix}permissions AS `TP`
                LEFT JOIN {$tablePrefix}rolepermissions AS `TR` ON (`TR`.PermissionID=`TP`.ID)
                WHERE RoleID=? ORDER BY TP.ID", $Role );
        }
    }
}