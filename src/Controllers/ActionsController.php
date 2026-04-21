<?php
/**
 * CodeVault - Actions 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\ActionsRunner;
use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class ActionsController
{
    /**
     * 获取工作流列表
     */
    public function listWorkflows(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $workflows = Connection::query(
            "SELECT * FROM workflows WHERE repo_id = ? ORDER BY created_at DESC",
            [$repoId]
        );
        
        return [
            'success' => true,
            'workflows' => $workflows,
        ];
    }
    
    /**
     * 获取工作流详情
     */
    public function getWorkflow(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => '无效的工作流ID'];
        }
        
        $workflow = Connection::queryOne(
            "SELECT * FROM workflows WHERE id = ?",
            [$id]
        );
        
        if (!$workflow) {
            return ['success' => false, 'message' => '工作流不存在'];
        }
        
        return [
            'success' => true,
            'workflow' => $workflow,
        ];
    }
    
    /**
     * 创建工作流
     */
    public function createWorkflow(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $config = $data['config'] ?? '';
        
        if ($repoId <= 0 || empty($name)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 检查仓库权限
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if ($repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作此仓库'];
        }
        
        // 验证 YAML 配置
        $parsed = yaml_parse($config);
        if (!$parsed) {
            return ['success' => false, 'message' => 'YAML 配置格式错误'];
        }
        
        $workflowId = Connection::insert(
            "INSERT INTO workflows (repo_id, name, config, is_active, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$repoId, $name, $config]
        );
        
        return [
            'success' => true,
            'workflow_id' => $workflowId,
        ];
    }
    
    /**
     * 更新工作流
     */
    public function updateWorkflow(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => '无效的工作流ID'];
        }
        
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = trim($data['name']);
        }
        
        if (isset($data['config'])) {
            $parsed = yaml_parse($data['config']);
            if (!$parsed) {
                return ['success' => false, 'message' => 'YAML 配置格式错误'];
            }
            $fields[] = "config = ?";
            $params[] = $data['config'];
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = (int) $data['is_active'];
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => '没有要更新的内容'];
        }
        
        $params[] = $id;
        
        Connection::execute(
            "UPDATE workflows SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );
        
        return ['success' => true, 'message' => '工作流已更新'];
    }
    
    /**
     * 删除工作流
     */
    public function deleteWorkflow(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => '无效的工作流ID'];
        }
        
        Connection::execute("DELETE FROM workflows WHERE id = ?", [$id]);
        
        return ['success' => true, 'message' => '工作流已删除'];
    }
    
    /**
     * 获取运行记录列表
     */
    public function listRuns(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $workflowId = (int) ($data['workflow_id'] ?? 0);
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        $sql = "SELECT wr.*, w.name as workflow_name 
                FROM workflow_runs wr 
                JOIN workflows w ON wr.workflow_id = w.id 
                WHERE 1=1";
        $params = [];
        
        if ($workflowId > 0) {
            $sql .= " AND wr.workflow_id = ?";
            $params[] = $workflowId;
        }
        
        if ($repoId > 0) {
            $sql .= " AND w.repo_id = ?";
            $params[] = $repoId;
        }
        
        $sql .= " ORDER BY wr.created_at DESC LIMIT 50";
        
        $runs = Connection::query($sql, $params);
        
        return [
            'success' => true,
            'runs' => $runs,
        ];
    }
    
    /**
     * 获取运行详情
     */
    public function getRun(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => '无效的运行ID'];
        }
        
        $run = Connection::queryOne(
            "SELECT wr.*, w.name as workflow_name, w.config 
             FROM workflow_runs wr 
             JOIN workflows w ON wr.workflow_id = w.id 
             WHERE wr.id = ?",
            [$id]
        );
        
        if (!$run) {
            return ['success' => false, 'message' => '运行记录不存在'];
        }
        
        return [
            'success' => true,
            'run' => $run,
        ];
    }
    
    /**
     * 手动触发工作流
     */
    public function triggerWorkflow(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $workflowId = (int) ($data['workflow_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        
        if ($workflowId <= 0) {
            return ['success' => false, 'message' => '无效的工作流ID'];
        }
        
        $workflow = Connection::queryOne(
            "SELECT * FROM workflows WHERE id = ?",
            [$workflowId]
        );
        
        if (!$workflow) {
            return ['success' => false, 'message' => '工作流不存在'];
        }
        
        // 创建运行记录
        $runId = Connection::insert(
            "INSERT INTO workflow_runs (workflow_id, status, branch, triggered_by, created_at)
             VALUES (?, 'pending', ?, ?, NOW())",
            [$workflowId, $branch, $user['id']]
        );
        
        // 异步执行（实际生产环境应使用队列）
        // 这里简单地在后台执行
        $runner = new ActionsRunner();
        
        // 可以使用 exec 在后台运行
        // $cmd = sprintf('php %s/actions/run.php %d > /dev/null 2>&1 &', ROOT_PATH, $runId);
        // exec($cmd);
        
        // 或者直接执行（适合测试）
        $result = $runner->run($runId);
        
        return [
            'success' => true,
            'run_id' => $runId,
            'status' => $result['status'] ?? 'pending',
        ];
    }
    
    /**
     * 重新运行
     */
    public function rerun(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $runId = (int) ($data['run_id'] ?? 0);
        if ($runId <= 0) {
            return ['success' => false, 'message' => '无效的运行ID'];
        }
        
        $oldRun = Connection::queryOne(
            "SELECT * FROM workflow_runs WHERE id = ?",
            [$runId]
        );
        
        if (!$oldRun) {
            return ['success' => false, 'message' => '运行记录不存在'];
        }
        
        // 创建新的运行记录
        $newRunId = Connection::insert(
            "INSERT INTO workflow_runs (workflow_id, status, branch, commit_sha, triggered_by, created_at)
             VALUES (?, 'pending', ?, ?, ?, NOW())",
            [
                $oldRun['workflow_id'],
                $oldRun['branch'],
                $oldRun['commit_sha'],
                $user['id'],
            ]
        );
        
        // 执行
        $runner = new ActionsRunner();
        $result = $runner->run($newRunId);
        
        return [
            'success' => true,
            'run_id' => $newRunId,
            'status' => $result['status'] ?? 'pending',
        ];
    }
    
    /**
     * 取消运行
     */
    public function cancelRun(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $runId = (int) ($data['run_id'] ?? 0);
        if ($runId <= 0) {
            return ['success' => false, 'message' => '无效的运行ID'];
        }
        
        $run = Connection::queryOne(
            "SELECT * FROM workflow_runs WHERE id = ? AND status = 'running'",
            [$runId]
        );
        
        if (!$run) {
            return ['success' => false, 'message' => '无法取消'];
        }
        
        Connection::execute(
            "UPDATE workflow_runs SET status = 'cancelled', completed_at = NOW() WHERE id = ?",
            [$runId]
        );
        
        return ['success' => true, 'message' => '已取消'];
    }
    
    /**
     * 触发定时工作流
     */
    public function triggerScheduled(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $scheduler = new \CodeVault\Services\SchedulerService();
        $triggered = $scheduler->checkScheduledWorkflows();
        
        return [
            'success' => true,
            'triggered' => $triggered,
            'count' => count($triggered),
        ];
    }
    
    /**
     * 获取工作流调度信息
     */
    public function getSchedule(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $workflowId = (int) ($data['workflow_id'] ?? 0);
        
        if ($workflowId <= 0) {
            return ['success' => false, 'message' => '无效的工作流ID'];
        }
        
        $scheduler = new \CodeVault\Services\SchedulerService();
        $schedule = $scheduler->getWorkflowSchedule($workflowId);
        
        return [
            'success' => true,
            'schedule' => $schedule,
        ];
    }
    
    /**
     * 验证 Cron 表达式
     */
    public function validateCron(array $data): array
    {
        $cronExpr = $data['cron'] ?? '';
        
        if (empty($cronExpr)) {
            return ['success' => false, 'message' => 'Cron 表达式不能为空'];
        }
        
        $scheduler = new \CodeVault\Services\SchedulerService();
        $result = $scheduler->validateCron($cronExpr);
        
        return $result;
    }
}
