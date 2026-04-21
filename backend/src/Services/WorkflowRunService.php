<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;
use App\Services\LogWebSocketService;

/**
 * CI/CD 工作流运行服务
 */
class WorkflowRunService
{
    private \PDO $db;
    private LoggerInterface $logger;
    private ?LogWebSocketService $wsService;
    
    public function __construct(\PDO $db, LoggerInterface $logger, ?LogWebSocketService $wsService = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->wsService = $wsService;
    }
    
    /**
     * 启动工作流运行
     */
    public function startRun(int $workflowId, string $trigger, ?string $branch = null): int
    {
        // 获取工作流信息
        $stmt = $this->db->prepare("
            SELECT w.*, r.name as repo_name, r.owner_name 
            FROM workflows w
            JOIN repositories r ON w.repo_id = r.id
            WHERE w.id = ?
        ");
        $stmt->execute([$workflowId]);
        $workflow = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$workflow) {
            throw new \RuntimeException("Workflow not found: {$workflowId}");
        }
        
        // 创建运行记录
        $stmt = $this->db->prepare("
            INSERT INTO workflow_runs (workflow_id, trigger, branch, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$workflowId, $trigger, $branch]);
        
        $runId = (int) $this->db->lastInsertId();
        
        // 异步执行工作流
        $this->executeAsync($runId, $workflow);
        
        return $runId;
    }
    
    /**
     * 异步执行工作流
     */
    private function executeAsync(int $runId, array $workflow): void
    {
        // 使用队列或进程异步执行
        // 这里简化为直接执行
        
        $this->updateStatus($runId, 'running');
        
        try {
            $steps = json_decode($workflow['steps'], true) ?? [];
            
            foreach ($steps as $index => $step) {
                $this->executeStep($runId, $step, $index + 1);
            }
            
            $this->updateStatus($runId, 'success');
            
        } catch (\Exception $e) {
            $this->log($runId, "Error: {$e->getMessage()}", 'error');
            $this->updateStatus($runId, 'failed');
        }
    }
    
    /**
     * 执行单个步骤
     */
    private function executeStep(int $runId, array $step, int $stepNumber): void
    {
        $this->log($runId, "Step {$stepNumber}: {$step['name']}", 'info');
        
        $command = $step['command'] ?? '';
        $workingDir = $step['working_dir'] ?? './';
        $timeout = ($step['timeout'] ?? 30) * 60;
        $env = $this->parseEnv($step['env'] ?? '');
        
        // 记录步骤开始
        $stmt = $this->db->prepare("
            INSERT INTO workflow_run_steps (run_id, step_number, name, status, started_at)
            VALUES (?, ?, ?, 'running', NOW())
        ");
        $stmt->execute([$runId, $stepNumber, $step['name']]);
        
        try {
            // 执行命令
            $output = [];
            $returnCode = 0;
            
            // 安全执行命令
            $safeCommand = escapeshellcmd($command);
            
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            
            $process = proc_open($safeCommand, $descriptors, $pipes, $workingDir, $env);
            
            if (is_resource($process)) {
                // 非阻塞读取输出
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                
                $startTime = time();
                
                while (true) {
                    $stdout = fgets($pipes[1]);
                    $stderr = fgets($pipes[2]);
                    
                    if ($stdout) {
                        $this->log($runId, trim($stdout), 'info');
                    }
                    
                    if ($stderr) {
                        $this->log($runId, trim($stderr), 'error');
                    }
                    
                    $status = proc_get_status($process);
                    
                    if (!$status['running']) {
                        $returnCode = $status['exitcode'];
                        break;
                    }
                    
                    // 检查超时
                    if (time() - $startTime > $timeout) {
                        proc_terminate($process);
                        throw new \RuntimeException("Step timeout after {$timeout} seconds");
                    }
                    
                    usleep(100000); // 100ms
                }
                
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
            
            // 更新步骤状态
            $stepStatus = $returnCode === 0 ? 'success' : 'failed';
            
            if ($returnCode !== 0 && !($step['continue_on_error'] ?? false)) {
                throw new \RuntimeException("Step failed with exit code {$returnCode}");
            }
            
            $stmt = $this->db->prepare("
                UPDATE workflow_run_steps 
                SET status = ?, completed_at = NOW()
                WHERE run_id = ? AND step_number = ?
            ");
            $stmt->execute([$stepStatus, $runId, $stepNumber]);
            
            $this->log($runId, "Step {$stepNumber} completed: {$stepStatus}", $stepStatus === 'success' ? 'info' : 'warning');
            
        } catch (\Exception $e) {
            $stmt = $this->db->prepare("
                UPDATE workflow_run_steps 
                SET status = 'failed', completed_at = NOW()
                WHERE run_id = ? AND step_number = ?
            ");
            $stmt->execute([$runId, $stepNumber]);
            
            throw $e;
        }
    }
    
    /**
     * 记录日志
     */
    private function log(int $runId, string $message, string $level = 'info'): void
    {
        // 写入数据库
        $stmt = $this->db->prepare("
            INSERT INTO workflow_run_logs (run_id, level, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$runId, $level, $message]);
        
        // 推送到 WebSocket
        if ($this->wsService) {
            $this->wsService->pushLog($runId, $message, $level);
        }
        
        // 本地日志
        $this->logger->log(
            $level === 'error' ? 'error' : 'info',
            "[Run {$runId}] {$message}"
        );
    }
    
    /**
     * 更新运行状态
     */
    private function updateStatus(int $runId, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE workflow_runs 
            SET status = ?, completed_at = IF(? IN ('success', 'failed', 'cancelled'), NOW(), NULL)
            WHERE id = ?
        ");
        $stmt->execute([$status, $status, $runId]);
        
        // 推送状态更新
        if ($this->wsService) {
            $this->wsService->pushStatus($runId, $status);
        }
    }
    
    /**
     * 解析环境变量
     */
    private function parseEnv(string $envString): array
    {
        $env = [];
        $lines = explode("\n", $envString);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[trim($parts[0])] = trim($parts[1]);
            }
        }
        
        return $env;
    }
    
    /**
     * 获取运行日志
     */
    public function getLogs(int $runId, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT level, message, created_at
            FROM workflow_run_logs
            WHERE run_id = ?
            ORDER BY id ASC
            LIMIT 1000 OFFSET ?
        ");
        $stmt->execute([$runId, $offset]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * 取消运行
     */
    public function cancelRun(int $runId): void
    {
        $this->updateStatus($runId, 'cancelled');
        $this->log($runId, "Run cancelled by user", 'warning');
    }
}
