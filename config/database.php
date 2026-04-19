<?php
/**
 * CodeVault - 数据库配置
 * PHP 原生实现，无框架依赖
 */

return [
    'host'      => getenv('DB_HOST') ?: 'localhost',
    'port'      => getenv('DB_PORT') ?: 3306,
    'dbname'    => getenv('DB_NAME') ?: 'codevault',
    'username'  => getenv('DB_USER') ?: 'root',
    'password'  => getenv('DB_PASS') ?: '',
    'charset'   => 'utf8mb4',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ],
];
