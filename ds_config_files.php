<?php
    
    function ds_config($str) {
        $config = (object)[];
        $clientId = getenv("DS_CLIENT_ID");
        if (!is_null($clientId) and !empty($clientId)) {
            $config[$str] = getenv($str);
        } 
        else {
            $config = parse_ini_file('ds_config.ini', true);
        }
        return $config[$str];
    }


