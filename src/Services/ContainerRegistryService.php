<?php
/**
 * CodeVault 容器镜像仓库服务
 * 
 * 功能：
 * - Docker 镜像存储
 * - OCI 镜像支持
 * - 镜像标签管理
 * - 漏洞扫描
 * - 访问控制
 */

namespace CodeVault\Services;

use Core\Database;
use Core\Logger;
use Services\SecurityService;

class ContainerRegistryService
{
    private $db;
    private $logger;
    private $security;
    
    // 镜像存储路径
    private const STORAGE_PATH = '/var/git/registry';
    
    // 支持的媒体类型
    private const MEDIA_TYPES = [
        'application/vnd.docker.distribution.manifest.v2+json',
        'application/vnd.docker.distribution.manifest.list.v2+json',
        'application/vnd.oci.image.manifest.v1+json',
        'application/vnd.oci.image.index.v1+json',
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('registry');
        $this->security = new SecurityService();
    }
    
    /**
     * 创建镜像仓库
     */
    public function createRepository(int $repoId, string $name, int $userId): array
    {
        // 验证名称
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name)) {
            return ['success' => false, 'error' => '无效的镜像名称'];
        }
        
        // 检查是否已存在
        $existing = $this->db->fetchOne(
            "SELECT id FROM container_repositories WHERE repo_id = ? AND name = ?",
            [$repoId, $name]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '镜像仓库已存在'];
        }
        
        $sql = "INSERT INTO container_repositories (repo_id, name, created_by, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $this->db->execute($sql, [$repoId, $name, $userId]);
        
        $registryId = (int)$this->db->lastInsertId();
        
        // 创建存储目录
        $storagePath = self::STORAGE_PATH . '/' . $name;
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        $this->logger->info('镜像仓库创建成功', ['registry_id' => $registryId, 'name' => $name]);
        
        return [
            'success' => true,
            'registry_id' => $registryId,
        ];
    }
    
    /**
     * 获取镜像仓库
     */
    public function getRepository(int $registryId): ?array
    {
        return $this->db->fetchOne(
            "SELECT cr.*, r.name as repo_name, u.username as creator_name
             FROM container_repositories cr
             JOIN repositories r ON cr.repo_id = r.id
             JOIN users u ON cr.created_by = u.id
             WHERE cr.id = ?",
            [$registryId]
        );
    }
    
    /**
     * 获取镜像仓库列表
     */
    public function listRepositories(int $repoId): array
    {
        return $this->db->fetchAll(
            "SELECT cr.*, 
                    (SELECT COUNT(*) FROM container_images WHERE registry_id = cr.id) as image_count,
                    (SELECT COUNT(*) FROM container_tags WHERE registry_id = cr.id) as tag_count
             FROM container_repositories cr
             WHERE cr.repo_id = ?
             ORDER BY cr.name",
            [$repoId]
        );
    }
    
    /**
     * 删除镜像仓库
     */
    public function deleteRepository(int $registryId, int $userId): array
    {
        $registry = $this->getRepository($registryId);
        
        if (!$registry) {
            return ['success' => false, 'error' => '镜像仓库不存在'];
        }
        
        // 检查权限
        if (!$this->canManage($registryId, $userId)) {
            return ['success' => false, 'error' => '无权限删除'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 删除标签
            $this->db->execute("DELETE FROM container_tags WHERE registry_id = ?", [$registryId]);
            
            // 删除镜像
            $this->db->execute("DELETE FROM container_images WHERE registry_id = ?", [$registryId]);
            
            // 删除仓库
            $this->db->execute("DELETE FROM container_repositories WHERE id = ?", [$registryId]);
            
            // 删除存储
            $storagePath = self::STORAGE_PATH . '/' . $registry['name'];
            if (is_dir($storagePath)) {
                exec("rm -rf " . escapeshellarg($storagePath));
            }
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 推送镜像
     */
    public function pushImage(string $name, string $reference, string $manifest, string $mediaType, int $userId): array
    {
        // 查找或创建仓库
        $registry = $this->db->fetchOne(
            "SELECT * FROM container_repositories WHERE name = ?",
            [$name]
        );
        
        if (!$registry) {
            return ['success' => false, 'error' => '镜像仓库不存在'];
        }
        
        // 验证媒体类型
        if (!in_array($mediaType, self::MEDIA_TYPES)) {
            return ['success' => false, 'error' => '不支持的媒体类型'];
        }
        
        // 解析 manifest
        $manifestData = json_decode($manifest, true);
        if (!$manifestData) {
            return ['success' => false, 'error' => '无效的 manifest'];
        }
        
        // 计算 digest
        $digest = 'sha256:' . hash('sha256', $manifest);
        
        $this->db->beginTransaction();
        
        try {
            // 创建或更新镜像
            $imageId = $this->createOrUpdateImage($registry['id'], $digest, $manifest, $mediaType, $manifestData, $userId);
            
            // 创建或更新标签
            if (!preg_match('/^sha256:/', $reference)) {
                $this->createOrUpdateTag($registry['id'], $reference, $digest, $userId);
            }
            
            $this->db->commit();
            
            $this->logger->info('镜像推送成功', [
                'name' => $name,
                'reference' => $reference,
                'digest' => $digest,
            ]);
            
            return [
                'success' => true,
                'digest' => $digest,
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 创建或更新镜像
     */
    private function createOrUpdateImage(int $registryId, string $digest, string $manifest, string $mediaType, array $manifestData, int $userId): int
    {
        // 检查是否已存在
        $existing = $this->db->fetchOne(
            "SELECT id FROM container_images WHERE registry_id = ? AND digest = ?",
            [$registryId, $digest]
        );
        
        if ($existing) {
            return (int)$existing['id'];
        }
        
        // 提取镜像信息
        $config = $manifestData['config'] ?? [];
        $layers = $manifestData['layers'] ?? [];
        
        $sql = "INSERT INTO container_images (registry_id, digest, manifest, media_type, config, layers, size, pushed_by, pushed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $registryId,
            $digest,
            $manifest,
            $mediaType,
            json_encode($config),
            json_encode($layers),
            $this->calculateSize($layers),
            $userId,
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * 创建或更新标签
     */
    private function createOrUpdateTag(int $registryId, string $tag, string $digest, int $userId): void
    {
        // 删除旧标签
        $this->db->execute(
            "DELETE FROM container_tags WHERE registry_id = ? AND name = ?",
            [$registryId, $tag]
        );
        
        // 创建新标签
        $sql = "INSERT INTO container_tags (registry_id, name, digest, pushed_by, pushed_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [$registryId, $tag, $digest, $userId]);
    }
    
    /**
     * 拉取镜像
     */
    public function pullImage(string $name, string $reference): array
    {
        $registry = $this->db->fetchOne(
            "SELECT * FROM container_repositories WHERE name = ?",
            [$name]
        );
        
        if (!$registry) {
            return ['success' => false, 'error' => '镜像仓库不存在'];
        }
        
        // 如果是 digest
        if (preg_match('/^sha256:/', $reference)) {
            $image = $this->db->fetchOne(
                "SELECT * FROM container_images WHERE registry_id = ? AND digest = ?",
                [$registry['id'], $reference]
            );
        } else {
            // 通过标签查找
            $tag = $this->db->fetchOne(
                "SELECT ct.*, ci.manifest, ci.media_type
                 FROM container_tags ct
                 JOIN container_images ci ON ct.registry_id = ci.registry_id AND ct.digest = ci.digest
                 WHERE ct.registry_id = ? AND ct.name = ?",
                [$registry['id'], $reference]
            );
            
            $image = $tag ? [
                'digest' => $tag['digest'],
                'manifest' => $tag['manifest'],
                'media_type' => $tag['media_type'],
            ] : null;
        }
        
        if (!$image) {
            return ['success' => false, 'error' => '镜像不存在'];
        }
        
        // 更新拉取计数
        $this->db->execute(
            "UPDATE container_repositories SET pull_count = pull_count + 1 WHERE id = ?",
            [$registry['id']]
        );
        
        return [
            'success' => true,
            'manifest' => $image['manifest'],
            'digest' => $image['digest'],
            'media_type' => $image['media_type'],
        ];
    }
    
    /**
     * 删除镜像
     */
    public function deleteImage(string $name, string $reference, int $userId): array
    {
        $registry = $this->db->fetchOne(
            "SELECT * FROM container_repositories WHERE name = ?",
            [$name]
        );
        
        if (!$registry) {
            return ['success' => false, 'error' => '镜像仓库不存在'];
        }
        
        if (!$this->canManage($registry['id'], $userId)) {
            return ['success' => false, 'error' => '无权限删除'];
        }
        
        $this->db->beginTransaction();
        
        try {
            if (preg_match('/^sha256:/', $reference)) {
                // 删除镜像
                $this->db->execute(
                    "DELETE FROM container_images WHERE registry_id = ? AND digest = ?",
                    [$registry['id'], $reference]
                );
                
                // 删除相关标签
                $this->db->execute(
                    "DELETE FROM container_tags WHERE registry_id = ? AND digest = ?",
                    [$registry['id'], $reference]
                );
            } else {
                // 只删除标签
                $this->db->execute(
                    "DELETE FROM container_tags WHERE registry_id = ? AND name = ?",
                    [$registry['id'], $reference]
                );
            }
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 获取镜像列表
     */
    public function listImages(int $registryId): array
    {
        return $this->db->fetchAll(
            "SELECT ci.*, 
                    (SELECT GROUP_CONCAT(name) FROM container_tags WHERE registry_id = ci.registry_id AND digest = ci.digest) as tags
             FROM container_images ci
             WHERE ci.registry_id = ?
             ORDER BY ci.pushed_at DESC",
            [$registryId]
        );
    }
    
    /**
     * 获取标签列表
     */
    public function listTags(int $registryId): array
    {
        return $this->db->fetchAll(
            "SELECT ct.*, u.username as pusher_name
             FROM container_tags ct
             JOIN users u ON ct.pushed_by = u.id
             WHERE ct.registry_id = ?
             ORDER BY ct.name",
            [$registryId]
        );
    }
    
    /**
     * 获取镜像详情
     */
    public function getImage(int $registryId, string $digest): ?array
    {
        $image = $this->db->fetchOne(
            "SELECT * FROM container_images WHERE registry_id = ? AND digest = ?",
            [$registryId, $digest]
        );
        
        if ($image) {
            $image['config'] = json_decode($image['config'], true);
            $image['layers'] = json_decode($image['layers'], true);
            
            // 获取标签
            $image['tags'] = $this->db->fetchAll(
                "SELECT name FROM container_tags WHERE registry_id = ? AND digest = ?",
                [$registryId, $digest]
            );
        }
        
        return $image;
    }
    
    /**
     * 漏洞扫描
     */
    public function scanImage(int $registryId, string $digest): array
    {
        $image = $this->getImage($registryId, $digest);
        
        if (!$image) {
            return ['success' => false, 'error' => '镜像不存在'];
        }
        
        // 模拟漏洞扫描（实际应集成 Trivy/Clair 等）
        $vulnerabilities = $this->performVulnerabilityScan($image);
        
        // 存储扫描结果
        $sql = "INSERT INTO container_scans (registry_id, digest, vulnerabilities, scanned_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE vulnerabilities = VALUES(vulnerabilities), scanned_at = NOW()";
        
        $this->db->execute($sql, [
            $registryId,
            $digest,
            json_encode($vulnerabilities),
        ]);
        
        return [
            'success' => true,
            'vulnerabilities' => $vulnerabilities,
        ];
    }
    
    /**
     * 执行漏洞扫描
     */
    private function performVulnerabilityScan(array $image): array
    {
        // 实际实现应调用 Trivy 或 Clair
        // 这里返回模拟数据
        return [
            'total' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'items' => [],
        ];
    }
    
    /**
     * 获取扫描结果
     */
    public function getScanResult(int $registryId, string $digest): ?array
    {
        $scan = $this->db->fetchOne(
            "SELECT * FROM container_scans WHERE registry_id = ? AND digest = ?",
            [$registryId, $digest]
        );
        
        if ($scan) {
            $scan['vulnerabilities'] = json_decode($scan['vulnerabilities'], true);
        }
        
        return $scan;
    }
    
    /**
     * 计算镜像大小
     */
    private function calculateSize(array $layers): int
    {
        $size = 0;
        foreach ($layers as $layer) {
            $size += $layer['size'] ?? 0;
        }
        return $size;
    }
    
    /**
     * 检查管理权限
     */
    private function canManage(int $registryId, int $userId): bool
    {
        $registry = $this->getRepository($registryId);
        
        if (!$registry) {
            return false;
        }
        
        // 检查仓库所有者
        $repo = $this->db->fetchOne(
            "SELECT user_id FROM repositories WHERE id = ?",
            [$registry['repo_id']]
        );
        
        if ($repo && $repo['user_id'] == $userId) {
            return true;
        }
        
        // 检查协作者权限
        $collab = $this->db->fetchOne(
            "SELECT permission FROM repo_collaborators WHERE repo_id = ? AND user_id = ?",
            [$registry['repo_id'], $userId]
        );
        
        return $collab && in_array($collab['permission'], ['admin', 'write']);
    }
    
    /**
     * 获取仓库统计
     */
    public function getStats(int $registryId): array
    {
        $registry = $this->getRepository($registryId);
        
        if (!$registry) {
            return [];
        }
        
        return [
            'image_count' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM container_images WHERE registry_id = ?",
                [$registryId]
            )['count'],
            'tag_count' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM container_tags WHERE registry_id = ?",
                [$registryId]
            )['count'],
            'total_size' => $this->db->fetchOne(
                "SELECT SUM(size) as total FROM container_images WHERE registry_id = ?",
                [$registryId]
            )['total'] ?? 0,
            'pull_count' => $registry['pull_count'],
        ];
    }
    
    /**
     * 清理未引用的镜像
     */
    public function garbageCollect(int $registryId): array
    {
        // 查找没有标签的镜像
        $orphanedImages = $this->db->fetchAll(
            "SELECT ci.digest FROM container_images ci
             LEFT JOIN container_tags ct ON ci.registry_id = ct.registry_id AND ci.digest = ct.digest
             WHERE ci.registry_id = ? AND ct.id IS NULL",
            [$registryId]
        );
        
        $deleted = 0;
        
        foreach ($orphanedImages as $image) {
            $this->db->execute(
                "DELETE FROM container_images WHERE registry_id = ? AND digest = ?",
                [$registryId, $image['digest']]
            );
            $deleted++;
        }
        
        $this->logger->info('镜像垃圾回收完成', ['registry_id' => $registryId, 'deleted' => $deleted]);
        
        return [
            'success' => true,
            'deleted' => $deleted,
        ];
    }
}
