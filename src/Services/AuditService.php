<?php
/**
 * CodeVault 审计日志服务
 * 
 * 功能：
 * - 操作日志记录
 * - 安全事件审计
 * - 合规报告生成
 * - 日志查询分析
 * - 告警规则
 */

namespace CodeVault\Services;

use Core\Database;
use Core\Logger;
use Core\Cache;

class AuditService
{
    private $db;
    private $logger;
    private $cache;
    
    // 审计事件类型
    public const EVENT_AUTH = 'auth';
    public const EVENT_USER = 'user';
    public const EVENT_REPO = 'repo';
    public const EVENT_ISSUE = 'issue';
    public const EVENT_PR = 'pr';
    public const EVENT_ADMIN = 'admin';
    public const EVENT_SECURITY = 'security';
    public const EVENT_SYSTEM = 'system';
    
    // 审计操作
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_PASSWORD_CHANGE = 'password_change';
    public const ACTION_PASSWORD_RESET = 'password_reset';
    public const ACTION_USER_CREATE = 'user_create';
    public const ACTION_USER_UPDATE = 'user_update';
    public const ACTION_USER_DELETE = 'user_delete';
    public const ACTION_REPO_CREATE = 'repo_create';
    public const ACTION_REPO_DELETE = 'repo_delete';
    public const ACTION_REPO_TRANSFER = 'repo_transfer';
    public const ACTION_REPO_VISIBILITY = 'repo_visibility';
    public const ACTION_COLLABORATOR_ADD = 'collaborator_add';
    public const ACTION_COLLABORATOR_REMOVE = 'collaborator_remove';
    public const ACTION_KEY_ADD = 'key_add';
    public const ACTION_KEY_REMOVE = 'key_remove';
    public const ACTION_TOKEN_CREATE = 'token_create';
    public const ACTION_TOKEN_REVOKE = 'token_revoke';
    public const ACTION_WEBHOOK_CREATE = 'webhook_create';
    public const ACTION_WEBHOOK_DELETE = 'webhook_delete';
    public const ACTION_SETTINGS_CHANGE = 'settings_change';
    public const ACTION_PERMISSION_CHANGE = 'permission_change';
    public const ACTION_EXPORT = 'export';
    public const ACTION_IMPORT = 'import';
    public const ACTION_SSO_LOGIN = 'sso_login';
    public const ACTION_MFA_ENABLE = 'mfa_enable';
    public const ACTION_MFA_DISABLE = 'mfa_disable';
    
