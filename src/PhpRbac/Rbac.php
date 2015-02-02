<?php

namespace PhpRbac;

use PhpRbac\Database\Jf;
use PhpRbac\Manager\RbacManager;

class Rbac
{
    /** @var RbacManager **/
    private $rbacManager;
    /** @var Rbac **/
    private static $instance;
    
    private function __construct()
    {
        $this->rbacManager = new RbacManager();
    }
    
    /**
     * @return RbacManager
     */
    public function getManager()
    {
        return $this->rbacManager;
    }

    /**
     * Assign a role to a permission.
     * Alias for what's in the base class
     *
     * @param string|integer $role
     *        	Id, Title or Path
     * @param string|integer $permission
     *        	Id, Title or Path
     * @return boolean
     */
    public function assign($role, $permission)
    {
        return $this->rbacManager->assign($role, $permission);
    }

    /**
     * Checks whether a user has a permission or not.
     *
     * @param string|integer $permission
     *        	you can provide a path like /some/permission, a title, or the
     *        	permission ID.
     *        	in case of ID, don't forget to provide integer (not a string
     *        	containing a number)
     * @param string|integer $user_id
     *        	User ID of a user
     *
     * @throws RbacPermissionNotFoundException
     * @throws RbacUserNotProvidedException
     * @return boolean
     */
    public function check($permission, $user_id)
    {
        return $this->rbacManager->check($permission, $user_id);
    }

    /**
    * Enforce a permission on a user
    *
    * @param string|integer $permission
    *        	path or title or ID of permission
    *
    * @param integer $user_id
    *
    * @throws RbacUserNotProvidedException
    */
    public function enforce($permission, $user_id)
    {
        return $this->rbacManager->enforce($permission, $user_id);
    }

    /**
     * Empty the RBAC tables
     * 
     * @param boolean $ensure
     * @return boolean
     */
    public function reset($ensure = false)
    {
        return $this->rbacManager->reset($ensure);
    }

    /**
     * Get the RBAC tables 
     * 
     * @return string
     */
    public function tablePrefix()
    {
        return Jf::getConfig('table_prefix');
    }
    
    /**
     * @return Rbac
     */
    public static function getInstance()
    {
        if(self::$instance === null)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }
}