<?php
/**
 * CodeVault - Diff 服务
 * 代码对比与审查功能
 */

namespace CodeVault\Services;

class DiffService
{
    /**
     * 获取两个提交之间的差异
     */
    public function getCommitDiff(string $repoPath, string $fromCommit, string $toCommit): array
    {
        $cmd = sprintf(
            'cd %s && git diff %s %s --numstat 2>&1',
            escapeshellarg($repoPath),
            escapeshellarg($fromCommit),
            escapeshellarg($toCommit)
        );
        
        exec($cmd, $output, $returnCode);
        
        $files = [];
        foreach ($output as $line) {
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 3) {
                $files[] = [
                    'added' => $parts[0] === '-' ? null : (int) $parts[0],
                    'deleted' => $parts[1] === '-' ? null : (int) $parts[1],
                    'file' => $parts[2],
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * 获取文件详细差异
     */
    public function getFileDiff(string $repoPath, string $fromCommit, string $toCommit, string $file): array
    {
        $cmd = sprintf(
            'cd %s && git diff %s %s -- %s 2>&1',
            escapeshellarg($repoPath),
            escapeshellarg($fromCommit),
            escapeshellarg($toCommit),
            escapeshellarg($file)
        );
        
        exec($cmd, $output, $returnCode);
        
        return $this->parseDiff($output);
    }
    
    /**
     * 获取 PR 差异
     */
    public function getPRDiff(string $repoPath, string $baseBranch, string $headBranch): array
    {
        // 获取变更文件列表
        $cmd = sprintf(
            'cd %s && git diff %s...%s --numstat 2>&1',
            escapeshellarg($repoPath),
            escapeshellarg($baseBranch),
            escapeshellarg($headBranch)
        );
        
        exec($cmd, $output, $returnCode);
        
        $files = [];
        foreach ($output as $line) {
            if (empty($line)) continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 3) {
                $files[] = [
                    'added' => $parts[0] === '-' ? null : (int) $parts[0],
                    'deleted' => $parts[1] === '-' ? null : (int) $parts[1],
                    'file' => $parts[2],
                ];
            }
        }
        
        // 统计
        $totalAdded = array_sum(array_filter(array_column($files, 'added'), fn($v) => $v !== null));
        $totalDeleted = array_sum(array_filter(array_column($files, 'deleted'), fn($v) => $v !== null));
        
        return [
            'files' => $files,
            'stats' => [
                'files_changed' => count($files),
                'additions' => $totalAdded,
                'deletions' => $totalDeleted,
            ],
        ];
    }
    
    /**
     * 解析 diff 输出
     */
    private function parseDiff(array $lines): array
    {
        $hunks = [];
        $currentHunk = null;
        $lineNumberOld = 0;
        $lineNumberNew = 0;
        
        foreach ($lines as $line) {
            // Hunk header: @@ -start,count +start,count @@ optional heading
            if (preg_match('/^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches)) {
                if ($currentHunk !== null) {
                    $hunks[] = $currentHunk;
                }
                $currentHunk = [
                    'old_start' => (int) $matches[1],
                    'new_start' => (int) $matches[2],
                    'lines' => [],
                ];
                $lineNumberOld = (int) $matches[1];
                $lineNumberNew = (int) $matches[2];
                continue;
            }
            
            if ($currentHunk === null) continue;
            
            $type = 'context';
            $content = substr($line, 1);
            
            if (str_starts_with($line, '+')) {
                $type = 'add';
                $currentHunk['lines'][] = [
                    'type' => $type,
                    'content' => $content,
                    'new_line' => $lineNumberNew++,
                ];
            } elseif (str_starts_with($line, '-')) {
                $type = 'delete';
                $currentHunk['lines'][] = [
                    'type' => $type,
                    'content' => $content,
                    'old_line' => $lineNumberOld++,
                ];
            } elseif (str_starts_with($line, ' ')) {
                $currentHunk['lines'][] = [
                    'type' => $type,
                    'content' => $content,
                    'old_line' => $lineNumberOld++,
                    'new_line' => $lineNumberNew++,
                ];
            }
        }
        
        if ($currentHunk !== null) {
            $hunks[] = $currentHunk;
        }
        
        return [
            'hunks' => $hunks,
        ];
    }
    
    /**
     * 获取并排视图数据
     */
    public function getSideBySideDiff(string $repoPath, string $fromCommit, string $toCommit, string $file): array
    {
        $diff = $this->getFileDiff($repoPath, $fromCommit, $toCommit, $file);
        
        $left = [];
        $right = [];
        
        foreach ($diff['hunks'] as $hunk) {
            foreach ($hunk['lines'] as $line) {
                switch ($line['type']) {
                    case 'context':
                        $left[] = ['type' => 'context', 'line' => $line['old_line'], 'content' => $line['content']];
                        $right[] = ['type' => 'context', 'line' => $line['new_line'], 'content' => $line['content']];
                        break;
                    case 'delete':
                        $left[] = ['type' => 'delete', 'line' => $line['old_line'], 'content' => $line['content']];
                        $right[] = ['type' => 'empty', 'line' => null, 'content' => ''];
                        break;
                    case 'add':
                        $left[] = ['type' => 'empty', 'line' => null, 'content' => ''];
                        $right[] = ['type' => 'add', 'line' => $line['new_line'], 'content' => $line['content']];
                        break;
                }
            }
        }
        
        return [
            'left' => $left,
            'right' => $right,
        ];
    }
    
    /**
     * 比较两个分支
     */
    public function compareBranches(string $repoPath, string $base, string $head): array
    {
        // 获取 base 和 head 的 commit
        $cmd = sprintf(
            'cd %s && git rev-parse %s %s 2>&1',
            escapeshellarg($repoPath),
            escapeshellarg($base),
            escapeshellarg($head)
        );
        
        exec($cmd, $commits, $returnCode);
        
        if ($returnCode !== 0 || count($commits) < 2) {
            return ['success' => false, 'message' => '分支不存在'];
        }
        
        $baseCommit = trim($commits[0]);
        $headCommit = trim($commits[1]);
        
        // 获取差异
        $diff = $this->getPRDiff($repoPath, $base, $head);
        
        // 获取提交列表
        $cmd = sprintf(
            'cd %s && git log %s..%s --oneline 2>&1',
            escapeshellarg($repoPath),
            escapeshellarg($base),
            escapeshellarg($head)
        );
        
        exec($cmd, $commitList, $returnCode);
        
        $commits = [];
        foreach ($commitList as $line) {
            if (preg_match('/^([a-f0-9]+)\s+(.+)$/', $line, $matches)) {
                $commits[] = [
                    'sha' => $matches[1],
                    'message' => $matches[2],
                ];
            }
        }
        
        return [
            'success' => true,
            'base_commit' => $baseCommit,
            'head_commit' => $headCommit,
            'commits' => $commits,
            'files' => $diff['files'],
            'stats' => $diff['stats'],
        ];
    }
    
    /**
     * 检查文件是否为二进制
     */
    public function isBinaryFile(string $repoPath, string $commit, string $file): bool
    {
        $cmd = sprintf(
            'cd %s && git show %s:%s 2>&1 | head -c 8000 | grep -q "[^[:print:][:space:]]"',
            escapeshellarg($repoPath),
            escapeshellarg($commit),
            escapeshellarg($file)
        );
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    /**
     * 获取文件内容
     */
    public function getFileContent(string $repoPath, string $commit, string $file): ?string
    {
        $cmd = sprintf(
            'cd %s && git show %s:%s 2>&1',
            escapeshellarg($repoPath),
            escapeshellarg($commit),
            escapeshellarg($file)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return null;
        }
        
        return implode("\n", $output);
    }
}
