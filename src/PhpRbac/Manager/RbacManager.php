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
    /** @var PermissionManager **/
    private $permissionManager;
    /** @var RoleManager **/
    private $roleManager;
    /** @var UserManager **/
    private $userManager;
    
    function __construct()
    {
        $this->userManager = new UserManager ();
        $this->roleManager = new RoleManager ();
        $this->permissionManager = new PermissionManager ();
    }
    
    /**
     * Get the PermissionManager
     * 
     * @return PermissionManager
     */
    public function getPermissionManager()
    {
        return $this->permissionManager;
    }
    
    /**
     * Get the RoleManager
     * 
     * @return RoleManager
     */
    public function getRoleManager()
    {
        return $this->roleManager;
    }
    
    /**
     * Get the UserManager
     * 
     * @return UserManager
     */
    public function getUserManager()
    {
        return $this->userManager;
    }

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
        return $this->roleManager->assign($Role, $Permission);
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
        {
            throw new RbacUserNotProvidedException('$UserID is a required argument.');
        }
            

        $PermissionID = $this->permissionManager->getId($Permission);

        // if invalid, throw exception
        if ($PermissionID === null)
        {
            throw new RbacPermissionNotFoundException("The permission '{$Permission}' not found.");
        }
            
        $LastPart =
            ($this->isSQLite())
            ? "AS Temp ON ( TR.ID = Temp.RoleID)
            WHERE TUrel.UserID=? AND Temp.ID=?"
            : "ON ( TR.ID = TRel.RoleID)
            WHERE TUrel.UserID=? AND TPdirect.ID=?"
        ;
        
        $tablePrefix = Jf::getConfig('table_prefix');
        $Res=Jf::sql ( "SELECT COUNT(*) AS Result
            FROM
            {$tablePrefix}userroles AS TUrel

            JOIN {$tablePrefix}roles AS TRdirect ON (TRdirect.ID=TUrel.RoleID)
            JOIN {$tablePrefix}roles AS TR ON ( TR.Lft BETWEEN TRdirect.Lft AND TRdirect.Rght)
            JOIN
            (	{$tablePrefix}permissions AS TPdirect
            JOIN {$tablePrefix}permissions AS TP ON ( TPdirect.Lft BETWEEN TP.Lft AND TP.Rght)
            JOIN {$tablePrefix}rolepermissions AS TRel ON (TP.ID=TRel.PermissionID)
            ) $LastPart",
            $UserID, $PermissionID );

        return $Res[0]['Result'] >= 1;
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
        if ($Ensure !== true)
        {
            throw new \Exception ("You must pass true to this function, otherwise it won't work.");
            return;
        }
        $res = true;
        $res = $res and $this->roleManager->resetAssignments(true);
        $res = $res and $this->roleManager->reset(true);
        $res = $res and $this->permissionManager->reset(true);
        $res = $res and $this->userManager->resetAssignments(true);

        return $res;
    }
}