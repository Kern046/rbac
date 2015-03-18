<?php

namespace PhpRbac\Manager;

use PhpRbac\Rbac;

use PhpRbac\Exception\RbacUserNotProvidedException;

/**
 * @defgroup phprbac_user_manager Documentation regarding Rbac User Manager Functionality
 * @ingroup phprbac
 * @{
 *
 * Documentation regarding Rbac User Manager functionality.
 *
 * Rbac User Manager: Contains functionality specific to Users
 *
 * @author abiusx
 * @version 1.0
 */
class UserManager
{
	/**
	 * Checks to see whether a user has a role or not
	 *
	 * @param integer|string $Role
	 *        	id, title or path
	 * @param integer $User
	 *        	UserID, not optional
	 *
	 * @throws RbacUserNotProvidedException
	 * @return boolean success
	 */
	function hasRole($Role, $UserID = null)
	{
            $databaseManager = Rbac::getInstance()->getDatabaseManager();
            $tablePrefix = $databaseManager->getTablePrefix();
            
	    if ($UserID === null)
            {
                throw new RbacUserNotProvidedException('$UserID is a required argument.');
            }
            $RoleID = Rbac::getInstance()
                ->getRbacManager()
                ->getRoleManager()
                ->getId($Role)
            ;

            return(
                $databaseManager->request(
                    "SELECT * FROM {$tablePrefix}userroles AS TUR
                    JOIN {$tablePrefix}roles AS TRdirect ON (TRdirect.ID=TUR.RoleID)
                    JOIN {$tablePrefix}roles AS TR ON (TR.Lft BETWEEN TRdirect.Lft AND TRdirect.Rght)
                    WHERE TUR.UserID=? AND TR.ID=?"
                , $UserID, $RoleID)
            !== null);
	}

	/**
	 * Assigns a role to a user
	 *
	 * @param mixed $Role
	 *        	Id, Path or Title
	 * @param integer $UserID
	 *        	UserID (use 0 for guest)
	 *
	 * @throws RbacUserNotProvidedException
	 * @return boolean inserted or existing
	 */
	function assign($Role, $UserID = null)
	{
            $databaseManager = Rbac::getInstance()->getDatabaseManager();
            
	    if ($UserID === null)
            {
                throw new RbacUserNotProvidedException('$UserID is a required argument.');
            }  
            $RoleID = Rbac::getInstance()
                ->getRbacManager()
                ->getRoleManager()
                ->getId($Role)
            ;

            return $databaseManager->request(
                'INSERT INTO ' . $databaseManager->getTablePrefix() . 'userroles
                (UserID,RoleID,AssignmentDate) VALUES (?,?,?)'
            , $UserID, $RoleID, time()) >= 1;
	}

	/**
	 * Unassigns a role from a user
	 *
	 * @param mixed $Role
	 *        	Id, Title, Path
	 * @param integer $UserID
	 *        	UserID (use 0 for guest)
	 *
	 * @throws RbacUserNotProvidedException
	 * @return boolean success
	 */
	function unassign($Role, $UserID = null)
	{
            $databaseManager = Rbac::getInstance()->getDatabaseManager();
            
	    if ($UserID === null)
            {
                throw new RbacUserNotProvidedException('$UserID is a required argument.');
            }
            $RoleID = Rbac::getInstance()
                ->getRbacManager()
                ->getRoleManager()
                ->getId($Role)
            ;
            
	    return $databaseManager->request(
                'DELETE FROM ' . $databaseManager->getTablePrefix() . 'userroles'
                . ' WHERE UserID=? AND RoleID=?'
            , $UserID, $RoleID) >= 1;
	}

	/**
	 * Returns all roles of a user
	 *
	 * @param integer $UserID
	 *        	Not optional
	 *
	 * @throws RbacUserNotProvidedException
	 * @return array null
	 *
	 */
	function allRoles($UserID = null)
	{
            $databaseManager = Rbac::getInstance()->getDatabaseManager();
            $tablePrefix = $databaseManager->getTablePrefix();
            
            if ($UserID === null)
            {
                throw new RbacUserNotProvidedException('$UserID is a required argument.');
            }

            return $databaseManager->request(
                "SELECT TR.* FROM {$tablePrefix}userroles AS `TRel`
                JOIN {$tablePrefix}roles AS `TR` ON
                (`TRel`.RoleID=`TR`.ID) WHERE TRel.UserID=?"
            , $UserID);
	}

	/**
	 * Return count of roles assigned to a user
	 *
	 * @param integer $UserID
	 *
	 * @throws RbacUserNotProvidedException
	 * @return integer Count of Roles assigned to a User
	 */
	function roleCount($UserID = null)
	{
            $databaseManager = Rbac::getInstance()->getDatabaseManager();
            
            if($UserID === null)
            {
                throw new RbacUserNotProvidedException('$UserID is a required argument.');
            }
            $Res = $databaseManager->request(
                'SELECT COUNT(*) AS Result FROM ' . $databaseManager->getTablePrefix()
                . 'userroles WHERE UserID=?'
            , $UserID);
            return (int) $Res[0]['Result'];
	}

	/**
	 * Remove all role-user relations
	 * mostly used for testing
	 *
	 * @param boolean $Ensure
	 *        	must set to true or throws an Exception
	 * @return number of deleted relations
	 */
	function resetAssignments($Ensure = false)
	{
            $databaseManager = Rbac::getInstance()->getDatabaseManager();
            $tablePrefix = $databaseManager->getTablePrefix();
            
            if($Ensure !== true)
            {
                throw new \Exception ("You must pass true to this function, otherwise it won't work.");
                return;
            }
            
            $res = $databaseManager->request("DELETE FROM {$tablePrefix}userroles");

            $Adapter = get_class($databaseManager->getConnection());
            if($databaseManager->isMySql())
            {
                $databaseManager->request("ALTER TABLE {$tablePrefix}userroles AUTO_INCREMENT = 1");
            }    
            elseif ($databaseManager->isSQLite())
            {
                $databaseManager->request('delete from sqlite_sequence where name=? ', "{$tablePrefix}_userroles");
            } 
            else
            {
                throw new \Exception("Rbac can not reset table on this type of database: {$Adapter}");
            }
            $this->assign('root', 1);
            return $res;
	}
}
