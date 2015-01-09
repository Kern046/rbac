<?php
namespace PhpRbac\Manager;

use PhpRbac\Database\JModel;
use PhpRbac\Database\Jf;

use PhpRbac\Exception\RbacPermissionNotFoundException;
use PhpRbac\Exception\RbacUserNotProvidedException;

/**
 * @defgroup phprbac_manager Documentation regarding Rbac Manager Functionality
 * @ingroup phprbac
 * @{
 *
 * Documentation regarding Rbac Manager functionality.
 *
 * Rbac Manager: Provides NIST Level 2 Standard Hierarchical Role Based Access Control
 *
 * Has three members, Roles, Users and Permissions for specific operations
 *
 * @author abiusx
 * @version 1.0
 */
class RbacManager extends JModel
{
    function __construct()
    {
        $this->Users = new RbacUserManager ();
        $this->Roles = new RoleManager ();
        $this->Permissions = new PermissionManager ();
    }

    /**
     *
     * @var \Jf\PermissionManager
     */
    public $Permissions;

    /**
     *
     * @var \Jf\RoleManager
     */
    public $Roles;

    /**
     *
     * @var \Jf\RbacUserManager
     */
    public $Users;

    /**
     * Assign a role to a permission.
     * Alias for what's in the base class
     *
     * @param string|integer $Role
     *        	Id, Title or Path
     * @param string|integer $Permission
     *        	Id, Title or Path
     * @return boolean
     */
    function assign($Role, $Permission)
    {
        return $this->Roles->assign($Role, $Permission);
    }

    /**
     * Prepared statement for check query
     *
     * @var BaseDatabaseStatement
     */
    private $ps_Check = null;

    /**
     * Checks whether a user has a permission or not.
     *
     * @param string|integer $Permission
     *        	you can provide a path like /some/permission, a title, or the
     *        	permission ID.
     *        	in case of ID, don't forget to provide integer (not a string
     *        	containing a number)
     * @param string|integer $UserID
     *        	User ID of a user
     *
     * @throws RbacPermissionNotFoundException
     * @throws RbacUserNotProvidedException
     * @return boolean
     */
    function check($Permission, $UserID = null)
    {
        if ($UserID === null)
            throw new RbacUserNotProvidedException ("\$UserID is a required argument.");

        // convert permission to ID
        if (is_numeric ( $Permission ))
        {
            $PermissionID = $Permission;
        }
        else
        {
            if (substr ( $Permission, 0, 1 ) == "/")
                $PermissionID = $this->Permissions->pathId ( $Permission );
            else
                $PermissionID = $this->Permissions->titleId ( $Permission );
        }

        // if invalid, throw exception
        if ($PermissionID === null)
            throw new RbacPermissionNotFoundException ( "The permission '{$Permission}' not found." );

        if ($this->isSQLite())
        {
            $LastPart="AS Temp ON ( TR.ID = Temp.RoleID)
 							WHERE
 							TUrel.UserID=?
 							AND
 							Temp.ID=?";
        }
        else //mysql
        {
            $LastPart="ON ( TR.ID = TRel.RoleID)
 							WHERE
 							TUrel.UserID=?
 							AND
 							TPdirect.ID=?";
        }
        $Res=Jf::sql ( "SELECT COUNT(*) AS Result
            FROM
            {$this->tablePrefix()}userroles AS TUrel

            JOIN {$this->tablePrefix()}roles AS TRdirect ON (TRdirect.ID=TUrel.RoleID)
            JOIN {$this->tablePrefix()}roles AS TR ON ( TR.Lft BETWEEN TRdirect.Lft AND TRdirect.Rght)
            JOIN
            (	{$this->tablePrefix()}permissions AS TPdirect
            JOIN {$this->tablePrefix()}permissions AS TP ON ( TPdirect.Lft BETWEEN TP.Lft AND TP.Rght)
            JOIN {$this->tablePrefix()}rolepermissions AS TRel ON (TP.ID=TRel.PermissionID)
            ) $LastPart",
            $UserID, $PermissionID );

        return $Res [0] ['Result'] >= 1;
    }

    /**
    * Enforce a permission on a user
    *
    * @param string|integer $Permission
    *        	path or title or ID of permission
    *
    * @param integer $UserID
    *
    * @throws RbacUserNotProvidedException
    */
	function enforce($Permission, $UserID = null)
	{
	if ($UserID === null)
                throw new RbacUserNotProvidedException ("\$UserID is a required argument.");

		if (! $this->check($Permission, $UserID)) {
            header('HTTP/1.1 403 Forbidden');
            die("<strong>Forbidden</strong>: You do not have permission to access this resource.");
        }

        return true;
	}

    /**
    * Remove all roles, permissions and assignments
    * mostly used for testing
    *
    * @param boolean $Ensure
	*        	must set or throws error
	* @return boolean
    */
    function reset($Ensure = false)
    {
        if ($Ensure !== true) {
            throw new \Exception ("You must pass true to this function, otherwise it won't work.");
            return;
        }

        $res = true;
        $res = $res and $this->Roles->resetAssignments ( true );
        $res = $res and $this->Roles->reset ( true );
		$res = $res and $this->Permissions->reset ( true );
		$res = $res and $this->Users->resetAssignments ( true );

		return $res;
	}
}