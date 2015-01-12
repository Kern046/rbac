<?php
namespace PhpRbac\Manager;

use PhpRbac\Database\JModel;
use PhpRbac\Database\Jf;
/**
 * Rbac base class, it contains operations that are essentially the same for
 * permissions and roles
 * and is inherited by both
 *
 * @author abiusx
 * @version 1.0
 */
abstract class BaseRbacManager extends JModel
{

	function rootId()
	{
		return 1;
	}

	/**
	 * Return type of current instance, e.g roles, permissions
	 *
	 * @return string
	 */
	abstract protected function type();

	/**
	 * Adds a new role or permission
	 * Returns new entry's ID
	 *
	 * @param string $Title
	 *        	Title of the new entry
	 * @param string $Description
	 *        	Description of the new entry
	 * @param integer $ParentID
	 *        	optional ID of the parent node in the hierarchy
	 * @return integer ID of the new entry
	 */
	function add($Title, $Description, $ParentID = null)
	{
            die('ok');
		if ($ParentID === null)
			$ParentID = $this->rootId ();
		return (int)$this->{$this->type ()}->insertChildData ( array ("Title" => $Title, "Description" => $Description ), "ID=?", $ParentID );
	}

	/**
	 * Adds a path and all its components.
	 * Will not replace or create siblings if a component exists.
	 *
	 * @param string $Path
	 *        	such as /some/role/some/where - Must begin with a / (slash)
	 * @param array $Descriptions
	 *        	array of descriptions (will add with empty description if not available)
	 *
	 * @return integer Number of nodes created (0 if none created)
	 */
	function addPath($Path, array $Descriptions = null)
	{
	    if ($Path[0] !== "/")
	        throw new \Exception ("The path supplied is not valid.");

	    $Path = substr ( $Path, 1 );
	    $Parts = explode ( "/", $Path );
	    $Parent = 1;
	    $index = 0;
	    $CurrentPath = "";
	    $NodesCreated = 0;

	    foreach ($Parts as $p)
	    {
	        if (isset ($Descriptions[$index]))
	            $Description = $Descriptions[$index];
	        else
	            $Description = "";
	        $CurrentPath .= "/{$p}";
	        $t = $this->pathId($CurrentPath);
	        if (! $t)
	        {
	            $IID = $this->add($p, $Description, $Parent);
	            $Parent = $IID;
	            $NodesCreated++;
	        }
	        else
	        {
	            $Parent = $t;
	        }

	        $index += 1;
	    }

	    return (int)$NodesCreated;
	}

	/**
	 * Return count of the entity
	 *
	 * @return integer
	 */
	function count()
	{
		$Res = Jf::sql ( "SELECT COUNT(*) FROM " . Jf::getConfig('table_prefix') . "{$this->type()}" );
		return (int)$Res [0] ['COUNT(*)'];
	}

	/**
	 * Returns ID of entity
	 *
	 * @param string $entity (Path or Title)
	 *
	 * @return mixed ID of entity or null
	 */
	public function returnId($entity = null)
	{
	    if (substr ($entity, 0, 1) == "/") {
	        $entityID = $this->pathId($entity);
	    } else {
	        $entityID = $this->titleId($entity);
	    }

	    return $entityID;
	}

