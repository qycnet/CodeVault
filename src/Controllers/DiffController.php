<?php
/**
 * CodeVault - Diff 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\DiffService;
use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class DiffController
{
    private DiffService $diffService;
    
    public function __construct()
    {
        $this->diffService = new DiffService();
    }
    
    /**
     * 比较两个分支
     */
    public function compare(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $base = trim($data['base'] ?? 'main');
        $head = trim($data['head'] ?? '');
        
        if ($repoId <= 0 || empty($head)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $result = $this->diffService->compareBranches($repo['git_path'], $base, $head);
        
        return $result;
    }
    
    /**
     * 获取文件差异
     */
    public function fileDiff(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $fromCommit = trim($data['from'] ?? '');
        $toCommit = trim($data['to'] ?? '');
        $file = trim($data['file'] ?? '');
        $view = $data['view'] ?? 'unified'; // unified | side-by-side
        
        if ($repoId <= 0 || empty($fromCommit) || empty($toCommit) || empty($file)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if ($view === 'side-by-side') {
            $diff = $this->diffService->getSideBySideDiff($repo['git_path'], $fromCommit, $toCommit, $file);
        } else {
            $diff = $this->diffService->getFileDiff($repo['git_path'], $fromCommit, $toCommit, $file);
        }
        
        return [
            'success' => true,
            'diff' => $diff,
            'view' => $view,
        ];
    }
    
    /**
     * 获取 PR 差异
     */
    public function prDiff(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $prId = (int) ($data['pr_id'] ?? 0);
        
        if ($prId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $pr = Connection::queryOne(
            "SELECT pr.*, r.git_path, r.id as repo_id 
             FROM pull_requests pr 
             JOIN repositories r ON pr.repo_id = r.id 
             WHERE pr.id = ?",
            [$prId]
        );
        
        if (!$pr) {
            return ['success' => false, 'message' => 'PR 不存在'];
        }
        
        $diff = $this->diffService->getPRDiff($pr['git_path'], $pr['base_branch'], $pr['head_branch']);
        
        return [
            'success' => true,
            'pr_id' => $prId,
            'files' => $diff['files'],
            'stats' => $diff['stats'],
        ];
    }
    
    /**
     * 获取提交差异
     */
    public function commitDiff(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $commit = trim($data['commit'] ?? '');
        
        if ($repoId <= 0 || empty($commit)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        // 获取父提交
        $cmd = sprintf(
            'cd %s && git rev-parse %s^ 2>&1',
            escapeshellarg($repo['git_path']),
            escapeshellarg($commit)
        );
        exec($cmd, $parentOutput, $returnCode);
        
        $parentCommit = trim($parentOutput[0] ?? '');
        
        if ($returnCode !== 0 || empty($parentCommit)) {
            // 可能是初始提交
            $parentCommit = '--root';
        }
        
        $files = $this->diffService->getCommitDiff($repo['git_path'], $parentCommit, $commit);
        
        return [
            'success' => true,
            'files' => $files,
        ];
    }
    
    /**
     * 获取文件内容
     */
    public function fileContent(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $commit = trim($data['commit'] ?? 'HEAD');
        $file = trim($data['file'] ?? '');
        
        if ($repoId <= 0 || empty($file)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $content = $this->diffService->getFileContent($repo['git_path'], $commit, $file);
        $isBinary = $this->diffService->isBinaryFile($repo['git_path'], $commit, $file);
        
        return [
            'success' => true,
            'content' => $content,
            'is_binary' => $isBinary,
        ];
    }
}
