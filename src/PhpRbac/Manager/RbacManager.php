<?php
namespace PhpRbac\Manager;

use PhpRbac\Exception\RbacPermissionNotFoundException;
use PhpRbac\Exception\RbacUserNotProvidedException;

use PhpRbac\Rbac;

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
class RbacManager
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
     * $permission and $role can be Id, Title or Path
     *
     * @param string|integer $role
     * @param string|integer $permission
     * @return boolean
     */
    function assign($role, $permission)
    {
        return $this->roleManager->assign($role, $permission);
    }

    /**
     * Prepared statement for check query
     *
     * @var BaseDatabaseStatement
     */
    private $ps_Check = null;

    /**
     * Checks whether a user has a permission or not.
     * you can provide a path like /some/permission, a title, or the
     * permission ID.
     * in case of ID, don't forget to provide integer (not a string
     * containing a number)
     *
     * @param string|integer $permission
     * @param string|integer $userId
     *
     * @throws RbacPermissionNotFoundException
     * @throws RbacUserNotProvidedException
     * @return boolean
     */
    function check($permission, $userId = null)
    {
        $databaseManager = Rbac::getInstance()->getDatabaseManager();
        $tablePrefix = $databaseManager->getTablePrefix();
        
        if ($userId === null)
        {
            throw new RbacUserNotProvidedException('$UserID is a required argument.');
        }
            
        $PermissionID = $this->permissionManager->getId($permission);

        // if invalid, throw exception
        if ($PermissionID === null)
        {
            throw new RbacPermissionNotFoundException("The permission '{$permission}' not found.");
        }
            
        $LastPart =
            ($databaseManager->isSQLite())
            ? "AS Temp ON ( TR.ID = Temp.RoleID)
            WHERE TUrel.UserID=? AND Temp.ID=?"
            : "ON ( TR.ID = TRel.RoleID)
            WHERE TUrel.UserID=? AND TPdirect.ID=?"
        ;
        
        $Res = $databaseManager->request(
            "SELECT COUNT(*) AS Result FROM {$tablePrefix}userroles AS TUrel
            JOIN {$tablePrefix}roles AS TRdirect ON (TRdirect.ID=TUrel.RoleID)
            JOIN {$tablePrefix}roles AS TR ON ( TR.Lft BETWEEN TRdirect.Lft AND TRdirect.Rght)
            JOIN
            (	{$tablePrefix}permissions AS TPdirect
            JOIN {$tablePrefix}permissions AS TP ON ( TPdirect.Lft BETWEEN TP.Lft AND TP.Rght)
            JOIN {$tablePrefix}rolepermissions AS TRel ON (TP.ID=TRel.PermissionID)
            ) $LastPart"
        , [$userId, $PermissionID]);

        return $Res[0]['Result'] >= 1;
    }

    /**
    * Enforce a permission on a user
    * $permission can be a path or title or ID of permission
    *
    * @param string|integer $permission
    * @param integer $userId
    * @throws RbacUserNotProvidedException
    */
    function enforce($permission, $userId = null)
    {
	if($userId === null)
        {
            throw new RbacUserNotProvidedException('$UserID is a required argument.');
        }
            
        if(!$this->check($permission, $userId))
        {
            header('HTTP/1.1 403 Forbidden');
            die('<strong>Forbidden</strong>: You do not have permission to access this resource.');
        }
        return true;
    }

    /**
    * Remove all roles, permissions and assignments
    * mostly used for testing
    * $ensure must be set to true or throws error
    *
    * @param boolean $ensure
    * @return boolean
    */
    function reset($ensure = false)
    {
        if ($ensure !== true)
        {
            throw new \Exception ("You must pass true to this function, otherwise it won't work.");
        }
        return
            $this->roleManager->resetAssignments(true) &&
            $this->roleManager->reset(true) &&
            $this->permissionManager->reset(true) &&
            $this->userManager->resetAssignments(true)
        ;
    }
}