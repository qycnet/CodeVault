<?php
/**
 * CodeVault - 定时任务调度器
 * 支持 cron 表达式的定时触发器
 */

namespace CodeVault\Services;

class SchedulerService
{
    private array $cronExpressions = [];
    
    /**
     * 检查并触发所有定时工作流
     */
    public function checkScheduledWorkflows(): array
    {
        $triggered = [];
        $now = new \DateTime();
        
        // 获取所有活跃的工作流
        $workflows = \CodeVault\Database\Connection::query(
            "SELECT w.*, r.name as repo_name, r.git_path 
             FROM workflows w 
             JOIN repositories r ON w.repo_id = r.id 
             WHERE w.is_active = 1 AND w.deleted_at IS NULL"
        );
        
        foreach ($workflows as $workflow) {
            $config = yaml_parse($workflow['config']);
            if (!$config) continue;
            
            // 检查是否有 schedule 触发器
            $schedule = $config['on']['schedule'] ?? null;
            
            if ($schedule) {
                // 支持多个定时表达式
                $schedules = is_array($schedule) && isset($schedule[0]) ? $schedule : [$schedule];
                
                foreach ($schedules as $sched) {
                    $cronExpr = $sched['cron'] ?? null;
                    
                    if ($cronExpr && $this->shouldRun($cronExpr, $now)) {
                        $result = $this->triggerScheduledWorkflow($workflow, $cronExpr);
                        if ($result) {
                            $triggered[] = $result;
                        }
                    }
                }
            }
        }
        
        return $triggered;
    }
    
