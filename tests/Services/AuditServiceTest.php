<?php
/**
 * CodeVault - AuditService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\AuditService;

class AuditServiceTest extends TestCase
{
    private AuditService $service;
    
    protected function setUp(): void
    {
        $this->service = new AuditService();
    }
    
    /**
     * 测试事件类型验证
     */
    public function testValidEventTypes(): void
    {
        $validEvents = [
            'auth.login', 'auth.logout', 'auth.login_failed', 'auth.password_change',
            'user.create', 'user.update', 'user.delete',
            'repo.create', 'repo.delete', 'repo.transfer', 'repo.visibility_change',
            'security.token_create', 'security.token_revoke', 'security.webhook_change',
            'sso.login', 'mfa.enable', 'mfa.disable',
        ];
        
        foreach ($validEvents as $event) {
            $this->assertTrue($this->service->isValidEventType($event), "Event '$event' should be valid");
        }
        
        $this->assertFalse($this->service->isValidEventType('invalid.event'));
        $this->assertFalse($this->service->isValidEventType(''));
    }
    
    /**
     * 测试风险级别验证
     */
    public function testValidRiskLevels(): void
    {
        $this->assertTrue($this->service->isValidRiskLevel('low'));
        $this->assertTrue($this->service->isValidRiskLevel('medium'));
        $this->assertTrue($this->service->isValidRiskLevel('high'));
        $this->assertTrue($this->service->isValidRiskLevel('critical'));
        
        $this->assertFalse($this->service->isValidRiskLevel('invalid'));
    }
    
    /**
     * 测试风险级别计算
     */
    public function testRiskLevelCalculation(): void
    {
        // 低风险：普通登录
        $this->assertEquals('low', $this->service->calculateRiskLevel('auth.login', []));
        
        // 中风险：登录失败
        $this->assertEquals('medium', $this->service->calculateRiskLevel('auth.login_failed', []));
        
        // 高风险：删除仓库
        $this->assertEquals('high', $this->service->calculateRiskLevel('repo.delete', []));
        
        // 严重：安全事件
        $this->assertEquals('critical', $this->service->calculateRiskLevel('security.token_create', []));
    }
    
    /**
     * 测试日志条目验证
     */
    public function testLogEntryValidation(): void
    {
        $validEntry = [
            'event_type' => 'auth.login',
            'user_id' => 1,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'details' => ['method' => 'password'],
        ];
        
        $this->assertTrue($this->service->isValidLogEntry($validEntry));
        
        $invalidEntry = [
            'event_type' => 'invalid',
            'user_id' => 1,
        ];
        
        $this->assertFalse($this->service->isValidLogEntry($invalidEntry));
    }
    
    /**
     * 测试 IP 地址验证
     */
    public function testIpAddressValidation(): void
    {
        $this->assertTrue($this->service->isValidIpAddress('192.168.1.1'));
        $this->assertTrue($this->service->isValidIpAddress('10.0.0.1'));
        $this->assertTrue($this->service->isValidIpAddress('::1'));
        $this->assertTrue($this->service->isValidIpAddress('2001:db8::1'));
        
        $this->assertFalse($this->service->isValidIpAddress('invalid'));
        $this->assertFalse($this->service->isValidIpAddress('256.256.256.256'));
    }
    
    /**
     * 测试敏感数据脱敏
     */
    public function testSensitiveDataMasking(): void
    {
        $data = [
            'password' => 'secret123',
            'token' => 'abc123xyz',
            'email' => 'user@example.com',
            'public' => 'public data',
        ];
        
        $masked = $this->service->maskSensitiveData($data);
        
        $this->assertEquals('***', $masked['password']);
        $this->assertEquals('***', $masked['token']);
        $this->assertEquals('u***@example.com', $masked['email']);
        $this->assertEquals('public data', $masked['public']);
    }
    
    /**
     * 测试查询过滤
     */
    public function testQueryFilters(): void
    {
        $filters = [
            'event_type' => 'auth.login',
            'risk_level' => 'high',
            'user_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ];
        
        $this->assertTrue($this->service->areValidFilters($filters));
        
        $invalidFilters = [
            'event_type' => 'invalid',
            'risk_level' => 'invalid',
        ];
        
        $this->assertFalse($this->service->areValidFilters($invalidFilters));
    }
    
    /**
     * 测试分页参数
     */
    public function testPaginationValidation(): void
    {
        $this->assertEquals(1, $this->service->validatePage(1));
        $this->assertEquals(1, $this->service->validatePage(0));
        $this->assertEquals(1, $this->service->validatePage(-1));
        
        $this->assertEquals(50, $this->service->validatePerPage(50));
        $this->assertEquals(100, $this->service->validatePerPage(200)); // 最大 100
        $this->assertEquals(20, $this->service->validatePerPage(0)); // 默认 20
    }
    
    /**
     * 测试告警条件
     */
    public function testAlertConditions(): void
    {
        // 多次登录失败应触发告警
        $events = [
            ['event_type' => 'auth.login_failed', 'created_at' => date('Y-m-d H:i:s')],
            ['event_type' => 'auth.login_failed', 'created_at' => date('Y-m-d H:i:s')],
            ['event_type' => 'auth.login_failed', 'created_at' => date('Y-m-d H:i:s')],
        ];
        
        $this->assertTrue($this->service->shouldTriggerAlert($events, 'auth.login_failed'));
        
        // 正常登录不应触发
        $normalEvents = [
            ['event_type' => 'auth.login', 'created_at' => date('Y-m-d H:i:s')],
        ];
        
        $this->assertFalse($this->service->shouldTriggerAlert($normalEvents, 'auth.login'));
    }
    
    /**
     * 测试日志保留策略
     */
    public function testRetentionPolicy(): void
    {
        $this->assertEquals(90, $this->service->getDefaultRetentionDays());
        
        // 不同风险级别不同保留时间
        $this->assertGreaterThanOrEqual(
            $this->service->getRetentionDays('low'),
            $this->service->getRetentionDays('critical')
        );
    }
    
    /**
     * 测试导出格式
     */
    public function testExportFormats(): void
    {
        $validFormats = ['json', 'csv', 'pdf'];
        
        foreach ($validFormats as $format) {
            $this->assertTrue($this->service->isValidExportFormat($format));
        }
        
        $this->assertFalse($this->service->isValidExportFormat('invalid'));
    }
    
    /**
     * 测试合规报告生成
     */
    public function testComplianceReportGeneration(): void
    {
        $filters = [
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ];
        
        $report = $this->service->generateReportSummary($filters);
        
        $this->assertArrayHasKey('total_events', $report);
        $this->assertArrayHasKey('by_risk_level', $report);
        $this->assertArrayHasKey('by_event_type', $report);
    }
}
