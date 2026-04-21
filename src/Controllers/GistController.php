<?php
/**
 * CodeVault Gist 控制器
 * 
 * API 端点：
 * - GET    /api/gists          - 列出 Gists
 * - POST   /api/gists          - 创建 Gist
 * - GET    /api/gists/{id}     - 获取 Gist
 * - PATCH  /api/gists/{id}     - 更新 Gist
 * - DELETE /api/gists/{id}     - 删除 Gist
 * - POST   /api/gists/{id}/fork - Fork Gist
 * - POST   /api/gists/{id}/star - 星标 Gist
 * - DELETE /api/gists/{id}/star - 取消星标
 * - GET    /api/gists/{id}/comments - 获取评论
 * - POST   /api/gists/{id}/comments - 添加评论
 * - GET    /api/gists/{id}/history - 获取历史
 * - GET    /api/gists/{id}/versions/{version} - 获取版本
 */

namespace CodeVault\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Services\GistService;
use Middleware\AuthMiddleware;

class GistController extends Controller
{
    private $gistService;
    
    public function __construct()
    {
        $this->gistService = new GistService();
    }
    
    /**
     * 列出 Gists
     * GET /api/gists
     */
    public function index(Request $request, Response $response): void
    {
        $params = [
            'user_id' => $request->query('user_id'),
            'username' => $request->query('username'),
            'q' => $request->query('q'),
            'language' => $request->query('language'),
            'sort' => $request->query('sort', 'updated'),
            'page' => (int)$request->query('page', 1),
            'per_page' => (int)$request->query('per_page', 30),
        ];
        
        $gists = $this->gistService->listGists($params);
        
        $response->json([
            'success' => true,
            'data' => $gists,
        ]);
    }
    
    /**
     * 创建 Gist
     * POST /api/gists
     */
    public function store(Request $request, Response $response): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $data = $request->getBody();
        
        $result = $this->gistService->create($user['id'], $data);
        
        if ($result['success']) {
            $response->json($result, 201);
        } else {
            $response->json($result, 400);
        }
    }
    
    /**
     * 获取 Gist
     * GET /api/gists/{id}
     */
    public function show(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        $userId = $user['id'] ?? null;
        
        $gist = $this->gistService->getGist($params['id'], $userId);
        
        if (!$gist) {
            $response->json(['success' => false, 'error' => 'Gist 不存在'], 404);
            return;
        }
        
        $response->json([
            'success' => true,
            'data' => $gist,
        ]);
    }
    
    /**
     * 更新 Gist
     * PATCH /api/gists/{id}
     */
    public function update(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $data = $request->getBody();
        
        $result = $this->gistService->update($params['id'], $user['id'], $data);
        
        if ($result['success']) {
            $response->json($result);
        } else {
            $response->json($result, 400);
        }
    }
    
    /**
     * 删除 Gist
     * DELETE /api/gists/{id}
     */
    public function destroy(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $result = $this->gistService->delete($params['id'], $user['id']);
        
        if ($result['success']) {
            $response->json($result);
        } else {
            $response->json($result, 400);
        }
    }
    
    /**
     * Fork Gist
     * POST /api/gists/{id}/fork
     */
    public function fork(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $result = $this->gistService->fork($params['id'], $user['id']);
        
        if ($result['success']) {
            $response->json($result, 201);
        } else {
            $response->json($result, 400);
        }
    }
    
    /**
     * 星标 Gist
     * POST /api/gists/{id}/star
     */
    public function star(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $result = $this->gistService->toggleStar($params['id'], $user['id']);
        
        $response->json($result);
    }
    
    /**
     * 取消星标
     * DELETE /api/gists/{id}/star
     */
    public function unstar(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $result = $this->gistService->toggleStar($params['id'], $user['id']);
        
        $response->json($result);
    }
    
    /**
     * 获取评论
     * GET /api/gists/{id}/comments
     */
    public function comments(Request $request, Response $response, array $params): void
    {
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 30);
        
        $comments = $this->gistService->getComments($params['id'], $page, $perPage);
        
        $response->json([
            'success' => true,
            'data' => $comments,
        ]);
    }
    
    /**
     * 添加评论
     * POST /api/gists/{id}/comments
     */
    public function addComment(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            $response->json(['success' => false, 'error' => '未授权'], 401);
            return;
        }
        
        $data = $request->getBody();
        
        if (empty($data['body'])) {
            $response->json(['success' => false, 'error' => '评论内容不能为空'], 400);
            return;
        }
        
        $result = $this->gistService->addComment($params['id'], $user['id'], $data['body']);
        
        if ($result['success']) {
            $response->json($result, 201);
        } else {
            $response->json($result, 400);
        }
    }
    
    /**
     * 获取历史版本
     * GET /api/gists/{id}/history
     */
    public function history(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        $userId = $user['id'] ?? null;
        
        $history = $this->gistService->getHistory($params['id'], $userId);
        
        $response->json([
            'success' => true,
            'data' => $history,
        ]);
    }
    
    /**
     * 获取特定版本
     * GET /api/gists/{id}/versions/{version}
     */
    public function version(Request $request, Response $response, array $params): void
    {
        $user = AuthMiddleware::getUser();
        $userId = $user['id'] ?? null;
        
        $version = $this->gistService->getVersion(
            $params['id'], 
            (int)$params['version'],
            $userId
        );
        
        if (!$version) {
            $response->json(['success' => false, 'error' => '版本不存在'], 404);
            return;
        }
        
        $response->json([
            'success' => true,
            'data' => $version,
        ]);
    }
}
