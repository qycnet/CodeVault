<?php
/**
 * CodeVault - API 入口文件
 */

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 自动加载
spl_autoload_register(function ($class) {
    $prefix = 'CodeVault\\';
    $baseDir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 启动Session
use CodeVault\Services\Session;
Session::start();

// CORS 头 - 限制特定域名
$allowedOrigins = [
    'http://localhost',
    'http://localhost:8000',
    'http://127.0.0.1',
    'http://127.0.0.1:8000',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');

// 简单路由
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// 路由表
$routes = [
    'POST /api/auth/send-code' => ['AuthController', 'sendRegisterCode'],
    'POST /api/auth/register' => ['AuthController', 'register'],
    'POST /api/auth/login' => ['AuthController', 'login'],
    'POST /api/auth/logout' => ['AuthController', 'logout'],
    'GET /api/auth/me' => ['AuthController', 'me'],
    
    'GET /api/ssh-keys' => ['SshKeyController', 'list'],
    'POST /api/ssh-keys' => ['SshKeyController', 'add'],
    'DELETE /api/ssh-keys' => ['SshKeyController', 'delete'],
    'GET /api/ssh-keys/detail' => ['SshKeyController', 'detail'],
    
    'GET /api/repos' => ['RepositoryController', 'list'],
    'POST /api/repos' => ['RepositoryController', 'create'],
    'GET /api/repos/detail' => ['RepositoryController', 'detail'],
    'PUT /api/repos' => ['RepositoryController', 'update'],
    'DELETE /api/repos' => ['RepositoryController', 'delete'],
    'GET /api/repos/tree' => ['RepositoryController', 'tree'],
    'GET /api/repos/branches' => ['GitController', 'branches'],
    
    'POST /api/git/clone' => ['GitController', 'clone'],
    'POST /api/git/push' => ['GitController', 'push'],
    'POST /api/git/pull' => ['GitController', 'pull'],
    'GET /api/git/status' => ['GitController', 'status'],
    'GET /api/git/log' => ['GitController', 'log'],
    'GET /api/repos/branches' => ['GitController', 'branches'],
    'GET /api/repos/commits' => ['GitController', 'commits'],
    'GET /api/repos/commit' => ['GitController', 'commit'],
    'GET /api/repos/branch/commit' => ['GitController', 'branchCommit'],
    'POST /api/repos/branch' => ['GitController', 'createBranch'],
    'DELETE /api/repos/branch' => ['GitController', 'deleteBranch'],
    
    'GET /api/issues' => ['IssueController', 'list'],
    'POST /api/issues' => ['IssueController', 'create'],
    'GET /api/issues/detail' => ['IssueController', 'detail'],
    'PUT /api/issues' => ['IssueController', 'update'],
    'POST /api/issues/close' => ['IssueController', 'close'],
    'DELETE /api/issues' => ['IssueController', 'delete'],
    
    'GET /api/pull-requests' => ['PRController', 'list'],
    'POST /api/pull-requests' => ['PRController', 'create'],
    'GET /api/pull-requests/detail' => ['PRController', 'detail'],
    'POST /api/pull-requests/merge' => ['PRController', 'merge'],
    'POST /api/pull-requests/close' => ['PRController', 'close'],
    'POST /api/pull-requests/reopen' => ['PRController', 'reopen'],
    
    'GET /api/comments' => ['CommentController', 'list'],
    'POST /api/comments' => ['CommentController', 'create'],
    'GET /api/comments/detail' => ['CommentController', 'detail'],
    'PUT /api/comments' => ['CommentController', 'update'],
    'DELETE /api/comments' => ['CommentController', 'delete'],
    'GET /api/comments/line' => ['CommentController', 'lineComments'],
    
    // 文件上传 API
    'POST /api/files/upload' => ['FileController', 'upload'],
    'POST /api/files/upload-multiple' => ['FileController', 'uploadMultiple'],
    'DELETE /api/files' => ['FileController', 'delete'],
    'POST /api/files/mkdir' => ['FileController', 'createDirectory'],
    
    // 分支保护 API
    'GET /api/branch-protection' => ['BranchProtectionController', 'list'],
    'GET /api/branch-protection/detail' => ['BranchProtectionController', 'detail'],
    'POST /api/branch-protection' => ['BranchProtectionController', 'create'],
    'PUT /api/branch-protection' => ['BranchProtectionController', 'update'],
    'DELETE /api/branch-protection' => ['BranchProtectionController', 'delete'],
    
    // 邮件通知 API
    'GET /api/notification/settings' => ['NotificationController', 'getSettings'],
    'PUT /api/notification/settings' => ['NotificationController', 'updateSettings'],
    'POST /api/notification/test' => ['NotificationController', 'sendTest'],
    
    // 站内通知 API
    'GET /api/notifications' => ['NotificationController', 'list'],
    'POST /api/notifications/read' => ['NotificationController', 'markRead'],
    'DELETE /api/notifications' => ['NotificationController', 'delete'],
    
    // REST API
    'GET /api' => ['ApiController', 'docs'],
    'GET /api/users' => ['ApiController', 'listUsers'],
    'GET /api/users/detail' => ['ApiController', 'getUser'],
    'GET /api/search/repositories' => ['ApiController', 'searchRepos'],
    'GET /api/search/issues' => ['ApiController', 'searchIssues'],
    'GET /api/search/users' => ['ApiController', 'searchUsers'],
    'GET /api/repos/stargazers' => ['ApiController', 'listStargazers'],
    'POST /api/repos/star' => ['ApiController', 'starRepo'],
    'DELETE /api/repos/star' => ['ApiController', 'unstarRepo'],
    'GET /api/repos/forks' => ['ApiController', 'listForks'],
    'POST /api/repos/fork' => ['ApiController', 'forkRepo'],
    'GET /api/repos/labels' => ['ApiController', 'listLabels'],
    'POST /api/repos/labels' => ['ApiController', 'createLabel'],
    'PUT /api/repos/labels' => ['ApiController', 'updateLabel'],
    'DELETE /api/repos/labels' => ['ApiController', 'deleteLabel'],
    'GET /api/repos/milestones' => ['ApiController', 'listMilestones'],
    'POST /api/repos/milestones' => ['ApiController', 'createMilestone'],
    'PUT /api/repos/milestones' => ['ApiController', 'updateMilestone'],
    'DELETE /api/repos/milestones' => ['ApiController', 'deleteMilestone'],
    'GET /api/orgs' => ['ApiController', 'listOrgs'],
    'POST /api/orgs' => ['ApiController', 'createOrg'],
    
    // Actions API
    'GET /api/actions/workflows' => ['ActionsController', 'listWorkflows'],
    'GET /api/actions/workflows/detail' => ['ActionsController', 'getWorkflow'],
    'POST /api/actions/workflows' => ['ActionsController', 'createWorkflow'],
    'PUT /api/actions/workflows' => ['ActionsController', 'updateWorkflow'],
    'DELETE /api/actions/workflows' => ['ActionsController', 'deleteWorkflow'],
    'GET /api/actions/runs' => ['ActionsController', 'listRuns'],
    'GET /api/actions/runs/detail' => ['ActionsController', 'getRun'],
    'POST /api/actions/trigger' => ['ActionsController', 'triggerWorkflow'],
    'POST /api/actions/rerun' => ['ActionsController', 'rerun'],
    'POST /api/actions/cancel' => ['ActionsController', 'cancelRun'],
    
    // Diff API
    'GET /api/diff/compare' => ['DiffController', 'compare'],
    'GET /api/diff/file' => ['DiffController', 'fileDiff'],
    'GET /api/diff/pr' => ['DiffController', 'prDiff'],
    'GET /api/diff/commit' => ['DiffController', 'commitDiff'],
    'GET /api/diff/content' => ['DiffController', 'fileContent'],
    
    // Review API
    'GET /api/reviews' => ['ReviewController', 'listReviews'],
    'POST /api/reviews' => ['ReviewController', 'createReview'],
    'PUT /api/reviews' => ['ReviewController', 'submitReview'],
    'GET /api/reviews/line-comments' => ['ReviewController', 'listLineComments'],
    'POST /api/reviews/line-comments' => ['ReviewController', 'createLineComment'],
    'PUT /api/reviews/line-comments' => ['ReviewController', 'updateLineComment'],
    'DELETE /api/reviews/line-comments' => ['ReviewController', 'deleteLineComment'],
];

// 匹配路由
$routeKey = "$method $uri";

if (isset($routes[$routeKey])) {
    [$controllerName, $methodName] = $routes[$routeKey];
    
    $controllerClass = "CodeVault\\Controllers\\$controllerName";
    $controller = new $controllerClass();
    
    try {
        // 检查是否有文件上传
        if (!empty($_FILES)) {
            $result = $controller->$methodName($input, $_FILES);
        } else {
            $result = $controller->$methodName($input);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => '服务器错误: ' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => '接口不存在',
    ], JSON_UNESCAPED_UNICODE);
}
