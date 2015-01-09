<?php
interface NestedSetInterface
{
    public function insertChild($PID=0);
    public function insertSibling($ID=0);
    
    function deleteSubtree($ID);
    function delete($ID);
    
    //function Move($ID,$NewPID);
    //function Copy($ID,$NewPID);
    
    function fullTree();
    function children($ID);
    function descendants($ID,$AbsoluteDepths=false);
    function leaves($PID=null);
    function path($ID);
    
    function depth($ID);
    function parentNode($ID);
    function sibling($ID,$SiblingDistance=1);
}