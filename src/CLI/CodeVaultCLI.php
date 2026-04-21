<?php
/**
 * CodeVault CLI 工具
 * 
 * 功能：
 * - 仓库管理
 * - PR 创建与管理
 * - Issue 操作
 * - 用户管理
 * - 配置管理
 */

namespace CLI;

class CodeVaultCLI
{
    private $apiBaseUrl;
    private $token;
    private $configFile;
    
    public function __construct()
    {
        $this->configFile = $_SERVER['HOME'] . '/.codevault/config.json';
        $this->loadConfig();
    }
    
    /**
     * 运行 CLI
     */
    public function run(array $argv): int
    {
        if (count($argv) < 2) {
            $this->showHelp();
            return 0;
        }
        
        $command = $argv[1];
        $args = array_slice($argv, 2);
        
        return match ($command) {
            'help' => $this->showHelp(),
            'version' => $this->showVersion(),
            'auth' => $this->handleAuth($args),
            'repo' => $this->handleRepo($args),
            'pr' => $this->handlePR($args),
            'issue' => $this->handleIssue($args),
            'user' => $this->handleUser($args),
            'config' => $this->handleConfig($args),
            'api' => $this->handleApi($args),
            default => $this->showError("未知命令: {$command}"),
        };
    }
    
    /**
     * 显示帮助
     */
    private function showHelp(): int
    {
        echo <<<HELP
CodeVault CLI - Git 托管平台命令行工具

用法: cv <command> [arguments]

命令:
  help        显示帮助信息
  version     显示版本信息
  auth        认证管理
  repo        仓库管理
  pr          Pull Request 管理
  issue       Issue 管理
  user        用户管理
  config      配置管理
  api         API 调用

认证命令:
  cv auth login              登录到 CodeVault
  cv auth logout             退出登录
  cv auth status             查看认证状态
  cv auth token              管理访问令牌

仓库命令:
  cv repo list               列出仓库
  cv repo create <name>      创建仓库
  cv repo delete <name>      删除仓库
  cv repo clone <url>        克隆仓库
  cv repo info <owner/repo>  查看仓库信息
  cv repo fork <owner/repo>  Fork 仓库

PR 命令:
  cv pr list <owner/repo>           列出 PR
  cv pr create <owner/repo>         创建 PR
  cv pr view <owner/repo> <number>  查看 PR
  cv pr merge <owner/repo> <number> 合并 PR
  cv pr close <owner/repo> <number> 关闭 PR

Issue 命令:
  cv issue list <owner/repo>           列出 Issue
  cv issue create <owner/repo>         创建 Issue
  cv issue view <owner/repo> <number>  查看 Issue
  cv issue close <owner/repo> <number> 关闭 Issue
  cv issue comment <owner/repo> <number>  添加评论

用户命令:
  cv user view <username>  查看用户信息
  cv user repos <username> 查看用户仓库
  cv user starred          查看星标仓库

配置命令:
  cv config list    列出配置
  cv config set     设置配置
  cv config get     获取配置

API 命令:
  cv api get <endpoint>    GET 请求
  cv api post <endpoint>   POST 请求
  cv api put <endpoint>    PUT 请求
  cv api delete <endpoint> DELETE 请求

示例:
  cv auth login
  cv repo create my-project --private
  cv pr create owner/repo --title "新功能" --base main
  cv issue create owner/repo --title "Bug 报告"

HELP;
        return 0;
    }
    
    /**
     * 显示版本
     */
    private function showVersion(): int
    {
        echo "CodeVault CLI v1.0.0\n";
        return 0;
    }
    
