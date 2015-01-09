<?php

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
class RoleManager extends BaseRbac
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
		$this->roles = new FullNestedSet ( $this->tablePrefix () . "roles", "ID", "Lft", "Rght" );
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
		$this->unassignPermissions ( $ID );
		$this->unassignUsers ( $ID );
		if (! $Recursive)
			return $this->roles->deleteConditional ( "ID=?", $ID );
		else
			return $this->roles->deleteSubtreeConditional ( "ID=?", $ID );
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
		$r = Jf::sql ( "DELETE FROM {$this->tablePrefix()}rolepermissions WHERE
			RoleID=? ", $ID );
		return $r;
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
		return Jf::sql ( "DELETE FROM {$this->tablePrefix()}userroles WHERE
			RoleID=?", $ID );
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
		$Res = Jf::sql ( "
					SELECT COUNT(*) AS Result
					FROM {$this->tablePrefix()}rolepermissions AS TRel
					JOIN {$this->tablePrefix()}permissions AS TP ON ( TP.ID= TRel.PermissionID)
					JOIN {$this->tablePrefix()}roles AS TR ON ( TR.ID = TRel.RoleID)
					WHERE TR.Lft BETWEEN
					(SELECT Lft FROM {$this->tablePrefix()}roles WHERE ID=?)
					AND
					(SELECT Rght FROM {$this->tablePrefix()}roles WHERE ID=?)
					/* the above section means any row that is a descendants of our role (if descendant roles have some permission, then our role has it two) */
					AND TP.ID IN (
					SELECT parent.ID
					FROM {$this->tablePrefix()}permissions AS node,
					{$this->tablePrefix()}permissions AS parent
					WHERE node.Lft BETWEEN parent.Lft AND parent.Rght
					AND ( node.ID=? )
					ORDER BY parent.Lft
					);
					/*
					the above section returns all the parents of (the path to) our permission, so if one of our role or its descendants
					has an assignment to any of them, we're good.
					*/
					", $Role, $Role, $Permission );
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
	        $Role = $this->returnId($Role);

		if ($OnlyIDs)
		{
			$Res = Jf::sql ( "SELECT PermissionID AS `ID` FROM {$this->tablePrefix()}rolepermissions WHERE RoleID=? ORDER BY PermissionID", $Role );
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
	        return Jf::sql ( "SELECT `TP`.ID, `TP`.Title, `TP`.Description FROM {$this->tablePrefix()}permissions AS `TP`
		        LEFT JOIN {$this->tablePrefix()}rolepermissions AS `TR` ON (`TR`.PermissionID=`TP`.ID)
		        WHERE RoleID=? ORDER BY TP.ID", $Role );
		}
	}
}