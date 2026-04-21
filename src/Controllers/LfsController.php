<?php
/**
 * CodeVault - Git LFS 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\LfsService;
use CodeVault\Services\Session;

class LfsController
{
    private $lfsService;
    
    public function __construct()
    {
        $this->lfsService = new LfsService();
    }
    
    /**
     * LFS 批量 API
     */
    public function batch(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['message' => 'Authentication required'], 401;
        }
        
        $operation = $data['operation'] ?? '';
        $repoId = (int) ($data['repo_id'] ?? 0);
        $objects = $data['objects'] ?? [];
        
        if ($operation === 'upload') {
            return $this->lfsService->handleUpload($repoId, $objects);
        } elseif ($operation === 'download') {
            return $this->lfsService->handleDownload($repoId, $objects);
        }
        
        return ['message' => 'Invalid operation'], 400;
    }
    
    /**
     * 上传 LFS 对象
     */
    public function upload(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        $oid = $data['oid'] ?? '';
        
        // 获取请求体
        $content = file_get_contents('php://input');
        
        if (empty($oid) || empty($content)) {
            return ['success' => false, 'message' => 'Invalid request'];
        }
        
        return $this->lfsService->uploadObject($repoId, $oid, $content);
    }
    
    /**
     * 下载 LFS 对象
     */
    public function download(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        $oid = $data['oid'] ?? '';
        
        $content = $this->lfsService->downloadObject($repoId, $oid);
        
        if ($content === null) {
            return ['success' => false, 'message' => 'Object not found'];
        }
        
        // 直接输出内容
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
    
    /**
     * 验证 LFS 对象
     */
    public function verify(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        $oid = $data['oid'] ?? '';
        $size = (int) ($data['size'] ?? 0);
        
        // 验证对象
        $exists = $this->lfsService->objectExists($oid, $size);
        
        return [
            'success' => $exists,
            'message' => $exists ? 'Object verified' : 'Object not found or size mismatch',
        ];
    }
    
    /**
     * 获取 LFS 统计
     */
    public function stats(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        return $this->lfsService->getStats($repoId);
    }
    
    /**
     * 清理未引用对象
     */
    public function cleanup(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        return $this->lfsService->cleanup($repoId);
    }
}
