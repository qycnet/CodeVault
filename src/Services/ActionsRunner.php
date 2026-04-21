<?php
/**
 * CodeVault - Actions 执行引擎
 * CI/CD 工作流执行系统
 */

namespace CodeVault\Services;

class ActionsRunner
{
    private string $workflowsDir;
    private string $workspaceDir;
    private array $env;
    
    public function __construct()
    {
        $this->workflowsDir = $_ENV['WORKFLOWS_DIR'] ?? '/var/git/workflows';
        $this->workspaceDir = $_ENV['WORKSPACE_DIR'] ?? '/tmp/codevault-actions';
        $this->env = $_ENV;
    }
    
    /**
     * 执行工作流
     */
    public function run(int $runId): array
    {
        // 获取运行记录
        $run = \CodeVault\Database\Connection::queryOne(
            "SELECT wr.*, w.name as workflow_name, w.config, r.git_path, r.name as repo_name
             FROM workflow_runs wr
             JOIN workflows w ON wr.workflow_id = w.id
             JOIN repositories r ON w.repo_id = r.id
             WHERE wr.id = ?",
            [$runId]
        );
        
        if (!$run) {
            return ['success' => false, 'message' => '运行记录不存在'];
        }
        
        // 更新状态为运行中
        \CodeVault\Database\Connection::execute(
            "UPDATE workflow_runs SET status = 'running', started_at = NOW() WHERE id = ?",
            [$runId]
        );
        
        try {
            // 解析工作流配置
            $config = yaml_parse($run['config']);
            if (!$config) {
                throw new \RuntimeException('工作流配置解析失败');
            }
            
            // 创建工作目录
            $workDir = $this->workspaceDir . '/' . $runId;
            if (!is_dir($workDir)) {
                mkdir($workDir, 0755, true);
            }
            
            // 克隆仓库
            $this->cloneRepo($run['git_path'], $workDir, $run['branch'] ?? 'main');
            
            // 执行作业
            $results = [];
            $success = true;
            
            foreach ($config['jobs'] ?? [] as $jobName => $job) {
                $jobResult = $this->runJob($workDir, $jobName, $job, $run);
                $results[$jobName] = $jobResult;
                
                if (!$jobResult['success']) {
                    $success = false;
                    break;
                }
            }
            
            // 更新最终状态
            $status = $success ? 'success' : 'failed';
            \CodeVault\Database\Connection::execute(
                "UPDATE workflow_runs SET status = ?, completed_at = NOW() WHERE id = ?",
                [$status, $runId]
            );
            
            // 保存日志
            $this->saveLogs($runId, $results);
            
            return [
                'success' => $success,
                'status' => $status,
                'results' => $results,
            ];
            
        } catch (\Exception $e) {
            \CodeVault\Database\Connection::execute(
                "UPDATE workflow_runs SET status = 'failed', completed_at = NOW() WHERE id = ?",
                [$runId]
            );
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * 克隆仓库
     */
    private function cloneRepo(string $gitPath, string $workDir, string $branch): void
    {
        // 使用 proc_open 安全执行 git clone
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open(
            ['git', 'clone', '--branch', $branch, $gitPath, $workDir . '/repo'],
            $descriptorspec,
            $pipes,
            null,
            null
        );
        
        if (!is_resource($process)) {
            throw new \RuntimeException('无法启动 git clone 进程');
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0) {
            throw new \RuntimeException('克隆仓库失败: ' . $error);
        }
    }
    
    /**
     * 执行作业
     */
    private function runJob(string $workDir, string $jobName, array $job, array $run): array
    {
        $log = [];
        $log[] = "## Job: {$jobName}";
        $log[] = "Started at: " . date('Y-m-d H:i:s');
        
        // 设置环境变量
        $env = array_merge($this->env, [
            'GITHUB_WORKFLOW' => $run['workflow_name'],
            'GITHUB_RUN_ID' => $run['id'],
            'GITHUB_REPOSITORY' => $run['repo_name'],
            'GITHUB_SHA' => $run['commit_sha'] ?? '',
            'GITHUB_REF' => 'refs/heads/' . ($run['branch'] ?? 'main'),
            'GITHUB_WORKSPACE' => $workDir . '/repo',
        ]);
        
        // 运行步骤
        $steps = $job['steps'] ?? [];
        $success = true;
        
        foreach ($steps as $stepName => $step) {
            $log[] = "\n### Step: {$stepName}";
            
            $stepResult = $this->runStep($workDir . '/repo', $step, $env);
            $log = array_merge($log, $stepResult['log']);
            
            if (!$stepResult['success']) {
                $success = false;
                $log[] = "❌ Step failed";
                break;
            }
            
            $log[] = "✅ Step completed";
        }
        
        return [
            'success' => $success,
            'log' => $log,
        ];
    }
    
    /**
     * 执行步骤
     */
    private function runStep(string $repoDir, array $step, array $env): array
    {
        $log = [];
        $success = true;
        
        // 检查条件
        if (isset($step['if'])) {
            // 简单的条件判断
            if (!$this->evaluateCondition($step['if'], $env)) {
                $log[] = "Skipped (condition not met)";
                return ['success' => true, 'log' => $log];
            }
        }
        
        // 使用预定义动作
        if (isset($step['uses'])) {
            $result = $this->runAction($repoDir, $step['uses'], $step['with'] ?? [], $env);
            $log = array_merge($log, $result['log']);
            $success = $result['success'];
        }
        
        // 运行命令
        if (isset($step['run'])) {
            $result = $this->runCommands($repoDir, $step['run'], $env);
            $log = array_merge($log, $result['log']);
            if (!$result['success']) {
                $success = false;
            }
        }
        
        return ['success' => $success, 'log' => $log];
    }
    
    /**
     * 执行命令
     */
    private function runCommands(string $repoDir, string $commands, array $env): array
    {
        $log = [];
        $lines = explode("\n", $commands);
        
        // 构建环境变量
        $envStr = '';
        foreach ($env as $key => $value) {
            $envStr .= sprintf('export %s=%s;', $key, escapeshellarg($value));
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 安全检查：只允许安全的命令
            $safeCommands = ['git', 'npm', 'node', 'yarn', 'pnpm', 'composer', 'php', 'make', 'echo', 'mkdir', 'cp', 'mv', 'rm', 'ls', 'cat'];
            $isSafe = false;
            foreach ($safeCommands as $cmd) {
                if (preg_match('/^' . preg_quote($cmd, '/') . '\b/i', $line)) {
                    $isSafe = true;
                    break;
                }
            }
            
            if (!$isSafe) {
                $log[] = "Error: Command not allowed: {$line}";
                continue;
            }
            
            $log[] = "$ {$line}";
            
            // 使用 proc_open 安全执行命令
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            
            // 构建环境变量
            $envArray = [];
            foreach ($env as $key => $value) {
                $envArray[] = "{$key}={$value}";
            }
            
            $process = proc_open(
                $line,
                $descriptorspec,
                $pipes,
                $repoDir,
                $envArray
            );
            
            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = explode("\n", trim(stream_get_contents($pipes[1])));
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $returnCode = proc_close($process);
                
                $log = array_merge($log, $output);
                
                if ($returnCode !== 0) {
                    $log[] = "Error: Command exited with code {$returnCode}";
                    $log[] = $error;
                    return ['success' => false, 'log' => $log];
                }
            } else {
                $log[] = "Error: Failed to start process";
                return ['success' => false, 'log' => $log];
            }
        }
        
        return ['success' => true, 'log' => $log];
    }
    
    /**
     * 运行预定义动作
     */
    private function runAction(string $repoDir, string $action, array $with, array $env): array
    {
        $log = [];
        $log[] = "Using action: {$action}";
        
        // 内置动作
        switch ($action) {
            case 'actions/checkout':
                // 已经克隆，跳过
                $log[] = "Repository already checked out";
                return ['success' => true, 'log' => $log];
                
            case 'actions/setup-php':
                $version = $with['php-version'] ?? '8.2';
                $log[] = "Setting up PHP {$version}";
                // 检查 PHP 版本（使用 proc_open）
                $descriptorspec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
                $process = proc_open(['php', '-v'], $descriptorspec, $pipes);
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    $output = explode("\n", trim(stream_get_contents($pipes[1])));
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    $log = array_merge($log, $output);
                }
                return ['success' => true, 'log' => $log];
                
            case 'actions/setup-node':
                $version = $with['node-version'] ?? '18';
                $log[] = "Setting up Node.js {$version}";
                $process = proc_open(['node', '--version'], $descriptorspec, $pipes);
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    $output = explode("\n", trim(stream_get_contents($pipes[1])));
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    $log = array_merge($log, $output);
                }
                return ['success' => true, 'log' => $log];
                
            default:
                $log[] = "Warning: Unknown action {$action}, skipping";
                return ['success' => true, 'log' => $log];
        }
    }
    
    /**
     * 评估条件
     */
    private function evaluateCondition(string $condition, array $env): bool
    {
        // 简单的条件解析
        $condition = str_replace(array_keys($env), array_values($env), $condition);
        
        // 替换 GitHub 上下文
        $condition = str_replace('success()', 'true', $condition);
        $condition = str_replace('failure()', 'false', $condition);
        
        // 基本比较
        if (strpos($condition, '==') !== false) {
            list($left, $right) = explode('==', $condition);
            return trim($left, " '\"\n\r\t") === trim($right, " '\"\n\r\t");
        }
        
        if (strpos($condition, '!=') !== false) {
            list($left, $right) = explode('!=', $condition);
            return trim($left, " '\"\n\r\t") !== trim($right, " '\"\n\r\t");
        }
        
        return true;
    }
    
    /**
     * 保存日志
     */
    private function saveLogs(int $runId, array $results): void
    {
        $logContent = '';
        foreach ($results as $jobName => $result) {
            $logContent .= implode("\n", $result['log']) . "\n\n";
        }
        
        $logPath = $this->workspaceDir . '/' . $runId . '.log';
        file_put_contents($logPath, $logContent);
        
        // 更新数据库
        \CodeVault\Database\Connection::execute(
            "UPDATE workflow_runs SET logs = ? WHERE id = ?",
            [$logContent, $runId]
        );
    }
    
    /**
     * 触发工作流
     */
    public static function trigger(int $repoId, string $event, array $data = []): array
    {
        // 查找匹配的工作流
        $workflows = \CodeVault\Database\Connection::query(
            "SELECT * FROM workflows WHERE repo_id = ? AND is_active = 1",
            [$repoId]
        );
        
        $triggered = [];
        
        foreach ($workflows as $workflow) {
            $config = yaml_parse($workflow['config']);
            if (!$config) continue;
            
            // 检查触发条件
            $on = $config['on'] ?? [];
            $shouldTrigger = false;
            
            if (isset($on[$event])) {
                $shouldTrigger = true;
            } elseif (in_array($event, (array) $on)) {
                $shouldTrigger = true;
            }
            
            // 检查分支过滤
            if ($shouldTrigger && isset($on[$event]['branches'])) {
                $branches = $on[$event]['branches'];
                $branch = $data['branch'] ?? 'main';
                if (!in_array($branch, (array) $branches)) {
                    $shouldTrigger = false;
                }
            }
            
            if ($shouldTrigger) {
                // 创建运行记录
                $runId = \CodeVault\Database\Connection::insert(
                    "INSERT INTO workflow_runs (workflow_id, status, branch, commit_sha, triggered_by, created_at)
                     VALUES (?, 'pending', ?, ?, ?, NOW())",
                    [
                        $workflow['id'],
                        $data['branch'] ?? 'main',
                        $data['commit_sha'] ?? '',
                        $data['user_id'] ?? 0,
                    ]
                );
                
                $triggered[] = [
                    'workflow_id' => $workflow['id'],
                    'run_id' => $runId,
                ];
            }
        }
        
        return $triggered;
    }
}
