<?php
/**
 * CodeVault - GraphQL API 服务
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class GraphQLService
{
    /**
     * 执行 GraphQL 查询
     */
    public function execute(string $query, array $variables = []): array
    {
        try {
            $parsed = $this->parseQuery($query);
            $result = $this->resolve($parsed, $variables);
            
            return [
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [
                    ['message' => $e->getMessage()],
                ],
            ];
        }
    }
    
    /**
     * 解析 GraphQL 查询
     */
    private function parseQuery(string $query): array
    {
        // 简化的查询解析
        $parsed = [
            'operation' => 'query',
            'fields' => [],
        ];
        
        // 提取操作类型
        if (preg_match('/^\s*(mutation|query)\s*\{/', $query, $matches)) {
            $parsed['operation'] = $matches[1];
        }
        
        // 提取字段
        preg_match('/\{(.+)\}/s', $query, $matches);
        if (isset($matches[1])) {
            $parsed['fields'] = $this->parseFields(trim($matches[1]));
        }
        
        return $parsed;
    }
    
    /**
     * 解析字段
     */
    private function parseFields(string $content): array
    {
        $fields = [];
        $lines = preg_split('/\n/', $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === '{' || $line === '}') {
                continue;
            }
            
            // 解析字段名和参数
            if (preg_match('/^(\w+)(\([^)]+\))?\s*(\{[^}]+\})?$/', $line, $matches)) {
                $field = ['name' => $matches[1]];
                
                if (isset($matches[2])) {
                    $field['args'] = $this->parseArgs($matches[2]);
                }
                
                if (isset($matches[3])) {
                    $field['selection'] = $this->parseFields(trim($matches[3], '{}'));
                }
                
                $fields[] = $field;
            }
        }
        
        return $fields;
    }
    
    /**
     * 解析参数
     */
    private function parseArgs(string $args): array
    {
        $result = [];
        
        if (preg_match('/\(([^)]+)\)/', $args, $matches)) {
            $pairs = explode(',', $matches[1]);
            
            foreach ($pairs as $pair) {
                if (preg_match('/(\w+):\s*(.+)/', trim($pair), $m)) {
                    $value = trim($m[2]);
                    
                    // 移除引号
                    if (preg_match('/^"(.+)"$/', $value, $v)) {
                        $value = $v[1];
                    } elseif (preg_match('/^\$(\w+)$/', $value, $v)) {
                        $value = ['variable' => $v[1]];
                    }
                    
                    $result[$m[1]] = $value;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 解析查询
     */
    private function resolve(array $parsed, array $variables): array
    {
        $result = [];
        
        foreach ($parsed['fields'] as $field) {
            $name = $field['name'];
            $args = $this->resolveVariables($field['args'] ?? [], $variables);
            $selection = $field['selection'] ?? [];
            
            $result[$name] = $this->resolveField($name, $args, $selection);
        }
        
        return $result;
    }
    
    /**
     * 解析变量
     */
    private function resolveVariables(array $args, array $variables): array
    {
        $resolved = [];
        
        foreach ($args as $key => $value) {
            if (is_array($value) && isset($value['variable'])) {
                $resolved[$key] = $variables[$value['variable']] ?? null;
            } else {
                $resolved[$key] = $value;
            }
        }
        
        return $resolved;
    }
    
    /**
     * 解析字段
     */
    private function resolveField(string $name, array $args, array $selection): mixed
    {
        return match ($name) {
            'viewer' => $this->resolveViewer($selection),
            'repository' => $this->resolveRepository($args, $selection),
            'repositories' => $this->resolveRepositories($args, $selection),
            'user' => $this->resolveUser($args, $selection),
            'users' => $this->resolveUsers($args, $selection),
            'issue' => $this->resolveIssue($args, $selection),
            'pullRequest' => $this->resolvePullRequest($args, $selection),
            'search' => $this->resolveSearch($args, $selection),
            default => null,
        };
    }
    
    /**
     * 解析 viewer
     */
    private function resolveViewer(array $selection): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return ['error' => '未登录'];
        }
        
        return $this->selectFields($user, $selection);
    }
    
    /**
     * 解析 repository
     */
    private function resolveRepository(array $args, array $selection): ?array
    {
        $owner = $args['owner'] ?? '';
        $name = $args['name'] ?? '';
        
        $repo = Connection::queryOne(
            "SELECT r.*, u.username as owner_name 
             FROM repositories r 
             JOIN users u ON r.user_id = u.id 
             WHERE u.username = ? AND r.name = ? AND r.deleted_at IS NULL",
            [$owner, $name]
        );
        
        if (!$repo) {
            return null;
        }
        
        $result = $this->selectFields($repo, $selection);
        
        // 解析嵌套字段
        foreach ($selection as $field) {
            $fieldName = $field['name'];
            
            if ($fieldName === 'issues') {
                $result['issues'] = $this->resolveRepositoryIssues($repo['id'], $field);
            } elseif ($fieldName === 'pullRequests') {
                $result['pullRequests'] = $this->resolveRepositoryPRs($repo['id'], $field);
            } elseif ($fieldName === 'branches') {
                $result['branches'] = $this->resolveRepositoryBranches($repo['id'], $field);
            } elseif ($fieldName === 'owner') {
                $result['owner'] = $this->resolveUser(['id' => $repo['user_id']], $field);
            }
        }
        
        return $result;
    }
    
    /**
     * 解析 repositories
     */
    private function resolveRepositories(array $args, array $selection): array
    {
        $limit = (int) ($args['first'] ?? 20);
        $after = $args['after'] ?? null;
        
        $sql = "SELECT r.*, u.username as owner_name 
                FROM repositories r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.deleted_at IS NULL 
                ORDER BY r.created_at DESC 
                LIMIT ?";
        
        $repos = Connection::query($sql, [$limit]);
        
        $nodes = [];
        foreach ($repos as $repo) {
            $nodes[] = $this->selectFields($repo, $selection);
        }
        
        return [
            'nodes' => $nodes,
            'totalCount' => count($nodes),
        ];
    }
    
    /**
     * 解析 user
     */
    private function resolveUser(array $args, array $selection): ?array
    {
        $login = $args['login'] ?? '';
        $id = $args['id'] ?? 0;
        
        if ($id) {
            $user = Connection::queryOne("SELECT * FROM users WHERE id = ?", [$id]);
        } else {
            $user = Connection::queryOne("SELECT * FROM users WHERE username = ?", [$login]);
        }
        
        if (!$user) {
            return null;
        }
        
        return $this->selectFields($user, $selection);
    }
    
    /**
     * 解析 users
     */
    private function resolveUsers(array $args, array $selection): array
    {
        $limit = (int) ($args['first'] ?? 20);
        
        $users = Connection::query("SELECT * FROM users ORDER BY created_at DESC LIMIT ?", [$limit]);
        
        $nodes = [];
        foreach ($users as $user) {
            $nodes[] = $this->selectFields($user, $selection);
        }
        
        return [
            'nodes' => $nodes,
            'totalCount' => count($nodes),
        ];
    }
    
    /**
     * 解析 issue
     */
    private function resolveIssue(array $args, array $selection): ?array
    {
        $number = (int) ($args['number'] ?? 0);
        $repoId = $args['repositoryId'] ?? 0;
        
        $issue = Connection::queryOne(
            "SELECT * FROM issues WHERE repo_id = ? AND number = ?",
            [$repoId, $number]
        );
        
        if (!$issue) {
            return null;
        }
        
        return $this->selectFields($issue, $selection);
    }
    
    /**
     * 解析 pullRequest
     */
    private function resolvePullRequest(array $args, array $selection): ?array
    {
        $number = (int) ($args['number'] ?? 0);
        $repoId = $args['repositoryId'] ?? 0;
        
        $pr = Connection::queryOne(
            "SELECT * FROM pull_requests WHERE repo_id = ? AND number = ?",
            [$repoId, $number]
        );
        
        if (!$pr) {
            return null;
        }
        
        return $this->selectFields($pr, $selection);
    }
    
    /**
     * 解析 search
     */
    private function resolveSearch(array $args, array $selection): array
    {
        $query = $args['query'] ?? '';
        $type = $args['type'] ?? 'REPOSITORY';
        $first = (int) ($args['first'] ?? 20);
        
        $results = [];
        
        if ($type === 'REPOSITORY') {
            $repos = Connection::query(
                "SELECT r.*, u.username as owner_name 
                 FROM repositories r 
                 JOIN users u ON r.user_id = u.id 
                 WHERE r.name LIKE ? AND r.deleted_at IS NULL 
                 LIMIT ?",
                ["%{$query}%", $first]
            );
            
            foreach ($repos as $repo) {
                $results[] = [
                    '__typename' => 'Repository',
                    'id' => $repo['id'],
                    'name' => $repo['name'],
                    'description' => $repo['description'],
                    'owner' => ['login' => $repo['owner_name']],
                ];
            }
        }
        
        return [
            'nodes' => $results,
            'repositoryCount' => count($results),
        ];
    }
    
    /**
     * 解析仓库 Issues
     */
    private function resolveRepositoryIssues(int $repoId, array $field): array
    {
        $args = $field['args'] ?? [];
        $limit = (int) ($args['first'] ?? 20);
        $state = $args['states'] ?? null;
        
        $sql = "SELECT * FROM issues WHERE repo_id = ?";
        $params = [$repoId];
        
        if ($state) {
            $sql .= " AND status = ?";
            $params[] = strtolower($state);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $issues = Connection::query($sql, $params);
        
        $nodes = [];
        foreach ($issues as $issue) {
            $nodes[] = $this->selectFields($issue, $field['selection'] ?? []);
        }
        
        return [
            'nodes' => $nodes,
            'totalCount' => count($nodes),
        ];
    }
    
    /**
     * 解析仓库 PRs
     */
    private function resolveRepositoryPRs(int $repoId, array $field): array
    {
        $args = $field['args'] ?? [];
        $limit = (int) ($args['first'] ?? 20);
        
        $prs = Connection::query(
            "SELECT * FROM pull_requests WHERE repo_id = ? ORDER BY created_at DESC LIMIT ?",
            [$repoId, $limit]
        );
        
        $nodes = [];
        foreach ($prs as $pr) {
            $nodes[] = $this->selectFields($pr, $field['selection'] ?? []);
        }
        
        return [
            'nodes' => $nodes,
            'totalCount' => count($nodes),
        ];
    }
    
    /**
     * 解析仓库分支
     */
    private function resolveRepositoryBranches(int $repoId, array $field): array
    {
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return [];
        }
        
        $cmd = sprintf('cd %s && git branch --format="%%(refname:short)" 2>/dev/null', escapeshellarg($repo['git_path']));
        exec($cmd, $output);
        
        $nodes = [];
        foreach ($output as $branch) {
            $nodes[] = ['name' => trim($branch)];
        }
        
        return $nodes;
    }
    
    /**
     * 选择字段
     */
    private function selectFields(array $data, array $selection): array
    {
        if (empty($selection)) {
            return $data;
        }
        
        $result = [];
        
        foreach ($selection as $field) {
            $name = $field['name'];
            $alias = $field['alias'] ?? $name;
            
            // 字段名映射
            $mappedName = match ($name) {
                'login' => 'username',
                'databaseId' => 'id',
                'url' => 'html_url',
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
                'isPrivate' => 'is_private',
                'isFork' => 'is_fork',
                'stargazerCount' => 'star_count',
                'forkCount' => 'fork_count',
                'openIssuesCount' => 'open_issues_count',
                default => $name,
            };
            
            if (isset($data[$mappedName])) {
                $result[$alias] = $data[$mappedName];
            }
        }
        
        return $result;
    }
    
    /**
     * 获取当前用户
     */
    private function getCurrentUser(): ?array
    {
        return Session::user();
    }
    
    /**
     * 获取 GraphQL Schema
     */
    public function getSchema(): string
    {
        return <<<'GRAPHQL'
type Query {
  viewer: User
  repository(owner: String!, name: String!): Repository
  repositories(first: Int, after: String): RepositoryConnection!
  user(login: String!): User
  users(first: Int): UserConnection!
  issue(repositoryId: ID!, number: Int!): Issue
  pullRequest(repositoryId: ID!, number: Int!): PullRequest
  search(query: String!, type: SearchType!, first: Int): SearchResult!
}

type Mutation {
  createRepository(input: CreateRepositoryInput!): Repository
  createIssue(input: CreateIssueInput!): Issue
  createPullRequest(input: CreatePullRequestInput!): PullRequest
  updateIssue(input: UpdateIssueInput!): Issue
  closeIssue(input: CloseIssueInput!): Issue
}

type User {
  id: ID!
  databaseId: Int!
  login: String!
  email: String
  avatarUrl: String
  bio: String
  createdAt: String!
  repositories(first: Int): RepositoryConnection!
}

type Repository {
  id: ID!
  databaseId: Int!
  name: String!
  description: String
  url: String!
  isPrivate: Boolean!
  isFork: Boolean!
  createdAt: String!
  updatedAt: String!
  owner: User!
  issues(first: Int, states: [IssueState]): IssueConnection!
  pullRequests(first: Int, states: [PullRequestState]): PullRequestConnection!
  branches: [Branch!]!
  stargazerCount: Int!
  forkCount: Int!
  openIssuesCount: Int!
}

type Issue {
  id: ID!
  databaseId: Int!
  number: Int!
  title: String!
  body: String
  state: IssueState!
  createdAt: String!
  updatedAt: String!
  author: User!
  labels(first: Int): LabelConnection!
  comments(first: Int): CommentConnection!
}

type PullRequest {
  id: ID!
  databaseId: Int!
  number: Int!
  title: String!
  body: String
  state: PullRequestState!
  createdAt: String!
  updatedAt: String!
  author: User!
  baseRefName: String!
  headRefName: String!
  mergeable: MergeableState!
  comments(first: Int): CommentConnection!
}

type Branch {
  name: String!
}

type Label {
  id: ID!
  name: String!
  color: String!
  description: String
}

type Comment {
  id: ID!
  body: String!
  author: User!
  createdAt: String!
}

type RepositoryConnection {
  nodes: [Repository!]!
  totalCount: Int!
  pageInfo: PageInfo!
}

type UserConnection {
  nodes: [User!]!
  totalCount: Int!
}

type IssueConnection {
  nodes: [Issue!]!
  totalCount: Int!
}

type PullRequestConnection {
  nodes: [PullRequest!]!
  totalCount: Int!
}

type LabelConnection {
  nodes: [Label!]!
}

type CommentConnection {
  nodes: [Comment!]!
}

type SearchResult {
  nodes: [SearchResultItem!]!
  repositoryCount: Int!
  issueCount: Int!
  userCount: Int!
}

union SearchResultItem = Repository | Issue | User

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}

enum IssueState {
  OPEN
  CLOSED
}

enum PullRequestState {
  OPEN
  CLOSED
  MERGED
}

enum MergeableState {
  MERGEABLE
  CONFLICTING
  UNKNOWN
}

enum SearchType {
  REPOSITORY
  ISSUE
  USER
}

input CreateRepositoryInput {
  name: String!
  description: String
  visibility: String!
}

input CreateIssueInput {
  repositoryId: ID!
  title: String!
  body: String
  labels: [String!]
}

input CreatePullRequestInput {
  repositoryId: ID!
  title: String!
  body: String
  baseRefName: String!
  headRefName: String!
}

input UpdateIssueInput {
  issueId: ID!
  title: String
  body: String
  labels: [String!]
}

input CloseIssueInput {
  issueId: ID!
}
GRAPHQL;
    }
}
