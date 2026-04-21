<?php
/**
 * CodeVault SSO 单点登录服务
 * 
 * 功能：
 * - SAML 2.0 认证
 * - OAuth 2.0/OIDC 认证
 * - LDAP 认证
 * - CAS 认证
 * - 多租户支持
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Config;

class SsoService
{
    private $db;
    private $logger;
    private $config;
    
    // SSO 提供商类型
    public const PROVIDER_SAML = 'saml';
    public const PROVIDER_OAUTH = 'oauth';
    public const PROVIDER_OIDC = 'oidc';
    public const PROVIDER_LDAP = 'ldap';
    public const PROVIDER_CAS = 'cas';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('sso');
        $this->config = Config::getInstance();
    }
    
    /**
     * 创建 SSO 提供商
     */
    public function createProvider(array $data): array
    {
        $type = $data['type'] ?? '';
        
        if (!in_array($type, [self::PROVIDER_SAML, self::PROVIDER_OAUTH, self::PROVIDER_OIDC, self::PROVIDER_LDAP, self::PROVIDER_CAS])) {
            return ['success' => false, 'error' => '不支持的 SSO 类型'];
        }
        
        $name = $data['name'] ?? '';
        if (empty($name)) {
            return ['success' => false, 'error' => '提供商名称不能为空'];
        }
        
        // 检查名称是否已存在
        $existing = $this->db->fetchOne(
            "SELECT id FROM sso_providers WHERE name = ?",
            [$name]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '提供商名称已存在'];
        }
        
        $sql = "INSERT INTO sso_providers (name, type, config, enabled, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $name,
            $type,
            json_encode($data['config'] ?? []),
            $data['enabled'] ?? true,
        ]);
        
        $providerId = (int)$this->db->lastInsertId();
        
        $this->logger->info('SSO 提供商创建成功', ['provider_id' => $providerId, 'type' => $type]);
        
        return [
            'success' => true,
            'provider_id' => $providerId,
        ];
    }
    
    /**
     * 获取 SSO 提供商
     */
    public function getProvider(int $providerId): ?array
    {
        $provider = $this->db->fetchOne(
            "SELECT * FROM sso_providers WHERE id = ?",
            [$providerId]
        );
        
        if ($provider) {
            $provider['config'] = json_decode($provider['config'], true);
        }
        
        return $provider;
    }
    
    /**
     * 获取启用的 SSO 提供商列表
     */
    public function getEnabledProviders(): array
    {
        $providers = $this->db->fetchAll(
            "SELECT id, name, type FROM sso_providers WHERE enabled = 1 ORDER BY name"
        );
        
        return $providers;
    }
    
    /**
     * 更新 SSO 提供商
     */
    public function updateProvider(int $providerId, array $data): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider) {
            return ['success' => false, 'error' => '提供商不存在'];
        }
        
        $updates = [];
        $bindings = [];
        
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $bindings[] = $data['name'];
        }
        
        if (isset($data['config'])) {
            $updates[] = 'config = ?';
            $bindings[] = json_encode($data['config']);
        }
        
        if (isset($data['enabled'])) {
            $updates[] = 'enabled = ?';
            $bindings[] = $data['enabled'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            return ['success' => true, 'provider' => $provider];
        }
        
        $bindings[] = $providerId;
        
        $sql = "UPDATE sso_providers SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $bindings);
        
        return [
            'success' => true,
            'provider' => $this->getProvider($providerId),
        ];
    }
    
    /**
     * 删除 SSO 提供商
     */
    public function deleteProvider(int $providerId): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider) {
            return ['success' => false, 'error' => '提供商不存在'];
        }
        
        $this->db->execute("DELETE FROM sso_providers WHERE id = ?", [$providerId]);
        
        return ['success' => true];
    }
    
    /**
     * SAML 认证 - 生成 AuthnRequest
     */
    public function samlLogin(int $providerId): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider || $provider['type'] !== self::PROVIDER_SAML) {
            return ['success' => false, 'error' => '无效的 SAML 提供商'];
        }
        
        $config = $provider['config'];
        
        // 生成 SAML AuthnRequest
        $requestId = '_' . bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');
        $acsUrl = $this->config->get('app.url') . '/auth/saml/acs';
        
        $authnRequest = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    ID="{$requestId}"
                    Version="2.0"
                    IssueInstant="{$issueInstant}"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                    AssertionConsumerServiceURL="{$acsUrl}">
    <saml:Issuer xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">{$config['entity_id']}</saml:Issuer>
    <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress"
                        AllowCreate="true"/>
