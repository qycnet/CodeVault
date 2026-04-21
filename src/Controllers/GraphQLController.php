<?php
/**
 * CodeVault - GraphQL 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\GraphQLService;

class GraphQLController
{
    private $graphql;
    
    public function __construct()
    {
        $this->graphql = new GraphQLService();
    }
    
    /**
     * 执行 GraphQL 查询
     */
    public function execute(array $data): array
    {
        $query = $data['query'] ?? '';
        $variables = $data['variables'] ?? [];
        
        if (empty($query)) {
            return [
                'errors' => [
                    ['message' => 'Query is required'],
                ],
            ];
        }
        
        return $this->graphql->execute($query, $variables);
    }
    
    /**
     * 获取 GraphQL Schema
     */
    public function schema(): string
    {
        return $this->graphql->getSchema();
    }
    
    /**
     * GraphQL Playground
     */
    public function playground(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GraphQL Playground</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/graphql-playground-react@1.7.8/build/static/css/index.css">
    <style>
        body { margin: 0; padding: 0; }
        #root { height: 100vh; }
    </style>
</head>
<body>
    <div id="root"></div>
    <script src="https://cdn.jsdelivr.net/npm/graphql-playground-react@1.7.8/build/static/js/middleware.js"></script>
    <script>
        window.addEventListener('load', function(event) {
            GraphQLPlayground.init(document.getElementById('root'), {
                endpoint: '/api/graphql'
            });
        });
    </script>
</body>
</html>
HTML;
    }
}
