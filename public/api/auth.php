<?php
/**
 * CodeVault - API 入口：用户认证
 * 路由: /api/auth.php?action=xxx
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

// CORS 头（开发环境）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$auth = new Auth();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'register':
            handleRegister($auth);
            break;

        case 'login':
            handleLogin($auth);
            break;

        case 'logout':
            handleLogout($auth);
            break;

        case 'me':
            handleMe($auth);
            break;

        default:
            errorResponse('无效的操作', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    errorResponse($e->getMessage(), $code);
}

// ==================== 处理函数 ====================

function handleRegister(Auth $auth): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 POST 请求', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    $user = $auth->register($username, $email, $password);
    successResponse(['user' => $user]);
}

function handleLogin(Auth $auth): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 POST 请求', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    $user = $auth->login($username, $password);
    successResponse(['user' => $user]);
}

function handleLogout(Auth $auth): void
{
    $auth->logout();
    successResponse(['message' => '已登出']);
}

function handleMe(Auth $auth): void
{
    $user = $auth->requireLogin();
    successResponse(['user' => $user]);
}
