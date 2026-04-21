<?php
/**
 * CodeVault - 仓库模板控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class TemplateController
{
    /**
     * 创建模板仓库
     */
    public function createFromTemplate(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $templateId = (int) ($data['template_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $isPrivate = (int) ($data['is_private'] ?? 0);
        
        if ($templateId <= 0 || empty($name)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 获取模板仓库
        $template = Connection::queryOne(
            "SELECT * FROM repositories WHERE id = ? AND is_template = 1",
            [$templateId]
        );
        
        if (!$template) {
            return ['success' => false, 'message' => '模板仓库不存在'];
        }
        
        // 检查是否已有同名仓库
        $existing = Connection::queryOne(
            "SELECT * FROM repositories WHERE user_id = ? AND name = ?",
            [$user['id'], $name]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => '已存在同名仓库'];
        }
        
        // 创建新仓库
        $repoId = Connection::insert(
            "INSERT INTO repositories (user_id, name, description, is_private, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$user['id'], $name, $description, $isPrivate]
        );
        
        // 复制 Git 仓库
        $sourceGitPath = $template['git_path'];
        $targetGitPath = "/var/git/repositories/{$user['id']}/{$repoId}.git";
        
        $dir = dirname($targetGitPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Clone 模板仓库
        $cmd = sprintf(
            'git clone --bare %s %s 2>&1',
            escapeshellarg($sourceGitPath),
            escapeshellarg($targetGitPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Connection::execute("DELETE FROM repositories WHERE id = ?", [$repoId]);
            return ['success' => false, 'message' => '创建仓库失败'];
        }
        
        // 更新 git_path
        Connection::execute("UPDATE repositories SET git_path = ? WHERE id = ?", [$targetGitPath, $repoId]);
        
        return [
            'success' => true,
            'repo_id' => $repoId,
            'message' => '仓库创建成功',
        ];
    }
    
    /**
     * 设置仓库为模板
     */
    public function setAsTemplate(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $isTemplate = (int) ($data['is_template'] ?? 1);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        Connection::execute("UPDATE repositories SET is_template = ? WHERE id = ?", [$isTemplate, $repoId]);
        
        return ['success' => true, 'message' => $isTemplate ? '已设为模板' : '已取消模板'];
    }
    
    /**
     * 获取模板列表
     */
    public function listTemplates(array $data): array
    {
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 20);
        $language = trim($data['language'] ?? '');
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT r.id, r.name, r.description, r.language, u.username as owner_name 
                FROM repositories r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.is_template = 1 AND r.is_private = 0 AND r.deleted_at IS NULL";
        $params = [];
        
        if ($language) {
            $sql .= " AND r.language = ?";
            $params[] = $language;
        }
        
        $sql .= " ORDER BY r.star_count DESC LIMIT {$perPage} OFFSET {$offset}";
        
        $templates = Connection::query($sql, $params);
        
        return ['success' => true, 'templates' => $templates];
    }
    
    /**
     * 获取官方模板
     */
    public function getOfficialTemplates(): array
    {
        return [
            [
                'id' => 0,
                'name' => 'PHP 项目模板',
                'description' => 'PHP 项目基础结构，包含 Composer 配置',
                'language' => 'PHP',
                'files' => [
                    'composer.json' => json_encode([
                        'name' => 'vendor/project',
                        'require' => ['php' => '>=8.0'],
                        'autoload' => ['psr-4' => ['App\\' => 'src/']],
                    ], JSON_PRETTY_PRINT),
                    'README.md' => "# Project\n\nDescription here.\n",
                    '.gitignore' => "/vendor\n/.env\n",
                ],
            ],
            [
                'id' => 0,
                'name' => 'Vue 3 项目模板',
                'description' => 'Vue 3 + TypeScript + Vite 项目模板',
                'language' => 'TypeScript',
                'files' => [
                    'package.json' => json_encode([
                        'name' => 'my-vue-app',
                        'scripts' => ['dev' => 'vite', 'build' => 'vite build'],
                        'dependencies' => ['vue' => '^3.0.0'],
                    ], JSON_PRETTY_PRINT),
                    'README.md' => "# Vue 3 Project\n\n## Setup\n\n```bash\nnpm install\nnpm run dev\n```\n",
                    '.gitignore' => "/node_modules\n/dist\n",
                ],
            ],
            [
                'id' => 0,
                'name' => 'Python 项目模板',
                'description' => 'Python 项目基础结构，包含虚拟环境配置',
                'language' => 'Python',
                'files' => [
                    'requirements.txt' => "# Add your dependencies here\n",
                    'README.md' => "# Python Project\n\n## Setup\n\n```bash\npython -m venv venv\nsource venv/bin/activate\npip install -r requirements.txt\n```\n",
                    '.gitignore' => "/venv\n__pycache__/\n*.pyc\n",
                ],
            ],
            [
                'id' => 0,
                'name' => 'Go 项目模板',
                'description' => 'Go 项目基础结构',
                'language' => 'Go',
                'files' => [
                    'go.mod' => "module github.com/user/project\n\ngo 1.21\n",
                    'main.go' => "package main\n\nimport \"fmt\"\n\nfunc main() {\n\tfmt.Println(\"Hello, World!\")\n}\n",
                    'README.md' => "# Go Project\n\n## Run\n\n```bash\ngo run main.go\n```\n",
                ],
            ],
        ];
    }
}
