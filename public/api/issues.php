<?php
/**
 * CodeVault - API 入口：Issue 管理
 * 路由: /api/issues.php?action=xxx
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Issue.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$auth = new Auth();
$issue = new Issue();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList($issue);
            break;

        case 'create':
            handleCreate($auth, $issue);
            break;

        case 'get':
            handleGet($issue);
            break;

        case 'update':
            handleUpdate($auth, $issue);
            break;

        case 'close':
        case 'reopen':
            handleStatus($auth, $issue, $action);
            break;

        case 'delete':
            handleDelete($auth, $issue);
            break;

        case 'count':
            handleCount($issue);
            break;

        default:
            errorResponse('无效的操作', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    errorResponse($e->getMessage(), $code);
}

// ==================== 处理函数 ====================

function handleList(Issue $issue): void
{
    $repoId = (int)($_GET['repo_id'] ?? 0);
    
    if ($repoId <= 0) {
        errorResponse('无效的仓库 ID');
    }

    $status = $_GET['status'] ?? null;
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);

    $issues = $issue->getRepoIssues($repoId, $status, $limit, $offset);
    successResponse(['issues' => $issues]);
}

function handleCreate(Auth $auth, Issue $issue): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 POST 请求', 405);
    }

    $user = $auth->requireLogin();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $repoId = (int)($data['repo_id'] ?? 0);
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';

    if ($repoId <= 0) {
        errorResponse('无效的仓库 ID');
    }

    $result = $issue->create($repoId, $user['id'], $title, $content);
    successResponse(['issue' => $result]);
}

function handleGet(Issue $issue): void
{
    $issueId = (int)($_GET['id'] ?? 0);
    
    if ($issueId <= 0) {
        errorResponse('无效的 Issue ID');
    }

    $result = $issue->get($issueId);
    if (!$result) {
        errorResponse('Issue 不存在', 404);
    }

    successResponse(['issue' => $result]);
}

function handleUpdate(Auth $auth, Issue $issue): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('仅支持 PUT/POST 请求', 405);
    }

    $user = $auth->requireLogin();
    $issueId = (int)($_GET['id'] ?? 0);
    
    if ($issueId <= 0) {
        errorResponse('无效的 Issue ID');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        errorResponse('无效的 JSON 数据');
    }

    $result = $issue->update($issueId, $user['id'], $data);
    successResponse(['issue' => $result]);
}

function handleStatus(Auth $auth, Issue $issue, string $action): void
{
    $user = $auth->requireLogin();
    $issueId = (int)($_GET['id'] ?? 0);
    
    if ($issueId <= 0) {
        errorResponse('无效的 Issue ID');
    }

    $status = $action === 'close' ? 'closed' : 'open';
    $result = $issue->updateStatus($issueId, $status);
    successResponse(['issue' => $result]);
}

function handleDelete(Auth $auth, Issue $issue): void
{
    $user = $auth->requireLogin();
    $issueId = (int)($_GET['id'] ?? 0);
    
    if ($issueId <= 0) {
        errorResponse('无效的 Issue ID');
    }

    $issue->delete($issueId, $user['id']);
    successResponse(['message' => '已删除']);
}

function handleCount(Issue $issue): void
{
    $repoId = (int)($_GET['repo_id'] ?? 0);
    
    if ($repoId <= 0) {
        errorResponse('无效的仓库 ID');
    }

    $count = $issue->countByRepo($repoId);
    successResponse(['count' => $count]);
}
