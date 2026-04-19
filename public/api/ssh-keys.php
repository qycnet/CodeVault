<?php
/**
 * CodeVault - API 入口：SSH Key 管理
 * 路由: /api/ssh-keys.php?action=xxx
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SSHKey.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$auth = new Auth();
$sshKey = new SSHKey();
$action = $_GET['action'] ?? '';

try {
    // 所有操作需要登录
    $user = $auth->requireLogin();
    $userId = $user['id'];

    switch ($action) {
        case 'list':
            handleList($sshKey, $userId);
            break;

        case 'add':
            handleAdd($sshKey, $userId);
            break;

        case 'delete':
            handleDelete($sshKey, $userId);
            break;

        default:
            errorResponse('无效的操作', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    errorResponse($e->getMessage(), $code);
}

// ==================== 处理函数 ====================

function handleList(SSHKey $sshKey, int $userId): void
{
    $keys = $sshKey->getUserKeys($userId);
    successResponse(['keys' => $keys]);
}

function handleAdd(SSHKey $sshKey, int $userId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 POST 请求', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $title = $data['title'] ?? '';
    $publicKey = $data['public_key'] ?? '';

    $key = $sshKey->addKey($userId, $title, $publicKey);
    successResponse(['key' => $key]);
}

function handleDelete(SSHKey $sshKey, int $userId): void
{
    $keyId = (int)($_GET['id'] ?? 0);
    
    if ($keyId <= 0) {
        errorResponse('无效的 Key ID');
    }

    $sshKey->deleteKey($userId, $keyId);
    successResponse(['message' => '已删除']);
}
