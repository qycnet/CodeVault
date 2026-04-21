<?php
/**
 * CodeVault - OAuth2 控制器
 * OAuth2 授权服务器实现
 */

namespace CodeVault\Controllers;

use CodeVault\Database\Connection;
use CodeVault\Services\Session;

class OAuthController
{
    private string $baseUrl;
    
    public function __construct()
    {
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    }
    
    /**
     * 注册 OAuth 应用
     */
    public function registerApp(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $name = trim($data['name'] ?? '');
        $redirectUri = trim($data['redirect_uri'] ?? '');
        $description = trim($data['description'] ?? '');
        $homepageUrl = trim($data['homepage_url'] ?? '');
        
        if (empty($name) || empty($redirectUri)) {
            return ['success' => false, 'message' => '应用名称和回调地址不能为空'];
        }
        
        // 验证回调地址
        if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => '回调地址格式无效'];
        }
        
        // 生成客户端 ID 和 Secret
        $clientId = 'cv_oauth_' . bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));
        $clientSecretHash = password_hash($clientSecret, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $appId = Connection::insert(
            "INSERT INTO oauth_applications (user_id, name, client_id, client_secret_hash, redirect_uri, description, homepage_url, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$user['id'], $name, $clientId, $clientSecretHash, $redirectUri, $description, $homepageUrl]
        );
        
        return [
            'success' => true,
            'app_id' => $appId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret, // 只返回一次
            'message' => '请妥善保管 Client Secret，此 Secret 只会显示一次',
        ];
    }
    
    /**
     * 获取用户的 OAuth 应用列表
     */
    public function listApps(): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $apps = Connection::query(
            "SELECT id, name, client_id, redirect_uri, description, homepage_url, created_at 
             FROM oauth_applications 
             WHERE user_id = ? AND deleted_at IS NULL 
             ORDER BY created_at DESC",
            [$user['id']]
        );
        
        return [
            'success' => true,
            'apps' => $apps,
        ];
    }
    
    /**
     * 更新 OAuth 应用
     */
    public function updateApp(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $appId = (int) ($data['id'] ?? 0);
        
        $app = Connection::queryOne(
            "SELECT * FROM oauth_applications WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$appId, $user['id']]
        );
        
        if (!$app) {
            return ['success' => false, 'message' => '应用不存在或无权修改'];
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['name']) && !empty(trim($data['name']))) {
            $updates[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        
        if (isset($data['redirect_uri'])) {
            $redirectUri = trim($data['redirect_uri']);
            if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
                return ['success' => false, 'message' => '回调地址格式无效'];
            }
            $updates[] = 'redirect_uri = ?';
            $params[] = $redirectUri;
        }
        
        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = trim($data['description']);
        }
        
        if (isset($data['homepage_url'])) {
            $updates[] = 'homepage_url = ?';
            $params[] = trim($data['homepage_url']);
        }
        
        if (empty($updates)) {
            return ['success' => true, 'message' => '无更新'];
        }
        
        $params[] = $appId;
        
        Connection::execute(
            "UPDATE oauth_applications SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );
        
        return [
            'success' => true,
            'message' => '应用已更新',
        ];
    }
    
    /**
     * 删除 OAuth 应用
     */
    public function deleteApp(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $appId = (int) ($data['id'] ?? 0);
        
        $app = Connection::queryOne(
            "SELECT id FROM oauth_applications WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$appId, $user['id']]
        );
        
        if (!$app) {
            return ['success' => false, 'message' => '应用不存在或无权删除'];
        }
        
        Connection::execute(
            "UPDATE oauth_applications SET deleted_at = NOW() WHERE id = ?",
            [$appId]
        );
        
        // 撤销所有相关 Token
        Connection::execute(
            "UPDATE oauth_tokens SET revoked_at = NOW() WHERE app_id = ?",
            [$appId]
        );
        
        return [
            'success' => true,
            'message' => '应用已删除',
        ];
    }
    
    /**
     * 重新生成 Client Secret
     */
    public function regenerateSecret(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $appId = (int) ($data['id'] ?? 0);
        
        $app = Connection::queryOne(
            "SELECT id FROM oauth_applications WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$appId, $user['id']]
        );
        
        if (!$app) {
            return ['success' => false, 'message' => '应用不存在或无权操作'];
        }
        
        $clientSecret = bin2hex(random_bytes(32));
        $clientSecretHash = password_hash($clientSecret, PASSWORD_BCRYPT, ['cost' => 12]);
        
        Connection::execute(
            "UPDATE oauth_applications SET client_secret_hash = ? WHERE id = ?",
            [$clientSecretHash, $appId]
        );
        
        return [
            'success' => true,
            'client_secret' => $clientSecret,
            'message' => '请妥善保管新的 Client Secret',
        ];
    }
    
    /**
     * OAuth2 授权端点
     */
    public function authorize(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录', 'redirect' => '/login'];
        }
        
        $clientId = $data['client_id'] ?? '';
        $redirectUri = $data['redirect_uri'] ?? '';
        $scope = $data['scope'] ?? 'user:read';
        $state = $data['state'] ?? '';
        $responseType = $data['response_type'] ?? 'code';
        
        // 验证应用
        $app = Connection::queryOne(
            "SELECT * FROM oauth_applications WHERE client_id = ? AND deleted_at IS NULL",
            [$clientId]
        );
        
        if (!$app) {
            return ['success' => false, 'message' => '无效的客户端 ID'];
        }
        
        // 验证回调地址
        if ($redirectUri && $redirectUri !== $app['redirect_uri']) {
            return ['success' => false, 'message' => '回调地址不匹配'];
        }
        
        // 如果用户已授权，直接返回
        $existingAuth = Connection::queryOne(
            "SELECT * FROM oauth_authorizations WHERE user_id = ? AND app_id = ? AND scopes = ?",
            [$user['id'], $app['id'], $scope]
        );
        
        if ($existingAuth && !$data['prompt']) {
            // 生成授权码
            $code = $this->generateAuthCode($user['id'], $app['id'], $scope, $redirectUri);
            
            $redirectUrl = $this->buildRedirectUrl($app['redirect_uri'], [
                'code' => $code,
                'state' => $state,
            ]);
            
            return [
                'success' => true,
                'redirect' => $redirectUrl,
            ];
        }
        
        // 返回授权页面信息
        return [
            'success' => true,
            'requires_consent' => true,
            'app' => [
                'name' => $app['name'],
                'description' => $app['description'],
                'homepage_url' => $app['homepage_url'],
            ],
            'scopes' => $this->parseScopes($scope),
            'authorize_url' => "/oauth/authorize?client_id={$clientId}&redirect_uri=" . urlencode($app['redirect_uri']) . "&scope={$scope}&state={$state}",
        ];
    }
    
    /**
     * 处理用户授权同意
     */
    public function grantAuthorization(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $clientId = $data['client_id'] ?? '';
        $scope = $data['scope'] ?? 'user:read';
        $state = $data['state'] ?? '';
        $granted = ($data['granted'] ?? 'true') === 'true';
        
        $app = Connection::queryOne(
            "SELECT * FROM oauth_applications WHERE client_id = ? AND deleted_at IS NULL",
            [$clientId]
        );
        
        if (!$app) {
            return ['success' => false, 'message' => '无效的客户端 ID'];
        }
        
        if (!$granted) {
            // 用户拒绝授权
            $redirectUrl = $this->buildRedirectUrl($app['redirect_uri'], [
                'error' => 'access_denied',
                'error_description' => '用户拒绝授权',
                'state' => $state,
            ]);
            
            return [
                'success' => true,
                'redirect' => $redirectUrl,
            ];
        }
        
        // 保存授权记录
        Connection::insert(
            "INSERT INTO oauth_authorizations (user_id, app_id, scopes, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE scopes = VALUES(scopes), updated_at = NOW()",
            [$user['id'], $app['id'], $scope]
        );
        
        // 生成授权码
        $code = $this->generateAuthCode($user['id'], $app['id'], $scope, $app['redirect_uri']);
        
        $redirectUrl = $this->buildRedirectUrl($app['redirect_uri'], [
            'code' => $code,
            'state' => $state,
        ]);
        
        return [
            'success' => true,
            'redirect' => $redirectUrl,
        ];
    }
    
    /**
     * OAuth2 Token 端点
     */
    public function token(array $data): array
    {
        $grantType = $data['grant_type'] ?? '';
        
        if ($grantType === 'authorization_code') {
            return $this->handleAuthorizationCode($data);
        } elseif ($grantType === 'refresh_token') {
            return $this->handleRefreshToken($data);
        } elseif ($grantType === 'client_credentials') {
            return $this->handleClientCredentials($data);
        }
        
        return [
            'error' => 'unsupported_grant_type',
            'error_description' => '不支持的授权类型',
        ];
    }
    
    /**
     * 处理授权码换取 Token
     */
    private function handleAuthorizationCode(array $data): array
    {
        $code = $data['code'] ?? '';
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';
        $redirectUri = $data['redirect_uri'] ?? '';
        
        // 验证应用
        $app = Connection::queryOne(
            "SELECT * FROM oauth_applications WHERE client_id = ? AND deleted_at IS NULL",
            [$clientId]
        );
        
        if (!$app || !password_verify($clientSecret, $app['client_secret_hash'])) {
            return [
                'error' => 'invalid_client',
                'error_description' => '客户端认证失败',
            ];
        }
        
        // 验证授权码
        $authCode = Connection::queryOne(
            "SELECT * FROM oauth_auth_codes WHERE code = ? AND app_id = ? AND used_at IS NULL AND expires_at > NOW()",
            [$code, $app['id']]
        );
        
        if (!$authCode) {
            return [
                'error' => 'invalid_grant',
                'error_description' => '无效或过期的授权码',
            ];
        }
        
        // 标记授权码已使用
        Connection::execute(
            "UPDATE oauth_auth_codes SET used_at = NOW() WHERE id = ?",
            [$authCode['id']]
        );
        
        // 生成访问令牌
        $accessToken = $this->generateAccessToken();
        $refreshToken = $this->generateRefreshToken();
        $expiresIn = 3600; // 1 小时
        
        Connection::insert(
            "INSERT INTO oauth_tokens (app_id, user_id, access_token, refresh_token, scopes, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
            [$app['id'], $authCode['user_id'], $accessToken, $refreshToken, $authCode['scopes'], $expiresIn]
        );
        
        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'scope' => $authCode['scopes'],
        ];
    }
    
    /**
     * 处理刷新令牌
     */
    private function handleRefreshToken(array $data): array
    {
        $refreshToken = $data['refresh_token'] ?? '';
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';
        
        // 验证应用
        $app = Connection::queryOne(
            "SELECT * FROM oauth_applications WHERE client_id = ? AND deleted_at IS NULL",
            [$clientId]
        );
        
        if (!$app || !password_verify($clientSecret, $app['client_secret_hash'])) {
            return [
                'error' => 'invalid_client',
                'error_description' => '客户端认证失败',
            ];
        }
        
        // 验证刷新令牌
        $token = Connection::queryOne(
            "SELECT * FROM oauth_tokens WHERE refresh_token = ? AND app_id = ? AND revoked_at IS NULL",
            [$refreshToken, $app['id']]
        );
        
        if (!$token) {
            return [
                'error' => 'invalid_grant',
                'error_description' => '无效的刷新令牌',
            ];
        }
        
        // 撤销旧令牌
        Connection::execute(
            "UPDATE oauth_tokens SET revoked_at = NOW() WHERE id = ?",
            [$token['id']]
        );
        
        // 生成新令牌
        $newAccessToken = $this->generateAccessToken();
        $newRefreshToken = $this->generateRefreshToken();
        $expiresIn = 3600;
        
        Connection::insert(
            "INSERT INTO oauth_tokens (app_id, user_id, access_token, refresh_token, scopes, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
            [$app['id'], $token['user_id'], $newAccessToken, $newRefreshToken, $token['scopes'], $expiresIn]
        );
        
        return [
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'refresh_token' => $newRefreshToken,
            'scope' => $token['scopes'],
        ];
    }
    
    /**
     * 处理客户端凭证授权
     */
    private function handleClientCredentials(array $data): array
    {
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';
        $scope = $data['scope'] ?? '';
        
        // 验证应用
        $app = Connection::queryOne(
            "SELECT * FROM oauth_applications WHERE client_id = ? AND deleted_at IS NULL",
            [$clientId]
        );
        
        if (!$app || !password_verify($clientSecret, $app['client_secret_hash'])) {
            return [
                'error' => 'invalid_client',
                'error_description' => '客户端认证失败',
            ];
        }
        
        // 生成访问令牌
        $accessToken = $this->generateAccessToken();
        $expiresIn = 3600;
        
        Connection::insert(
            "INSERT INTO oauth_tokens (app_id, user_id, access_token, scopes, expires_at, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
            [$app['id'], $app['user_id'], $accessToken, $scope, $expiresIn]
        );
        
        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => $scope,
        ];
    }
    
    /**
     * 验证访问令牌
     */
    public static function verifyToken(string $accessToken): ?array
    {
        $token = Connection::queryOne(
            "SELECT t.*, u.username, u.email, u.is_admin 
             FROM oauth_tokens t 
             JOIN users u ON t.user_id = u.id 
             WHERE t.access_token = ? AND t.revoked_at IS NULL AND t.expires_at > NOW()",
            [$accessToken]
        );
        
        if (!$token) {
            return null;
        }
        
        return [
            'user_id' => $token['user_id'],
            'username' => $token['username'],
            'email' => $token['email'],
            'is_admin' => $token['is_admin'],
            'scopes' => $this->parseScopes($token['scopes']),
            'app_id' => $token['app_id'],
        ];
    }
    
    /**
     * 生成授权码
     */
    private function generateAuthCode(int $userId, int $appId, string $scope, string $redirectUri): string
    {
        $code = bin2hex(random_bytes(32));
        
        Connection::insert(
            "INSERT INTO oauth_auth_codes (code, user_id, app_id, scopes, redirect_uri, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())",
            [$code, $userId, $appId, $scope, $redirectUri]
        );
        
        return $code;
    }
    
    /**
     * 生成访问令牌
     */
    private function generateAccessToken(): string
    {
        return 'cv_oauth_at_' . bin2hex(random_bytes(32));
    }
    
    /**
     * 生成刷新令牌
     */
    private function generateRefreshToken(): string
    {
        return 'cv_oauth_rt_' . bin2hex(random_bytes(32));
    }
    
    /**
     * 构建重定向 URL
     */
    private function buildRedirectUrl(string $baseUrl, array $params): string
    {
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }
    
    /**
     * 解析权限范围
     */
    private function parseScopes(string $scope): array
    {
        $scopes = explode(' ', $scope);
        $result = [];
        
        $scopeDescriptions = [
            'user:read' => '读取用户信息',
            'user:email' => '读取用户邮箱',
            'repo' => '完全访问仓库',
            'repo:read' => '读取仓库',
            'repo:write' => '写入仓库',
            'repo:delete' => '删除仓库',
            'workflow' => '管理工作流',
            'admin' => '管理员权限',
        ];
        
        foreach ($scopes as $s) {
            $s = trim($s);
            if ($s) {
                $result[$s] = $scopeDescriptions[$s] ?? $s;
            }
        }
        
        return $result;
    }
    
    /**
     * 获取用户授权的应用列表
     */
    public function listAuthorizations(): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $authorizations = Connection::query(
            "SELECT a.*, o.name as app_name, o.description as app_description 
             FROM oauth_authorizations a 
             JOIN oauth_applications o ON a.app_id = o.id 
             WHERE a.user_id = ? AND o.deleted_at IS NULL 
             ORDER BY a.created_at DESC",
            [$user['id']]
        );
        
        foreach ($authorizations as &$auth) {
            $auth['scopes'] = $this->parseScopes($auth['scopes']);
        }
        
        return [
            'success' => true,
            'authorizations' => $authorizations,
        ];
    }
    
    /**
     * 撤销应用授权
     */
    public function revokeAuthorization(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $appId = (int) ($data['app_id'] ?? 0);
        
        Connection::execute(
            "DELETE FROM oauth_authorizations WHERE user_id = ? AND app_id = ?",
            [$user['id'], $appId]
        );
        
        // 撤销相关 Token
        Connection::execute(
            "UPDATE oauth_tokens SET revoked_at = NOW() WHERE user_id = ? AND app_id = ?",
            [$user['id'], $appId]
        );
        
        return [
            'success' => true,
            'message' => '授权已撤销',
        ];
    }
}
