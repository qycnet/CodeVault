<?php
/**
 * CodeVault - SSH Key 管理类
 * PHP 原生开发，支持 RSA/ED25519 公钥
 */

require_once __DIR__ . '/../config/database.php';

class SSHKey
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * 添加 SSH 公钥
     * @param int $userId 用户ID
     * @param string $title 标题
     * @param string $publicKey 公钥内容
     * @return array Key 信息
     * @throws Exception
     */
    public function addKey(int $userId, string $title, string $publicKey): array
    {
        $title = trim($title);
        $publicKey = trim($publicKey);

        if (empty($title) || strlen($title) > 100) {
            throw new Exception('标题长度无效（1-100字符）');
        }

        // 验证公钥格式
        $keyData = $this->parsePublicKey($publicKey);
        if (!$keyData) {
            throw new Exception('SSH 公钥格式无效');
        }

        // 计算指纹
        $fingerprint = $this->calculateFingerprint($keyData['key']);

        // 检查指纹是否已存在
        if ($this->fingerprintExists($fingerprint)) {
            throw new Exception('该 SSH 公钥已被添加');
        }

        // 插入数据库
        $stmt = $this->db->prepare(
            'INSERT INTO ssh_keys (user_id, title, public_key, fingerprint) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $title, $publicKey, $fingerprint]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'title' => $title,
            'fingerprint' => $fingerprint,
            'type' => $keyData['type'],
        ];
    }

    /**
     * 获取用户的所有 SSH Key
     * @param int $userId 用户ID
     * @return array Key 列表
     */
    public function getUserKeys(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, fingerprint, created_at FROM ssh_keys WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * 删除 SSH Key
     * @param int $userId 用户ID
     * @param int $keyId Key ID
     * @throws Exception
     */
    public function deleteKey(int $userId, int $keyId): void
    {
        $stmt = $this->db->prepare('DELETE FROM ssh_keys WHERE id = ? AND user_id = ?');
        $stmt->execute([$keyId, $userId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('SSH Key 不存在或无权删除');
        }
    }

    /**
     * 验证用户是否拥有该 Key
     * @param int $userId 用户ID
     * @param string $fingerprint 指纹
     * @return bool
     */
    public function verifyKeyOwnership(int $userId, string $fingerprint): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM ssh_keys WHERE user_id = ? AND fingerprint = ?'
        );
        $stmt->execute([$userId, $fingerprint]);
        return $stmt->fetch() !== false;
    }

    /**
     * 根据指纹获取用户ID
     * @param string $fingerprint 指纹
     * @return int|null 用户ID
     */
    public function getUserIdByFingerprint(string $fingerprint): ?int
    {
        $stmt = $this->db->prepare('SELECT user_id FROM ssh_keys WHERE fingerprint = ?');
        $stmt->execute([$fingerprint]);
        $result = $stmt->fetch();
        return $result ? (int)$result['user_id'] : null;
    }

    // ==================== 私有方法 ====================

    /**
     * 解析 SSH 公钥
     * @param string $publicKey 公钥字符串
     * @return array|null ['type' => 'ssh-rsa', 'key' => base64_key, 'comment' => '...'] 或 null
     */
    private function parsePublicKey(string $publicKey): ?array
    {
        // SSH 公钥格式: type base64-key comment
        $parts = preg_split('/\s+/', $publicKey, 3);
        
        if (count($parts) < 2) {
            return null;
        }

        $type = $parts[0];
        $key = $parts[1];
        $comment = $parts[2] ?? '';

        // 支持的类型
        $allowedTypes = ['ssh-rsa', 'ssh-ed25519', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521'];
        
        if (!in_array($type, $allowedTypes, true)) {
            return null;
        }

        // 验证 base64
        $decoded = base64_decode($key, true);
        if ($decoded === false) {
            return null;
        }

        return [
            'type' => $type,
            'key' => $key,
            'comment' => $comment,
        ];
    }

    /**
     * 计算指纹 (SHA256)
     * @param string $base64Key base64 编码的公钥
     * @return string SHA256 指纹
     */
    private function calculateFingerprint(string $base64Key): string
    {
        $decoded = base64_decode($base64Key, true);
        return 'SHA256:' . base64_encode(hash('sha256', $decoded, true));
    }

    private function fingerprintExists(string $fingerprint): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM ssh_keys WHERE fingerprint = ?');
        $stmt->execute([$fingerprint]);
        return $stmt->fetch() !== false;
    }
}
