<?php
namespace PhpRbac\Manager;

use PhpRbac\NestedSet\FullNestedSet;

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
    function __construct()
    {
        $this->type = 'roles';
        $this->nestedSet = new FullNestedSet(
            Rbac::getInstance()->getDatabaseManager()->getTablePrefix() . 'roles',
        'ID', 'Lft', 'Rght');
    }
    
    /**
     * {@inheritdoc}
     */
    public function remove($id, $recursive = false)
    {
        $this->unassignPermissions($id);
        $this->unassignUsers($id);
        return parent::remove($id, $recursive);
    }

    /**
     * Unassigns all permissions belonging to the role related to $id
     * It returns the number of assignments deleted
     *
     * @param integer $id
     * @return integer
     */
    function unassignPermissions($id)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        return $databaseManager->request(
            'DELETE FROM ' . $databaseManager->getTablePrefix() . 'rolepermissions
            WHERE RoleID=?'
        , [$id]);
    }

    /**
     * Unassign all users that have the role related to $id
     *
     * @param integer $id
     * @return integer number of deleted assignments
     */
    function unassignUsers($id)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        
        return $databaseManager->request(
            'DELETE FROM ' . $databaseManager->getTablePrefix() . 'userroles WHERE RoleID=?'
        , [$id]);
    }

    /**
     * Checks to see if a role has a permission or not
     *
     * @param integer $roleId
     * @param integer $permissionId
     * @return boolean
     *
     * @todo: If we pass a Role that doesn't exist the method just returns false. We may want to check for a valid Role.
     */
    function hasPermission($roleId, $permissionId)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        return $databaseManager->request(
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
        , [$roleId, $roleId, $permissionId])[0]['Result'] >= 1;
    }

    /**
     * Returns all permissions assigned to a role
     * If $onlyIds is set to true, result would be a 1D array of IDs
     * This method returns the two dimensional array would have ID,Title and Description of permissions
     * Or 1D array or null
     *
     * @param integer $roleId
     * @param boolean $onlyIds
     * @return mixed
     */
    function permissions($roleId, $onlyIds = true)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        if (!is_numeric($roleId))
        {
            $roleId = $this->returnId($roleId);
        }

        if ($onlyIds)
        {
            $Res = $databaseManager->request(
                "SELECT PermissionID AS `ID` FROM {$tablePrefix}rolepermissions WHERE RoleID=? ORDER BY PermissionID"
            , [$roleId]);
            if (!is_array($Res))
            {
                return null;
            }
            $out = [];
            foreach ($Res as $R)
            {
                $out [] = $R ['ID'];
            }
            return $out;
        }
        return $databaseManager->request(
            "SELECT `TP`.ID, `TP`.Title, `TP`.Description FROM {$tablePrefix}permissions AS `TP`
            LEFT JOIN {$tablePrefix}rolepermissions AS `TR` ON (`TR`.PermissionID=`TP`.ID)
            WHERE RoleID=? ORDER BY TP.ID"
        , [$roleId]);
    }
}