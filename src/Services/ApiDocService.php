<?php
/**
 * CodeVault API 文档服务
 * 
 * 功能：
 * - OpenAPI 3.0 规范生成
 * - Swagger UI 集成
 * - 交互式 API Playground
 * - SDK 生成支持
 */

namespace Services;

class ApiDocService
{
    private $apiVersion = '1.0.0';
    private $baseUrl;
    
    public function __construct(string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?: 'https://api.codevault.example.com';
    }
    
    /**
     * 生成 OpenAPI 规范
     */
    public function generateOpenAPISpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CodeVault API',
                'description' => 'CodeVault Git 托管平台 RESTful API',
                'version' => $this->apiVersion,
                'contact' => [
                    'name' => 'CodeVault Support',
                    'email' => 'support@codevault.example.com',
                    'url' => 'https://codevault.example.com',
                ],
                'license' => [
                    'name' => 'Apache 2.0',
                    'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
                ],
            ],
            'servers' => [
                [
                    'url' => $this->baseUrl,
                    'description' => 'API Server',
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
                ['tokenAuth' => []],
            ],
            'paths' => $this->getPaths(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                    'tokenAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Token',
                    ],
                    'basicAuth' => [
                        'type' => 'http',
                        'scheme' => 'basic',
                    ],
                ],
                'schemas' => $this->getSchemas(),
                'parameters' => $this->getParameters(),
                'responses' => $this->getResponses(),
            ],
            'tags' => $this->getTags(),
        ];
    }
    
    /**
     * 获取 API 路径定义
     */
    private function getPaths(): array
    {
        return [
            // 认证
            '/auth/login' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => '用户登录',
                    'operationId' => 'authLogin',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/LoginRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '登录成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/AuthResponse',
                                    ],
                                ],
                            ],
                        ],
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    ],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => '用户登出',
                    'operationId' => 'authLogout',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => ['description' => '登出成功'],
                    ],
                ],
            ],
            
            // 用户
            '/user' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => '获取当前用户信息',
                    'operationId' => 'getCurrentUser',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/User',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/users/{username}' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => '获取用户信息',
                    'operationId' => 'getUser',
                    'parameters' => [
                        ['$ref' => '#/components/parameters/username'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/User',
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                    ],
                ],
            ],
            
            // 仓库
            '/user/repos' => [
                'get' => [
                    'tags' => ['Repositories'],
                    'summary' => '列出当前用户的仓库',
                    'operationId' => 'listUserRepos',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'visibility', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['all', 'public', 'private']]],
                        ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['created', 'updated', 'pushed', 'full_name']]],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 30, 'maximum' => 100]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => '#/components/schemas/Repository'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Repositories'],
                    'summary' => '创建仓库',
                    'operationId' => 'createRepo',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreateRepoRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => '创建成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Repository'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/repos/{owner}/{repo}' => [
                'get' => [
                    'tags' => ['Repositories'],
                    'summary' => '获取仓库信息',
                    'operationId' => 'getRepo',
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Repository'],
                                ],
                            ],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                    ],
                ],
                'delete' => [
                    'tags' => ['Repositories'],
                    'summary' => '删除仓库',
                    'operationId' => 'deleteRepo',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                    ],
                    'responses' => [
                        '204' => ['description' => '删除成功'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                    ],
                ],
            ],
            
            // Issue
            '/repos/{owner}/{repo}/issues' => [
                'get' => [
                    'tags' => ['Issues'],
                    'summary' => '列出仓库的 Issue',
                    'operationId' => 'listIssues',
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                        ['name' => 'state', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['open', 'closed', 'all'], 'default' => 'open']],
                        ['name' => 'labels', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => '逗号分隔的标签列表'],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 30]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => '#/components/schemas/Issue'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Issues'],
                    'summary' => '创建 Issue',
                    'operationId' => 'createIssue',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreateIssueRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => '创建成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Issue'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/repos/{owner}/{repo}/issues/{issue_number}' => [
                'get' => [
                    'tags' => ['Issues'],
                    'summary' => '获取 Issue',
                    'operationId' => 'getIssue',
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                        ['$ref' => '#/components/parameters/issue_number'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Issue'],
                                ],
                            ],
                        ],
                    ],
                ],
                'patch' => [
                    'tags' => ['Issues'],
                    'summary' => '更新 Issue',
                    'operationId' => 'updateIssue',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                        ['$ref' => '#/components/parameters/issue_number'],
                    ],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UpdateIssueRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '更新成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Issue'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            
            // Pull Request
            '/repos/{owner}/{repo}/pulls' => [
                'get' => [
                    'tags' => ['Pull Requests'],
                    'summary' => '列出 Pull Request',
                    'operationId' => 'listPullRequests',
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                        ['name' => 'state', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['open', 'closed', 'all'], 'default' => 'open']],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 30]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => '#/components/schemas/PullRequest'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Pull Requests'],
                    'summary' => '创建 Pull Request',
                    'operationId' => 'createPullRequest',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreatePullRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => '创建成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PullRequest'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/repos/{owner}/{repo}/pulls/{pull_number}' => [
                'get' => [
                    'tags' => ['Pull Requests'],
                    'summary' => '获取 Pull Request',
                    'operationId' => 'getPullRequest',
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                        ['$ref' => '#/components/parameters/pull_number'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PullRequest'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/repos/{owner}/{repo}/pulls/{pull_number}/merge' => [
                'put' => [
                    'tags' => ['Pull Requests'],
                    'summary' => '合并 Pull Request',
                    'operationId' => 'mergePullRequest',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                        ['$ref' => '#/components/parameters/pull_number'],
                    ],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'commit_title' => ['type' => 'string'],
                                        'commit_message' => ['type' => 'string'],
                                        'merge_method' => ['type' => 'string', 'enum' => ['merge', 'squash', 'rebase'], 'default' => 'merge'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => '合并成功'],
                        '405' => ['description' => '无法合并'],
                    ],
                ],
            ],
            
            // Webhooks
            '/repos/{owner}/{repo}/hooks' => [
                'get' => [
                    'tags' => ['Webhooks'],
                    'summary' => '列出 Webhooks',
                    'operationId' => 'listWebhooks',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => '成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => '#/components/schemas/Webhook'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => '创建 Webhook',
                    'operationId' => 'createWebhook',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['$ref' => '#/components/parameters/owner'],
                        ['$ref' => '#/components/parameters/repo'],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreateWebhookRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => '创建成功',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Webhook'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * 获取 Schema 定义
     */
    private function getSchemas(): array
    {
        return [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'login' => ['type' => 'string', 'example' => 'octocat'],
                    'name' => ['type' => 'string', 'example' => 'The Octocat'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'octocat@example.com'],
                    'avatar_url' => ['type' => 'string', 'format' => 'uri'],
                    'bio' => ['type' => 'string'],
                    'location' => ['type' => 'string'],
                    'blog' => ['type' => 'string', 'format' => 'uri'],
                    'public_repos' => ['type' => 'integer'],
                    'followers' => ['type' => 'integer'],
                    'following' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Repository' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'full_name' => ['type' => 'string', 'example' => 'octocat/Hello-World'],
                    'description' => ['type' => 'string'],
                    'private' => ['type' => 'boolean'],
                    'owner' => ['$ref' => '#/components/schemas/User'],
                    'html_url' => ['type' => 'string', 'format' => 'uri'],
                    'clone_url' => ['type' => 'string', 'format' => 'uri'],
                    'ssh_url' => ['type' => 'string'],
                    'default_branch' => ['type' => 'string', 'example' => 'main'],
                    'stars' => ['type' => 'integer'],
                    'forks' => ['type' => 'integer'],
                    'watchers' => ['type' => 'integer'],
                    'open_issues' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Issue' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'number' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'state' => ['type' => 'string', 'enum' => ['open', 'closed']],
                    'user' => ['$ref' => '#/components/schemas/User'],
                    'labels' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Label'],
                    ],
                    'assignees' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/User'],
                    ],
                    'comments' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'closed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'PullRequest' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'number' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'state' => ['type' => 'string', 'enum' => ['open', 'closed']],
                    'merged' => ['type' => 'boolean'],
                    'user' => ['$ref' => '#/components/schemas/User'],
                    'head' => ['$ref' => '#/components/schemas/PRBranch'],
                    'base' => ['$ref' => '#/components/schemas/PRBranch'],
                    'draft' => ['type' => 'boolean'],
                    'mergeable' => ['type' => 'boolean', 'nullable' => true],
                    'merged_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'PRBranch' => [
                'type' => 'object',
                'properties' => [
                    'ref' => ['type' => 'string'],
                    'sha' => ['type' => 'string'],
                    'repo' => ['$ref' => '#/components/schemas/Repository'],
                ],
            ],
            'Label' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'color' => ['type' => 'string', 'example' => 'ff0000'],
                    'description' => ['type' => 'string'],
                ],
            ],
            'Webhook' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'events' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'active' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'LoginRequest' => [
                'type' => 'object',
                'required' => ['username', 'password'],
                'properties' => [
                    'username' => ['type' => 'string'],
                    'password' => ['type' => 'string', 'format' => 'password'],
                    'otp' => ['type' => 'string', 'description' => 'Two-factor authentication code'],
                ],
            ],
            'AuthResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'token' => ['type' => 'string'],
                    'user' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
            'CreateRepoRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'private' => ['type' => 'boolean', 'default' => false],
                    'auto_init' => ['type' => 'boolean', 'default' => false],
                    'gitignore_template' => ['type' => 'string'],
                    'license_template' => ['type' => 'string'],
                ],
            ],
            'CreateIssueRequest' => [
                'type' => 'object',
                'required' => ['title'],
                'properties' => [
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'assignees' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'milestone' => ['type' => 'integer'],
                ],
            ],
            'UpdateIssueRequest' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'state' => ['type' => 'string', 'enum' => ['open', 'closed']],
                    'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'assignees' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'CreatePullRequest' => [
                'type' => 'object',
                'required' => ['title', 'head', 'base'],
                'properties' => [
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'head' => ['type' => 'string', 'description' => 'The name of the branch where your changes are implemented'],
                    'base' => ['type' => 'string', 'description' => 'The name of the branch you want the changes pulled into'],
                    'draft' => ['type' => 'boolean', 'default' => false],
                ],
            ],
            'CreateWebhookRequest' => [
                'type' => 'object',
                'required' => ['url'],
                'properties' => [
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'content_type' => ['type' => 'string', 'enum' => ['json', 'form'], 'default' => 'json'],
                    'secret' => ['type' => 'string'],
                    'events' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => ['push']],
                    'active' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => ['type' => 'string'],
                    'code' => ['type' => 'integer'],
                ],
            ],
        ];
    }
    
    /**
     * 获取参数定义
     */
    private function getParameters(): array
    {
        return [
            'owner' => [
                'name' => 'owner',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => 'Repository owner',
            ],
            'repo' => [
                'name' => 'repo',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => 'Repository name',
            ],
            'username' => [
                'name' => 'username',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => 'Username',
            ],
            'issue_number' => [
                'name' => 'issue_number',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
                'description' => 'Issue number',
            ],
            'pull_number' => [
                'name' => 'pull_number',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
                'description' => 'Pull request number',
            ],
        ];
    }
    
    /**
     * 获取响应定义
     */
    private function getResponses(): array
    {
        return [
            'Unauthorized' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => ['success' => false, 'error' => 'Unauthorized', 'code' => 401],
                    ],
                ],
            ],
            'Forbidden' => [
                'description' => 'Forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => ['success' => false, 'error' => 'Forbidden', 'code' => 403],
                    ],
                ],
            ],
            'NotFound' => [
                'description' => 'Not Found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                        'example' => ['success' => false, 'error' => 'Not Found', 'code' => 404],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * 获取标签定义
     */
    private function getTags(): array
    {
        return [
            ['name' => 'Authentication', 'description' => '认证相关 API'],
            ['name' => 'Users', 'description' => '用户管理 API'],
            ['name' => 'Repositories', 'description' => '仓库管理 API'],
            ['name' => 'Issues', 'description' => 'Issue 管理 API'],
            ['name' => 'Pull Requests', 'description' => 'Pull Request 管理 API'],
            ['name' => 'Webhooks', 'description' => 'Webhook 管理 API'],
        ];
    }
    
    /**
     * 生成 Swagger UI HTML
     */
    public function generateSwaggerUI(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeVault API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info .title { font-size: 2em; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "/api/docs/openapi.json",
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                deepLinking: true,
                displayOperationId: false,
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 1,
                docExpansion: "list",
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                syntaxHighlight: {
                    activate: true,
                    theme: "monokai"
                }
            });
        }
    </script>
