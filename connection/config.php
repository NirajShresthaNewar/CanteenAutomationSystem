<?php
$host = 'localhost';
$mysql_user = 'root';
$mysql_pwd = '';
$dbms = 'cas';

$con = mysqli_connect($host, $mysql_user, $mysql_pwd, $dbms);

if (!$con) {
    die('Could not connect: ' . mysqli_connect_error());
}
?>