<?php
/*
  Database configuration for the PHP menu application. Adjust these
  constants to match your MySQL server credentials. After creating
  the database (see schema.sql), ensure $dbname corresponds to the
  created database.
*/

$host = 'localhost';
$dbname = 'menu_db';
$dbuser = 'root';
$dbpass = '';

// Create a new MySQLi connection
$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

// Check for connection errors
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>