</body>
</html>
HTML;
    }
    
    /**
     * 生成 SDK 代码
     */
    public function generateSDK(string $language = 'php'): string
    {
        return match ($language) {
            'php' => $this->generatePHPSDK(),
            'javascript' => $this->generateJavaScriptSDK(),
            'python' => $this->generatePythonSDK(),
            default => throw new \InvalidArgumentException("Unsupported language: {$language}"),
        };
    }
    
    /**
     * 生成 PHP SDK
     */
    private function generatePHPSDK(): string
    {
        return <<<'PHP'
<?php
/**
 * CodeVault PHP SDK
 * 自动生成的 API 客户端
 */

namespace CodeVault;

class Client
{
    private $baseUrl;
    private $token;
    
    public function __construct(string $baseUrl, string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }
    
    public function setToken(string $token): void
    {
        $this->token = $token;
    }
    
    // 用户 API
    public function getCurrentUser(): array
    {
        return $this->request('GET', '/user');
    }
    
    public function getUser(string $username): array
    {
        return $this->request('GET', "/users/{$username}");
    }
    
    // 仓库 API
    public function listRepos(array $params = []): array
    {
        return $this->request('GET', '/user/repos', $params);
    }
    
    public function createRepo(array $data): array
    {
        return $this->request('POST', '/user/repos', $data);
    }
    
    public function getRepo(string $owner, string $repo): array
    {
        return $this->request('GET', "/repos/{$owner}/{$repo}");
    }
    
    public function deleteRepo(string $owner, string $repo): array
    {
        return $this->request('DELETE', "/repos/{$owner}/{$repo}");
    }
    
    // Issue API
    public function listIssues(string $owner, string $repo, array $params = []): array
    {
        return $this->request('GET', "/repos/{$owner}/{$repo}/issues", $params);
    }
    
    public function createIssue(string $owner, string $repo, array $data): array
    {
        return $this->request('POST', "/repos/{$owner}/{$repo}/issues", $data);
    }
    
    public function getIssue(string $owner, string $repo, int $number): array
    {
        return $this->request('GET', "/repos/{$owner}/{$repo}/issues/{$number}");
    }
    
    // Pull Request API
    public function listPullRequests(string $owner, string $repo, array $params = []): array
    {
        return $this->request('GET', "/repos/{$owner}/{$repo}/pulls", $params);
    }
    
    public function createPullRequest(string $owner, string $repo, array $data): array
    {
        return $this->request('POST', "/repos/{$owner}/{$repo}/pulls", $data);
    }
    
    public function mergePullRequest(string $owner, string $repo, int $number, array $data = []): array
    {
        return $this->request('PUT', "/repos/{$owner}/{$repo}/pulls/{$number}/merge", $data);
    }
    
    // 通用请求方法
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ];
        
        if ($this->token) {
            $options[CURLOPT_HTTPHEADER][] = "Authorization: Bearer {$this->token}";
        }
        
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
}
PHP;
    }
    
    /**
     * 生成 JavaScript SDK
     */
    private function generateJavaScriptSDK(): string
    {
        return <<<'JS'
/**
 * CodeVault JavaScript SDK
 * 自动生成的 API 客户端
 */

class CodeVaultClient {
    constructor(baseUrl, token = null) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = token;
    }
    
    setToken(token) {
        this.token = token;
    }
    
    // 用户 API
    async getCurrentUser() {
        return this.request('GET', '/user');
    }
    
    async getUser(username) {
        return this.request('GET', `/users/${username}`);
    }
    
    // 仓库 API
    async listRepos(params = {}) {
        return this.request('GET', '/user/repos', params);
    }
    
    async createRepo(data) {
        return this.request('POST', '/user/repos', data);
    }
    
    async getRepo(owner, repo) {
        return this.request('GET', `/repos/${owner}/${repo}`);
    }
    
    async deleteRepo(owner, repo) {
        return this.request('DELETE', `/repos/${owner}/${repo}`);
    }
    
    // Issue API
    async listIssues(owner, repo, params = {}) {
        return this.request('GET', `/repos/${owner}/${repo}/issues`, params);
    }
    
    async createIssue(owner, repo, data) {
        return this.request('POST', `/repos/${owner}/${repo}/issues`, data);
    }
    
    async getIssue(owner, repo, number) {
        return this.request('GET', `/repos/${owner}/${repo}/issues/${number}`);
    }
    
    // Pull Request API
    async listPullRequests(owner, repo, params = {}) {
        return this.request('GET', `/repos/${owner}/${repo}/pulls`, params);
    }
    
    async createPullRequest(owner, repo, data) {
        return this.request('POST', `/repos/${owner}/${repo}/pulls`, data);
    }
    
    async mergePullRequest(owner, repo, number, data = {}) {
        return this.request('PUT', `/repos/${owner}/${repo}/pulls/${number}/merge`, data);
    }
    
    // 通用请求方法
    async request(method, endpoint, data = {}) {
        const url = new URL(this.baseUrl + endpoint);
        
        if (method === 'GET' && Object.keys(data).length > 0) {
            Object.keys(data).forEach(key => url.searchParams.append(key, data[key]));
        }
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        };
        
        if (this.token) {
            options.headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        if (['POST', 'PUT', 'PATCH'].includes(method) && Object.keys(data).length > 0) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        return response.json();
    }
}

