<?php

// read ADMINER_PASSWORD from .env
$password = file_get_contents(__DIR__ . '/../../.env');
$password = explode("\n", $password);
$password = array_map('trim', $password);
$password = array_filter($password, function ($value) {
    return strpos($value, 'ADMINER_PASSWORD') === 0;
});
$password = array_pop($password);

if ($password === null) {
    header('HTTP/1.1 500 Internal Server Error');
    die('ADMINER_PASSWORD not found in .env');
}

$password = explode('=', $password);
$password = array_pop($password);

// basic authentication
if ($_SERVER['PHP_AUTH_USER'] != 'adminer' || $_SERVER['PHP_AUTH_PW'] != $password) {
    header('WWW-Authenticate: Basic realm="Adminer"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

function adminer_object() {
    // Required to run any plugin.
    include_once(__DIR__ . "/plugins/plugin.php");

    // Load plugins.
    foreach (glob(__DIR__ . "/plugins/*.php") as $file) {
        include_once($file);
    }

    $plugins = [
        new AdminerLoginSqlite()
    ];

    return new AdminerPlugin($plugins);
}

// Include original Adminer or Adminer Editor.
include "./adminer.php";