    /**
     * 认证管理
     */
    private function handleAuth(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv auth <login|logout|status|token>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'login' => $this->authLogin(),
            'logout' => $this->authLogout(),
            'status' => $this->authStatus(),
            'token' => $this->authToken(array_slice($args, 1)),
            default => $this->showError("未知认证命令: {$subCommand}"),
        };
    }
    
    /**
     * 登录
     */
    private function authLogin(): int
    {
        echo "登录到 CodeVault\n\n";
        
        echo "服务器地址 [" . ($this->apiBaseUrl ?: 'https://codevault.example.com') . "]: ";
        $server = trim(fgets(STDIN));
        $this->apiBaseUrl = $server ?: $this->apiBaseUrl ?: 'https://codevault.example.com';
        
        echo "用户名: ";
        $username = trim(fgets(STDIN));
        
        echo "密码: ";
        $password = $this->readPassword();
        echo "\n";
        
        // 调用 API 登录
        $response = $this->apiRequest('POST', '/api/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);
        
        if ($response['success'] ?? false) {
            $this->token = $response['token'] ?? null;
            $this->saveConfig();
            echo "\n✓ 登录成功！\n";
            return 0;
        }
        
        echo "\n✗ 登录失败: " . ($response['error'] ?? '未知错误') . "\n";
        return 1;
    }
    
    /**
     * 退出登录
     */
    private function authLogout(): int
    {
        $this->token = null;
        $this->saveConfig();
        echo "✓ 已退出登录\n";
        return 0;
    }
    
    /**
     * 认证状态
     */
    private function authStatus(): int
    {
        if ($this->token) {
            echo "✓ 已登录\n";
            echo "服务器: {$this->apiBaseUrl}\n";
            return 0;
        }
        
        echo "✗ 未登录\n";
        echo "运行 'cv auth login' 进行登录\n";
        return 1;
    }
    
    /**
     * Token 管理
     */
    private function authToken(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv auth token <list|create|revoke>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'list' => $this->tokenList(),
            'create' => $this->tokenCreate(array_slice($args, 1)),
            'revoke' => $this->tokenRevoke(array_slice($args, 1)),
            default => $this->showError("未知 token 命令: {$subCommand}"),
        };
    }
    
    /**
     * Token 列表
     */
    private function tokenList(): int
    {
        $response = $this->apiRequest('GET', '/api/user/tokens');
        
        if ($response['success'] ?? false) {
            $tokens = $response['data'] ?? [];
            
            if (empty($tokens)) {
                echo "没有访问令牌\n";
                return 0;
            }
            
            echo "访问令牌列表:\n";
            foreach ($tokens as $token) {
                $status = ($token['expired'] ?? false) ? '(已过期)' : '(有效)';
                echo "  - {$token['name']} {$status}\n";
                echo "    创建于: {$token['created_at']}\n";
            }
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取令牌列表失败');
    }
    
    /**
     * 创建 Token
     */
    private function tokenCreate(array $args): int
    {
        $name = $args[0] ?? 'CLI Token';
        
        $response = $this->apiRequest('POST', '/api/user/tokens', [
            'name' => $name,
        ]);
        
        if ($response['success'] ?? false) {
            echo "✓ 令牌创建成功\n";
            echo "令牌: {$response['token']}\n";
            echo "\n请妥善保存此令牌，它只会显示一次！\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '创建令牌失败');
    }
    
    /**
     * 撤销 Token
     */
    private function tokenRevoke(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv auth token revoke <token_id>\n";
            return 1;
        }
        
        $tokenId = $args[0];
        
        $response = $this->apiRequest('DELETE', "/api/user/tokens/{$tokenId}");
        
        if ($response['success'] ?? false) {
            echo "✓ 令牌已撤销\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '撤销令牌失败');
    }
    
    /**
     * 仓库管理
     */
    private function handleRepo(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv repo <list|create|delete|clone|info|fork>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'list' => $this->repoList(array_slice($args, 1)),
            'create' => $this->repoCreate(array_slice($args, 1)),
            'delete' => $this->repoDelete(array_slice($args, 1)),
            'clone' => $this->repoClone(array_slice($args, 1)),
            'info' => $this->repoInfo(array_slice($args, 1)),
            'fork' => $this->repoFork(array_slice($args, 1)),
            default => $this->showError("未知仓库命令: {$subCommand}"),
        };
    }
    
    /**
     * 仓库列表
     */
    private function repoList(array $args): int
    {
        $params = [];
        
        // 解析参数
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $params[$parts[0]] = $parts[1] ?? true;
            }
        }
        
        $response = $this->apiRequest('GET', '/api/user/repos', $params);
        
        if ($response['success'] ?? false) {
            $repos = $response['data'] ?? [];
            
            if (empty($repos)) {
                echo "没有仓库\n";
                return 0;
            }
            
            echo "仓库列表:\n";
            foreach ($repos as $repo) {
                $visibility = ($repo['private'] ?? false) ? '🔒' : '🌐';
                echo "  {$visibility} {$repo['full_name']}\n";
                echo "    {$repo['description']}\n";
                echo "    ⭐ {$repo['stars']} | 🍴 {$repo['forks']} | 📅 {$repo['updated_at']}\n";
            }
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取仓库列表失败');
    }
    
    /**
     * 创建仓库
     */
    private function repoCreate(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv repo create <name> [--private] [--description=\"描述\"]\n";
            return 1;
        }
        
        $name = $args[0];
        $params = ['name' => $name];
        
        // 解析参数
        foreach (array_slice($args, 1) as $arg) {
            if ($arg === '--private') {
                $params['private'] = true;
            } elseif (strpos($arg, '--description=') === 0) {
                $params['description'] = substr($arg, 14);
            }
        }
        
        $response = $this->apiRequest('POST', '/api/user/repos', $params);
        
        if ($response['success'] ?? false) {
            echo "✓ 仓库创建成功\n";
            echo "URL: {$response['html_url']}\n";
            echo "\n克隆命令:\n";
            echo "  git clone {$response['clone_url']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '创建仓库失败');
    }
    
    /**
     * 删除仓库
     */
    private function repoDelete(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv repo delete <owner/repo>\n";
            return 1;
        }
        
        $repo = $args[0];
        
        echo "确定要删除仓库 {$repo} 吗？此操作不可撤销！[y/N] ";
        $confirm = trim(fgets(STDIN));
        
        if (strtolower($confirm) !== 'y') {
            echo "已取消\n";
            return 0;
        }
        
        $response = $this->apiRequest('DELETE', "/api/repos/{$repo}");
        
        if ($response['success'] ?? false) {
            echo "✓ 仓库已删除\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '删除仓库失败');
    }
    
    /**
     * 克隆仓库
     */
    private function repoClone(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv repo clone <url|owner/repo> [directory]\n";
            return 1;
        }
        
        $repo = $args[0];
        $directory = $args[1] ?? null;
        
        // 构建克隆 URL
        if (strpos($repo, '/') !== false && strpos($repo, '://') === false) {
            $cloneUrl = "{$this->apiBaseUrl}/{$repo}.git";
        } else {
            $cloneUrl = $repo;
        }
        
        // 执行 git clone
        $cmd = "git clone {$cloneUrl}";
        if ($directory) {
            $cmd .= " {$directory}";
        }
        
        echo "执行: {$cmd}\n";
        passthru($cmd, $returnCode);
        
        return $returnCode;
    }
    
    /**
     * 仓库信息
     */
    private function repoInfo(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv repo info <owner/repo>\n";
            return 1;
        }
        
        $repo = $args[0];
        
        $response = $this->apiRequest('GET', "/api/repos/{$repo}");
        
        if ($response['success'] ?? false) {
            $data = $response['data'];
            
            echo "仓库信息:\n";
            echo "  名称: {$data['full_name']}\n";
            echo "  描述: {$data['description']}\n";
            echo "  可见性: " . ($data['private'] ? '私有' : '公开') . "\n";
            echo "  默认分支: {$data['default_branch']}\n";
            echo "  星标: {$data['stars']}\n";
            echo "  Fork: {$data['forks']}\n";
            echo "  Watch: {$data['watchers']}\n";
            echo "  开放 Issue: {$data['open_issues']}\n";
            echo "  创建时间: {$data['created_at']}\n";
            echo "  更新时间: {$data['updated_at']}\n";
            echo "\n克隆地址:\n";
            echo "  HTTPS: {$data['clone_url']}\n";
            echo "  SSH: {$data['ssh_url']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取仓库信息失败');
    }
    
    /**
     * Fork 仓库
     */
    private function repoFork(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv repo fork <owner/repo>\n";
            return 1;
        }
        
        $repo = $args[0];
        
        $response = $this->apiRequest('POST', "/api/repos/{$repo}/forks");
        
        if ($response['success'] ?? false) {
            echo "✓ Fork 成功\n";
            echo "URL: {$response['html_url']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? 'Fork 失败');
    }
    
    /**
     * PR 管理
     */
    private function handlePR(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv pr <list|create|view|merge|close>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'list' => $this->prList(array_slice($args, 1)),
            'create' => $this->prCreate(array_slice($args, 1)),
            'view' => $this->prView(array_slice($args, 1)),
            'merge' => $this->prMerge(array_slice($args, 1)),
            'close' => $this->prClose(array_slice($args, 1)),
            default => $this->showError("未知 PR 命令: {$subCommand}"),
        };
    }
    
    /**
     * PR 列表
     */
    private function prList(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv pr list <owner/repo>\n";
            return 1;
        }
        
        $repo = $args[0];
        
        $response = $this->apiRequest('GET', "/api/repos/{$repo}/pulls");
        
        if ($response['success'] ?? false) {
            $prs = $response['data'] ?? [];
            
            if (empty($prs)) {
                echo "没有 Pull Request\n";
                return 0;
            }
            
            echo "Pull Request 列表:\n";
            foreach ($prs as $pr) {
                $status = $pr['merged'] ? '✓ 已合并' : ($pr['closed'] ? '✗ 已关闭' : '⏳ 开放');
                echo "  #{$pr['number']} {$pr['title']} {$status}\n";
                echo "    作者: {$pr['user']['login']} | 分支: {$pr['head']['ref']} → {$pr['base']['ref']}\n";
            }
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取 PR 列表失败');
    }
    
    /**
     * 创建 PR
     */
    private function prCreate(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv pr create <owner/repo> --title=\"标题\" [--body=\"内容\"] [--base=main]\n";
            return 1;
        }
        
        $repo = $args[0];
        $params = [];
        
        // 解析参数
        foreach (array_slice($args, 1) as $arg) {
            if (strpos($arg, '--title=') === 0) {
                $params['title'] = substr($arg, 8);
            } elseif (strpos($arg, '--body=') === 0) {
                $params['body'] = substr($arg, 7);
            } elseif (strpos($arg, '--base=') === 0) {
                $params['base'] = substr($arg, 7);
            } elseif (strpos($arg, '--head=') === 0) {
                $params['head'] = substr($arg, 7);
            }
        }
        
        // 如果没有提供标题，打开编辑器
        if (empty($params['title'])) {
            $params['title'] = $this->openEditor('标题');
            $params['body'] = $this->openEditor('描述');
        }
        
        $response = $this->apiRequest('POST', "/api/repos/{$repo}/pulls", $params);
        
        if ($response['success'] ?? false) {
            echo "✓ Pull Request 创建成功\n";
            echo "URL: {$response['html_url']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '创建 PR 失败');
    }
    
    /**
     * 查看 PR
     */
    private function prView(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv pr view <owner/repo> <number>\n";
            return 1;
        }
        
        $repo = $args[0];
        $number = $args[1];
        
        $response = $this->apiRequest('GET', "/api/repos/{$repo}/pulls/{$number}");
        
        if ($response['success'] ?? false) {
            $pr = $response['data'];
            
            echo "Pull Request #{$pr['number']}: {$pr['title']}\n";
            echo str_repeat('-', 50) . "\n";
            echo "状态: " . ($pr['merged'] ? '已合并' : ($pr['closed'] ? '已关闭' : '开放')) . "\n";
            echo "作者: {$pr['user']['login']}\n";
            echo "分支: {$pr['head']['ref']} → {$pr['base']['ref']}\n";
            echo "创建时间: {$pr['created_at']}\n";
            echo "\n描述:\n{$pr['body']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取 PR 失败');
    }
    
    /**
     * 合并 PR
     */
    private function prMerge(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv pr merge <owner/repo> <number>\n";
            return 1;
        }
        
        $repo = $args[0];
        $number = $args[1];
        
        $response = $this->apiRequest('PUT', "/api/repos/{$repo}/pulls/{$number}/merge");
        
        if ($response['success'] ?? false) {
            echo "✓ Pull Request 已合并\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '合并 PR 失败');
    }
    
    /**
     * 关闭 PR
     */
    private function prClose(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv pr close <owner/repo> <number>\n";
            return 1;
        }
        
        $repo = $args[0];
        $number = $args[1];
        
        $response = $this->apiRequest('PATCH', "/api/repos/{$repo}/pulls/{$number}", [
            'state' => 'closed',
        ]);
        
        if ($response['success'] ?? false) {
            echo "✓ Pull Request 已关闭\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '关闭 PR 失败');
    }
    
    /**
     * Issue 管理
     */
    private function handleIssue(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv issue <list|create|view|close|comment>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'list' => $this->issueList(array_slice($args, 1)),
            'create' => $this->issueCreate(array_slice($args, 1)),
            'view' => $this->issueView(array_slice($args, 1)),
            'close' => $this->issueClose(array_slice($args, 1)),
            'comment' => $this->issueComment(array_slice($args, 1)),
            default => $this->showError("未知 Issue 命令: {$subCommand}"),
        };
    }
    
    /**
     * Issue 列表
     */
    private function issueList(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv issue list <owner/repo>\n";
            return 1;
        }
        
        $repo = $args[0];
        
        $response = $this->apiRequest('GET', "/api/repos/{$repo}/issues");
        
        if ($response['success'] ?? false) {
            $issues = $response['data'] ?? [];
            
            if (empty($issues)) {
                echo "没有 Issue\n";
                return 0;
            }
            
            echo "Issue 列表:\n";
            foreach ($issues as $issue) {
                $status = $issue['closed'] ? '✗ 已关闭' : '⏳ 开放';
                echo "  #{$issue['number']} {$issue['title']} {$status}\n";
                echo "    作者: {$issue['user']['login']} | 标签: " . implode(', ', $issue['labels'] ?? []) . "\n";
            }
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取 Issue 列表失败');
    }
    
    /**
     * 创建 Issue
     */
    private function issueCreate(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv issue create <owner/repo> --title=\"标题\" [--body=\"内容\"]\n";
            return 1;
        }
        
        $repo = $args[0];
        $params = [];
        
        // 解析参数
        foreach (array_slice($args, 1) as $arg) {
            if (strpos($arg, '--title=') === 0) {
                $params['title'] = substr($arg, 8);
            } elseif (strpos($arg, '--body=') === 0) {
                $params['body'] = substr($arg, 7);
            } elseif (strpos($arg, '--labels=') === 0) {
                $params['labels'] = explode(',', substr($arg, 9));
            }
        }
        
        // 如果没有提供标题，打开编辑器
        if (empty($params['title'])) {
            $params['title'] = $this->openEditor('标题');
            $params['body'] = $this->openEditor('描述');
        }
        
        $response = $this->apiRequest('POST', "/api/repos/{$repo}/issues", $params);
        
        if ($response['success'] ?? false) {
            echo "✓ Issue 创建成功\n";
            echo "URL: {$response['html_url']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '创建 Issue 失败');
    }
    
    /**
     * 查看 Issue
     */
    private function issueView(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv issue view <owner/repo> <number>\n";
            return 1;
        }
        
        $repo = $args[0];
        $number = $args[1];
        
        $response = $this->apiRequest('GET', "/api/repos/{$repo}/issues/{$number}");
        
        if ($response['success'] ?? false) {
            $issue = $response['data'];
            
            echo "Issue #{$issue['number']}: {$issue['title']}\n";
            echo str_repeat('-', 50) . "\n";
            echo "状态: " . ($issue['closed'] ? '已关闭' : '开放') . "\n";
            echo "作者: {$issue['user']['login']}\n";
            echo "标签: " . implode(', ', $issue['labels'] ?? []) . "\n";
            echo "创建时间: {$issue['created_at']}\n";
            echo "\n描述:\n{$issue['body']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取 Issue 失败');
    }
    
    /**
     * 关闭 Issue
     */
    private function issueClose(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv issue close <owner/repo> <number>\n";
            return 1;
        }
        
        $repo = $args[0];
        $number = $args[1];
        
        $response = $this->apiRequest('PATCH', "/api/repos/{$repo}/issues/{$number}", [
            'state' => 'closed',
        ]);
        
        if ($response['success'] ?? false) {
            echo "✓ Issue 已关闭\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '关闭 Issue 失败');
    }
    
    /**
     * Issue 评论
     */
    private function issueComment(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv issue comment <owner/repo> <number> [--body=\"评论内容\"]\n";
            return 1;
        }
        
        $repo = $args[0];
        $number = $args[1];
        $body = null;
        
        // 解析参数
        foreach (array_slice($args, 2) as $arg) {
            if (strpos($arg, '--body=') === 0) {
                $body = substr($arg, 7);
            }
        }
        
        // 如果没有提供内容，打开编辑器
        if (empty($body)) {
            $body = $this->openEditor('评论内容');
        }
        
        $response = $this->apiRequest('POST', "/api/repos/{$repo}/issues/{$number}/comments", [
            'body' => $body,
        ]);
        
        if ($response['success'] ?? false) {
            echo "✓ 评论已添加\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '添加评论失败');
    }
    
    /**
     * 用户管理
     */
    private function handleUser(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv user <view|repos|starred>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'view' => $this->userView(array_slice($args, 1)),
            'repos' => $this->userRepos(array_slice($args, 1)),
            'starred' => $this->userStarred(),
            default => $this->showError("未知用户命令: {$subCommand}"),
        };
    }
    
    /**
     * 查看用户
     */
    private function userView(array $args): int
    {
        $username = $args[0] ?? '';
        
        $endpoint = $username ? "/api/users/{$username}" : '/api/user';
        
        $response = $this->apiRequest('GET', $endpoint);
        
        if ($response['success'] ?? false) {
            $user = $response['data'];
            
            echo "用户信息:\n";
            echo "  用户名: {$user['login']}\n";
            echo "  名称: {$user['name']}\n";
            echo "  邮箱: {$user['email']}\n";
            echo "  简介: {$user['bio']}\n";
            echo "  位置: {$user['location']}\n";
            echo "  网站: {$user['blog']}\n";
            echo "  仓库数: {$user['public_repos']}\n";
            echo "  粉丝: {$user['followers']}\n";
            echo "  关注: {$user['following']}\n";
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取用户信息失败');
    }
    
    /**
     * 用户仓库
     */
    private function userRepos(array $args): int
    {
        $username = $args[0] ?? '';
        
        $endpoint = $username ? "/api/users/{$username}/repos" : '/api/user/repos';
        
        $response = $this->apiRequest('GET', $endpoint);
        
        if ($response['success'] ?? false) {
            $repos = $response['data'] ?? [];
            
            if (empty($repos)) {
                echo "没有仓库\n";
                return 0;
            }
            
            echo "仓库列表:\n";
            foreach ($repos as $repo) {
                $visibility = ($repo['private'] ?? false) ? '🔒' : '🌐';
                echo "  {$visibility} {$repo['full_name']}\n";
            }
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取仓库列表失败');
    }
    
    /**
     * 星标仓库
     */
    private function userStarred(): int
    {
        $response = $this->apiRequest('GET', '/api/user/starred');
        
        if ($response['success'] ?? false) {
            $repos = $response['data'] ?? [];
            
            if (empty($repos)) {
                echo "没有星标仓库\n";
                return 0;
            }
            
            echo "星标仓库:\n";
            foreach ($repos as $repo) {
                echo "  ⭐ {$repo['full_name']}\n";
                echo "    {$repo['description']}\n";
            }
            return 0;
        }
        
        return $this->showError($response['error'] ?? '获取星标仓库失败');
    }
    
    /**
     * 配置管理
     */
    private function handleConfig(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv config <list|set|get>\n";
            return 1;
        }
        
        $subCommand = $args[0];
        
        return match ($subCommand) {
            'list' => $this->configList(),
            'set' => $this->configSet(array_slice($args, 1)),
            'get' => $this->configGet(array_slice($args, 1)),
            default => $this->showError("未知配置命令: {$subCommand}"),
        };
    }
    
    /**
     * 配置列表
     */
    private function configList(): int
    {
        $config = $this->loadConfig();
        
        echo "配置列表:\n";
        foreach ($config as $key => $value) {
            if ($key === 'token') {
                $value = '***' . substr($value, -8);
            }
            echo "  {$key}: {$value}\n";
        }
        return 0;
    }
    
    /**
     * 设置配置
     */
    private function configSet(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv config set <key> <value>\n";
            return 1;
        }
        
        $key = $args[0];
        $value = $args[1];
        
        $config = $this->loadConfig();
        $config[$key] = $value;
        $this->saveConfig($config);
        
        echo "✓ 配置已更新\n";
        return 0;
    }
    
    /**
     * 获取配置
     */
    private function configGet(array $args): int
    {
        if (empty($args)) {
            echo "用法: cv config get <key>\n";
            return 1;
        }
        
        $key = $args[0];
        $config = $this->loadConfig();
        
        if (isset($config[$key])) {
            echo "{$key}: {$config[$key]}\n";
            return 0;
        }
        
        echo "配置项不存在: {$key}\n";
        return 1;
    }
    
    /**
     * API 调用
     */
    private function handleApi(array $args): int
    {
        if (count($args) < 2) {
            echo "用法: cv api <get|post|put|delete> <endpoint> [--data='{}']\n";
            return 1;
        }
        
        $method = strtoupper($args[0]);
        $endpoint = $args[1];
        $data = [];
        
        // 解析参数
        foreach (array_slice($args, 2) as $arg) {
            if (strpos($arg, '--data=') === 0) {
                $data = json_decode(substr($arg, 7), true) ?: [];
            }
        }
        
        $response = $this->apiRequest($method, $endpoint, $data);
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        return 0;
    }
    
    /**
     * API 请求
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->apiBaseUrl, '/') . $endpoint;
        
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $method === 'GET' && !empty($data) ? $url . '?' . http_build_query($data) : $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];
        
        if ($this->token) {
            $options[CURLOPT_HTTPHEADER][] = "Authorization: Bearer {$this->token}";
        }
        
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        return json_decode($response, true) ?: ['success' => false, 'error' => 'Invalid response'];
    }
    
    /**
     * 加载配置
     */
    private function loadConfig(): array
    {
        if (file_exists($this->configFile)) {
            $config = json_decode(file_get_contents($this->configFile), true) ?: [];
            $this->apiBaseUrl = $config['api_url'] ?? null;
            $this->token = $config['token'] ?? null;
            return $config;
        }
        return [];
    }
    
    /**
     * 保存配置
     */
    private function saveConfig(array $config = null): void
    {
        if ($config === null) {
            $config = [
                'api_url' => $this->apiBaseUrl,
                'token' => $this->token,
            ];
        }
        
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        
        file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT));
        chmod($this->configFile, 0600);
    }
    
    /**
     * 打开编辑器
     */
    private function openEditor(string $prompt): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cv_');
        file_put_contents($tempFile, "# {$prompt}\n# (保存并关闭编辑器继续)\n");
        
        $editor = $_SERVER['EDITOR'] ?? 'nano';
        system("{$editor} {$tempFile}");
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        // 移除注释行
        $lines = explode("\n", $content);
        $content = '';
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') !== 0) {
                $content .= $line . "\n";
            }
        }
        
        return trim($content);
    }
    
    /**
     * 读取密码（不显示）
     */
    private function readPassword(): string
    {
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        return $password;
    }
    
    /**
     * 显示错误
     */
    private function showError(string $message): int
    {
        echo "✗ 错误: {$message}\n";
        return 1;
    }
}

// CLI 入口
if (php_sapi_name() === 'cli') {
    $cli = new CodeVaultCLI();
    exit($cli->run($argv));
}
