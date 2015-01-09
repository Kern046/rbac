<?php

class JModel
{
    function tablePrefix()
    {
        return Jf::tablePrefix();
    }

    protected function isSQLite()
    {
        $Adapter=get_class(Jf::$Db);
        return $Adapter == "PDO" and Jf::$Db->getAttribute(PDO::ATTR_DRIVER_NAME)=="sqlite";
    }
    
    protected function isMySql()
    {
        $Adapter=get_class(Jf::$Db);
        return $Adapter == "mysqli" or ($Adapter == "PDO" and Jf::$Db->getAttribute(PDO::ATTR_DRIVER_NAME)=="mysql");
    }
}