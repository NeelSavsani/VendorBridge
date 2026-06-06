<?php

require_once 'config/config.php';

try {

    $db = getDB();

    echo "Database Connected";

} catch (Exception $e) {

    echo $e->getMessage();
}