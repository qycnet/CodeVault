<?php
/**
 * CodeVault - Release 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Services\GitService;
use CodeVault\Database\Connection;

class ReleaseController
{
    /**
     * 创建 Tag
     */
    public function createTag(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $tagName = trim($data['tag_name'] ?? '');
        $target = trim($data['target'] ?? 'main');
        $message = trim($data['message'] ?? '');
        
        if ($repoId <= 0 || empty($tagName)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 检查权限
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        // 验证 tag 名称格式
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $tagName)) {
            return ['success' => false, 'message' => 'Tag 名称格式无效'];
        }
        
        // 创建 Git Tag
        $gitPath = $repo['git_path'];
        $cmd = sprintf(
            'cd %s && git tag -a %s %s -m %s 2>&1',
            escapeshellarg($gitPath),
            escapeshellarg($tagName),
            escapeshellarg($target),
            escapeshellarg($message ?: "Release {$tagName}")
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'message' => '创建 Tag 失败: ' . implode("\n", $output)];
        }
        
        return ['success' => true, 'message' => 'Tag 创建成功'];
    }
    
    /**
     * 获取 Tag 列表
     */
    public function listTags(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $gitPath = $repo['git_path'];
        $cmd = sprintf('cd %s && git tag -l 2>&1', escapeshellarg($gitPath));
        
        exec($cmd, $output, $returnCode);
        
        $tags = [];
        foreach ($output as $tag) {
            $tag = trim($tag);
            if (empty($tag)) continue;
            
            // 获取 tag 信息
            $infoCmd = sprintf(
                'cd %s && git log -1 --format="%%H|%%s|%%ci" %s 2>&1',
                escapeshellarg($gitPath),
                escapeshellarg($tag)
            );
            
            exec($infoCmd, $infoOutput);
            $info = explode('|', $infoOutput[0] ?? '');
            
            $tags[] = [
                'name' => $tag,
                'commit' => $info[0] ?? '',
                'message' => $info[1] ?? '',
                'date' => $info[2] ?? '',
            ];
        }
        
        return ['success' => true, 'tags' => $tags];
    }
    
    /**
     * 删除 Tag
     */
    public function deleteTag(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $tagName = trim($data['tag_name'] ?? '');
        
        if ($repoId <= 0 || empty($tagName)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $gitPath = $repo['git_path'];
        $cmd = sprintf('cd %s && git tag -d %s 2>&1', escapeshellarg($gitPath), escapeshellarg($tagName));
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'message' => '删除 Tag 失败'];
        }
        
        return ['success' => true, 'message' => 'Tag 已删除'];
    }
    
    /**
     * 创建 Release
     */
    public function createRelease(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $tagName = trim($data['tag_name'] ?? '');
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $prerelease = ($data['prerelease'] ?? false) ? 1 : 0;
        
        if ($repoId <= 0 || empty($tagName)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        // 检查 tag 是否存在
        $gitPath = $repo['git_path'];
        $cmd = sprintf('cd %s && git rev-parse %s 2>&1', escapeshellarg($gitPath), escapeshellarg($tagName));
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // 创建 tag
            $createResult = $this->createTag([
                'repo_id' => $repoId,
                'tag_name' => $tagName,
                'target' => $data['target'] ?? 'main',
                'message' => $title ?: "Release {$tagName}",
            ]);
            
            if (!$createResult['success']) {
                return $createResult;
            }
        }
        
        $releaseId = Connection::insert(
            "INSERT INTO releases (repo_id, tag_name, title, body, prerelease, author_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$repoId, $tagName, $title ?: $tagName, $body, $prerelease, $user['id']]
        );
        
        return ['success' => true, 'release_id' => $releaseId];
    }
    
    /**
     * 获取 Release 列表
     */
    public function listReleases(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $releases = Connection::query(
            "SELECT r.*, u.username as author_name FROM releases r JOIN users u ON r.author_id = u.id WHERE r.repo_id = ? ORDER BY r.created_at DESC",
            [$repoId]
        );
        
        return ['success' => true, 'releases' => $releases];
    }
    
    /**
     * 更新 Release
     */
    public function updateRelease(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        
        $release = Connection::queryOne("SELECT * FROM releases WHERE id = ?", [$id]);
        if (!$release) {
            return ['success' => false, 'message' => 'Release 不存在'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$release['repo_id']]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $fields = [];
        $params = [];
        
        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = trim($data['title']);
        }
        
        if (isset($data['body'])) {
            $fields[] = "body = ?";
            $params[] = trim($data['body']);
        }
        
        if (isset($data['prerelease'])) {
            $fields[] = "prerelease = ?";
            $params[] = (int) $data['prerelease'];
        }
        
        if (empty($fields)) {
            return ['success' => true, 'message' => '无更新'];
        }
        
        $params[] = $id;
        Connection::execute("UPDATE releases SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        
        return ['success' => true, 'message' => 'Release 已更新'];
    }
    
    /**
     * 删除 Release
     */
    public function deleteRelease(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        
        $release = Connection::queryOne("SELECT * FROM releases WHERE id = ?", [$id]);
        if (!$release) {
            return ['success' => true, 'message' => '已删除'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$release['repo_id']]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        Connection::execute("DELETE FROM releases WHERE id = ?", [$id]);
        
        return ['success' => true, 'message' => 'Release 已删除'];
    }
}
