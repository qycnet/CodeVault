<?php
/**
 * CodeVault - 应用配置
 */

return [
    'app_name'      => 'CodeVault',
    'app_version'   => '1.0.0',
    'debug'         => getenv('APP_DEBUG') === 'true',
    'base_url'      => getenv('APP_URL') ?: 'http://localhost',
    
    // Session 配置
    'session'       => [
        'name'          => 'codevault_session',
        'lifetime'      => 86400, // 24小时
        'cookie_path'   => '/',
        'cookie_secure' => false, // 生产环境改为 true
        'cookie_httponly' => true,
    ],
    
    // Git 仓库路径
    'git'           => [
        'repositories_path' => '/var/git/repositories/',
    ],
    
    // 邮箱验证码配置
    'verification'  => [
        'code_length'   => 6,
        'expire_time'   => 300, // 5分钟
    ],
];
