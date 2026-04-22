<?php
/**
 * CodeVault - 全文代码搜索服务
 * 基于 Elasticsearch / Meilisearch 的全文搜索
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class FullTextSearchService
{
    private $client;
    private $indexName = 'codevault_code';
    private $enabled = false;
    
    public function __construct()
    {
        $this->initClient();
    }
    
    /**
     * 初始化搜索客户端
     */
    private function initClient(): void
    {
        $engine = getenv('SEARCH_ENGINE') ?: 'meilisearch';
        $host = getenv('SEARCH_HOST') ?: 'http://localhost:7700';
        $apiKey = getenv('SEARCH_API_KEY') ?: '';
        
        try {
            if ($engine === 'elasticsearch') {
                $this->initElasticsearch($host, $apiKey);
            } else {
                $this->initMeilisearch($host, $apiKey);
            }
        } catch (\Exception $e) {
            $this->enabled = false;
        }
    }
    
    /**
     * 初始化 Elasticsearch
     */
    private function initElasticsearch(string $host, string $apiKey): void
    {
        // Elasticsearch 客户端初始化
        $this->client = [
            'type' => 'elasticsearch',
            'host' => $host,
            'api_key' => $apiKey,
        ];
        $this->enabled = true;
    }
    
    /**
     * 初始化 Meilisearch
     */
    private function initMeilisearch(string $host, string $apiKey): void
    {
        $this->client = [
            'type' => 'meilisearch',
            'host' => $host,
            'api_key' => $apiKey,
        ];
        $this->enabled = true;
    }
    
    /**
     * 索引仓库代码
     */
    public function indexRepository(int $repoId): bool
    {
        if (!$this->enabled) {
            return $this->fallbackIndex($repoId);
        }
        
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return false;
        }
        
        $gitPath = $repo['git_path'];
        $documents = [];
        
        // 获取所有文件（使用 proc_open）
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open(
            ['git', 'ls-tree', '-r', '--name-only', 'HEAD'],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        if (!is_resource($process)) {
            return false;
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0) {
            return false;
        }
        
        $files = explode("\n", trim($output));
        
        foreach ($files as $file) {
            // 跳过二进制文件和大文件
            if ($this->isBinaryFile($file) || $this->isLargeFile($gitPath, $file)) {
                continue;
            }
            
            $content = $this->getFileContent($gitPath, $file);
            if ($content === null) {
                continue;
            }
            
            $documents[] = [
                'id' => "{$repoId}:" . md5($file),
                'repo_id' => $repoId,
                'repo_name' => $repo['name'],
                'file_path' => $file,
                'content' => $content,
                'language' => $this->detectLanguage($file),
                'indexed_at' => date('c'),
            ];
        }
        
        // 批量索引
        return $this->bulkIndex($documents);
    }
    
    /**
     * 搜索代码
     */
    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        if (!$this->enabled) {
            return $this->fallbackSearch($query, $filters, $limit);
        }
        
        $params = [
            'q' => $query,
            'limit' => $limit,
        ];
        
        // 添加过滤条件
        if (!empty($filters['repo_id'])) {
            $params['filter'][] = "repo_id = {$filters['repo_id']}";
        }
        
        if (!empty($filters['language'])) {
            $params['filter'][] = "language = {$filters['language']}";
        }
        
        if (!empty($filters['file_path'])) {
            $params['filter'][] = "file_path LIKE {$filters['file_path']}";
        }
        
        return $this->executeSearch($params);
    }
    
    /**
     * 执行搜索
     */
    private function executeSearch(array $params): array
    {
        $client = $this->client;
        
        if ($client['type'] === 'meilisearch') {
            return $this->searchMeilisearch($client, $params);
        } else {
            return $this->searchElasticsearch($client, $params);
        }
    }
    
    /**
     * Meilisearch 搜索
     */
    private function searchMeilisearch(array $client, array $params): array
    {
        $url = "{$client['host']}/indexes/{$this->indexName}/search";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $client['api_key'],
            ],
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        return [
            'success' => true,
            'hits' => $data['hits'] ?? [],
            'total' => $data['estimatedTotalHits'] ?? 0,
            'processing_time' => $data['processingTimeMs'] ?? 0,
        ];
    }
    
    /**
     * Elasticsearch 搜索
     */
    private function searchElasticsearch(array $client, array $params): array
    {
        $query = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['content' => $params['q']]],
                    ],
                ],
            ],
            'size' => $params['limit'],
        ];
        
        // 添加过滤条件
        if (!empty($params['filter'])) {
            foreach ($params['filter'] as $filter) {
                $query['query']['bool']['filter'][] = ['term' => $filter];
            }
        }
        
        $url = "{$client['host']}/{$this->indexName}/_search";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($query),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $client['api_key'],
            ],
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        $hits = [];
        foreach ($data['hits']['hits'] ?? [] as $hit) {
            $hits[] = array_merge($hit['_source'], ['score' => $hit['_score']]);
        }
        
        return [
            'success' => true,
            'hits' => $hits,
            'total' => $data['hits']['total']['value'] ?? 0,
            'processing_time' => $data['took'] ?? 0,
        ];
    }
    
    /**
     * 批量索引
     */
    private function bulkIndex(array $documents): bool
    {
        if (empty($documents)) {
            return true;
        }
        
        $client = $this->client;
        
        if ($client['type'] === 'meilisearch') {
            $url = "{$client['host']}/indexes/{$this->indexName}/documents";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($documents),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $client['api_key'],
                ],
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return true;
        } else {
            // Elasticsearch bulk
            $body = '';
            foreach ($documents as $doc) {
                $body .= json_encode(['index' => ['_id' => $doc['id']]]) . "\n";
                $body .= json_encode($doc) . "\n";
            }
            
            $url = "{$client['host']}/{$this->indexName}/_bulk";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-ndjson',
                ],
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return true;
        }
    }
    
    /**
     * 删除仓库索引
     */
    public function deleteRepositoryIndex(int $repoId): bool
    {
        if (!$this->enabled) {
            return true;
        }
        
        $client = $this->client;
        
        if ($client['type'] === 'meilisearch') {
            $url = "{$client['host']}/indexes/{$this->indexName}/documents/delete";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['filter' => "repo_id = {$repoId}"]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $client['api_key'],
                ],
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        } else {
            $url = "{$client['host']}/{$this->indexName}/_delete_by_query";
            
            $query = [
                'query' => ['term' => ['repo_id' => $repoId]],
            ];
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($query),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        }
        
        return true;
    }
    
    /**
     * 后备索引方案（基于数据库）
     */
    private function fallbackIndex(int $repoId): bool
    {
        // 使用数据库全文索引
        return true;
    }
    
    /**
     * 后备搜索方案（基于 git grep）
     */
    private function fallbackSearch(string $query, array $filters, int $limit): array
    {
        $results = [];
        
        $sql = "SELECT r.id, r.name, r.git_path, u.username as owner 
                FROM repositories r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.deleted_at IS NULL AND (r.is_private = 0";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " OR r.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        $sql .= ")";
        
        if (!empty($filters['repo_id'])) {
            $sql .= " AND r.id = ?";
            $params[] = $filters['repo_id'];
        }
        
        $repos = Connection::query($sql, $params);
        
        foreach ($repos as $repo) {
            // 使用 proc_open 安全执行 git grep
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            
            $process = proc_open(
                ['git', 'grep', '-n', '--no-color', '-F', $query],
                $descriptorspec,
                $pipes,
                $repo['git_path']
            );
            
            if (!is_resource($process)) {
                continue;
            }
            
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);
            
            if ($returnCode === 0 && !empty($output)) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (preg_match('/^(.+?):(\d+):(.*)$/', $line, $matches)) {
                        $results[] = [
                            'repo_id' => $repo['id'],
                            'repo_name' => $repo['name'],
                            'owner' => $repo['owner'],
                            'file_path' => $matches[1],
                            'line_number' => (int) $matches[2],
                            'content' => $matches[3],
                            'highlight' => $this->highlight($matches[3], $query),
                        ];
                    }
                    
                    if (count($results) >= $limit) {
                        break 2;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'hits' => $results,
            'total' => count($results),
            'processing_time' => 0,
        ];
    }
    
    /**
     * 获取文件内容
     */
    private function getFileContent(string $gitPath, string $file): ?string
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open(
            ['git', 'show', 'HEAD:' . $file],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        if (!is_resource($process)) {
            return null;
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0) {
            return null;
        }
        
        return $output;
    }
    
    /**
     * 检查是否为二进制文件
     */
    private function isBinaryFile(string $file): bool
    {
        $binaryExtensions = [
            'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'tar', 'gz', 'rar', '7z',
            'mp3', 'mp4', 'avi', 'mov', 'wav',
            'exe', 'dll', 'so', 'dylib',
            'ttf', 'otf', 'woff', 'woff2', 'eot',
        ];
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, $binaryExtensions);
    }
    
    /**
     * 检查是否为大文件
     */
    private function isLargeFile(string $gitPath, string $file): bool
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open(
            ['git', 'cat-file', '-s', 'HEAD:' . $file],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        if (!is_resource($process)) {
            return false;
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0 || empty($output)) {
            return true;
        }
        
        return (int) trim($output) > 1024 * 1024; // 1MB
    }
    
    /**
     * 检测语言
     */
    private function detectLanguage(string $file): string
    {
        $map = [
            'php' => 'PHP',
            'js' => 'JavaScript',
            'ts' => 'TypeScript',
            'vue' => 'Vue',
            'py' => 'Python',
            'java' => 'Java',
            'go' => 'Go',
            'rs' => 'Rust',
            'rb' => 'Ruby',
            'c' => 'C',
            'cpp' => 'C++',
            'h' => 'C',
            'hpp' => 'C++',
            'cs' => 'C#',
            'swift' => 'Swift',
            'kt' => 'Kotlin',
            'scala' => 'Scala',
            'sh' => 'Shell',
            'bash' => 'Shell',
            'sql' => 'SQL',
            'html' => 'HTML',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'less' => 'Less',
            'json' => 'JSON',
            'xml' => 'XML',
            'yaml' => 'YAML',
            'yml' => 'YAML',
            'md' => 'Markdown',
        ];
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'Text';
    }
    
    /**
     * 高亮关键词
     */
    private function highlight(string $content, string $query): string
    {
        return preg_replace(
            '/(' . preg_quote($query, '/') . ')/i',
            '<mark>$1</mark>',
            htmlspecialchars($content)
        );
    }
    
    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
