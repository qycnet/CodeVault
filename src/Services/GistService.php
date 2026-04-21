<?php
/**
 * CodeVault Gists 代码片段服务
 * 
 * 功能：
 * - 创建/编辑/删除 Gist
 * - 公开/私密/仅链接可见
 * - 多文件支持
 * - 版本历史
 * - Fork 功能
 * - 评论功能
 * - 星标功能
 */

namespace CodeVault\Services;

use Core\Database;
use Core\Logger;
use Services\SecurityService;

class GistService
{
    private $db;
    private $logger;
    private $security;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('gist');
        $this->security = new SecurityService();
    }
    
    /**
     * 创建 Gist
     */
    public function create(int $userId, array $data): array
    {
        // 验证必填字段
        if (empty($data['files']) || !is_array($data['files'])) {
            return ['success' => false, 'error' => '至少需要一个文件'];
        }
        
        // 生成唯一 ID
        $gistId = $this->generateGistId();
        
        // 验证可见性
        $visibility = $data['visibility'] ?? 'public';
        if (!in_array($visibility, ['public', 'private', 'link_only'])) {
            $visibility = 'public';
        }
        
        // 开始事务
        $this->db->beginTransaction();
        
        try {
            // 创建 Gist 主记录
            $sql = "INSERT INTO gists (id, user_id, description, visibility, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            
            $this->db->execute($sql, [
                $gistId,
                $userId,
                $data['description'] ?? '',
                $visibility,
            ]);
            
            // 创建初始版本
            $versionId = $this->createVersion($gistId, $userId, $data['files'], 'Initial version');
            
            // 更新当前版本
            $this->db->execute(
                "UPDATE gists SET current_version_id = ? WHERE id = ?",
                [$versionId, $gistId]
            );
            
            $this->db->commit();
            
            $this->logger->info('Gist 创建成功', ['gist_id' => $gistId, 'user_id' => $userId]);
            
            return [
                'success' => true,
                'gist' => $this->getGist($gistId, $userId),
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error('Gist 创建失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => '创建失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 创建版本
     */
    private function createVersion(string $gistId, int $userId, array $files, string $comment = ''): int
    {
        // 创建版本记录
        $sql = "INSERT INTO gist_versions (gist_id, user_id, comment, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $this->db->execute($sql, [$gistId, $userId, $comment]);
        $versionId = (int)$this->db->lastInsertId();
        
        // 添加文件
        foreach ($files as $filename => $content) {
            // 安全验证文件名
            $filename = $this->security->sanitizeFilename($filename);
            
            // 检测语言
            $language = $this->detectLanguage($filename);
            
            // 计算文件大小
            $size = strlen($content);
            
            $sql = "INSERT INTO gist_files (version_id, filename, content, language, size) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [$versionId, $filename, $content, $language, $size]);
        }
        
        return $versionId;
    }
    
    /**
     * 更新 Gist
     */
    public function update(string $gistId, int $userId, array $data): array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return ['success' => false, 'error' => 'Gist 不存在'];
        }
        
        if ($gist['user_id'] != $userId) {
            return ['success' => false, 'error' => '无权限修改'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 更新描述
            if (isset($data['description'])) {
                $this->db->execute(
                    "UPDATE gists SET description = ?, updated_at = NOW() WHERE id = ?",
                    [$data['description'], $gistId]
                );
            }
            
            // 更新可见性
            if (isset($data['visibility'])) {
                $this->db->execute(
                    "UPDATE gists SET visibility = ?, updated_at = NOW() WHERE id = ?",
                    [$data['visibility'], $gistId]
                );
            }
            
            // 如果有文件变更，创建新版本
            if (!empty($data['files'])) {
                $versionId = $this->createVersion(
                    $gistId, 
                    $userId, 
                    $data['files'], 
                    $data['comment'] ?? 'Update'
                );
                
                $this->db->execute(
                    "UPDATE gists SET current_version_id = ?, updated_at = NOW() WHERE id = ?",
                    [$versionId, $gistId]
                );
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'gist' => $this->getGist($gistId, $userId),
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '更新失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 删除 Gist
     */
    public function delete(string $gistId, int $userId): array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return ['success' => false, 'error' => 'Gist 不存在'];
        }
        
        if ($gist['user_id'] != $userId) {
            return ['success' => false, 'error' => '无权限删除'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 删除文件
            $this->db->execute(
                "DELETE gf FROM gist_files gf 
                 JOIN gist_versions gv ON gf.version_id = gv.id 
                 WHERE gv.gist_id = ?",
                [$gistId]
            );
            
            // 删除版本
            $this->db->execute("DELETE FROM gist_versions WHERE gist_id = ?", [$gistId]);
            
            // 删除评论
            $this->db->execute("DELETE FROM gist_comments WHERE gist_id = ?", [$gistId]);
            
            // 删除星标
            $this->db->execute("DELETE FROM gist_stars WHERE gist_id = ?", [$gistId]);
            
            // 删除 Fork 关联
            $this->db->execute("DELETE FROM gist_forks WHERE gist_id = ? OR forked_from = ?", [$gistId, $gistId]);
            
            // 删除 Gist
            $this->db->execute("DELETE FROM gists WHERE id = ?", [$gistId]);
            
            $this->db->commit();
            
            $this->logger->info('Gist 删除成功', ['gist_id' => $gistId, 'user_id' => $userId]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '删除失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取 Gist
     */
    public function getGist(string $gistId, ?int $userId = null): ?array
    {
        $sql = "SELECT g.*, u.username, u.name, u.avatar_url,
                       gv.id as version_id, gv.comment as version_comment, gv.created_at as version_created_at
                FROM gists g
                JOIN users u ON g.user_id = u.id
                JOIN gist_versions gv ON g.current_version_id = gv.id
                WHERE g.id = ?";
        
        $gist = $this->db->fetchOne($sql, [$gistId]);
        
        if (!$gist) {
            return null;
        }
        
        // 检查访问权限
        if ($gist['visibility'] === 'private' && $gist['user_id'] != $userId) {
            return null;
        }
        
        if ($gist['visibility'] === 'link_only' && !$userId) {
            return null;
        }
        
        // 获取文件
        $files = $this->db->fetchAll(
            "SELECT filename, content, language, size FROM gist_files WHERE version_id = ?",
            [$gist['version_id']]
        );
        
        $gist['files'] = [];
        foreach ($files as $file) {
            $gist['files'][$file['filename']] = [
                'content' => $file['content'],
                'language' => $file['language'],
                'size' => $file['size'],
            ];
        }
        
        // 获取统计
        $stats = $this->db->fetchOne(
            "SELECT 
                (SELECT COUNT(*) FROM gist_stars WHERE gist_id = ?) as stars,
                (SELECT COUNT(*) FROM gist_forks WHERE gist_id = ?) as forks,
                (SELECT COUNT(*) FROM gist_comments WHERE gist_id = ?) as comments,
                (SELECT COUNT(*) FROM gist_versions WHERE gist_id = ?) as revisions
            ",
            [$gistId, $gistId, $gistId, $gistId]
        );
        
        $gist['stats'] = $stats;
        
        // 检查用户是否已星标
        if ($userId) {
            $starred = $this->db->fetchOne(
                "SELECT 1 FROM gist_stars WHERE gist_id = ? AND user_id = ?",
                [$gistId, $userId]
            );
            $gist['starred'] = (bool)$starred;
        }
        
        return $gist;
    }
    
    /**
     * 获取 Gist 列表
     */
    public function listGists(array $params = []): array
    {
        $where = ["g.visibility = 'public'"];
        $bindings = [];
        
        // 用户筛选
        if (!empty($params['user_id'])) {
            $where[] = "g.user_id = ?";
            $bindings[] = $params['user_id'];
        }
        
        // 用户名筛选
        if (!empty($params['username'])) {
            $where[] = "u.username = ?";
            $bindings[] = $params['username'];
        }
        
        // 搜索
        if (!empty($params['q'])) {
            $where[] = "(g.description LIKE ? OR gf.filename LIKE ? OR gf.content LIKE ?)";
            $searchTerm = "%{$params['q']}%";
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        // 语言筛选
        if (!empty($params['language'])) {
            $where[] = "gf.language = ?";
            $bindings[] = $params['language'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // 排序
        $orderBy = match ($params['sort'] ?? 'updated') {
            'created' => 'g.created_at DESC',
            'updated' => 'g.updated_at DESC',
            'stars' => 'stars DESC',
            default => 'g.updated_at DESC',
        };
        
        // 分页
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 30)));
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT DISTINCT g.id, g.description, g.visibility, g.created_at, g.updated_at,
                       u.username, u.name, u.avatar_url,
                       (SELECT COUNT(*) FROM gist_stars WHERE gist_id = g.id) as stars,
                       (SELECT COUNT(*) FROM gist_forks WHERE gist_id = g.id) as forks
                FROM gists g
                JOIN users u ON g.user_id = u.id
                JOIN gist_versions gv ON g.current_version_id = gv.id
                JOIN gist_files gf ON gv.id = gf.version_id
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}";
        
        $gists = $this->db->fetchAll($sql, $bindings);
        
        // 获取每个 Gist 的文件列表
        foreach ($gists as &$gist) {
            $gist['files'] = $this->db->fetchAll(
                "SELECT filename, language, size 
                 FROM gist_files 
                 WHERE version_id = (SELECT current_version_id FROM gists WHERE id = ?)",
                [$gist['id']]
            );
        }
        
        return $gists;
    }
    
    /**
     * Fork Gist
     */
    public function fork(string $gistId, int $userId): array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return ['success' => false, 'error' => 'Gist 不存在或无权限访问'];
        }
        
        // 检查是否已 Fork
        $existing = $this->db->fetchOne(
            "SELECT id FROM gists WHERE user_id = ? AND forked_from = ?",
            [$userId, $gistId]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '已 Fork 过此 Gist'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 创建新 Gist
            $newGistId = $this->generateGistId();
            
            $sql = "INSERT INTO gists (id, user_id, description, visibility, forked_from, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $this->db->execute($sql, [
                $newGistId,
                $userId,
                $gist['description'],
                'public',
                $gistId,
            ]);
            
            // 创建版本
            $versionId = $this->createVersion($newGistId, $userId, $gist['files'], 'Forked from ' . $gistId);
            
            // 更新当前版本
            $this->db->execute(
                "UPDATE gists SET current_version_id = ? WHERE id = ?",
                [$versionId, $newGistId]
            );
            
            // 记录 Fork
            $this->db->execute(
                "INSERT INTO gist_forks (gist_id, user_id, forked_from, created_at) VALUES (?, ?, ?, NOW())",
                [$newGistId, $userId, $gistId]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'gist' => $this->getGist($newGistId, $userId),
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => 'Fork 失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 星标/取消星标
     */
    public function toggleStar(string $gistId, int $userId): array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return ['success' => false, 'error' => 'Gist 不存在'];
        }
        
        $existing = $this->db->fetchOne(
            "SELECT 1 FROM gist_stars WHERE gist_id = ? AND user_id = ?",
            [$gistId, $userId]
        );
        
        if ($existing) {
            $this->db->execute(
                "DELETE FROM gist_stars WHERE gist_id = ? AND user_id = ?",
                [$gistId, $userId]
            );
            $starred = false;
        } else {
            $this->db->execute(
                "INSERT INTO gist_stars (gist_id, user_id, created_at) VALUES (?, ?, NOW())",
                [$gistId, $userId]
            );
            $starred = true;
        }
        
        return [
            'success' => true,
            'starred' => $starred,
            'stars' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM gist_stars WHERE gist_id = ?",
                [$gistId]
            )['count'],
        ];
    }
    
    /**
     * 添加评论
     */
    public function addComment(string $gistId, int $userId, string $body): array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return ['success' => false, 'error' => 'Gist 不存在'];
        }
        
        // XSS 过滤
        $body = $this->security->sanitizeInput($body);
        
        $sql = "INSERT INTO gist_comments (gist_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())";
        $this->db->execute($sql, [$gistId, $userId, $body]);
        
        $commentId = (int)$this->db->lastInsertId();
        
        return [
            'success' => true,
            'comment' => $this->getComment($commentId),
        ];
    }
    
    /**
     * 获取评论
     */
    private function getComment(int $commentId): ?array
    {
        return $this->db->fetchOne(
            "SELECT gc.*, u.username, u.name, u.avatar_url 
             FROM gist_comments gc 
             JOIN users u ON gc.user_id = u.id 
             WHERE gc.id = ?",
            [$commentId]
        );
    }
    
    /**
     * 获取评论列表
     */
    public function getComments(string $gistId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        
        return $this->db->fetchAll(
            "SELECT gc.*, u.username, u.name, u.avatar_url 
             FROM gist_comments gc 
             JOIN users u ON gc.user_id = u.id 
             WHERE gc.gist_id = ? 
             ORDER BY gc.created_at DESC 
             LIMIT ? OFFSET ?",
            [$gistId, $perPage, $offset]
        );
    }
    
    /**
     * 获取版本历史
     */
    public function getHistory(string $gistId, ?int $userId = null): array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT gv.id, gv.comment, gv.created_at, u.username, u.name
             FROM gist_versions gv
             JOIN users u ON gv.user_id = u.id
             WHERE gv.gist_id = ?
             ORDER BY gv.created_at DESC",
            [$gistId]
        );
    }
    
    /**
     * 获取特定版本
     */
    public function getVersion(string $gistId, int $versionId, ?int $userId = null): ?array
    {
        $gist = $this->getGist($gistId, $userId);
        
        if (!$gist) {
            return null;
        }
        
        $version = $this->db->fetchOne(
            "SELECT gv.*, u.username, u.name
             FROM gist_versions gv
             JOIN users u ON gv.user_id = u.id
             WHERE gv.gist_id = ? AND gv.id = ?",
            [$gistId, $versionId]
        );
        
        if (!$version) {
            return null;
        }
        
        $files = $this->db->fetchAll(
            "SELECT filename, content, language, size FROM gist_files WHERE version_id = ?",
            [$versionId]
        );
        
        $version['files'] = [];
        foreach ($files as $file) {
            $version['files'][$file['filename']] = [
                'content' => $file['content'],
                'language' => $file['language'],
                'size' => $file['size'],
            ];
        }
        
        return $version;
    }
    
    /**
     * 生成 Gist ID
     */
    private function generateGistId(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * 检测语言
     */
    private function detectLanguage(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $map = [
            'php' => 'PHP',
            'js' => 'JavaScript',
            'ts' => 'TypeScript',
            'py' => 'Python',
            'rb' => 'Ruby',
            'java' => 'Java',
            'go' => 'Go',
            'rs' => 'Rust',
            'c' => 'C',
            'cpp' => 'C++',
            'h' => 'C',
            'hpp' => 'C++',
            'cs' => 'C#',
            'swift' => 'Swift',
            'kt' => 'Kotlin',
            'scala' => 'Scala',
            'sh' => 'Shell',
            'bash' => 'Shell',
            'zsh' => 'Shell',
            'ps1' => 'PowerShell',
            'html' => 'HTML',
            'htm' => 'HTML',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'sass' => 'Sass',
            'less' => 'Less',
            'json' => 'JSON',
            'xml' => 'XML',
            'yaml' => 'YAML',
            'yml' => 'YAML',
            'toml' => 'TOML',
            'ini' => 'INI',
            'sql' => 'SQL',
            'md' => 'Markdown',
            'markdown' => 'Markdown',
            'txt' => 'Text',
            'vue' => 'Vue',
            'jsx' => 'JSX',
            'tsx' => 'TSX',
            'dockerfile' => 'Dockerfile',
            'makefile' => 'Makefile',
        ];
        
        return $map[$ext] ?? 'Text';
    }
}
