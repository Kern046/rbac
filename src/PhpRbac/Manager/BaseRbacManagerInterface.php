<?php

namespace PhpRbac\Manager;
/* 
 * @name BaseRbacManagerInterface
 * @author Axel Venet <axel-venet@developtech.fr>
 */
interface BaseRbacManagerInterface
{
    /**
     * Adds a new role or permission
     * Returns new entry's ID
     *
     * @param string $title
     * @param string $description
     * @param integer $parentId
     * @return integer
     */
    public function add($title, $description, $parentId = null);

    /**
     * Adds a path and all its components.
     * Will not replace or create siblings if a component exists.
     * $path is such as /some/role/some/where - Must begin with a / (slash)
     * $descriptions is an array of descriptions (will add with empty description if not available)
     * Return the number of nodes created (0 if none created)
     * 
     * @param string $path
     * @param array $descriptions
     * @return integer
     */
    public function addPath($path, array $descriptions = null);
    
    /**
     * Return count of the entity
     *
     * @return integer
     */
    public function count();
    
    /**
     * Get ID from a path or a title
     * 
     * @param string $item
     * @return integer
     */
    public function getId($item);
    
    /**
     * Returns ID of entity
     * $entity can be a path or a title
     * This method returns the given entity's id or null
     *
     * @param string $entity
     * @return mixed
     */
    public function returnId($entity = null);

    /**
     * Returns ID of a path
     * $path such as /role1/role2/role3 ( a single slash is root)
     *
     * @todo this has a limit of 1000 characters on $Path
     * @param string $path
     * @return integer
     */
    public function pathId($path);
    
    /**
     * Returns ID belonging to a title, and the first one on that
     *
     * @param string $title
     * @return integer
     */
    public function titleId($title);
    
    /**
     * Return the whole record of a single entry (including Rght and Lft fields)
     *
     * @param integer $id
     */
    public function getRecord($id);
    
    /**
     * Returns title of entity
     *
     * @param integer $id
     * @return string NULL
     */
    public function getTitle($id);
    
    /**
     * Returns path of a node
     *
     * @param integer $id
     * @return string
     */
    public function getPath($id);

    /**
     * Return description of entity
     *
     * @param integer $id
     * @return string
     */
    public function getDescription($id);
    
    /**
     * Edits an entity, changing title and/or description. Maintains Id.
     *
     * @param integer $id
     * @param string $newTitle
     * @param string $newDescription
     */
    public function edit($id, $newTitle = null, $newDescription = null);
    
    /**
     * Returns children of an entity
     *
     * @param integer $id
     * @return array
     */
    public function children($id);
    
    /**
     * Returns descendants of a node, with 
     * keys as titles and Title,ID, Depth and Description
     * 
     * @param integer $id
     * @return array
     */
    public function descendants($id);
    
    /**
     * Return depth of a node
     *
     * @param integer $id
     */
    public function depth($id);
    
    /**
     * Returns parent of a node including Title, Description and ID
     *
     * @param integer $id
     * @return array
     */
    public function parentNode($id);

    /**
     * Reset the table back to its initial state
     * Keep in mind that this will not touch relations
     * $ensure must be true to work, otherwise an \Exception is thrown
     * This method returns the number of deleted entries
     * 
     * @param boolean $ensure
     * @throws \Exception
     * @return integer
     */
    public function reset($ensure = false);
    
    /**
     * Remove roles or permissions from system
     * If $recursive is set to true, it deletes all descendants
     *
     * @param integer $id
     * @param boolean $recursive
     * @return boolean
     */
    public function remove($id, $recursive = false);
    
    /**
     * Assigns a role to a permission (or vice-verse)
     * $role can be an id, title or path
     * $permission can be an id, title or path
     * 
     * @param mixed $role
     * @param mixed $permission
     * @return boolean inserted or existing
     *
     * @todo: Check for valid permissions/roles
     * @todo: Implement custom error handler
     */
    public function assign($role, $permission);

    /**
     * Unassigns a role-permission relation
     * $role can be an id, title or path
     * $permission can be an id, title or path
     *
     * @param mixed $role
     * @param mixed $permission
     * @return boolean
     */
    public function unassign($role, $permission);
    
    /**
     * Remove all role-permission relations
     * mostly used for testing
     * $ensure must be set to true or throws an \Exception
     * This method returns the number of deleted assignments
     *
     * @param boolean $ensure
     * @return integer
     */
    public function resetAssignments($ensure = false);
}