<?php
/**
 * CodeVault - 高级搜索服务
 * 支持高级搜索语法、代码搜索
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class AdvancedSearchService
{
    /**
     * 高级搜索
     * 支持语法：
     * - repo:owner/name 指定仓库
     * - user:username 指定用户
     * - lang:language 指定语言
     * - is:issue/is:pr/is:open/is:closed 状态过滤
     * - label:name 标签过滤
     * - created:>2024-01-01 日期过滤
     */
    public function search(string $query, int $userId = 0): array
    {
        $results = [
            'repositories' => [],
            'issues' => [],
            'pull_requests' => [],
            'code' => [],
            'users' => [],
        ];
        
        // 解析搜索语法
        $parsed = $this->parseQuery($query);
        
        // 搜索仓库
        if ($parsed['type'] === 'all' || $parsed['type'] === 'repo') {
            $results['repositories'] = $this->searchRepositories($parsed, $userId);
        }
        
        // 搜索 Issue
        if ($parsed['type'] === 'all' || $parsed['type'] === 'issue') {
            $results['issues'] = $this->searchIssues($parsed, $userId);
        }
        
        // 搜索 PR
        if ($parsed['type'] === 'all' || $parsed['type'] === 'pr') {
            $results['pull_requests'] = $this->searchPullRequests($parsed, $userId);
        }
        
        // 搜索代码
        if ($parsed['type'] === 'all' || $parsed['type'] === 'code') {
            $results['code'] = $this->searchCode($parsed, $userId);
        }
        
        // 搜索用户
        if ($parsed['type'] === 'all' || $parsed['type'] === 'user') {
            $results['users'] = $this->searchUsers($parsed);
        }
        
        return $results;
    }
    
    /**
     * 解析搜索查询
     */
    private function parseQuery(string $query): array
    {
        $parsed = [
            'keywords' => [],
            'repo' => null,
            'user' => null,
            'language' => null,
            'type' => 'all',
            'state' => null,
            'labels' => [],
            'created' => null,
            'updated' => null,
        ];
        
        // 分词
        $tokens = preg_split('/\s+/', $query);
        
        foreach ($tokens as $token) {
            if (strpos($token, ':') === false) {
                $parsed['keywords'][] = $token;
                continue;
            }
            
            list($key, $value) = explode(':', $token, 2);
            
            switch (strtolower($key)) {
                case 'repo':
                    $parsed['repo'] = $value;
                    break;
                case 'user':
                    $parsed['user'] = $value;
                    break;
                case 'lang':
                case 'language':
                    $parsed['language'] = $value;
                    break;
                case 'is':
                    switch (strtolower($value)) {
                        case 'issue':
                            $parsed['type'] = 'issue';
                            break;
                        case 'pr':
                        case 'pull':
                        case 'pullrequest':
                            $parsed['type'] = 'pr';
                            break;
                        case 'repo':
                        case 'repository':
                            $parsed['type'] = 'repo';
                            break;
                        case 'user':
                            $parsed['type'] = 'user';
                            break;
                        case 'code':
                            $parsed['type'] = 'code';
                            break;
                        case 'open':
                            $parsed['state'] = 'open';
                            break;
                        case 'closed':
                            $parsed['state'] = 'closed';
                            break;
                    }
                    break;
                case 'label':
                    $parsed['labels'][] = $value;
                    break;
                case 'created':
                    $parsed['created'] = $this->parseDateCondition($value);
                    break;
                case 'updated':
                    $parsed['updated'] = $this->parseDateCondition($value);
                    break;
            }
        }
        
        return $parsed;
    }
    
    /**
     * 解析日期条件
     */
    private function parseDateCondition(string $value): array
    {
        $operator = '=';
        
        if (strpos($value, '>') === 0) {
            $operator = '>';
            $value = ltrim($value, '>');
        } elseif (strpos($value, '<') === 0) {
            $operator = '<';
            $value = ltrim($value, '<');
        } elseif (strpos($value, '>=') === 0) {
            $operator = '>=';
            $value = ltrim($value, '>=');
        } elseif (strpos($value, '<=') === 0) {
            $operator = '<=';
            $value = ltrim($value, '<=');
        }
        
        return ['operator' => $operator, 'date' => $value];
    }
    
    /**
     * 搜索仓库
     */
    private function searchRepositories(array $parsed, int $userId): array
    {
        $sql = "SELECT r.*, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE 1=1";
        $params = [];
        
        // 关键词
        if (!empty($parsed['keywords'])) {
            $keyword = implode(' ', $parsed['keywords']);
            $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // 用户过滤
        if ($parsed['user']) {
            $sql .= " AND u.username = ?";
            $params[] = $parsed['user'];
        }
        
        // 语言过滤
        if ($parsed['language']) {
            $sql .= " AND r.language = ?";
            $params[] = $parsed['language'];
        }
        
        // 权限过滤
        $sql .= " AND (r.is_private = 0 OR r.user_id = ?)";
        $params[] = $userId;
        
        $sql .= " ORDER BY r.created_at DESC LIMIT 50";
        
        return Connection::query($sql, $params);
    }
    
    /**
     * 搜索 Issue
     */
    private function searchIssues(array $parsed, int $userId): array
    {
        $sql = "SELECT i.*, r.name as repo_name, u.username as author_name 
                FROM issues i 
                JOIN repositories r ON i.repo_id = r.id 
                JOIN users u ON i.author_id = u.id 
                WHERE 1=1";
        $params = [];
        
        // 关键词
        if (!empty($parsed['keywords'])) {
            $keyword = implode(' ', $parsed['keywords']);
            $sql .= " AND (i.title LIKE ? OR i.body LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // 仓库过滤
        if ($parsed['repo']) {
            if (strpos($parsed['repo'], '/') !== false) {
                list($owner, $repoName) = explode('/', $parsed['repo']);
                $sql .= " AND r.name = ? AND r.user_id = (SELECT id FROM users WHERE username = ?)";
                $params[] = $repoName;
                $params[] = $owner;
            }
        }
        
        // 状态过滤
        if ($parsed['state']) {
            $sql .= " AND i.status = ?";
            $params[] = $parsed['state'];
        }
        
        // 标签过滤
        if (!empty($parsed['labels'])) {
            foreach ($parsed['labels'] as $label) {
                $sql .= " AND EXISTS (SELECT 1 FROM issue_labels il JOIN labels l ON il.label_id = l.id WHERE il.issue_id = i.id AND l.name = ?)";
                $params[] = $label;
            }
        }
        
        // 权限过滤
        $sql .= " AND (r.is_private = 0 OR r.user_id = ?)";
        $params[] = $userId;
        
        $sql .= " ORDER BY i.created_at DESC LIMIT 50";
        
        return Connection::query($sql, $params);
    }
    
    /**
     * 搜索 Pull Request
     */
    private function searchPullRequests(array $parsed, int $userId): array
    {
        $sql = "SELECT pr.*, r.name as repo_name, u.username as author_name 
                FROM pull_requests pr 
                JOIN repositories r ON pr.repo_id = r.id 
                JOIN users u ON pr.author_id = u.id 
                WHERE 1=1";
        $params = [];
        
        // 关键词
        if (!empty($parsed['keywords'])) {
            $keyword = implode(' ', $parsed['keywords']);
            $sql .= " AND (pr.title LIKE ? OR pr.body LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // 仓库过滤
        if ($parsed['repo']) {
            if (strpos($parsed['repo'], '/') !== false) {
                list($owner, $repoName) = explode('/', $parsed['repo']);
                $sql .= " AND r.name = ? AND r.user_id = (SELECT id FROM users WHERE username = ?)";
                $params[] = $repoName;
                $params[] = $owner;
            }
        }
        
        // 状态过滤
        if ($parsed['state']) {
            $sql .= " AND pr.status = ?";
            $params[] = $parsed['state'];
        }
        
        // 权限过滤
        $sql .= " AND (r.is_private = 0 OR r.user_id = ?)";
        $params[] = $userId;
        
        $sql .= " ORDER BY pr.created_at DESC LIMIT 50";
        
        return Connection::query($sql, $params);
    }
    
    /**
     * 搜索代码
     */
    private function searchCode(array $parsed, int $userId): array
    {
        $results = [];
        
        if (empty($parsed['keywords'])) {
            return $results;
        }
        
        $keyword = implode(' ', $parsed['keywords']);
        
        // 获取可访问的仓库
        $repos = Connection::query(
            "SELECT r.*, r.git_path FROM repositories r WHERE r.is_private = 0 OR r.user_id = ?",
            [$userId]
        );
        
        foreach ($repos as $repo) {
            // 使用 git grep 搜索代码
            $cmd = sprintf(
                'cd %s && git grep -n --no-color -e %s 2>/dev/null | head -50',
                escapeshellarg($repo['git_path']),
                escapeshellarg($keyword)
            );
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                foreach ($output as $line) {
                    if (preg_match('/^(.+?):(\d+):(.*)$/', $line, $matches)) {
                        $results[] = [
                            'repo_id' => $repo['id'],
                            'repo_name' => $repo['name'],
                            'file' => $matches[1],
                            'line' => (int) $matches[2],
                            'content' => $matches[3],
                            'highlight' => $this->highlightKeyword($matches[3], $keyword),
                        ];
                    }
                }
            }
        }
        
        return array_slice($results, 0, 100);
    }
    
    /**
     * 搜索用户
     */
    private function searchUsers(array $parsed): array
    {
        $sql = "SELECT id, username, email, avatar_url, bio FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($parsed['keywords'])) {
            $keyword = implode(' ', $parsed['keywords']);
            $sql .= " AND (username LIKE ? OR bio LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        if ($parsed['user']) {
            $sql .= " AND username = ?";
            $params[] = $parsed['user'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        return Connection::query($sql, $params);
    }
    
    /**
     * 高亮关键词
     */
    private function highlightKeyword(string $content, string $keyword): string
    {
        return preg_replace(
            '/(' . preg_quote($keyword, '/') . ')/i',
            '<mark>$1</mark>',
            htmlspecialchars($content)
        );
    }
}
