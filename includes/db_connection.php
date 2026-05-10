<?php

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'hospital_system';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_errno) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

