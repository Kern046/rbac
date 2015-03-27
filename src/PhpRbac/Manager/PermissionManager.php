<?php
namespace PhpRbac\Manager;

use PhpRbac\NestedSet\FullNestedSet;

use PhpRbac\Rbac;

/**
 * @defgroup phprbac_permission_manager Documentation regarding Permission Manager Functionality
 * @ingroup phprbac
 * @{
 *
 * Documentation regarding Permission Manager functionality.
 *
 * Permission Manager: Contains functionality specific to Permissions
 *
 * @author abiusx
 * @version 1.0
 */
class PermissionManager extends BaseRbacManager
{
    function __construct()
    {
        $this->type = 'permissions';
        $this->nestedSet = new FullNestedSet(
            Rbac::getInstance()->getDatabaseManager()->getTablePrefix() .
        'permissions', 'ID', 'Lft', 'Rght');
    }

    /**
     * {inheritdoc}
     */
    function remove($id, $recursive = false)
    {
        $this->unassignRoles($id);
        return parent::remove($id, $recursive);
    }

    /**
     * Unassignes all roles of this permission, and returns their number
     *
     * @param integer $permissionId
     * @return integer
     */
    function unassignRoles($permissionId)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();

        return $databaseManager->request(
            'DELETE FROM ' . $databaseManager->getTablePrefix() .
            'rolepermissions WHERE PermissionID=?'
        , [$permissionId]);
    }

    /**
     * Returns all roles assigned to a permission
     * $permission can be Id, Title, Path
     * if $onlyIds is true, result will be a 1D array of IDs
     * 
     * @param mixed $permission
     * @param boolean $onlyIds
     * @return array
     */
    function roles($permission, $onlyIds = true)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();

        if (!is_numeric($permission))
        {
            $permission = $this->returnId($permission);
        }

        if ($onlyIds)
        {
            $Res = $databaseManager->request(
                "SELECT RoleID AS `ID` FROM {$tablePrefix}rolepermissions "
                . "WHERE PermissionID=? ORDER BY RoleID"
            , [$permission]);

            if(is_array($Res))
            {
                $out = [];
                foreach($Res as $R)
                {
                    $out[] = $R['ID'];
                }
                return $out;
            }
            return null;
        }
        return $databaseManager->request(
            "SELECT `TP`.ID, `TP`.Title, `TP`.Description FROM {$tablePrefix}roles AS `TP`
            LEFT JOIN {$tablePrefix}rolepermissions AS `TR` ON (`TR`.RoleID=`TP`.ID)
            WHERE PermissionID=? ORDER BY TP.ID"
        , [$permission]);
    }
}