</samlp:AuthnRequest>
XML;
        
        // Base64 编码
        $encodedRequest = base64_encode($authnRequest);
        
        // 存储 request ID 用于验证
        $this->db->execute(
            "INSERT INTO sso_sessions (request_id, provider_id, created_at) VALUES (?, ?, NOW())",
            [$requestId, $providerId]
        );
        
        return [
            'success' => true,
            'sso_url' => $config['sso_url'],
            'request_id' => $requestId,
            'saml_request' => $encodedRequest,
        ];
    }
    
    /**
     * SAML 认证 - 处理响应
     */
    public function samlAcs(string $samlResponse): array
    {
        // 解码 SAML Response
        $response = base64_decode($samlResponse);
        
        // 解析 XML
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            return ['success' => false, 'error' => '无效的 SAML 响应'];
        }
        
        $xml->registerXPathNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xml->registerXPathNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        
        // 提取用户信息
        $nameId = (string)$xml->xpath('//saml:NameID')[0];
        $attributes = [];
        
        foreach ($xml->xpath('//saml:Attribute') as $attr) {
            $name = (string)$attr['Name'];
            $values = [];
            foreach ($attr->xpath('saml:AttributeValue') as $value) {
                $values[] = (string)$value;
            }
            $attributes[$name] = $values;
        }
        
        // 查找或创建用户
        $user = $this->findOrCreateUser($nameId, $attributes);
        
        if (!$user) {
            return ['success' => false, 'error' => '用户创建失败'];
        }
        
        // 生成令牌
        $token = $this->generateToken($user['id']);
        
        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
        ];
    }
    
    /**
     * OAuth/OIDC 认证 - 获取授权 URL
     */
    public function oauthAuthorize(int $providerId, string $redirectUri): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider || !in_array($provider['type'], [self::PROVIDER_OAUTH, self::PROVIDER_OIDC])) {
            return ['success' => false, 'error' => '无效的 OAuth 提供商'];
        }
        
        $config = $provider['config'];
        $state = bin2hex(random_bytes(32));
        
        // 存储 state 用于验证
        $this->db->execute(
            "INSERT INTO sso_sessions (request_id, provider_id, redirect_uri, created_at) VALUES (?, ?, ?, NOW())",
            [$state, $providerId, $redirectUri]
        );
        
        $params = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $config['scope'] ?? 'openid profile email',
            'state' => $state,
        ]);
        
        return [
            'success' => true,
            'authorize_url' => $config['authorize_url'] . '?' . $params,
            'state' => $state,
        ];
    }
    
    /**
     * OAuth/OIDC 认证 - 处理回调
     */
    public function oauthCallback(string $code, string $state): array
    {
        // 验证 state
        $session = $this->db->fetchOne(
            "SELECT * FROM sso_sessions WHERE request_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            [$state]
        );
        
        if (!$session) {
            return ['success' => false, 'error' => '无效的 state'];
        }
        
        $provider = $this->getProvider($session['provider_id']);
        
        if (!$provider) {
            return ['success' => false, 'error' => '提供商不存在'];
        }
        
        $config = $provider['config'];
        
        // 交换 code 获取 token
        $tokenData = $this->exchangeCodeForToken($code, $config, $session['redirect_uri']);
        
        if (!$tokenData) {
            return ['success' => false, 'error' => 'Token 获取失败'];
        }
        
        // 获取用户信息
        $userInfo = $this->fetchUserInfo($tokenData['access_token'], $config);
        
        if (!$userInfo) {
            return ['success' => false, 'error' => '用户信息获取失败'];
        }
        
        // 查找或创建用户
        $user = $this->findOrCreateUser(
            $userInfo['email'] ?? $userInfo['sub'],
            [
                'email' => [$userInfo['email'] ?? ''],
                'name' => [$userInfo['name'] ?? ''],
                'picture' => [$userInfo['picture'] ?? ''],
            ]
        );
        
        if (!$user) {
            return ['success' => false, 'error' => '用户创建失败'];
        }
        
        // 生成令牌
        $token = $this->generateToken($user['id']);
        
        // 清理 session
        $this->db->execute("DELETE FROM sso_sessions WHERE request_id = ?", [$state]);
        
        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
        ];
    }
    
    /**
     * LDAP 认证
     */
    public function ldapAuthenticate(int $providerId, string $username, string $password): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider || $provider['type'] !== self::PROVIDER_LDAP) {
            return ['success' => false, 'error' => '无效的 LDAP 提供商'];
        }
        
        $config = $provider['config'];
        
        // 连接 LDAP
        $connection = ldap_connect($config['host'], $config['port'] ?? 389);
        
        if (!$connection) {
            return ['success' => false, 'error' => 'LDAP 连接失败'];
        }
        
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        
        // 构造 DN
        $dn = str_replace('{username}', $username, $config['bind_dn']);
        
        // 绑定认证
        $bind = @ldap_bind($connection, $dn, $password);
        
        if (!$bind) {
            ldap_close($connection);
            return ['success' => false, 'error' => '认证失败'];
        }
        
        // 搜索用户信息
        $search = ldap_search(
            $connection,
            $config['base_dn'],
            str_replace('{username}', $username, $config['filter'] ?? '(uid={username})')
        );
        
        $entries = ldap_get_entries($connection, $search);
        
        if ($entries['count'] === 0) {
            ldap_close($connection);
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        $entry = $entries[0];
        
        // 提取用户信息
        $email = $entry['mail'][0] ?? '';
        $name = $entry['cn'][0] ?? $username;
        
        ldap_close($connection);
        
        // 查找或创建用户
        $user = $this->findOrCreateUser($email ?: $username, [
            'email' => [$email],
            'name' => [$name],
        ]);
        
        if (!$user) {
            return ['success' => false, 'error' => '用户创建失败'];
        }
        
        // 生成令牌
        $token = $this->generateToken($user['id']);
        
        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
        ];
    }
    
    /**
     * CAS 认证 - 获取登录 URL
     */
    public function casLogin(int $providerId, string $serviceUrl): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider || $provider['type'] !== self::PROVIDER_CAS) {
            return ['success' => false, 'error' => '无效的 CAS 提供商'];
        }
        
        $config = $provider['config'];
        
        $loginUrl = $config['login_url'] . '?service=' . urlencode($serviceUrl);
        
        return [
            'success' => true,
            'login_url' => $loginUrl,
        ];
    }
    
    /**
     * CAS 认证 - 验证 ticket
     */
    public function casValidate(int $providerId, string $ticket, string $serviceUrl): array
    {
        $provider = $this->getProvider($providerId);
        
        if (!$provider || $provider['type'] !== self::PROVIDER_CAS) {
            return ['success' => false, 'error' => '无效的 CAS 提供商'];
        }
        
        $config = $provider['config'];
        
        // 验证 ticket
        $validateUrl = $config['validate_url'] . '?service=' . urlencode($serviceUrl) . '&ticket=' . urlencode($ticket);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $validateUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // 解析 CAS 响应
        if (strpos($response, '<cas:authenticationSuccess>') !== false) {
            preg_match('/<cas:user>([^<]+)<\/cas:user>/', $response, $matches);
            $username = $matches[1] ?? '';
            
            if (empty($username)) {
                return ['success' => false, 'error' => '无法获取用户名'];
            }
            
            // 查找或创建用户
            $user = $this->findOrCreateUser($username, [
                'name' => [$username],
            ]);
            
            if (!$user) {
                return ['success' => false, 'error' => '用户创建失败'];
            }
            
            // 生成令牌
            $token = $this->generateToken($user['id']);
            
            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
            ];
        }
        
        return ['success' => false, 'error' => 'CAS 认证失败'];
    }
    
    /**
     * 查找或创建用户
     */
    private function findOrCreateUser(string $identifier, array $attributes): ?array
    {
        // 尝试通过 SSO 标识符查找
        $ssoUser = $this->db->fetchOne(
            "SELECT u.* FROM users u JOIN sso_users su ON u.id = su.user_id WHERE su.identifier = ?",
            [$identifier]
        );
        
        if ($ssoUser) {
            return $ssoUser;
        }
        
        // 尝试通过邮箱查找
        $email = $attributes['email'][0] ?? '';
        
        if (!empty($email)) {
            $existingUser = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = ?",
                [$email]
            );
            
            if ($existingUser) {
                // 关联 SSO
                $this->db->execute(
                    "INSERT INTO sso_users (user_id, identifier, provider_data, created_at) VALUES (?, ?, ?, NOW())",
                    [$existingUser['id'], $identifier, json_encode($attributes)]
                );
                
                return $existingUser;
            }
        }
        
        // 创建新用户
        $username = $this->generateUsername($identifier);
        $name = $attributes['name'][0] ?? $username;
        
        $sql = "INSERT INTO users (username, email, name, password, created_at) VALUES (?, ?, ?, '', NOW())";
        
        $this->db->execute($sql, [$username, $email, $name]);
        
        $userId = (int)$this->db->lastInsertId();
        
        // 关联 SSO
        $this->db->execute(
            "INSERT INTO sso_users (user_id, identifier, provider_data, created_at) VALUES (?, ?, ?, NOW())",
            [$userId, $identifier, json_encode($attributes)]
        );
        
        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }
    
    /**
     * 生成用户名
     */
    private function generateUsername(string $identifier): string
    {
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $identifier);
        $username = $base;
        $counter = 1;
        
        while ($this->db->fetchOne("SELECT 1 FROM users WHERE username = ?", [$username])) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * 生成认证令牌
     */
    private function generateToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        
        $this->db->execute(
            "INSERT INTO auth_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())",
            [$userId, $token]
        );
        
        return $token;
    }
    
    /**
     * 交换 code 获取 token
     */
    private function exchangeCodeForToken(string $code, array $config, string $redirectUri): ?array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['token_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * 获取用户信息
     */
    private function fetchUserInfo(string $accessToken, array $config): ?array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['userinfo_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
}
