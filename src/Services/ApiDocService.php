<?php
/**
 * CodeVault API 文档生成器
 * 
 * 功能：
 * - 自动生成 OpenAPI 3.0 规范
 * - 从代码注释提取文档
 * - 支持多种认证方式
 * - 生成 Swagger UI
 */

namespace Services;

use Core\Database;
use Core\Logger;

class ApiDocService
{
    private $db;
    private $logger;
    
    // OpenAPI 规范基础信息
    private $openApiSpec = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'CodeVault API',
            'description' => 'CodeVault - 自托管 Git 托管平台 API',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'CodeVault Support',
                'email' => 'support@codevault.local',
            ],
            'license' => [
                'name' => 'Apache 2.0',
                'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
            ],
        ],
        'servers' => [
            ['url' => '/api', 'description' => '当前服务器'],
        ],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'JWT 认证令牌',
                ],
                'tokenAuth' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'Authorization',
                    'description' => 'Personal Access Token',
                ],
                'basicAuth' => [
                    'type' => 'http',
                    'scheme' => 'basic',
                    'description' => 'Basic 认证',
                ],
            ],
            'schemas' => [],
            'responses' => [
                'Unauthorized' => [
                    'description' => '未授权',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error'],
                        ],
                    ],
                ],
                'NotFound' => [
                    'description' => '资源不存在',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error'],
                        ],
                    ],
                ],
                'ValidationError' => [
                    'description' => '验证错误',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ValidationError'],
                        ],
                    ],
                ],
            ],
        ],
        'paths' => [],
        'tags' => [],
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('apidoc');
        $this->initSchemas();
    }
    
    /**
     * 初始化通用 Schema
     */
    private function initSchemas(): void
    {
        $this->openApiSpec['components']['schemas'] = [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => ['type' => 'string', 'example' => '错误信息'],
                ],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'username' => ['type' => 'string', 'example' => 'johndoe'],
                    'name' => ['type' => 'string', 'example' => 'John Doe'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                    'avatar_url' => ['type' => 'string', 'format' => 'uri'],
                    'bio' => ['type' => 'string'],
                    'location' => ['type' => 'string'],
                    'website' => ['type' => 'string', 'format' => 'uri'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Repository' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'example' => 'my-project'],
                    'full_name' => ['type' => 'string', 'example' => 'johndoe/my-project'],
                    'description' => ['type' => 'string'],
                    'private' => ['type' => 'boolean'],
                    'fork' => ['type' => 'boolean'],
                    'language' => ['type' => 'string'],
                    'stars_count' => ['type' => 'integer'],
                    'forks_count' => ['type' => 'integer'],
                    'watchers_count' => ['type' => 'integer'],
                    'open_issues_count' => ['type' => 'integer'],
                    'default_branch' => ['type' => 'string', 'example' => 'main'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'pushed_at' => ['type' => 'string', 'format' => 'date-time'],
                    'owner' => ['$ref' => '#/components/schemas/User'],
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
                    'labels' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Label']],
                    'assignees' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/User']],
                    'milestone' => ['$ref' => '#/components/schemas/Milestone'],
                    'comments_count' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'closed_at' => ['type' => 'string', 'format' => 'date-time'],
                    'author' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
            'PullRequest' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'number' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'state' => ['type' => 'string', 'enum' => ['open', 'closed', 'merged']],
                    'draft' => ['type' => 'boolean'],
                    'mergeable' => ['type' => 'boolean'],
                    'merged' => ['type' => 'boolean'],
                    'base' => ['$ref' => '#/components/schemas/BranchRef'],
                    'head' => ['$ref' => '#/components/schemas/BranchRef'],
                    'author' => ['$ref' => '#/components/schemas/User'],
                    'reviewers' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/User']],
                    'additions' => ['type' => 'integer'],
                    'deletions' => ['type' => 'integer'],
                    'changed_files' => ['type' => 'integer'],
                    'commits_count' => ['type' => 'integer'],
                    'comments_count' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'merged_at' => ['type' => 'string', 'format' => 'date-time'],
                    'closed_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'BranchRef' => [
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
            'Milestone' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'number' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'state' => ['type' => 'string', 'enum' => ['open', 'closed']],
                    'open_issues' => ['type' => 'integer'],
                    'closed_issues' => ['type' => 'integer'],
                    'due_on' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Commit' => [
                'type' => 'object',
                'properties' => [
                    'sha' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'author' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'date' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'committer' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'date' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'parents' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'stats' => [
                        'type' => 'object',
                        'properties' => [
                            'additions' => ['type' => 'integer'],
                            'deletions' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'Gist' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'visibility' => ['type' => 'string', 'enum' => ['public', 'private', 'link_only']],
                    'files' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'object',
                            'properties' => [
                                'content' => ['type' => 'string'],
                                'language' => ['type' => 'string'],
                                'size' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                    'stats' => [
                        'type' => 'object',
                        'properties' => [
                            'stars' => ['type' => 'integer'],
                            'forks' => ['type' => 'integer'],
                            'comments' => ['type' => 'integer'],
                            'revisions' => ['type' => 'integer'],
                        ],
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Webhook' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'events' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'active' => ['type' => 'boolean'],
                    'content_type' => ['type' => 'string', 'enum' => ['json', 'form']],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
        ];
    }
    
    /**
     * 生成完整 API 文档
     */
    public function generate(): array
    {
        // 添加标签
        $this->addTags();
        
        // 添加路径
        $this->addPaths();
        
        return $this->openApiSpec;
    }
    
    /**
     * 添加标签
     */
    private function addTags(): void
    {
        $this->openApiSpec['tags'] = [
            ['name' => 'auth', 'description' => '认证相关接口'],
            ['name' => 'users', 'description' => '用户管理接口'],
            ['name' => 'repositories', 'description' => '仓库管理接口'],
            ['name' => 'issues', 'description' => 'Issue 管理接口'],
            ['name' => 'pull-requests', 'description' => 'Pull Request 管理接口'],
            ['name' => 'commits', 'description' => '提交管理接口'],
            ['name' => 'branches', 'description' => '分支管理接口'],
            ['name' => 'releases', 'description' => '发布管理接口'],
            ['name' => 'gists', 'description' => 'Gist 代码片段接口'],
            ['name' => 'webhooks', 'description' => 'Webhook 管理接口'],
            ['name' => 'wiki', 'description' => 'Wiki 管理接口'],
            ['name' => 'search', 'description' => '搜索接口'],
            ['name' => 'activity', 'description' => '活动接口'],
            ['name' => 'notifications', 'description' => '通知接口'],
        ];
    }
    
    /**
     * 添加路径
     */
    private function addPaths(): void
    {
        // 认证
        $this->addAuthPaths();
        
        // 用户
        $this->addUserPaths();
        
        // 仓库
        $this->addRepositoryPaths();
        
        // Issue
        $this->addIssuePaths();
        
        // Pull Request
        $this->addPullRequestPaths();
        
        // Gist
        $this->addGistPaths();
        
        // Webhook
        $this->addWebhookPaths();
        
        // 搜索
        $this->addSearchPaths();
    }
    
    /**
     * 认证路径
     */
    private function addAuthPaths(): void
    {
        $this->openApiSpec['paths']['/auth/register'] = [
            'post' => [
                'tags' => ['auth'],
                'summary' => '注册新用户',
                'operationId' => 'register',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['username', 'email', 'password'],
                                'properties' => [
                                    'username' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 50],
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string', 'minLength' => 8],
                                    'name' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => '注册成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/User']]],
                    ],
                    '400' => ['$ref' => '#/components/responses/ValidationError'],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/auth/login'] = [
            'post' => [
                'tags' => ['auth'],
                'summary' => '用户登录',
                'operationId' => 'login',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['username', 'password'],
                                'properties' => [
                                    'username' => ['type' => 'string', 'description' => '用户名或邮箱'],
                                    'password' => ['type' => 'string'],
                                    'remember' => ['type' => 'boolean'],
                                ],
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
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                        'token' => ['type' => 'string'],
                                        'user' => ['$ref' => '#/components/schemas/User'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/auth/logout'] = [
            'post' => [
                'tags' => ['auth'],
                'summary' => '用户登出',
                'operationId' => 'logout',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => ['description' => '登出成功'],
                ],
            ],
        ];
    }
    
    /**
     * 用户路径
     */
    private function addUserPaths(): void
    {
        $this->openApiSpec['paths']['/user'] = [
            'get' => [
                'tags' => ['users'],
                'summary' => '获取当前用户信息',
                'operationId' => 'getCurrentUser',
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/User']]],
                    ],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/users/{username}'] = [
            'get' => [
                'tags' => ['users'],
                'summary' => '获取用户信息',
                'operationId' => 'getUser',
                'parameters' => [
                    ['name' => 'username', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/User']]],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/users/{username}/repos'] = [
            'get' => [
                'tags' => ['users'],
                'summary' => '获取用户仓库列表',
                'operationId' => 'getUserRepos',
                'parameters' => [
                    ['name' => 'username', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['all', 'owner', 'member'], 'default' => 'all']],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['created', 'updated', 'pushed', 'full_name'], 'default' => 'full_name']],
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 30, 'maximum' => 100]],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Repository']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * 仓库路径
     */
    private function addRepositoryPaths(): void
    {
        $this->openApiSpec['paths']['/user/repos'] = [
            'get' => [
                'tags' => ['repositories'],
                'summary' => '获取当前用户仓库列表',
                'operationId' => 'listCurrentUserRepos',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'visibility', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['all', 'public', 'private']]],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['created', 'updated', 'pushed', 'full_name']]],
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Repository']],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['repositories'],
                'summary' => '创建仓库',
                'operationId' => 'createRepo',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
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
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => '创建成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Repository']]],
                    ],
                    '400' => ['$ref' => '#/components/responses/ValidationError'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/repos/{owner}/{repo}'] = [
            'get' => [
                'tags' => ['repositories'],
                'summary' => '获取仓库信息',
                'operationId' => 'getRepo',
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Repository']]],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'patch' => [
                'tags' => ['repositories'],
                'summary' => '更新仓库信息',
                'operationId' => 'updateRepo',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'private' => ['type' => 'boolean'],
                                    'default_branch' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => '更新成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Repository']]],
                    ],
                ],
            ],
            'delete' => [
                'tags' => ['repositories'],
                'summary' => '删除仓库',
                'operationId' => 'deleteRepo',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '204' => ['description' => '删除成功'],
                    '403' => ['description' => '无权限'],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
        ];
    }
    
    /**
     * Issue 路径
     */
    private function addIssuePaths(): void
    {
        $this->openApiSpec['paths']['/repos/{owner}/{repo}/issues'] = [
            'get' => [
                'tags' => ['issues'],
                'summary' => '获取 Issue 列表',
                'operationId' => 'listIssues',
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'state', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['open', 'closed', 'all'], 'default' => 'open']],
                    ['name' => 'labels', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => '逗号分隔的标签列表'],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['created', 'updated', 'comments'], 'default' => 'created']],
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Issue']],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['issues'],
                'summary' => '创建 Issue',
                'operationId' => 'createIssue',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
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
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => '创建成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Issue']]],
                    ],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/repos/{owner}/{repo}/issues/{issue_number}'] = [
            'get' => [
                'tags' => ['issues'],
                'summary' => '获取 Issue 详情',
                'operationId' => 'getIssue',
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'issue_number', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Issue']]],
                    ],
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                ],
            ],
            'patch' => [
                'tags' => ['issues'],
                'summary' => '更新 Issue',
                'operationId' => 'updateIssue',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'issue_number', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'body' => ['type' => 'string'],
                                    'state' => ['type' => 'string', 'enum' => ['open', 'closed']],
                                    'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'assignees' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => '更新成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Issue']]],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Pull Request 路径
     */
    private function addPullRequestPaths(): void
    {
        $this->openApiSpec['paths']['/repos/{owner}/{repo}/pulls'] = [
            'get' => [
                'tags' => ['pull-requests'],
                'summary' => '获取 Pull Request 列表',
                'operationId' => 'listPullRequests',
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'state', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['open', 'closed', 'all']]],
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PullRequest']],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['pull-requests'],
                'summary' => '创建 Pull Request',
                'operationId' => 'createPullRequest',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['title', 'head', 'base'],
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'body' => ['type' => 'string'],
                                    'head' => ['type' => 'string', 'description' => '源分支'],
                                    'base' => ['type' => 'string', 'description' => '目标分支'],
                                    'draft' => ['type' => 'boolean', 'default' => false],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => '创建成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PullRequest']]],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Gist 路径
     */
    private function addGistPaths(): void
    {
        $this->openApiSpec['paths']['/gists'] = [
            'get' => [
                'tags' => ['gists'],
                'summary' => '获取 Gist 列表',
                'operationId' => 'listGists',
                'parameters' => [
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Gist']],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['gists'],
                'summary' => '创建 Gist',
                'operationId' => 'createGist',
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['files'],
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'visibility' => ['type' => 'string', 'enum' => ['public', 'private', 'link_only']],
                                    'files' => [
                                        'type' => 'object',
                                        'additionalProperties' => [
                                            'type' => 'object',
                                            'properties' => ['content' => ['type' => 'string']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => '创建成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Gist']]],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Webhook 路径
     */
    private function addWebhookPaths(): void
    {
        $this->openApiSpec['paths']['/repos/{owner}/{repo}/hooks'] = [
            'get' => [
                'tags' => ['webhooks'],
                'summary' => '获取 Webhook 列表',
                'operationId' => 'listWebhooks',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Webhook']],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'tags' => ['webhooks'],
                'summary' => '创建 Webhook',
                'operationId' => 'createWebhook',
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    ['name' => 'owner', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'repo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['url', 'events'],
                                'properties' => [
                                    'url' => ['type' => 'string', 'format' => 'uri'],
                                    'events' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'secret' => ['type' => 'string'],
                                    'active' => ['type' => 'boolean', 'default' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => '创建成功',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Webhook']]],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * 搜索路径
     */
    private function addSearchPaths(): void
    {
        $this->openApiSpec['paths']['/search/repositories'] = [
            'get' => [
                'tags' => ['search'],
                'summary' => '搜索仓库',
                'operationId' => 'searchRepos',
                'parameters' => [
                    ['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['stars', 'forks', 'updated']]],
                    ['name' => 'order', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'total_count' => ['type' => 'integer'],
                                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Repository']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        
        $this->openApiSpec['paths']['/search/issues'] = [
            'get' => [
                'tags' => ['search'],
                'summary' => '搜索 Issue',
                'operationId' => 'searchIssues',
                'parameters' => [
                    ['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['comments', 'created', 'updated']]],
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => '成功',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'total_count' => ['type' => 'integer'],
                                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Issue']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * 导出为 JSON
     */
    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 导出为 YAML
     */
    public function toYaml(): string
    {
        $spec = $this->generate();
        return $this->arrayToYaml($spec);
    }
    
    /**
     * 数组转 YAML
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (empty($value) || array_keys($value) === range(0, count($value) - 1)) {
                    // 索引数组
                    $yaml .= "{$prefix}{$key}:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "{$prefix}  -\n";
                            foreach ($item as $k => $v) {
                                if (is_array($v)) {
                                    $yaml .= "{$prefix}    {$k}:\n";
                                    foreach ($v as $k2 => $v2) {
                                        $yaml .= "{$prefix}      {$k2}: " . $this->yamlValue($v2) . "\n";
                                    }
                                } else {
                                    $yaml .= "{$prefix}    {$k}: " . $this->yamlValue($v) . "\n";
                                }
                            }
                        } else {
                            $yaml .= "{$prefix}  - " . $this->yamlValue($item) . "\n";
                        }
                    }
                } else {
                    // 关联数组
                    $yaml .= "{$prefix}{$key}:\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= "{$prefix}{$key}: " . $this->yamlValue($value) . "\n";
            }
        }
        
        return $yaml;
    }
    
    /**
     * YAML 值格式化
     */
    private function yamlValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_string($value) && (strpos($value, ':') !== false || strpos($value, '#') !== false)) {
            return '"' . addslashes($value) . '"';
        }
        return (string)$value;
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
    <title>CodeVault API 文档</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; padding: 0; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info .title { font-size: 2rem; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "/api/docs/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 1,
                docExpansion: 'list',
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
            });
            window.ui = ui;
        }
    </script>
</body>
</html>
HTML;
    }
}