// 导出
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CodeVaultClient;
} else {
    window.CodeVaultClient = CodeVaultClient;
}
JS;
    }
    
    /**
     * 生成 Python SDK
     */
    private function generatePythonSDK(): string
    {
        return <<<'PYTHON'
"""
CodeVault Python SDK
自动生成的 API 客户端
"""

import requests
from typing import Optional, Dict, List, Any


class CodeVaultClient:
    def __init__(self, base_url: str, token: Optional[str] = None):
        self.base_url = base_url.rstrip('/')
        self.token = token
        self.session = requests.Session()
        self.session.headers.update({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        })
        if token:
            self.session.headers['Authorization'] = f'Bearer {token}'
    
    def set_token(self, token: str) -> None:
        self.token = token
        self.session.headers['Authorization'] = f'Bearer {token}'
    
    # 用户 API
    def get_current_user(self) -> Dict[str, Any]:
        return self._request('GET', '/user')
    
    def get_user(self, username: str) -> Dict[str, Any]:
        return self._request('GET', f'/users/{username}')
    
    # 仓库 API
    def list_repos(self, **params) -> List[Dict[str, Any]]:
        return self._request('GET', '/user/repos', params=params)
    
    def create_repo(self, **data) -> Dict[str, Any]:
        return self._request('POST', '/user/repos', json=data)
    
    def get_repo(self, owner: str, repo: str) -> Dict[str, Any]:
        return self._request('GET', f'/repos/{owner}/{repo}')
    
    def delete_repo(self, owner: str, repo: str) -> None:
        self._request('DELETE', f'/repos/{owner}/{repo}')
    
    # Issue API
    def list_issues(self, owner: str, repo: str, **params) -> List[Dict[str, Any]]:
        return self._request('GET', f'/repos/{owner}/{repo}/issues', params=params)
    
    def create_issue(self, owner: str, repo: str, **data) -> Dict[str, Any]:
        return self._request('POST', f'/repos/{owner}/{repo}/issues', json=data)
    
    def get_issue(self, owner: str, repo: str, number: int) -> Dict[str, Any]:
        return self._request('GET', f'/repos/{owner}/{repo}/issues/{number}')
    
    # Pull Request API
    def list_pull_requests(self, owner: str, repo: str, **params) -> List[Dict[str, Any]]:
        return self._request('GET', f'/repos/{owner}/{repo}/pulls', params=params)
    
    def create_pull_request(self, owner: str, repo: str, **data) -> Dict[str, Any]:
        return self._request('POST', f'/repos/{owner}/{repo}/pulls', json=data)
    
    def merge_pull_request(self, owner: str, repo: str, number: int, **data) -> Dict[str, Any]:
        return self._request('PUT', f'/repos/{owner}/{repo}/pulls/{number}/merge', json=data)
    
    # 通用请求方法
    def _request(self, method: str, endpoint: str, **kwargs) -> Any:
        url = self.base_url + endpoint
        response = self.session.request(method, url, **kwargs)
        response.raise_for_status()
        return response.json()
PYTHON;
    }
}
