<?php
/**
 * CodeVault - Git LFS 服务
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class LfsService
{
    private $lfsPath = '/var/git/lfs';
    
    /**
     * 处理 LFS 上传请求
     */
    public function handleUpload(int $repoId, array $objects): array
    {
        $result = ['objects' => []];
        
        foreach ($objects as $object) {
            $oid = $object['oid'] ?? '';
            $size = (int) ($object['size'] ?? 0);
            
            // 检查对象是否已存在
            if ($this->objectExists($oid, $size)) {
                $result['objects'][] = [
                    'oid' => $oid,
                    'size' => $size,
                    'actions' => [],
                    'authenticated' => true,
                ];
            } else {
                // 生成上传 URL
                $uploadUrl = $this->generateUploadUrl($repoId, $oid);
                $verifyUrl = $this->generateVerifyUrl($repoId, $oid);
                
                $result['objects'][] = [
                    'oid' => $oid,
                    'size' => $size,
                    'actions' => [
                        'upload' => [
                            'href' => $uploadUrl,
                            'header' => [
                                'Authorization' => 'Bearer ' . $this->generateToken($repoId),
                            ],
                            'expires_in' => 86400,
                        ],
                    ],
                    'authenticated' => true,
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * 处理 LFS 下载请求
     */
    public function handleDownload(int $repoId, array $objects): array
    {
        $result = ['objects' => []];
        
        foreach ($objects as $object) {
            $oid = $object['oid'] ?? '';
            $size = (int) ($object['size'] ?? 0);
            
            if ($this->objectExists($oid, $size)) {
                $downloadUrl = $this->generateDownloadUrl($repoId, $oid);
                
                $result['objects'][] = [
                    'oid' => $oid,
                    'size' => $size,
                    'actions' => [
                        'download' => [
                            'href' => $downloadUrl,
                            'header' => [
                                'Authorization' => 'Bearer ' . $this->generateToken($repoId),
                            ],
                            'expires_in' => 86400,
                        ],
                    ],
                    'authenticated' => true,
                ];
            } else {
                $result['objects'][] = [
                    'oid' => $oid,
                    'size' => $size,
                    'error' => [
                        'code' => 404,
                        'message' => 'Object not found',
                    ],
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * 上传 LFS 对象
     */
    public function uploadObject(int $repoId, string $oid, $content): array
    {
        $objectPath = $this->getObjectPath($oid);
        $dir = dirname($objectPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 验证 SHA256
        $hash = hash('sha256', $content);
        if ($hash !== $oid) {
            return [
                'success' => false,
                'message' => 'Hash mismatch',
            ];
        }
        
        // 保存文件
        file_put_contents($objectPath, $content);
        
        // 记录到数据库
        Connection::insert(
            "INSERT INTO lfs_objects (repo_id, oid, size, path, created_at) VALUES (?, ?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE updated_at = NOW()",
            [$repoId, $oid, strlen($content), $objectPath]
        );
        
        return [
            'success' => true,
            'oid' => $oid,
            'size' => strlen($content),
        ];
    }
    
    /**
     * 下载 LFS 对象
     */
    public function downloadObject(int $repoId, string $oid): ?string
    {
        $objectPath = $this->getObjectPath($oid);
        
        if (!file_exists($objectPath)) {
            return null;
        }
        
        return file_get_contents($objectPath);
    }
    
    /**
     * 检查对象是否存在
     */
    private function objectExists(string $oid, int $size): bool
    {
        $objectPath = $this->getObjectPath($oid);
        
        if (!file_exists($objectPath)) {
            return false;
        }
        
        $actualSize = filesize($objectPath);
        return $actualSize === $size;
    }
    
    /**
     * 获取对象存储路径
     */
    private function getObjectPath(string $oid): string
    {
        // LFS 使用分片存储：objects/aa/bb/aabbccdd...
        $shard1 = substr($oid, 0, 2);
        $shard2 = substr($oid, 2, 2);
        
        return "{$this->lfsPath}/objects/{$shard1}/{$shard2}/{$oid}";
    }
    
    /**
     * 生成上传 URL
     */
    private function generateUploadUrl(int $repoId, string $oid): string
    {
        $baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        return "{$baseUrl}/api/lfs/objects/{$repoId}/{$oid}";
    }
    
    /**
     * 生成下载 URL
     */
    private function generateDownloadUrl(int $repoId, string $oid): string
    {
        $baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        return "{$baseUrl}/api/lfs/objects/{$repoId}/{$oid}";
    }
    
    /**
     * 生成验证 URL
     */
    private function generateVerifyUrl(int $repoId, string $oid): string
    {
        $baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        return "{$baseUrl}/api/lfs/verify/{$repoId}/{$oid}";
    }
    
    /**
     * 生成访问令牌
     */
    private function generateToken(int $repoId): string
    {
        $payload = [
            'repo_id' => $repoId,
            'exp' => time() + 86400,
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * 验证访问令牌
     */
    public function verifyToken(string $token): ?array
    {
        $payload = json_decode(base64_decode($token), true);
        
        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * 获取仓库 LFS 统计
     */
    public function getStats(int $repoId): array
    {
        $stats = Connection::queryOne(
            "SELECT COUNT(*) as count, SUM(size) as total_size FROM lfs_objects WHERE repo_id = ?",
            [$repoId]
        );
        
        return [
            'object_count' => (int) ($stats['count'] ?? 0),
            'total_size' => (int) ($stats['total_size'] ?? 0),
            'total_size_human' => $this->formatBytes($stats['total_size'] ?? 0),
        ];
    }
    
    /**
     * 清理未引用的 LFS 对象
     */
    public function cleanup(int $repoId): array
    {
        // 获取仓库的所有 LFS 对象
        $objects = Connection::query(
            "SELECT * FROM lfs_objects WHERE repo_id = ?",
            [$repoId]
        );
        
        $cleaned = 0;
        $freedSize = 0;
        
        foreach ($objects as $object) {
            // 检查是否被引用
            $referenced = $this->isObjectReferenced($repoId, $object['oid']);
            
            if (!$referenced) {
                // 删除文件
                if (file_exists($object['path'])) {
                    $freedSize += filesize($object['path']);
                    unlink($object['path']);
                }
                
                // 删除记录
                Connection::execute("DELETE FROM lfs_objects WHERE id = ?", [$object['id']]);
                $cleaned++;
            }
        }
        
        return [
            'cleaned_objects' => $cleaned,
            'freed_size' => $freedSize,
            'freed_size_human' => $this->formatBytes($freedSize),
        ];
    }
    
    /**
     * 检查对象是否被引用
     */
    private function isObjectReferenced(int $repoId, string $oid): bool
    {
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return false;
        }
        
        // 在 Git 仓库中搜索 LFS 指针
        $cmd = sprintf(
            'cd %s && git grep -l "oid sha256:%s" 2>/dev/null | head -1',
            escapeshellarg($repo['git_path']),
            escapeshellarg($oid)
        );
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0 && !empty($output);
    }
    
    /**
     * 格式化字节
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
