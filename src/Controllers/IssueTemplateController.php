<?php
/**
 * CodeVault - Issue/PR 模板控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class IssueTemplateController
{
    /**
     * 获取仓库的 Issue 模板
     */
    public function getIssueTemplates(int $repoId): array
    {
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        // 从 .github/ISSUE_TEMPLATE/ 目录读取模板
        $gitPath = $repo['git_path'];
        $templates = [];
        
        // 检查是否有模板目录
        $cmd = sprintf(
            'cd %s && git ls-tree HEAD .github/ISSUE_TEMPLATE/ 2>/dev/null',
            escapeshellarg($gitPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/\.md$/', $line)) {
                    // 提取文件名
                    if (preg_match('/\t(.+\.md)$/', $line, $matches)) {
                        $fileName = $matches[1];
                        $content = $this->getFileContent($gitPath, ".github/ISSUE_TEMPLATE/{$fileName}");
                        
                        if ($content) {
                            $templates[] = [
                                'name' => $fileName,
                                'content' => $content,
                                'title' => $this->extractTitle($content),
                            ];
                        }
                    }
                }
            }
        }
        
        // 如果没有自定义模板，返回默认模板
        if (empty($templates)) {
            $templates = $this->getDefaultIssueTemplates();
        }
        
        return ['success' => true, 'templates' => $templates];
    }
    
    /**
     * 获取仓库的 PR 模板
     */
    public function getPRTemplate(int $repoId): ?array
    {
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return null;
        }
        
        $gitPath = $repo['git_path'];
        
        // 检查多个可能的 PR 模板位置
        $possiblePaths = [
            '.github/PULL_REQUEST_TEMPLATE.md',
            '.github/pull_request_template.md',
            'PULL_REQUEST_TEMPLATE.md',
            'docs/PULL_REQUEST_TEMPLATE.md',
        ];
        
        foreach ($possiblePaths as $path) {
            $content = $this->getFileContent($gitPath, $path);
            if ($content) {
                return [
                    'name' => basename($path),
                    'content' => $content,
                ];
            }
        }
        
        // 返回默认模板
        return $this->getDefaultPRTemplate();
    }
    
    /**
     * 保存 Issue 模板
     */
    public function saveIssueTemplate(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $content = $data['content'] ?? '';
        
        if ($repoId <= 0 || empty($name) || empty($content)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        // 这里应该通过 Git 操作保存文件
        // 简化处理：保存到数据库
        Connection::insert(
            "INSERT INTO issue_templates (repo_id, name, content, created_at) VALUES (?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE content = ?, updated_at = NOW()",
            [$repoId, $name, $content, $content]
        );
        
        return ['success' => true, 'message' => '模板已保存'];
    }
    
    /**
     * 获取文件内容
     */
    private function getFileContent(string $gitPath, string $path): ?string
    {
        $cmd = sprintf(
            'cd %s && git show HEAD:%s 2>/dev/null',
            escapeshellarg($gitPath),
            escapeshellarg($path)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return null;
        }
        
        return implode("\n", $output);
    }
    
    /**
     * 从 Markdown 提取标题
     */
    private function extractTitle(string $content): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return 'Issue 模板';
    }
    
    /**
     * 默认 Issue 模板
     */
    private function getDefaultIssueTemplates(): array
    {
        return [
            [
                'name' => 'bug_report.md',
                'title' => 'Bug 报告',
                'content' => <<<MD
# Bug 报告

## 问题描述
简要描述遇到的问题。

## 复现步骤
1. 
2. 
3. 

## 期望行为
描述你期望发生什么。

## 实际行为
描述实际发生了什么。

## 截图
如果适用，添加截图帮助解释问题。

## 环境
- 操作系统：
- 浏览器：
- 版本：

## 其他信息
添加任何其他相关信息。
MD,
            ],
            [
                'name' => 'feature_request.md',
                'title' => '功能请求',
                'content' => <<<MD
# 功能请求

## 功能描述
清晰简洁地描述你想要的功能。

## 问题背景
描述这个功能要解决什么问题。

## 建议方案
描述你建议的解决方案。

## 替代方案
描述你考虑过的其他方案。

## 附加信息
添加任何其他相关信息或截图。
MD,
            ],
        ];
    }
    
    /**
     * 默认 PR 模板
     */
    private function getDefaultPRTemplate(): array
    {
        return [
            'name' => 'pull_request_template.md',
            'content' => <<<MD
# Pull Request

## 变更类型
- [ ] Bug 修复
- [ ] 新功能
- [ ] 重构
- [ ] 文档更新
- [ ] 其他

## 变更描述
简要描述此 PR 的变更内容。

## 相关 Issue
关闭 #

## 测试
描述如何测试这些变更。

## 检查清单
- [ ] 代码遵循项目编码规范
- [ ] 已添加必要的测试
- [ ] 所有测试通过
- [ ] 文档已更新

## 截图
如果适用，添加截图展示变更效果。
MD,
        ];
    }
}
