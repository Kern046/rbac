<?php
namespace PhpRbac\Manager;

use PhpRbac\NestedSet\FullNestedSet;
use PhpRbac\Database\Jf;
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
	/**
	 * Permissions Nested Set
	 *
	 * @var FullNestedSet
	 */
	protected $permissions;

	protected function type()
	{
		return "permissions";
	}

	function __construct()
	{
		$this->permissions = new FullNestedSet ( Jf::getConfig('table_prefix') . "permissions", "ID", "Lft", "Rght" );
	}

	/**
	 * Remove permissions from system
	 *
	 * @param integer $ID
	 *        	permission id
	 * @param boolean $Recursive
	 *        	delete all descendants
	 *
	 */
	function remove($ID, $Recursive = false)
	{
		$this->unassignRoles ( $ID );
		if (! $Recursive)
			return $this->permissions->deleteConditional ( "ID=?", $ID );
		else
			return $this->permissions->deleteSubtreeConditional ( "ID=?", $ID );
	}

	/**
	 * Unassignes all roles of this permission, and returns their number
	 *
	 * @param integer $ID
	 *      Permission Id
	 * @return integer
	 */
	function unassignRoles($ID)
	{
		$res = Jf::sql ( 'DELETE FROM ' . Jf::getConfig('table_prefix') . 'rolepermissions WHERE
			PermissionID=?', $ID );
		return (int)$res;
	}

	/**
	 * Returns all roles assigned to a permission
	 *
	 * @param mixed $Permission
	 *        	Id, Title, Path
	 * @param boolean $OnlyIDs
	 *        	if true, result will be a 1D array of IDs
	 * @return Array 2D or 1D or null
	 */
	function roles($Permission, $OnlyIDs = true)
	{
            if (!is_numeric($Permission))
            {
                $Permission = $this->returnId($Permission);
            }
	    
            $tablePrefix = Jf::getConfig('table_prefix');
            if ($OnlyIDs)
            {
                    $Res = Jf::sql ( "SELECT RoleID AS `ID` FROM
                            {$tablePrefix}rolepermissions WHERE PermissionID=? ORDER BY RoleID", $Permission );

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
                return Jf::sql ( "SELECT `TP`.ID, `TP`.Title, `TP`.Description FROM {$tablePrefix}roles AS `TP`
                LEFT JOIN {$tablePrefix}rolepermissions AS `TR` ON (`TR`.RoleID=`TP`.ID)
                WHERE PermissionID=? ORDER BY TP.ID", $Permission );
            }
	}
}