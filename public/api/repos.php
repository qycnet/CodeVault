<?php
/**
 * CodeVault - API 入口：仓库管理
 * 路由: /api/repos.php?action=xxx
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Repository.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$auth = new Auth();
$repo = new Repository();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList($auth, $repo);
            break;

        case 'create':
            handleCreate($auth, $repo);
            break;

        case 'get':
            handleGet($repo);
            break;

        case 'update':
            handleUpdate($auth, $repo);
            break;

        case 'delete':
            handleDelete($auth, $repo);
            break;

        default:
            errorResponse('无效的操作', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    errorResponse($e->getMessage(), $code);
}

// ==================== 处理函数 ====================

function handleList(Auth $auth, Repository $repo): void
{
    $user = $auth->requireLogin();
    $repos = $repo->getUserRepos($user['id']);
    successResponse(['repos' => $repos]);
}

function handleCreate(Auth $auth, Repository $repo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 POST 请求', 405);
    }

    $user = $auth->requireLogin();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $isPrivate = !empty($data['is_private']);

    $result = $repo->create($user['id'], $name, $description, $isPrivate);
    successResponse(['repo' => $result]);
}

function handleGet(Repository $repo): void
{
    $repoId = (int)($_GET['id'] ?? 0);
    
    if ($repoId <= 0) {
        errorResponse('无效的仓库 ID');
    }

    $result = $repo->get($repoId);
    if (!$result) {
        errorResponse('仓库不存在', 404);
    }

    successResponse(['repo' => $result]);
}

function handleUpdate(Auth $auth, Repository $repo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 PUT/POST 请求', 405);
    }

    $user = $auth->requireLogin();
    $repoId = (int)($_GET['id'] ?? 0);
    
    if ($repoId <= 0) {
        errorResponse('无效的仓库 ID');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $result = $repo->update($user['id'], $repoId, $data);
    successResponse(['repo' => $result]);
}

function handleDelete(Auth $auth, Repository $repo): void
{
    $user = $auth->requireLogin();
    $repoId = (int)($_GET['id'] ?? 0);
    
    if ($repoId <= 0) {
        errorResponse('无效的仓库 ID');
    }

    $repo->delete($user['id'], $repoId);
    successResponse(['message' => '已删除']);
}
