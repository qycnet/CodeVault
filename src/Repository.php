<?php
/**
 * CodeVault - 代码仓库管理类
 * PHP 原生开发，Git 存储路径: /var/git/repositories/{owner}/{repo}.git
 */

require_once __DIR__ . '/../config/database.php';

class Repository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * 创建仓库
     * @param int $ownerId 所有者ID
     * @param string $name 仓库名
     * @param string $description 描述
     * @param bool $isPrivate 是否私有
     * @return array 仓库信息
     * @throws Exception
     */
    public function create(int $ownerId, string $name, string $description = '', bool $isPrivate = false): array
    {
        $name = trim($name);
        $description = trim($description);

        // 验证仓库名
        if (!$this->validateRepoName($name)) {
            throw new Exception('仓库名格式无效（1-100字符，字母数字下划线连字符）');
        }

        // 检查是否已存在
        if ($this->repoExists($ownerId, $name)) {
            throw new Exception('仓库名已被使用');
        }

        // 获取所有者用户名
        $stmt = $this->db->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$ownerId]);
        $owner = $stmt->fetch();
        if (!$owner) {
            throw new Exception('用户不存在');
        }

        // 构建 Git 路径
        $gitPath = sprintf('%s/%s/%s.git', GIT_REPOS_PATH, $owner['username'], $name);

        // 初始化 Git 裸仓库
        $this->initGitRepo($gitPath);

        // 插入数据库
        $stmt = $this->db->prepare(
            'INSERT INTO repositories (owner_id, name, description, is_private, git_path) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ownerId, $name, $description, (int)$isPrivate, $gitPath]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'owner_id' => $ownerId,
            'name' => $name,
            'description' => $description,
            'is_private' => $isPrivate,
            'git_path' => $gitPath,
        ];
    }

    /**
     * 获取仓库详情
     * @param int $repoId 仓库ID
     * @return array|null
     */
    public function get(int $repoId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.username as owner_name FROM repositories r 
             JOIN users u ON r.owner_id = u.id 
             WHERE r.id = ?'
        );
        $stmt->execute([$repoId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 根据所有者和名称获取仓库
     * @param string $ownerName 所有者用户名
     * @param string $repoName 仓库名
     * @return array|null
     */
    public function getByName(string $ownerName, string $repoName): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.username as owner_name FROM repositories r 
             JOIN users u ON r.owner_id = u.id 
             WHERE u.username = ? AND r.name = ?'
        );
        $stmt->execute([$ownerName, $repoName]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 获取用户的仓库列表
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserRepos(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, is_private, created_at, updated_at 
             FROM repositories WHERE owner_id = ? ORDER BY updated_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * 更新仓库
     * @param int $userId 当前用户ID
     * @param int $repoId 仓库ID
     * @param array $data 更新数据
     * @return array
     * @throws Exception
     */
    public function update(int $userId, int $repoId, array $data): array
    {
        $repo = $this->get($repoId);
        if (!$repo) {
            throw new Exception('仓库不存在');
        }

        if ($repo['owner_id'] !== $userId) {
            throw new Exception('无权修改此仓库');
        }

        $updates = [];
        $params = [];

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = trim($data['description']);
        }

        if (isset($data['is_private'])) {
            $updates[] = 'is_private = ?';
            $params[] = (int)$data['is_private'];
        }

        if (empty($updates)) {
            return $repo;
        }

        $params[] = $repoId;
        $stmt = $this->db->prepare('UPDATE repositories SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        return $this->get($repoId);
    }

    /**
     * 删除仓库
     * @param int $userId 当前用户ID
     * @param int $repoId 仓库ID
     * @throws Exception
     */
    public function delete(int $userId, int $repoId): void
    {
        $repo = $this->get($repoId);
        if (!$repo) {
            throw new Exception('仓库不存在');
        }

        if ($repo['owner_id'] !== $userId) {
            throw new Exception('无权删除此仓库');
        }

        // 删除 Git 目录
        $this->deleteGitRepo($repo['git_path']);

        // 删除数据库记录
        $stmt = $this->db->prepare('DELETE FROM repositories WHERE id = ?');
        $stmt->execute([$repoId]);
    }

    // ==================== 私有方法 ====================

    private function validateRepoName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $name) === 1;
    }

    private function repoExists(int $ownerId, string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM repositories WHERE owner_id = ? AND name = ?');
        $stmt->execute([$ownerId, $name]);
        return $stmt->fetch() !== false;
    }

    /**
     * 初始化 Git 裸仓库
     * @param string $gitPath Git 路径
     * @throws Exception
     */
    private function initGitRepo(string $gitPath): void
    {
        // 创建目录
        $dir = dirname($gitPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new Exception('无法创建仓库目录');
        }

        // 初始化裸仓库
        $cmd = sprintf('git init --bare %s', escapeshellarg($gitPath));
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception('Git 仓库初始化失败');
        }

        // 设置权限
        chmod($gitPath, 0755);
    }

    /**
     * 删除 Git 仓库目录
     * @param string $gitPath Git 路径
     */
    private function deleteGitRepo(string $gitPath): void
    {
        if (is_dir($gitPath)) {
            $this->recursiveDelete($gitPath);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->recursiveDelete($file) : unlink($file);
        }
        rmdir($dir);
    }
}