    /**
     * 判断 cron 表达式是否应该在当前时间运行
     */
    public function shouldRun(string $cronExpr, \DateTime $time): bool
    {
        try {
            $parts = $this->parseCronExpression($cronExpr);
            if (!$parts) {
                return false;
            }
            
            $minute = (int) $time->format('i');
            $hour = (int) $time->format('H');
            $day = (int) $time->format('d');
            $month = (int) $time->format('m');
            $weekday = (int) $time->format('w'); // 0 = Sunday
            
            return $this->matchCronPart($parts['minute'], $minute, 0, 59) &&
                   $this->matchCronPart($parts['hour'], $hour, 0, 23) &&
                   $this->matchCronPart($parts['day'], $day, 1, 31) &&
                   $this->matchCronPart($parts['month'], $month, 1, 12) &&
                   $this->matchCronPart($parts['weekday'], $weekday, 0, 6);
                   
        } catch (\Exception $e) {
            error_log("Cron expression error: {$cronExpr} - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 解析 cron 表达式
     * 格式: minute hour day month weekday
     * 示例: "*/15 * * * *" (每15分钟)
     *       "0 2 * * *" (每天凌晨2点)
     *       "0 9 * * 1-5" (工作日上午9点)
     */
    private function parseCronExpression(string $expr): ?array
    {
        $parts = preg_split('/\s+/', trim($expr));
        
        if (count($parts) !== 5) {
            return null;
        }
        
        return [
            'minute' => $parts[0],
            'hour' => $parts[1],
            'day' => $parts[2],
            'month' => $parts[3],
            'weekday' => $parts[4],
        ];
    }
    
    /**
     * 匹配 cron 部分
     */
    private function matchCronPart(string $part, int $value, int $min, int $max): bool
    {
        // * 匹配所有
        if ($part === '*') {
            return true;
        }
        
        // */n 步进
        if (preg_match('/^\*\/(\d+)$/', $part, $matches)) {
            $step = (int) $matches[1];
            return $value % $step === 0;
        }
        
        // n-m 范围
        if (preg_match('/^(\d+)-(\d+)$/', $part, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            return $value >= $start && $value <= $end;
        }
        
        // n,m,o 列表
        if (strpos($part, ',') !== false) {
            $values = array_map('intval', explode(',', $part));
            return in_array($value, $values);
        }
        
        // 单个数字
        if (is_numeric($part)) {
            return $value === (int) $part;
        }
        
        return false;
    }
    
    /**
     * 触发定时工作流
     */
    private function triggerScheduledWorkflow(array $workflow, string $cronExpr): ?array
    {
        // 检查是否已经在这一分钟内触发过
        $lastRun = \CodeVault\Database\Connection::queryOne(
            "SELECT * FROM workflow_runs 
             WHERE workflow_id = ? AND event = 'schedule' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$workflow['id']]
        );
        
        if ($lastRun) {
            return null; // 已触发，跳过
        }
        
        // 获取默认分支
        $defaultBranch = 'main';
        $repo = \CodeVault\Database\Connection::queryOne(
            "SELECT default_branch FROM repositories WHERE id = ?",
            [$workflow['repo_id']]
        );
        if ($repo && $repo['default_branch']) {
            $defaultBranch = $repo['default_branch'];
        }
        
        // 获取最新提交
        $commitSha = $this->getLatestCommit($workflow['git_path'], $defaultBranch);
        
        // 创建运行记录
        $runId = \CodeVault\Database\Connection::insert(
            "INSERT INTO workflow_runs (workflow_id, status, event, branch, commit_sha, triggered_by, created_at)
             VALUES (?, 'pending', 'schedule', ?, ?, 0, NOW())",
            [$workflow['id'], $defaultBranch, $commitSha]
        );
        
        // 记录调度日志
        \CodeVault\Database\Connection::execute(
            "INSERT INTO workflow_schedule_logs (workflow_id, run_id, cron_expr, triggered_at)
             VALUES (?, ?, ?, NOW())",
            [$workflow['id'], $runId, $cronExpr]
        );
        
        return [
            'workflow_id' => $workflow['id'],
            'workflow_name' => $workflow['name'],
            'run_id' => $runId,
            'cron_expr' => $cronExpr,
            'branch' => $defaultBranch,
        ];
    }
    
    /**
     * 获取最新提交 SHA
     */
    private function getLatestCommit(string $gitPath, string $branch): string
    {
        $cmd = sprintf(
            'cd %s && git rev-parse HEAD 2>/dev/null',
            escapeshellarg($gitPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0 ? trim($output[0] ?? '') : '';
    }
    
    /**
     * 获取下次运行时间
     */
    public function getNextRunTime(string $cronExpr): ?\DateTime
    {
        $now = new \DateTime();
        
        // 简单实现：检查未来 365 天内的运行时间
        for ($i = 1; $i <= 525600; $i++) { // 365 * 24 * 60 分钟
            $next = clone $now;
            $next->modify("+{$i} minutes");
            
            if ($this->shouldRun($cronExpr, $next)) {
                return $next;
            }
        }
        
        return null;
    }
    
    /**
     * 获取工作流调度信息
     */
    public function getWorkflowSchedule(int $workflowId): ?array
    {
        $workflow = \CodeVault\Database\Connection::queryOne(
            "SELECT * FROM workflows WHERE id = ?",
            [$workflowId]
        );
        
        if (!$workflow) {
            return null;
        }
        
        $config = yaml_parse($workflow['config']);
        $schedule = $config['on']['schedule'] ?? null;
        
        if (!$schedule) {
            return null;
        }
        
        $schedules = is_array($schedule) && isset($schedule[0]) ? $schedule : [$schedule];
        $result = [];
        
        foreach ($schedules as $sched) {
            $cronExpr = $sched['cron'] ?? null;
            if ($cronExpr) {
                $result[] = [
                    'cron' => $cronExpr,
                    'next_run' => $this->getNextRunTime($cronExpr)?->format('Y-m-d H:i:s'),
                    'description' => $this->describeCron($cronExpr),
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * 人类可读的 cron 描述
     */
    public function describeCron(string $cronExpr): string
    {
        $descriptions = [
            '* * * * *' => '每分钟',
            '*/5 * * * *' => '每 5 分钟',
            '*/15 * * * *' => '每 15 分钟',
            '*/30 * * * *' => '每 30 分钟',
            '0 * * * *' => '每小时',
            '0 */2 * * *' => '每 2 小时',
            '0 */6 * * *' => '每 6 小时',
            '0 0 * * *' => '每天午夜',
            '0 2 * * *' => '每天凌晨 2 点',
            '0 9 * * *' => '每天上午 9 点',
            '0 9 * * 1-5' => '工作日上午 9 点',
            '0 9 * * 1' => '每周一上午 9 点',
            '0 0 1 * *' => '每月 1 日午夜',
        ];
        
        if (isset($descriptions[$cronExpr])) {
            return $descriptions[$cronExpr];
        }
        
        // 尝试解析
        $parts = $this->parseCronExpression($cronExpr);
        if (!$parts) {
            return '自定义时间';
        }
        
        $desc = [];
        
        // 分钟
        if ($parts['minute'] === '*') {
            $desc[] = '每分钟';
        } elseif (preg_match('/^\*\/(\d+)$/', $parts['minute'], $m)) {
            $desc[] = "每 {$m[1]} 分钟";
        } elseif (is_numeric($parts['minute'])) {
            $desc[] = "第 {$parts['minute']} 分钟";
        }
        
        // 小时
        if ($parts['hour'] !== '*') {
            if (preg_match('/^\*\/(\d+)$/', $parts['hour'], $m)) {
                $desc[] = "每 {$m[1]} 小时";
            } elseif (is_numeric($parts['hour'])) {
                $desc[] = "{$parts['hour']} 点";
            }
        }
        
        // 星期
        if ($parts['weekday'] !== '*') {
            $weekdays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
            if (preg_match('/^(\d+)-(\d+)$/', $parts['weekday'], $m)) {
                $desc[] = "{$weekdays[$m[1]]} 到 {$weekdays[$m[2]]}";
            } elseif (is_numeric($parts['weekday'])) {
                $desc[] = $weekdays[$parts['weekday']];
            }
        }
        
        return implode(', ', $desc) ?: '自定义时间';
    }
    
    /**
     * 验证 cron 表达式
     */
    public function validateCron(string $cronExpr): array
    {
        $parts = $this->parseCronExpression($cronExpr);
        
        if (!$parts) {
            return [
                'valid' => false,
                'error' => 'Cron 表达式格式错误，应为 5 个字段：minute hour day month weekday',
            ];
        }
        
        $errors = [];
        
        // 验证各部分
        if (!$this->validateCronPart($parts['minute'], 0, 59)) {
            $errors[] = '分钟字段无效 (0-59)';
        }
        if (!$this->validateCronPart($parts['hour'], 0, 23)) {
            $errors[] = '小时字段无效 (0-23)';
        }
        if (!$this->validateCronPart($parts['day'], 1, 31)) {
            $errors[] = '日期字段无效 (1-31)';
        }
        if (!$this->validateCronPart($parts['month'], 1, 12)) {
            $errors[] = '月份字段无效 (1-12)';
        }
        if (!$this->validateCronPart($parts['weekday'], 0, 6)) {
            $errors[] = '星期字段无效 (0-6, 0=周日)';
        }
        
        if (!empty($errors)) {
            return [
                'valid' => false,
                'error' => implode('; ', $errors),
            ];
        }
        
        return [
            'valid' => true,
            'description' => $this->describeCron($cronExpr),
            'next_run' => $this->getNextRunTime($cronExpr)?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 验证 cron 部分
     */
    private function validateCronPart(string $part, int $min, int $max): bool
    {
        if ($part === '*') {
            return true;
        }
        
        if (preg_match('/^\*\/(\d+)$/', $part, $m)) {
            return $m[1] >= 1 && $m[1] <= $max;
        }
        
        if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
            return $m[1] >= $min && $m[2] <= $max && $m[1] <= $m[2];
        }
        
        if (strpos($part, ',') !== false) {
            $values = explode(',', $part);
            foreach ($values as $v) {
                if (!is_numeric($v) || $v < $min || $v > $max) {
                    return false;
                }
            }
            return true;
        }
        
        return is_numeric($part) && $part >= $min && $part <= $max;
    }
}
