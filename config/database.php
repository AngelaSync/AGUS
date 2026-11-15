<?php
/**
 * Database connection. Pick the driver in .env:
 *   DB_DRIVER=mysql   for XAMPP / MariaDB (the usual choice)
 *   DB_DRIVER=sqlite  for a quick start with no database server
 */

return [
    'driver' => env('DB_DRIVER', 'mysql'),

    'mysql' => [
        // DB_* comes from .env (XAMPP). On Railway, its MySQL service provides MYSQL* instead.
        'host' => env('DB_HOST', env('MYSQLHOST', '127.0.0.1')),
        'port' => env('DB_PORT', env('MYSQLPORT', '3306')),
        'database' => env('DB_DATABASE', env('MYSQLDATABASE', 'agus')),
        'username' => env('DB_USERNAME', env('MYSQLUSER', 'root')),
        'password' => env('DB_PASSWORD', env('MYSQLPASSWORD', '')),
        'charset' => 'utf8mb4',
    ],

    'sqlite' => [
        'database' => BASE_PATH . '/' . env('DB_SQLITE_PATH', 'storage/database.sqlite'),
    ],
];
