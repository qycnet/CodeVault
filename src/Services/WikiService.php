<?php
/**
 * CodeVault Wiki 增强服务
 * 
 * 功能：
 * - 富文本编辑器支持
 * - Markdown 增强
 * - 版本历史
 * - 页面对比
 * - 目录自动生成
 * - 侧边栏导航
 * - 页面模板
 * - 搜索功能
 */

namespace CodeVault\Services;

use Core\Database;
use Core\Logger;
use Services\SecurityService;

class WikiService
{
    private $db;
    private $logger;
    private $security;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('wiki');
        $this->security = new SecurityService();
    }
    
    /**
     * 创建 Wiki 页面
     */
    public function createPage(int $repoId, int $userId, array $data): array
    {
        // 验证必填字段
        if (empty($data['title'])) {
            return ['success' => false, 'error' => '标题不能为空'];
        }
        
        // 生成 slug
        $slug = $this->generateSlug($data['title']);
        
        // 检查是否已存在
        $existing = $this->db->fetchOne(
            "SELECT id FROM wiki_pages WHERE repo_id = ? AND slug = ?",
            [$repoId, $slug]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '页面已存在'];
        }
        
        // 处理内容
        $content = $data['content'] ?? '';
        $format = $data['format'] ?? 'markdown';
        
        // XSS 过滤
        $title = $this->security->sanitizeInput($data['title']);
        
        $this->db->beginTransaction();
        
        try {
            // 创建页面
            $sql = "INSERT INTO wiki_pages (repo_id, title, slug, content, format, author_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $this->db->execute($sql, [
                $repoId,
                $title,
                $slug,
                $content,
                $format,
                $userId,
            ]);
            
            $pageId = (int)$this->db->lastInsertId();
            
            // 创建初始版本
            $this->createRevision($pageId, $userId, $content, 'Initial version');
            
            // 更新目录
            $this->updateTableOfContents($pageId, $content);
            
            $this->db->commit();
            
            $this->logger->info('Wiki 页面创建成功', ['page_id' => $pageId, 'repo_id' => $repoId]);
            
            return [
                'success' => true,
                'page' => $this->getPage($repoId, $slug),
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error('Wiki 页面创建失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => '创建失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 更新 Wiki 页面
     */
    public function updatePage(int $repoId, string $slug, int $userId, array $data): array
    {
        $page = $this->getPage($repoId, $slug);
        
        if (!$page) {
            return ['success' => false, 'error' => '页面不存在'];
        }
        
        $this->db->beginTransaction();
        
        try {
            $updates = [];
            $bindings = [];
            
            // 更新标题
            if (isset($data['title'])) {
                $updates[] = 'title = ?';
                $bindings[] = $this->security->sanitizeInput($data['title']);
                
                // 更新 slug
                $newSlug = $this->generateSlug($data['title']);
                if ($newSlug !== $slug) {
                    $updates[] = 'slug = ?';
                    $bindings[] = $newSlug;
                }
            }
            
            // 更新内容
            $newContent = $data['content'] ?? null;
            if ($newContent !== null && $newContent !== $page['content']) {
                $updates[] = 'content = ?';
                $bindings[] = $newContent;
                
                // 创建新版本
                $this->createRevision($page['id'], $userId, $newContent, $data['comment'] ?? 'Update');
                
                // 更新目录
                $this->updateTableOfContents($page['id'], $newContent);
            }
            
            // 更新格式
            if (isset($data['format'])) {
                $updates[] = 'format = ?';
                $bindings[] = $data['format'];
            }
            
            if (!empty($updates)) {
                $updates[] = 'updated_at = NOW()';
                $bindings[] = $page['id'];
                
                $sql = "UPDATE wiki_pages SET " . implode(', ', $updates) . " WHERE id = ?";
                $this->db->execute($sql, $bindings);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'page' => $this->getPage($repoId, $newSlug ?? $slug),
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '更新失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 删除 Wiki 页面
     */
    public function deletePage(int $repoId, string $slug, int $userId): array
    {
        $page = $this->getPage($repoId, $slug);
        
        if (!$page) {
            return ['success' => false, 'error' => '页面不存在'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 删除版本
            $this->db->execute("DELETE FROM wiki_revisions WHERE page_id = ?", [$page['id']]);
            
            // 删除目录
            $this->db->execute("DELETE FROM wiki_toc WHERE page_id = ?", [$page['id']]);
            
            // 删除页面
            $this->db->execute("DELETE FROM wiki_pages WHERE id = ?", [$page['id']]);
            
            $this->db->commit();
            
            $this->logger->info('Wiki 页面删除成功', ['page_id' => $page['id'], 'repo_id' => $repoId]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '删除失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取 Wiki 页面
     */
    public function getPage(int $repoId, string $slug): ?array
    {
        $page = $this->db->fetchOne(
            "SELECT wp.*, u.username as author_name, u.avatar_url as author_avatar
             FROM wiki_pages wp
             JOIN users u ON wp.author_id = u.id
             WHERE wp.repo_id = ? AND wp.slug = ?",
            [$repoId, $slug]
        );
        
        if (!$page) {
            return null;
        }
        
        // 获取目录
        $page['toc'] = $this->getTableOfContents($page['id']);
        
        // 渲染内容
        $page['rendered'] = $this->renderContent($page['content'], $page['format']);
        
        return $page;
    }
    
    /**
     * 列出 Wiki 页面
     */
    public function listPages(int $repoId): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, slug, format, created_at, updated_at,
                    (SELECT COUNT(*) FROM wiki_revisions WHERE page_id = wp.id) as revisions
             FROM wiki_pages wp
             WHERE repo_id = ?
             ORDER BY title ASC",
            [$repoId]
        );
    }
    
    /**
     * 创建版本
     */
    private function createRevision(int $pageId, int $userId, string $content, string $comment): int
    {
        $sql = "INSERT INTO wiki_revisions (page_id, author_id, content, comment, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [$pageId, $userId, $content, $comment]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * 获取版本历史
     */
    public function getHistory(int $pageId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT wr.*, u.username, u.avatar_url
             FROM wiki_revisions wr
             JOIN users u ON wr.author_id = u.id
             WHERE wr.page_id = ?
             ORDER BY wr.created_at DESC
             LIMIT ?",
            [$pageId, $limit]
        );
    }
    
    /**
     * 获取特定版本
     */
    public function getRevision(int $pageId, int $revisionId): ?array
    {
        return $this->db->fetchOne(
            "SELECT wr.*, u.username, u.avatar_url
             FROM wiki_revisions wr
             JOIN users u ON wr.author_id = u.id
             WHERE wr.page_id = ? AND wr.id = ?",
            [$pageId, $revisionId]
        );
    }
    
    /**
     * 对比两个版本
     */
    public function compare(int $pageId, int $fromRevision, int $toRevision): array
    {
        $from = $this->getRevision($pageId, $fromRevision);
        $to = $this->getRevision($pageId, $toRevision);
        
        if (!$from || !$to) {
            return ['success' => false, 'error' => '版本不存在'];
        }
        
        // 生成差异
        $diff = $this->generateDiff($from['content'], $to['content']);
        
        return [
            'success' => true,
            'from' => $from,
            'to' => $to,
            'diff' => $diff,
        ];
    }
    
    /**
     * 生成差异
     */
    private function generateDiff(string $old, string $new): array
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);
        
        $diff = [];
        $oldIndex = 0;
        $newIndex = 0;
        
        // 简单的行级差异算法
        while ($oldIndex < count($oldLines) || $newIndex < count($newLines)) {
            if ($oldIndex >= count($oldLines)) {
                $diff[] = ['type' => 'add', 'line' => $newLines[$newIndex], 'new_num' => $newIndex + 1];
                $newIndex++;
            } elseif ($newIndex >= count($newLines)) {
                $diff[] = ['type' => 'delete', 'line' => $oldLines[$oldIndex], 'old_num' => $oldIndex + 1];
                $oldIndex++;
            } elseif ($oldLines[$oldIndex] === $newLines[$newIndex]) {
                $diff[] = [
                    'type' => 'context',
                    'line' => $oldLines[$oldIndex],
                    'old_num' => $oldIndex + 1,
                    'new_num' => $newIndex + 1,
                ];
                $oldIndex++;
                $newIndex++;
            } else {
                // 检查是否是删除
                $foundInNew = array_search($oldLines[$oldIndex], array_slice($newLines, $newIndex));
                $foundInOld = array_search($newLines[$newIndex], array_slice($oldLines, $oldIndex));
                
                if ($foundInNew === false && $foundInOld === false) {
                    $diff[] = ['type' => 'delete', 'line' => $oldLines[$oldIndex], 'old_num' => $oldIndex + 1];
                    $diff[] = ['type' => 'add', 'line' => $newLines[$newIndex], 'new_num' => $newIndex + 1];
                    $oldIndex++;
                    $newIndex++;
                } elseif ($foundInNew === false || ($foundInOld !== false && $foundInNew > $foundInOld)) {
                    $diff[] = ['type' => 'add', 'line' => $newLines[$newIndex], 'new_num' => $newIndex + 1];
                    $newIndex++;
                } else {
                    $diff[] = ['type' => 'delete', 'line' => $oldLines[$oldIndex], 'old_num' => $oldIndex + 1];
                    $oldIndex++;
                }
            }
        }
        
        return $diff;
    }
    
    /**
     * 更新目录
     */
    private function updateTableOfContents(int $pageId, string $content): void
    {
        // 删除旧目录
        $this->db->execute("DELETE FROM wiki_toc WHERE page_id = ?", [$pageId]);
        
        // 解析标题
        $lines = explode("\n", $content);
        $toc = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $title = trim($matches[2]);
                $anchor = $this->generateAnchor($title);
                
                $toc[] = [
                    'level' => $level,
                    'title' => $title,
                    'anchor' => $anchor,
                ];
            }
        }
        
        // 保存目录
        foreach ($toc as $index => $item) {
            $this->db->execute(
                "INSERT INTO wiki_toc (page_id, level, title, anchor, position) VALUES (?, ?, ?, ?, ?)",
                [$pageId, $item['level'], $item['title'], $item['anchor'], $index]
            );
        }
    }
    
    /**
     * 获取目录
     */
    private function getTableOfContents(int $pageId): array
    {
        return $this->db->fetchAll(
            "SELECT level, title, anchor FROM wiki_toc WHERE page_id = ? ORDER BY position",
            [$pageId]
        );
    }
    
    /**
     * 渲染内容
     */
    private function renderContent(string $content, string $format): string
    {
        if ($format === 'markdown') {
            return $this->renderMarkdown($content);
        }
        
        return $this->security->sanitizeInput($content);
    }
    
    /**
     * 渲染 Markdown
     */
    private function renderMarkdown(string $content): string
    {
        // 简单的 Markdown 渲染器
        $html = $content;
        
        // 代码块
        $html = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
        
        // 行内代码
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
        
        // 标题
        $html = preg_replace('/^###### (.+)$/m', '<h6 id="$1">$1</h6>', $html);
        $html = preg_replace('/^##### (.+)$/m', '<h5 id="$1">$1</h5>', $html);
        $html = preg_replace('/^#### (.+)$/m', '<h4 id="$1">$1</h4>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3 id="$1">$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2 id="$1">$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1 id="$1">$1</h1>', $html);
        
        // 粗体和斜体
        $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // 链接
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // 图片
        $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $html);
        
        // 列表
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html);
        
        // 引用
        $html = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $html);
        
        // 水平线
        $html = preg_replace('/^---$/m', '<hr>', $html);
        
        // 段落
        $html = preg_replace('/\n\n/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';
        
        // 清理空段落
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        
        return $html;
    }
    
    /**
     * 生成 slug
     */
    private function generateSlug(string $title): string
    {
        // 转换为小写
        $slug = strtolower($title);
        
        // 替换空格为连字符
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // 移除特殊字符
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // 移除多余的连字符
        $slug = preg_replace('/-+/', '-', $slug);
        
        return trim($slug, '-');
    }
    
    /**
     * 生成锚点
     */
    private function generateAnchor(string $title): string
    {
        return $this->generateSlug($title);
    }
    
    /**
     * 搜索 Wiki
     */
    public function search(int $repoId, string $query): array
    {
        $searchTerm = "%{$query}%";
        
        return $this->db->fetchAll(
            "SELECT id, title, slug, 
                    SUBSTRING(content, 1, 200) as excerpt,
                    updated_at
             FROM wiki_pages
             WHERE repo_id = ? AND (title LIKE ? OR content LIKE ?)
             ORDER BY updated_at DESC
             LIMIT 50",
            [$repoId, $searchTerm, $searchTerm]
        );
    }
    
    /**
     * 获取侧边栏
     */
    public function getSidebar(int $repoId): ?array
    {
        $sidebar = $this->db->fetchOne(
            "SELECT content, format FROM wiki_pages WHERE repo_id = ? AND slug = '_Sidebar'",
            [$repoId]
        );
        
        if (!$sidebar) {
            return null;
        }
        
        return [
            'content' => $sidebar['content'],
            'rendered' => $this->renderContent($sidebar['content'], $sidebar['format']),
        ];
    }
    
    /**
     * 获取页脚
     */
    public function getFooter(int $repoId): ?array
    {
        $footer = $this->db->fetchOne(
            "SELECT content, format FROM wiki_pages WHERE repo_id = ? AND slug = '_Footer'",
            [$repoId]
        );
        
        if (!$footer) {
            return null;
        }
        
        return [
            'content' => $footer['content'],
            'rendered' => $this->renderContent($footer['content'], $footer['format']),
        ];
    }
    
    /**
     * 恢复到特定版本
     */
    public function restore(int $pageId, int $revisionId, int $userId): array
    {
        $revision = $this->getRevision($pageId, $revisionId);
        
        if (!$revision) {
            return ['success' => false, 'error' => '版本不存在'];
        }
        
        $page = $this->db->fetchOne("SELECT * FROM wiki_pages WHERE id = ?", [$pageId]);
        
        if (!$page) {
            return ['success' => false, 'error' => '页面不存在'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 更新内容
            $this->db->execute(
                "UPDATE wiki_pages SET content = ?, updated_at = NOW() WHERE id = ?",
                [$revision['content'], $pageId]
            );
            
            // 创建新版本
            $this->createRevision($pageId, $userId, $revision['content'], "Restored from revision #{$revisionId}");
            
            // 更新目录
            $this->updateTableOfContents($pageId, $revision['content']);
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '恢复失败: ' . $e->getMessage()];
        }
    }
}
