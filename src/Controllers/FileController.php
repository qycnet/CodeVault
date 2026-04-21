<?php
/**
 * CodeVault - 文件上传控制器
 * 处理 Web 端文件上传到 Git 仓库
 */

namespace CodeVault\Controllers;

use CodeVault\Models\Repository;
use CodeVault\Services\Session;
use CodeVault\Services\GitService;

class FileController
{
    /**
     * 上传文件到仓库
     */
    public function upload(array $data, array $files): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        $path = trim($data['path'] ?? '');
        $message = trim($data['message'] ?? '上传文件');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 检查仓库权限
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if (!Repository::canWrite($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权上传文件到此仓库'];
        }
        
        // 检查是否有上传文件
        if (empty($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => '没有上传文件或上传失败'];
        }
        
        $file = $files['file'];
        $fileName = basename($file['name']);
        $tmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        // 文件大小限制 (10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            return ['success' => false, 'message' => '文件大小不能超过 10MB'];
        }
        
        // 安全检查：文件名
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fileName)) {
            return ['success' => false, 'message' => '文件名只能包含字母、数字、下划线、连字符和点'];
        }
        
        // 安全检查：危险文件类型（扩展黑名单）
        $dangerousExtensions = [
            // PHP 相关
            'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
            // 服务器端脚本
            'jsp', 'asp', 'aspx', 'ashx', 'asmx', 'axd',
            // 可执行文件
            'exe', 'bat', 'cmd', 'sh', 'bash', 'ps1', 'vbs',
            // Web 配置
            'htaccess', 'htpasswd',
            // 可能包含 XSS 的文件
            'html', 'htm', 'svg', 'js',
            // 其他危险类型
            'shtml', 'ssi', 'pl', 'cgi', 'py'
        ];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, $dangerousExtensions)) {
            return ['success' => false, 'message' => '不允许上传此类型的文件'];
        }
        
        // 构建目标路径
        $targetPath = empty($path) ? $fileName : rtrim($path, '/') . '/' . $fileName;
        
        try {
            $gitPath = $repo['git_path'];
            
            // 克隆到临时目录
            $tempDir = sys_get_temp_dir() . '/codevault_' . uniqid();
            $cloneUrl = $gitPath;
            
            // 获取分支列表，检查分支是否存在
            $branches = GitService::branches($gitPath);
            $branchExists = in_array($branch, $branches);
            
            // 克隆仓库
            $cmd = sprintf(
                'git clone %s %s 2>&1',
                escapeshellarg($cloneUrl),
                escapeshellarg($tempDir)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \RuntimeException('克隆仓库失败');
            }
            
            // 切换到目标分支
            if ($branchExists) {
                $cmd = sprintf(
                    'cd %s && git checkout %s 2>&1',
                    escapeshellarg($tempDir),
                    escapeshellarg($branch)
                );
                exec($cmd, $output, $returnCode);
            } else {
                // 创建新分支
                $cmd = sprintf(
                    'cd %s && git checkout -b %s 2>&1',
                    escapeshellarg($tempDir),
                    escapeshellarg($branch)
                );
                exec($cmd, $output, $returnCode);
            }
            
            // 创建目录结构
            $dirPath = dirname($tempDir . '/' . $targetPath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            
            // 移动上传文件
            if (!move_uploaded_file($tmpName, $tempDir . '/' . $targetPath)) {
                throw new \RuntimeException('文件保存失败');
            }
            
            // Git 操作
            $authorName = $user['username'] ?? 'unknown';
            $authorEmail = $user['email'] ?? 'unknown@example.com';
            
            // git add
            $cmd = sprintf(
                'cd %s && git add %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($targetPath)
            );
            exec($cmd, $output, $returnCode);
            
            // git commit
            $cmd = sprintf(
                'cd %s && git -c user.name=%s -c user.email=%s commit -m %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($authorName),
                escapeshellarg($authorEmail),
                escapeshellarg($message)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0 && !in_array('nothing to commit', $output)) {
                throw new \RuntimeException('提交失败: ' . implode("\n", $output));
            }
            
            // git push
            $cmd = sprintf(
                'cd %s && git push origin %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($branch)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \RuntimeException('推送失败');
            }
            
            // 清理临时目录
            $this->removeDirectory($tempDir);
            
            return [
                'success' => true,
                'message' => '文件上传成功',
                'file' => [
                    'name' => $fileName,
                    'path' => $targetPath,
                    'size' => $fileSize,
                    'branch' => $branch
                ]
            ];
            
        } catch (\Exception $e) {
            // 清理临时目录
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 批量上传文件
     */
    public function uploadMultiple(array $data, array $files): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        $path = trim($data['path'] ?? '');
        $message = trim($data['message'] ?? '批量上传文件');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 检查仓库权限
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if (!Repository::canWrite($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权上传文件到此仓库'];
        }
        
        // 检查是否有上传文件
        if (empty($files['files'])) {
            return ['success' => false, 'message' => '没有上传文件'];
        }
        
        $uploadedFiles = [];
        $errors = [];
        
        // 处理多个文件
        $fileCount = count($files['files']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['files']['name'][$i],
                'tmp_name' => $files['files']['tmp_name'][$i],
                'size' => $files['files']['size'][$i],
                'error' => $files['files']['error'][$i]
            ];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = $file['name'] . ': 上传失败';
                continue;
            }
            
            // 单文件上传
            $singleData = [
                'repo_id' => $repoId,
                'branch' => $branch,
                'path' => $path,
                'message' => $message
            ];
            
            $singleFiles = ['file' => $file];
            $result = $this->upload($singleData, $singleFiles);
            
            if ($result['success']) {
                $uploadedFiles[] = $result['file'];
            } else {
                $errors[] = $file['name'] . ': ' . $result['message'];
            }
        }
        
        return [
            'success' => count($uploadedFiles) > 0,
            'message' => sprintf('成功上传 %d 个文件，失败 %d 个', count($uploadedFiles), count($errors)),
            'uploaded' => $uploadedFiles,
            'errors' => $errors
        ];
    }
    
    /**
     * 删除文件
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        $filePath = trim($data['path'] ?? '');
        $message = trim($data['message'] ?? '删除文件');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        if (empty($filePath)) {
            return ['success' => false, 'message' => '文件路径不能为空'];
        }
        
        // 检查仓库权限
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if (!Repository::canWrite($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权删除此仓库的文件'];
        }
        
        try {
            $gitPath = $repo['git_path'];
            
            // 克隆到临时目录
            $tempDir = sys_get_temp_dir() . '/codevault_' . uniqid();
            
            $cmd = sprintf(
                'git clone %s %s 2>&1',
                escapeshellarg($gitPath),
                escapeshellarg($tempDir)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \RuntimeException('克隆仓库失败');
            }
            
            // 切换分支
            $cmd = sprintf(
                'cd %s && git checkout %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($branch)
            );
            exec($cmd, $output, $returnCode);
            
            // 检查文件是否存在
            $fullPath = $tempDir . '/' . $filePath;
            if (!file_exists($fullPath)) {
                $this->removeDirectory($tempDir);
                return ['success' => false, 'message' => '文件不存在'];
            }
            
            // Git 删除文件
            $authorName = $user['username'] ?? 'unknown';
            $authorEmail = $user['email'] ?? 'unknown@example.com';
            
            $cmd = sprintf(
                'cd %s && git rm %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($filePath)
            );
            exec($cmd, $output, $returnCode);
            
            // git commit
            $cmd = sprintf(
                'cd %s && git -c user.name=%s -c user.email=%s commit -m %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($authorName),
                escapeshellarg($authorEmail),
                escapeshellarg($message)
            );
            exec($cmd, $output, $returnCode);
            
            // git push
            $cmd = sprintf(
                'cd %s && git push origin %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($branch)
            );
            exec($cmd, $output, $returnCode);
            
            // 清理临时目录
            $this->removeDirectory($tempDir);
            
            return [
                'success' => true,
                'message' => '文件删除成功'
            ];
            
        } catch (\Exception $e) {
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 创建目录
     */
    public function createDirectory(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        $dirPath = trim($data['path'] ?? '');
        $message = trim($data['message'] ?? '创建目录');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        if (empty($dirPath)) {
            return ['success' => false, 'message' => '目录路径不能为空'];
        }
        
        // 检查仓库权限
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if (!Repository::canWrite($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权在此仓库创建目录'];
        }
        
        try {
            $gitPath = $repo['git_path'];
            
            // 克隆到临时目录
            $tempDir = sys_get_temp_dir() . '/codevault_' . uniqid();
            
            $cmd = sprintf(
                'git clone %s %s 2>&1',
                escapeshellarg($gitPath),
                escapeshellarg($tempDir)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \RuntimeException('克隆仓库失败');
            }
            
            // 切换分支
            $cmd = sprintf(
                'cd %s && git checkout %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($branch)
            );
            exec($cmd, $output, $returnCode);
            
            // 创建目录
            $fullPath = $tempDir . '/' . $dirPath;
            if (is_dir($fullPath)) {
                $this->removeDirectory($tempDir);
                return ['success' => false, 'message' => '目录已存在'];
            }
            
            mkdir($fullPath, 0755, true);
            
            // 创建 .gitkeep 文件
            file_put_contents($fullPath . '/.gitkeep', '');
            
            // Git 操作
            $authorName = $user['username'] ?? 'unknown';
            $authorEmail = $user['email'] ?? 'unknown@example.com';
            
            $cmd = sprintf(
                'cd %s && git add %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($dirPath . '/.gitkeep')
            );
            exec($cmd, $output, $returnCode);
            
            $cmd = sprintf(
                'cd %s && git -c user.name=%s -c user.email=%s commit -m %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($authorName),
                escapeshellarg($authorEmail),
                escapeshellarg($message)
            );
            exec($cmd, $output, $returnCode);
            
            $cmd = sprintf(
                'cd %s && git push origin %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($branch)
            );
            exec($cmd, $output, $returnCode);
            
            // 清理临时目录
            $this->removeDirectory($tempDir);
            
            return [
                'success' => true,
                'message' => '目录创建成功',
                'path' => $dirPath
            ];
            
        } catch (\Exception $e) {
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 递归删除目录
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $cmd = sprintf('rm -rf %s', escapeshellarg($dir));
        exec($cmd);
    }
}
