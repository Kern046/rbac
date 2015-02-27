<?php
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
}