    // 风险级别
    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('audit');
        $this->cache = Cache::getInstance();
    }
    
    /**
     * 记录审计日志
     */
    public function log(array $data): bool
    {
        $event = $data['event'] ?? '';
        $action = $data['action'] ?? '';
        
        if (empty($event) || empty($action)) {
            return false;
        }
        
        // 计算风险级别
        $riskLevel = $data['risk_level'] ?? $this->calculateRiskLevel($event, $action);
        
        $sql = "INSERT INTO audit_logs (
                    event, action, user_id, username, ip_address, user_agent,
                    resource_type, resource_id, resource_name,
                    old_value, new_value, details, risk_level, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $event,
            $action,
            $data['user_id'] ?? null,
            $data['username'] ?? null,
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            $data['resource_type'] ?? null,
            $data['resource_id'] ?? null,
            $data['resource_name'] ?? null,
            isset($data['old_value']) ? json_encode($data['old_value']) : null,
            isset($data['new_value']) ? json_encode($data['new_value']) : null,
            isset($data['details']) ? json_encode($data['details']) : null,
            $riskLevel,
        ]);
        
        // 检查告警规则
        $this->checkAlertRules($event, $action, $riskLevel, $data);
        
        return true;
    }
    
    /**
     * 计算风险级别
     */
    private function calculateRiskLevel(string $event, string $action): string
    {
        // 高风险操作
        $criticalActions = [
            self::ACTION_USER_DELETE,
            self::ACTION_REPO_DELETE,
            self::ACTION_PERMISSION_CHANGE,
        ];
        
        $highRiskActions = [
            self::ACTION_PASSWORD_CHANGE,
            self::ACTION_REPO_TRANSFER,
            self::ACTION_REPO_VISIBILITY,
            self::ACTION_COLLABORATOR_ADD,
            self::ACTION_COLLABORATOR_REMOVE,
            self::ACTION_KEY_ADD,
            self::ACTION_KEY_REMOVE,
            self::ACTION_MFA_DISABLE,
        ];
        
        $mediumRiskActions = [
            self::ACTION_LOGIN_FAILED,
            self::ACTION_USER_CREATE,
            self::ACTION_REPO_CREATE,
            self::ACTION_TOKEN_CREATE,
            self::ACTION_WEBHOOK_CREATE,
            self::ACTION_EXPORT,
            self::ACTION_IMPORT,
            self::ACTION_SSO_LOGIN,
        ];
        
        if (in_array($action, $criticalActions)) {
            return self::RISK_CRITICAL;
        }
        
        if (in_array($action, $highRiskActions)) {
            return self::RISK_HIGH;
        }
        
        if (in_array($action, $mediumRiskActions)) {
            return self::RISK_MEDIUM;
        }
        
        return self::RISK_LOW;
    }
    
    /**
     * 检查告警规则
     */
    private function checkAlertRules(string $event, string $action, string $riskLevel, array $data): void
    {
        // 关键操作立即告警
        if ($riskLevel === self::RISK_CRITICAL) {
            $this->sendAlert('critical', $event, $action, $data);
            return;
        }
        
        // 高风险操作检查频率
        if ($riskLevel === self::RISK_HIGH) {
            $userId = $data['user_id'] ?? null;
            $ipAddress = $data['ip_address'] ?? null;
            
            // 检查用户操作频率
            if ($userId) {
                $count = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM audit_logs 
                     WHERE user_id = ? AND risk_level IN ('high', 'critical') 
                     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                    [$userId]
                )['count'];
                
                if ($count >= 5) {
                    $this->sendAlert('high_frequency_user', $event, $action, $data);
                }
            }
            
            // 检查 IP 操作频率
            if ($ipAddress) {
                $count = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM audit_logs 
                     WHERE ip_address = ? AND risk_level IN ('high', 'critical') 
                     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                    [$ipAddress]
                )['count'];
                
                if ($count >= 10) {
                    $this->sendAlert('high_frequency_ip', $event, $action, $data);
                }
            }
        }
        
        // 登录失败检查
        if ($action === self::ACTION_LOGIN_FAILED) {
            $ipAddress = $data['ip_address'] ?? null;
            
            if ($ipAddress) {
                $count = $this->db->fetchOne(
                    "SELECT COUNT(*) as count FROM audit_logs 
                     WHERE action = ? AND ip_address = ? 
                     AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                    [self::ACTION_LOGIN_FAILED, $ipAddress]
                )['count'];
                
                if ($count >= 5) {
                    $this->sendAlert('brute_force', $event, $action, $data);
                }
            }
        }
    }
    
    /**
     * 发送告警
     */
    private function sendAlert(string $type, string $event, string $action, array $data): void
    {
        $this->logger->warning('审计告警', [
            'type' => $type,
            'event' => $event,
            'action' => $action,
            'data' => $data,
        ]);
        
        // 存储告警记录
        $sql = "INSERT INTO audit_alerts (type, event, action, user_id, ip_address, details, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $type,
            $event,
            $action,
            $data['user_id'] ?? null,
            $data['ip_address'] ?? null,
            json_encode($data),
        ]);
        
        // 发送通知
        $this->sendAlertNotification($type, $event, $action, $data);
    }
    
    /**
     * 发送告警通知
     */
    private function sendAlertNotification(string $type, string $event, string $action, array $data): void
    {
        $alertConfig = $_ENV['ALERT_CONFIG'] ?? null;
        if (!$alertConfig) {
            return;
        }
        
        $config = json_decode($alertConfig, true);
        if (!$config) {
            return;
        }
        
        $message = sprintf(
            '[%s] %s - %s (用户: %s, IP: %s)',
            strtoupper($type),
            $event,
            $action,
            $data['user_id'] ?? 'unknown',
            $data['ip_address'] ?? 'unknown'
        );
        
        // 邮件通知
        if (!empty($config['email'])) {
            $mailer = new Mailer();
            $mailer->send(
                $config['email'],
                "[CodeVault 安全告警] {$event}",
                $message,
                ['html' => false]
            );
        }
        
        // Webhook 通知（Slack/钉钉等）
        if (!empty($config['webhook_url'])) {
            $payload = json_encode([
                'text' => $message,
                'attachments' => [
                    [
                        'title' => '安全告警',
                        'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                        'color' => $type === 'critical' ? 'danger' : 'warning',
                    ]
                ]
            ]);
            
            $ch = curl_init($config['webhook_url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }
    
    /**
     * 查询审计日志
     */
    public function query(array $params = []): array
    {
        $where = ['1=1'];
        $bindings = [];
        
        // 用户筛选
        if (!empty($params['user_id'])) {
            $where[] = 'user_id = ?';
            $bindings[] = $params['user_id'];
        }
        
        // 事件类型筛选
        if (!empty($params['event'])) {
            $where[] = 'event = ?';
            $bindings[] = $params['event'];
        }
        
        // 操作类型筛选
        if (!empty($params['action'])) {
            $where[] = 'action = ?';
            $bindings[] = $params['action'];
        }
        
        // 资源类型筛选
        if (!empty($params['resource_type'])) {
            $where[] = 'resource_type = ?';
            $bindings[] = $params['resource_type'];
        }
        
        // 资源 ID 筛选
        if (!empty($params['resource_id'])) {
            $where[] = 'resource_id = ?';
            $bindings[] = $params['resource_id'];
        }
        
        // 风险级别筛选
        if (!empty($params['risk_level'])) {
            $where[] = 'risk_level = ?';
            $bindings[] = $params['risk_level'];
        }
        
        // IP 地址筛选
        if (!empty($params['ip_address'])) {
            $where[] = 'ip_address = ?';
            $bindings[] = $params['ip_address'];
        }
        
        // 时间范围
        if (!empty($params['start_date'])) {
            $where[] = 'created_at >= ?';
            $bindings[] = $params['start_date'];
        }
        
        if (!empty($params['end_date'])) {
            $where[] = 'created_at <= ?';
            $bindings[] = $params['end_date'];
        }
        
        // 搜索
        if (!empty($params['search'])) {
            $where[] = '(username LIKE ? OR resource_name LIKE ? OR details LIKE ?)';
            $searchTerm = "%{$params['search']}%";
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // 分页
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        
        // 获取总数
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM audit_logs WHERE {$whereClause}",
            $bindings
        )['count'];
        
        // 获取数据
        $logs = $this->db->fetchAll(
            "SELECT * FROM audit_logs WHERE {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
        
        return [
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'data' => $logs,
        ];
    }
    
    /**
     * 获取用户活动历史
     */
    public function getUserActivity(int $userId, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }
    
    /**
     * 获取资源活动历史
     */
    public function getResourceActivity(string $resourceType, int $resourceId, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs WHERE resource_type = ? AND resource_id = ? ORDER BY created_at DESC LIMIT ?",
            [$resourceType, $resourceId, $limit]
        );
    }
    
    /**
     * 获取安全事件
     */
    public function getSecurityEvents(array $params = []): array
    {
        $params['risk_level'] = 'high';
        
        return $this->query($params);
    }
    
    /**
     * 获取告警列表
     */
    public function getAlerts(array $params = []): array
    {
        $where = ['1=1'];
        $bindings = [];
        
        if (!empty($params['type'])) {
            $where[] = 'type = ?';
            $bindings[] = $params['type'];
        }
        
        if (!empty($params['acknowledged']) !== null) {
            $where[] = 'acknowledged = ?';
            $bindings[] = $params['acknowledged'] ? 1 : 0;
        }
        
        $whereClause = implode(' AND ', $where);
        
        return $this->db->fetchAll(
            "SELECT * FROM audit_alerts WHERE {$whereClause} ORDER BY created_at DESC LIMIT 100",
            $bindings
        );
    }
    
    /**
     * 确认告警
     */
    public function acknowledgeAlert(int $alertId, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE audit_alerts SET acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?",
            [$userId, $alertId]
        ) > 0;
    }
    
    /**
     * 生成审计报告
     */
    public function generateReport(array $params = []): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => $this->getReportSummary($startDate, $endDate),
            'top_users' => $this->getTopActiveUsers($startDate, $endDate),
            'top_actions' => $this->getTopActions($startDate, $endDate),
            'security_events' => $this->getSecurityEventsSummary($startDate, $endDate),
            'risk_distribution' => $this->getRiskDistribution($startDate, $endDate),
            'daily_activity' => $this->getDailyActivity($startDate, $endDate),
        ];
        
        return $report;
    }
    
    /**
     * 获取报告摘要
     */
    private function getReportSummary(string $startDate, string $endDate): array
    {
        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_events,
                SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_events,
                SUM(CASE WHEN action = 'login_failed' THEN 1 ELSE 0 END) as failed_logins
             FROM audit_logs 
             WHERE created_at BETWEEN ? AND ?",
            [$startDate, $endDate . ' 23:59:59']
        );
    }
    
    /**
     * 获取活跃用户 Top 10
     */
    private function getTopActiveUsers(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT user_id, username, COUNT(*) as event_count
             FROM audit_logs 
             WHERE created_at BETWEEN ? AND ? AND user_id IS NOT NULL
             GROUP BY user_id, username
             ORDER BY event_count DESC
             LIMIT 10",
            [$startDate, $endDate . ' 23:59:59']
        );
    }
    
    /**
     * 获取操作类型 Top 10
     */
    private function getTopActions(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT event, action, COUNT(*) as count
             FROM audit_logs 
             WHERE created_at BETWEEN ? AND ?
             GROUP BY event, action
             ORDER BY count DESC
             LIMIT 10",
            [$startDate, $endDate . ' 23:59:59']
        );
    }
    
    /**
     * 获取安全事件摘要
     */
    private function getSecurityEventsSummary(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT action, COUNT(*) as count
             FROM audit_logs 
             WHERE created_at BETWEEN ? AND ? 
             AND risk_level IN ('high', 'critical')
             GROUP BY action
             ORDER BY count DESC",
            [$startDate, $endDate . ' 23:59:59']
        );
    }
    
    /**
     * 获取风险分布
     */
    private function getRiskDistribution(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT risk_level, COUNT(*) as count
             FROM audit_logs 
             WHERE created_at BETWEEN ? AND ?
             GROUP BY risk_level",
            [$startDate, $endDate . ' 23:59:59']
        );
    }
    
    /**
     * 获取每日活动
     */
    private function getDailyActivity(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT DATE(created_at) as date, 
                    COUNT(*) as total,
                    SUM(CASE WHEN risk_level IN ('high', 'critical') THEN 1 ELSE 0 END) as high_risk
             FROM audit_logs 
             WHERE created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$startDate, $endDate . ' 23:59:59']
        );
    }
    
    /**
     * 清理过期日志
     */
    public function cleanup(int $retentionDays = 90): int
    {
        $result = $this->db->execute(
            "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );
        
        $this->logger->info('审计日志清理完成', ['deleted' => $result, 'retention_days' => $retentionDays]);
        
        return $result;
    }
    
    /**
     * 导出审计日志
     */
    public function export(array $params = []): string
    {
        $logs = $this->query(array_merge($params, ['per_page' => 10000]));
        
        $csv = [];
        $csv[] = ['ID', '事件', '操作', '用户', 'IP地址', '资源类型', '资源ID', '风险级别', '时间'];
        
        foreach ($logs['data'] as $log) {
            $csv[] = [
                $log['id'],
                $log['event'],
                $log['action'],
                $log['username'] ?? '',
                $log['ip_address'] ?? '',
                $log['resource_type'] ?? '',
                $log['resource_id'] ?? '',
                $log['risk_level'],
                $log['created_at'],
            ];
        }
        
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }
}