	/**
	 * Returns ID of a path
	 *
	 * @todo this has a limit of 1000 characters on $Path
	 * @param string $Path
	 *        	such as /role1/role2/role3 ( a single slash is root)
	 * @return integer NULL
	 */
	public function pathId($Path)
	{
		$Path = "root" . $Path;

		if ($Path [strlen ( $Path ) - 1] == "/")
			$Path = substr ( $Path, 0, strlen ( $Path ) - 1 );
		$Parts = explode ( "/", $Path );

		$Adapter = get_class(Jf::$Db);
		if ($Adapter == "mysqli" or ($Adapter == "PDO" and Jf::$Db->getAttribute(PDO::ATTR_DRIVER_NAME)=="mysql")) {
			$GroupConcat="GROUP_CONCAT(parent.Title ORDER BY parent.Lft SEPARATOR '/')";
		} elseif ($Adapter == "PDO" and Jf::$Db->getAttribute(PDO::ATTR_DRIVER_NAME)=="sqlite") {
			$GroupConcat="GROUP_CONCAT(parent.Title,'/')";
		} else {
			throw new \Exception ("Unknown Group_Concat on this type of database: {$Adapter}");
		}

                $tablePrefix = Jf::getConfig('table_prefix');
		$res = Jf::sql ( "SELECT node.ID,{$GroupConcat} AS Path
				FROM {$tablePrefix}{$this->type()} AS node,
				{$tablePrefix}{$this->type()} AS parent
				WHERE node.Lft BETWEEN parent.Lft AND parent.Rght
				AND  node.Title=?
				GROUP BY node.ID
				HAVING Path = ?
				", $Parts [count ( $Parts ) - 1], $Path );

		if ($res)
			return $res [0] ['ID'];
		else
			return null;
			// TODO: make the below SQL work, so that 1024 limit is over

		$QueryBase = ("SELECT n0.ID  \nFROM {$tablePrefix}{$this->type()} AS n0");
		$QueryCondition = "\nWHERE 	n0.Title=?";

		for($i = 1; $i < count ( $Parts ); ++ $i)
		{
			$j = $i - 1;
			$QueryBase .= "\nJOIN 		{$tablePrefix}{$this->type()} AS n{$i} ON (n{$j}.Lft BETWEEN n{$i}.Lft+1 AND n{$i}.Rght)";
			$QueryCondition .= "\nAND 	n{$i}.Title=?";
			// Forcing middle elements
			$QueryBase .= "\nLEFT JOIN 	{$tablePrefix}{$this->type()} AS nn{$i} ON (nn{$i}.Lft BETWEEN n{$i}.Lft+1 AND n{$j}.Lft-1)";
			$QueryCondition .= "\nAND 	nn{$i}.Lft IS NULL";
		}
		$Query = $QueryBase . $QueryCondition;
		$PartsRev = array_reverse ( $Parts );
		array_unshift ( $PartsRev, $Query );

		print_ ( $PartsRev );
		$res = call_user_func_array ( "Jf::sql", $PartsRev );

		if ($res)
			return $res [0] ['ID'];
		else
			return null;
	}

	/**
	 * Returns ID belonging to a title, and the first one on that
	 *
	 * @param string $Title
	 * @return integer Id of specified Title
	 */
	public function titleId($Title)
	{
		return $this->{$this->type ()}->getID ( "Title=?", $Title );
	}

	/**
	 * Return the whole record of a single entry (including Rght and Lft fields)
	 *
	 * @param integer $ID
	 */
	protected function getRecord($ID)
	{
		$args = func_get_args ();
		return call_user_func_array ( array ($this->{$this->type ()}, "getRecord" ), $args );
	}

	/**
	 * Returns title of entity
	 *
	 * @param integer $ID
	 * @return string NULL
	 */
	function getTitle($ID)
	{
		$r = $this->getRecord ( "ID=?", $ID );
		if ($r)
			return $r ['Title'];
		else
			return null;
	}

	/**
	 * Returns path of a node
	 *
	 * @param integer $ID
	 * @return string path
	 */
	function getPath($ID)
	{
	    $res = $this->{$this->type ()}->pathConditional ( "ID=?", $ID );
	    $out = null;
	    if (is_array ( $res ))
	        foreach ( $res as $r )
	            if ($r ['ID'] == 1)
	                $out = '/';
	            else
	                $out .= "/" . $r ['Title'];
	            if (strlen ( $out ) > 1)
	                return substr ( $out, 1 );
	            else
	                return $out;
	}

	/**
	 * Return description of entity
	 *
	 * @param integer $ID
	 * @return string NULL
	 */
	function getDescription($ID)
	{
	    $r = $this->getRecord ( "ID=?", $ID );
	    if ($r)
	        return $r ['Description'];
	    else
	        return null;
	}

	/**
	 * Edits an entity, changing title and/or description. Maintains Id.
	 *
	 * @param integer $ID
	 * @param string $NewTitle
	 * @param string $NewDescription
	 *
	 */
	function edit($ID, $NewTitle = null, $NewDescription = null)
	{
		$Data = array ();

		if ($NewTitle !== null)
			$Data ['Title'] = $NewTitle;

		if ($NewDescription !== null)
			$Data ['Description'] = $NewDescription;

        return $this->{$this->type ()}->editData ( $Data, "ID=?", $ID ) == 1;
	}

	/**
	 * Returns children of an entity
	 *
	 * @param integer $ID
	 * @return array
	 *
	 */
	function children($ID)
	{
		return $this->{$this->type ()}->childrenConditional ( "ID=?", $ID );
	}

	/**
	 * Returns descendants of a node, with their depths in integer
	 *
	 * @param integer $ID
	 * @return array with keys as titles and Title,ID, Depth and Description
	 *
	 */
	function descendants($ID)
	{
		$res = $this->{$this->type ()}->descendantsConditional(/* absolute depths*/false, "ID=?", $ID );
		$out = array ();
		if (is_array ( $res ))
			foreach ( $res as $v )
				$out [$v ['Title']] = $v;
		return $out;
	}

	/**
	 * Return depth of a node
	 *
	 * @param integer $ID
	 */
	function depth($ID)
	{
		return $this->{$this->type ()}->depthConditional ( "ID=?", $ID );
	}

	/**
	 * Returns parent of a node
	 *
	 * @param integer $ID
	 * @return array including Title, Description and ID
	 *
	 */
	function parentNode($ID)
	{
		return $this->{$this->type ()}->parentNodeConditional ( "ID=?", $ID );
	}

	/**
	 * Reset the table back to its initial state
	 * Keep in mind that this will not touch relations
	 *
	 * @param boolean $Ensure
	 *        	must be true to work, otherwise an \Exception is thrown
	 * @throws \Exception
	 * @return integer number of deleted entries
	 *
	 */
	function reset($Ensure = false)
	{
            $tablePrefix = Jf::getConfig('table_prefix');
		if ($Ensure !== true)
		{
			throw new \Exception ("You must pass true to this function, otherwise it won't work.");
			return;
		}
		$res = Jf::sql ( "DELETE FROM {$tablePrefix}{$this->type()}" );
		$Adapter = get_class(Jf::$Db);
		if ($this->isMySql())
			Jf::sql ( "ALTER TABLE {$tablePrefix}{$this->type()} AUTO_INCREMENT=1 " );
		elseif ($this->isSQLite())
                        Jf::sql ( "delete from sqlite_sequence where name=? ", "{$tablePrefix}{$this->type()}" );
		else
			throw new \Exception ( "Rbac can not reset table on this type of database: {$Adapter}" );
		$iid = Jf::sql ( "INSERT INTO {$tablePrefix}{$this->type()} (Title,Description,Lft,Rght) VALUES (?,?,?,?)", "root", "root",0,1 );
		return (int)$res;
	}

	/**
	 * Assigns a role to a permission (or vice-verse)
	 *
	 * @param mixed $Role
	 *         Id, Title and Path
	 * @param mixed $Permission
	 *         Id, Title and Path
	 * @return boolean inserted or existing
	 *
	 * @todo: Check for valid permissions/roles
	 * @todo: Implement custom error handler
	 */
	function assign($Role, $Permission)
	{
	    if (is_numeric($Role))
	    {
	        $RoleID = $Role;
	    } else {
	        if (substr($Role, 0, 1) == "/")
	            $RoleID = Jf::$Rbac->Roles->pathId($Role);
	        else
	            $RoleID = Jf::$Rbac->Roles->titleId($Role);
	    }

	    if (is_numeric($Permission))
	    {
	        $PermissionID = $Permission;
	    }  else {
	        if (substr($Permission, 0, 1) == "/")
	            $PermissionID = Jf::$Rbac->Permissions->pathId($Permission);
	        else
	            $PermissionID = Jf::$Rbac->Permissions->titleId($Permission);
	    }

	    return Jf::sql('INSERT INTO ' . Jf::getConfig('table_prefix') . 'rolepermissions
	        (RoleID,PermissionID,AssignmentDate)
	        VALUES (?,?,?)', $RoleID, $PermissionID, Jf::time()) >= 1;
	}

	/**
	 * Unassigns a role-permission relation
	 *
	 * @param mixed $Role
	 *         Id, Title and Path
	 * @param mixed $Permission:
	 *         Id, Title and Path
	 * @return boolean
	 */
	function unassign($Role, $Permission)
	{
	    if (is_numeric($Role))
	    {
	        $RoleID = $Role;
	    }  else {
	        if (substr($Role, 0, 1) == "/")
	            $RoleID = Jf::$Rbac->Roles->pathId($Role);
	        else
	            $RoleID = Jf::$Rbac->Roles->titleId($Role);
	    }

	    if (is_numeric($Permission))
	    {
	        $PermissionID = $Permission;
	    }  else {
	        if (substr($Permission, 0, 1) == "/")
	            $PermissionID = Jf::$Rbac->Permissions->pathId($Permission);
	        else
	            $PermissionID = Jf::$Rbac->Permissions->titleId($Permission);
	    }

		return Jf::sql('DELETE FROM ' . Jf::getConfig('table_prefix') . 'rolepermissions WHERE
		    RoleID=? AND PermissionID=?', $RoleID, $PermissionID) == 1;
	}

	/**
	 * Remove all role-permission relations
	 * mostly used for testing
	 *
	 * @param boolean $Ensure
	 *        	must be set to true or throws an \Exception
	 * @return number of deleted assignments
	 */
	function resetAssignments($Ensure = false)
	{
		if ($Ensure !== true)
		{
			throw new \Exception ("You must pass true to this function, otherwise it won't work.");
			return;
		}
                $tablePrefix = Jf::getConfig('table_prefix');
		$res = Jf::sql ( "DELETE FROM {$tablePrefix}rolepermissions" );

		$Adapter = get_class(Jf::$Db);
		if ($this->isMySql())
			Jf::sql ( "ALTER TABLE {$tablePrefix}rolepermissions AUTO_INCREMENT =1 " );
		elseif ($this->isSQLite())
			Jf::sql ( "delete from sqlite_sequence where name=? ", "{$tablePrefix}_rolepermissions" );
		else
			throw new \Exception ( "Rbac can not reset table on this type of database: {$Adapter}" );
		$this->assign ( $this->rootId(), $this->rootId());
		return $res;
	}
}