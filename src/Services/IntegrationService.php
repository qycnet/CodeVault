<?php
/**
 * CodeVault 第三方集成服务
 * 
 * 功能：
 * - 第三方登录（微信/QQ）
 * - Slack/DingTalk 集成
 * - VS Code 插件支持
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Cache;

class IntegrationService
{
    private $db;
    private $logger;
    private $cache;
    
    // 支持的第三方平台
    public const PLATFORMS = [
        'wechat' => [
            'name' => '微信',
            'auth_url' => 'https://open.weixin.qq.com/connect/qrconnect',
            'token_url' => 'https://api.weixin.qq.com/sns/oauth2/access_token',
            'user_url' => 'https://api.weixin.qq.com/sns/userinfo',
        ],
        'qq' => [
            'name' => 'QQ',
            'auth_url' => 'https://graph.qq.com/oauth2.0/authorize',
            'token_url' => 'https://graph.qq.com/oauth2.0/token',
            'user_url' => 'https://graph.qq.com/user/get_user_info',
        ],
        'slack' => [
            'name' => 'Slack',
            'auth_url' => 'https://slack.com/oauth/v2/authorize',
            'api_url' => 'https://slack.com/api/',
        ],
        'dingtalk' => [
            'name' => '钉钉',
            'auth_url' => 'https://login.dingtalk.com/oauth2/auth',
            'api_url' => 'https://api.dingtalk.com/',
        ],
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('integration');
        $this->cache = new Cache();
    }
    
    // ==================== 第三方登录 ====================
    
    /**
     * 获取第三方登录授权 URL
     */
    public function getAuthUrl(string $platform, string $redirectUri, ?string $state = null): array
    {
        if (!isset(self::PLATFORMS[$platform])) {
            return ['success' => false, 'error' => '不支持的平台'];
        }
        
        $config = $this->getPlatformConfig($platform);
        if (!$config) {
            return ['success' => false, 'error' => '平台未配置'];
        }
        
        $state = $state ?? bin2hex(random_bytes(16));
        
        // 缓存 state 用于验证
        $this->cache->set("oauth_state:{$state}", [
            'platform' => $platform,
            'redirect_uri' => $redirectUri,
        ], 600); // 10分钟有效
        
        $authUrl = match ($platform) {
            'wechat' => sprintf(
                '%s?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_login&state=%s#wechat_redirect',
                self::PLATFORMS[$platform]['auth_url'],
                $config['app_id'],
                urlencode($redirectUri),
                $state
            ),
            'qq' => sprintf(
                '%s?client_id=%s&redirect_uri=%s&response_type=code&scope=get_user_info&state=%s',
                self::PLATFORMS[$platform]['auth_url'],
                $config['app_id'],
                urlencode($redirectUri),
                $state
            ),
            default => '',
        };
        
        return [
            'success' => true,
            'auth_url' => $authUrl,
            'state' => $state,
        ];
    }
    
    /**
     * 处理第三方登录回调
     */
    public function handleCallback(string $platform, string $code, string $state): array
    {
        // 验证 state
        $stateData = $this->cache->get("oauth_state:{$state}");
        if (!$stateData || $stateData['platform'] !== $platform) {
            return ['success' => false, 'error' => '无效的 state'];
        }
        
        $config = $this->getPlatformConfig($platform);
        if (!$config) {
            return ['success' => false, 'error' => '平台未配置'];
        }
        
        // 获取 access_token
        $tokenData = $this->getAccessToken($platform, $code, $config);
        if (!$tokenData['success']) {
            return $tokenData;
        }
        
        // 获取用户信息
        $userInfo = $this->getUserInfo($platform, $tokenData['access_token'], $tokenData['openid'], $config);
        if (!$userInfo['success']) {
            return $userInfo;
        }
        
        // 查找或创建用户
        $user = $this->findOrCreateUser($platform, $userInfo['user']);
        
        return [
            'success' => true,
            'user' => $user,
            'is_new_user' => $user['is_new'] ?? false,
        ];
    }
    
    /**
     * 获取 access_token
     */
    private function getAccessToken(string $platform, string $code, array $config): array
    {
        $tokenUrl = match ($platform) {
            'wechat' => sprintf(
                '%s?appid=%s&secret=%s&code=%s&grant_type=authorization_code',
                self::PLATFORMS[$platform]['token_url'],
                $config['app_id'],
                $config['app_secret'],
                $code
            ),
            'qq' => sprintf(
                '%s?grant_type=authorization_code&client_id=%s&client_secret=%s&code=%s&redirect_uri=%s',
                self::PLATFORMS[$platform]['token_url'],
                $config['app_id'],
                $config['app_secret'],
                $code,
                urlencode($config['redirect_uri'])
            ),
            default => '',
        };
        
        $response = $this->httpGet($tokenUrl);
        
        if (!$response['success']) {
            return ['success' => false, 'error' => '获取 token 失败'];
        }
        
        $data = $response['data'];
        
        return [
            'success' => true,
            'access_token' => $data['access_token'] ?? '',
            'openid' => $data['openid'] ?? $data['openid'] ?? '',
            'expires_in' => $data['expires_in'] ?? 7200,
        ];
    }
    
    /**
     * 获取用户信息
     */
    private function getUserInfo(string $platform, string $accessToken, string $openid, array $config): array
    {
        $userUrl = match ($platform) {
            'wechat' => sprintf(
                '%s?access_token=%s&openid=%s',
                self::PLATFORMS[$platform]['user_url'],
                $accessToken,
                $openid
            ),
            'qq' => sprintf(
                '%s?access_token=%s&oauth_consumer_key=%s&openid=%s',
                self::PLATFORMS[$platform]['user_url'],
                $accessToken,
                $config['app_id'],
                $openid
            ),
            default => '',
        };
        
        $response = $this->httpGet($userUrl);
        
        if (!$response['success']) {
            return ['success' => false, 'error' => '获取用户信息失败'];
        }
        
        $data = $response['data'];
        
        $user = [
            'platform_id' => $openid,
            'nickname' => $data['nickname'] ?? $data['name'] ?? 'User',
            'avatar' => $data['headimgurl'] ?? $data['figureurl_qq_2'] ?? '',
            'gender' => $data['sex'] ?? $data['gender'] ?? 0,
        ];
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * 查找或创建用户
     */
    private function findOrCreateUser(string $platform, array $platformUser): array
    {
        // 查找已绑定的用户
        $existing = $this->db->fetchOne(
            "SELECT u.* FROM users u
             JOIN user_oauth_bindings uob ON u.id = uob.user_id
             WHERE uob.platform = ? AND uob.platform_user_id = ?",
            [$platform, $platformUser['platform_id']]
        );
        
        if ($existing) {
            $existing['is_new'] = false;
            return $existing;
        }
        
        // 创建新用户
        $this->db->beginTransaction();
        
        try {
            // 生成唯一用户名
            $username = $this->generateUniqueUsername($platformUser['nickname'], $platform);
            
            // 创建用户
            $this->db->execute(
                "INSERT INTO users (username, name, avatar_url, status, created_at) VALUES (?, ?, ?, 'active', NOW())",
                [$username, $platformUser['nickname'], $platformUser['avatar']]
            );
            
            $userId = (int)$this->db->lastInsertId();
            
            // 绑定第三方账号
            $this->db->execute(
                "INSERT INTO user_oauth_bindings (user_id, platform, platform_user_id, platform_data, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$userId, $platform, $platformUser['platform_id'], json_encode($platformUser)]
            );
            
            $this->db->commit();
            
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            $user['is_new'] = true;
            
            return $user;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 绑定第三方账号到已有用户
     */
    public function bindPlatform(int $userId, string $platform, string $code, string $state): array
    {
        $result = $this->handleCallback($platform, $code, $state);
        
        if (!$result['success']) {
            return $result;
        }
        
        // 检查是否已被其他用户绑定
        $existing = $this->db->fetchOne(
            "SELECT user_id FROM user_oauth_bindings WHERE platform = ? AND platform_user_id = ?",
            [$platform, $result['user']['platform_id'] ?? '']
        );
        
        if ($existing && $existing['user_id'] != $userId) {
            return ['success' => false, 'error' => '该账号已被其他用户绑定'];
        }
        
        // 创建绑定
        $this->db->execute(
            "INSERT INTO user_oauth_bindings (user_id, platform, platform_user_id, created_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()",
            [$userId, $platform, $result['user']['platform_id'] ?? '']
        );
        
        return ['success' => true];
    }
    
    /**
     * 解绑第三方账号
     */
    public function unbindPlatform(int $userId, string $platform): array
    {
        $this->db->execute(
            "DELETE FROM user_oauth_bindings WHERE user_id = ? AND platform = ?",
            [$userId, $platform]
        );
        
        return ['success' => true];
    }
    
    // ==================== Slack/DingTalk 集成 ====================
    
    /**
     * 配置 Slack 集成
     */
    public function configureSlack(int $repoId, int $userId, array $config): array
    {
        // 验证配置
        if (empty($config['webhook_url'])) {
            return ['success' => false, 'error' => 'Webhook URL 不能为空'];
        }
        
        // 测试 Webhook
        $testResult = $this->testSlackWebhook($config['webhook_url']);
        if (!$testResult['success']) {
            return ['success' => false, 'error' => 'Webhook 测试失败: ' . $testResult['error']];
        }
        
        // 保存配置
        $this->db->execute(
            "INSERT INTO repo_integrations (repo_id, platform, config, events, created_by, created_at) 
             VALUES (?, 'slack', ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE config = VALUES(config), events = VALUES(events), updated_at = NOW()",
            [$repoId, json_encode($config), json_encode($config['events'] ?? ['push', 'pr', 'issue'])]
        );
        
        $this->logger->info('Slack 集成配置成功', ['repo_id' => $repoId, 'user_id' => $userId]);
        
        return ['success' => true];
    }
    
    /**
     * 配置钉钉集成
     */
    public function configureDingTalk(int $repoId, int $userId, array $config): array
    {
        if (empty($config['webhook_url'])) {
            return ['success' => false, 'error' => 'Webhook URL 不能为空'];
        }
        
        // 测试 Webhook
        $testResult = $this->testDingTalkWebhook($config['webhook_url']);
        if (!$testResult['success']) {
            return ['success' => false, 'error' => 'Webhook 测试失败: ' . $testResult['error']];
        }
        
        $this->db->execute(
            "INSERT INTO repo_integrations (repo_id, platform, config, events, created_by, created_at) 
             VALUES (?, 'dingtalk', ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE config = VALUES(config), events = VALUES(events), updated_at = NOW()",
            [$repoId, json_encode($config), json_encode($config['events'] ?? ['push', 'pr', 'issue'])]
        );
        
        $this->logger->info('钉钉集成配置成功', ['repo_id' => $repoId, 'user_id' => $userId]);
        
        return ['success' => true];
    }
    
    /**
     * 发送 Slack 通知
     */
    public function sendSlackNotification(int $repoId, string $event, array $data): bool
    {
        $integration = $this->db->fetchOne(
            "SELECT * FROM repo_integrations WHERE repo_id = ? AND platform = 'slack'",
            [$repoId]
        );
        
        if (!$integration) {
            return false;
        }
        
        $config = json_decode($integration['config'], true);
        $events = json_decode($integration['events'], true);
        
        if (!in_array($event, $events)) {
            return false;
        }
        
        $message = $this->formatSlackMessage($event, $data);
        
        return $this->postToSlack($config['webhook_url'], $message);
    }
    
    /**
     * 发送钉钉通知
     */
    public function sendDingTalkNotification(int $repoId, string $event, array $data): bool
    {
        $integration = $this->db->fetchOne(
            "SELECT * FROM repo_integrations WHERE repo_id = ? AND platform = 'dingtalk'",
            [$repoId]
        );
        
        if (!$integration) {
            return false;
        }
        
        $config = json_decode($integration['config'], true);
        $events = json_decode($integration['events'], true);
        
        if (!in_array($event, $events)) {
            return false;
        }
        
        $message = $this->formatDingTalkMessage($event, $data);
        
        return $this->postToDingTalk($config['webhook_url'], $message);
    }
    
    // ==================== VS Code 插件支持 ====================
    
    /**
     * 生成 VS Code 插件 Token
     */
    public function generateVSCodeToken(int $userId): array
    {
        $token = 'vscode_' . bin2hex(random_bytes(32));
        
        $this->db->execute(
            "INSERT INTO api_tokens (user_id, token, name, scopes, expires_at, created_at) 
             VALUES (?, ?, 'VS Code Extension', ?, DATE_ADD(NOW(), INTERVAL 1 YEAR), NOW())",
            [$userId, hash('sha256', $token), json_encode(['repo:read', 'repo:write', 'issue:read', 'issue:write', 'pr:read', 'pr:write'])]
        );
        
        return [
            'success' => true,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
        ];
    }
    
    /**
     * 获取 VS Code 插件配置
     */
    public function getVSCodeConfig(int $userId): array
    {
        $repos = $this->db->fetchAll(
            "SELECT id, name, description, is_public FROM repositories WHERE owner_id = ? ORDER BY updated_at DESC LIMIT 50",
            [$userId]
        );
        
        return [
            'success' => true,
            'repos' => $repos,
            'settings' => [
                'auto_fetch' => true,
                'notification_enabled' => true,
                'theme' => 'auto',
            ],
        ];
    }
    
    // ==================== 辅助方法 ====================
    
    /**
     * 获取平台配置
     */
    private function getPlatformConfig(string $platform): ?array
    {
        // 从配置或数据库获取
        $config = $this->db->fetchOne(
            "SELECT * FROM oauth_providers WHERE platform = ? AND enabled = 1",
            [$platform]
        );
        
        if (!$config) {
            return null;
        }
        
        return [
            'app_id' => $config['client_id'],
            'app_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
        ];
    }
    
    /**
     * 生成唯一用户名
     */
    private function generateUniqueUsername(string $nickname, string $platform): string
    {
        // 清理昵称
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', $nickname);
        $baseName = strtolower(substr($baseName ?: 'user', 0, 20));
        
        $username = $baseName;
        $counter = 1;
        
        while ($this->db->fetchOne("SELECT id FROM users WHERE username = ?", [$username])) {
            $username = $baseName . '_' . $platform . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * HTTP GET 请求
     */
    private function httpGet(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        $data = json_decode($response, true);
        
        return ['success' => true, 'data' => $data];
    }
    
    /**
     * 测试 Slack Webhook
     */
    private function testSlackWebhook(string $webhookUrl): array
    {
        $payload = json_encode(['text' => 'CodeVault 集成测试成功！']);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        return ['success' => true];
    }
    
    /**
     * 测试钉钉 Webhook
     */
    private function testDingTalkWebhook(string $webhookUrl): array
    {
        $payload = json_encode([
            'msgtype' => 'text',
            'text' => ['content' => 'CodeVault 集成测试成功！'],
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        return ['success' => true];
    }
    
    /**
     * 格式化 Slack 消息
     */
    private function formatSlackMessage(string $event, array $data): array
    {
        return match ($event) {
            'push' => [
                'text' => sprintf('[%s] New push by %s', $data['repo_name'] ?? '', $data['author'] ?? ''),
                'attachments' => [[
                    'color' => 'good',
                    'fields' => [
                        ['title' => 'Branch', 'value' => $data['branch'] ?? '', 'short' => true],
                        ['title' => 'Commits', 'value' => strval($data['commit_count'] ?? 0), 'short' => true],
                    ],
                ]],
            ],
            'pr' => [
                'text' => sprintf('[%s] New Pull Request #%d', $data['repo_name'] ?? '', $data['pr_number'] ?? 0),
                'attachments' => [[
                    'color' => '#36a64f',
                    'title' => $data['title'] ?? '',
                    'title_link' => $data['url'] ?? '',
                    'fields' => [
                        ['title' => 'Author', 'value' => $data['author'] ?? '', 'short' => true],
                        ['title' => 'Status', 'value' => $data['status'] ?? 'open', 'short' => true],
                    ],
                ]],
            ],
            'issue' => [
                'text' => sprintf('[%s] Issue #%d: %s', $data['repo_name'] ?? '', $data['issue_number'] ?? 0, $data['title'] ?? ''),
                'attachments' => [[
                    'color' => 'warning',
                    'fields' => [
                        ['title' => 'Author', 'value' => $data['author'] ?? '', 'short' => true],
                        ['title' => 'Status', 'value' => $data['status'] ?? 'open', 'short' => true],
                    ],
                ]],
            ],
            default => ['text' => 'New activity in repository'],
        };
    }
    
    /**
     * 格式化钉钉消息
     */
    private function formatDingTalkMessage(string $event, array $data): array
    {
        $content = match ($event) {
            'push' => sprintf(
                "【%s】新推送\n分支: %s\n提交数: %d\n提交者: %s",
                $data['repo_name'] ?? '',
                $data['branch'] ?? '',
                $data['commit_count'] ?? 0,
                $data['author'] ?? ''
            ),
            'pr' => sprintf(
                "【%s】新 Pull Request #%d\n标题: %s\n作者: %s\n状态: %s",
                $data['repo_name'] ?? '',
                $data['pr_number'] ?? 0,
                $data['title'] ?? '',
                $data['author'] ?? '',
                $data['status'] ?? 'open'
            ),
            'issue' => sprintf(
                "【%s】Issue #%d\n标题: %s\n作者: %s\n状态: %s",
                $data['repo_name'] ?? '',
                $data['issue_number'] ?? 0,
                $data['title'] ?? '',
                $data['author'] ?? '',
                $data['status'] ?? 'open'
            ),
            default => '仓库有新动态',
        };
        
        return [
            'msgtype' => 'text',
            'text' => ['content' => $content],
        ];
    }
    
    /**
     * 发送到 Slack
     */
    private function postToSlack(string $webhookUrl, array $message): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return !$error;
    }
    
    /**
     * 发送到钉钉
     */
    private function postToDingTalk(string $webhookUrl, array $message): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return !$error;
    }
}
