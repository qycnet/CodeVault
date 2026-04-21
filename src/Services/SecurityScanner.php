<?php
/**
 * CodeVault - 安全扫描服务
 * 依赖漏洞扫描、代码扫描
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class SecurityScanner
{
    // 已知漏洞数据库（简化版，实际应接入 CVE 数据库）
    private array $knownVulnerabilities = [];
    
    /**
     * 扫描仓库依赖
     */
    public function scanDependencies(int $repoId): array
    {
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $gitPath = $repo['git_path'];
        $vulnerabilities = [];
        
        // 扫描 package.json (Node.js)
        $packageJson = $this->getFileContent($gitPath, 'package.json');
        if ($packageJson) {
            $vulns = $this->scanNodeDependencies($packageJson, $repoId);
            $vulnerabilities = array_merge($vulnerabilities, $vulns);
        }
        
        // 扫描 composer.json (PHP)
        $composerJson = $this->getFileContent($gitPath, 'composer.json');
        if ($composerJson) {
            $vulns = $this->scanPhpDependencies($composerJson, $repoId);
            $vulnerabilities = array_merge($vulnerabilities, $vulns);
        }
        
        // 扫描 requirements.txt (Python)
        $requirements = $this->getFileContent($gitPath, 'requirements.txt');
        if ($requirements) {
            $vulns = $this->scanPythonDependencies($requirements, $repoId);
            $vulnerabilities = array_merge($vulnerabilities, $vulns);
        }
        
        // 扫描 pom.xml (Java/Maven)
        $pomXml = $this->getFileContent($gitPath, 'pom.xml');
        if ($pomXml) {
            $vulns = $this->scanJavaDependencies($pomXml, $repoId);
            $vulnerabilities = array_merge($vulnerabilities, $vulns);
        }
        
        // 扫描 go.mod (Go)
        $goMod = $this->getFileContent($gitPath, 'go.mod');
        if ($goMod) {
            $vulns = $this->scanGoDependencies($goMod, $repoId);
            $vulnerabilities = array_merge($vulnerabilities, $vulns);
        }
        
        // 保存扫描结果
        $this->saveScanResults($repoId, 'dependency', $vulnerabilities);
        
        return [
            'success' => true,
            'vulnerabilities' => $vulnerabilities,
            'count' => count($vulnerabilities),
        ];
    }
    
    /**
     * 代码安全扫描
     */
    public function scanCode(int $repoId): array
    {
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $gitPath = $repo['git_path'];
        $issues = [];
        
        // 扫描敏感信息泄露
        $secretsPatterns = [
            'api_key' => '/(?:api[_-]?key|apikey)\s*[=:]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i',
            'secret' => '/(?:secret|secret[_-]?key)\s*[=:]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i',
            'password' => '/(?:password|passwd|pwd)\s*[=:]\s*["\']?([^\s"\']{8,})["\']?/i',
            'token' => '/(?:token|access[_-]?token)\s*[=:]\s*["\']?([a-zA-Z0-9_\-\.]{20,})["\']?/i',
            'private_key' => '/-----BEGIN (?:RSA |EC |DSA )?PRIVATE KEY-----/',
            'aws_key' => '/AKIA[0-9A-Z]{16}/',
        ];
        
        // 获取所有代码文件
        $cmd = sprintf(
            'cd %s && git ls-files | grep -E "\.(php|js|ts|py|java|go|rb|cs)$" 2>/dev/null',
            escapeshellarg($gitPath)
        );
        
        exec($cmd, $files);
        
        foreach ($files as $file) {
            $content = $this->getFileContent($gitPath, $file);
            if (!$content) continue;
            
            foreach ($secretsPatterns as $type => $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $issues[] = [
                        'type' => 'secret_leak',
                        'severity' => 'high',
                        'file' => $file,
                        'pattern' => $type,
                        'message' => "检测到可能的敏感信息泄露: {$type}",
                    ];
                }
            }
            
            // SQL 注入检测
            if (preg_match('/\$_(?:GET|POST|REQUEST)\[[\'"].*[\'"]\]\s*\.\s*\$/', $content)) {
                $issues[] = [
                    'type' => 'sql_injection',
                    'severity' => 'critical',
                    'file' => $file,
                    'message' => '可能的 SQL 注入漏洞',
                ];
            }
            
            // XSS 检测
            if (preg_match('/echo\s+\$_(?:GET|POST|REQUEST)/', $content)) {
                $issues[] = [
                    'type' => 'xss',
                    'severity' => 'high',
                    'file' => $file,
                    'message' => '可能的 XSS 漏洞',
                ];
            }
            
            // 命令注入检测
            if (preg_match('/exec\(|system\(|passthru\(|shell_exec\(|`.*\$_/', $content)) {
                $issues[] = [
                    'type' => 'command_injection',
                    'severity' => 'critical',
                    'file' => $file,
                    'message' => '可能的命令注入漏洞',
                ];
            }
        }
        
        // 保存扫描结果
        $this->saveScanResults($repoId, 'code', $issues);
        
        return [
            'success' => true,
            'issues' => $issues,
            'count' => count($issues),
        ];
    }
    
    /**
     * 扫描 Node.js 依赖
     */
    private function scanNodeDependencies(string $content, int $repoId): array
    {
        $vulns = [];
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['dependencies'])) {
            return $vulns;
        }
        
        $dependencies = array_merge(
            $data['dependencies'] ?? [],
            $data['devDependencies'] ?? []
        );
        
        foreach ($dependencies as $name => $version) {
            // 检查已知漏洞（简化版）
            $vuln = $this->checkNpmVulnerability($name, $version);
            if ($vuln) {
                $vulns[] = [
                    'type' => 'dependency',
                    'ecosystem' => 'npm',
                    'package' => $name,
                    'version' => $version,
                    'severity' => $vuln['severity'],
                    'cve' => $vuln['cve'] ?? null,
                    'message' => $vuln['message'],
                    'recommendation' => $vuln['recommendation'] ?? null,
                ];
            }
        }
        
        return $vulns;
    }
    
    /**
     * 扫描 PHP 依赖
     */
    private function scanPhpDependencies(string $content, int $repoId): array
    {
        $vulns = [];
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['require'])) {
            return $vulns;
        }
        
        foreach ($data['require'] as $name => $version) {
            $vuln = $this->checkPackagistVulnerability($name, $version);
            if ($vuln) {
                $vulns[] = [
                    'type' => 'dependency',
                    'ecosystem' => 'packagist',
                    'package' => $name,
                    'version' => $version,
                    'severity' => $vuln['severity'],
                    'cve' => $vuln['cve'] ?? null,
                    'message' => $vuln['message'],
                    'recommendation' => $vuln['recommendation'] ?? null,
                ];
            }
        }
        
        return $vulns;
    }
    
    /**
     * 扫描 Python 依赖
     */
    private function scanPythonDependencies(string $content, int $repoId): array
    {
        $vulns = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            
            // 解析 package==version 格式
            if (preg_match('/^([a-zA-Z0-9_-]+)\s*[=<>]+\s*([0-9.]+)/', $line, $matches)) {
                $name = $matches[1];
                $version = $matches[2];
                
                $vuln = $this->checkPyPIVulnerability($name, $version);
                if ($vuln) {
                    $vulns[] = [
                        'type' => 'dependency',
                        'ecosystem' => 'pypi',
                        'package' => $name,
                        'version' => $version,
                        'severity' => $vuln['severity'],
                        'cve' => $vuln['cve'] ?? null,
                        'message' => $vuln['message'],
                        'recommendation' => $vuln['recommendation'] ?? null,
                    ];
                }
            }
        }
        
        return $vulns;
    }
    
    /**
     * 扫描 Java 依赖
     */
    private function scanJavaDependencies(string $content, int $repoId): array
    {
        $vulns = [];
        
        // 简化解析 - 提取 groupId:artifactId:version
        if (preg_match_all('/<dependency>.*?<groupId>(.*?)<\/groupId>.*?<artifactId>(.*?)<\/artifactId>.*?<version>(.*?)<\/version>.*?<\/dependency>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $groupId = $match[1];
                $artifactId = $match[2];
                $version = $match[3];
                
                $vuln = $this->checkMavenVulnerability($groupId, $artifactId, $version);
                if ($vuln) {
                    $vulns[] = [
                        'type' => 'dependency',
                        'ecosystem' => 'maven',
                        'package' => "{$groupId}:{$artifactId}",
                        'version' => $version,
                        'severity' => $vuln['severity'],
                        'cve' => $vuln['cve'] ?? null,
                        'message' => $vuln['message'],
                        'recommendation' => $vuln['recommendation'] ?? null,
                    ];
                }
            }
        }
        
        return $vulns;
    }
    
    /**
     * 扫描 Go 依赖
     */
    private function scanGoDependencies(string $content, int $repoId): array
    {
        $vulns = [];
        
        // 解析 go.mod 格式
        if (preg_match_all('/([a-zA-Z0-9._\/-]+)\s+v([0-9.]+)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $module = $match[1];
                $version = $match[2];
                
                $vuln = $this->checkGoVulnerability($module, $version);
                if ($vuln) {
                    $vulns[] = [
                        'type' => 'dependency',
                        'ecosystem' => 'go',
                        'package' => $module,
                        'version' => $version,
                        'severity' => $vuln['severity'],
                        'cve' => $vuln['cve'] ?? null,
                        'message' => $vuln['message'],
                        'recommendation' => $vuln['recommendation'] ?? null,
                    ];
                }
            }
        }
        
        return $vulns;
    }
    
    /**
     * 检查 NPM 漏洞（简化版）
     */
    private function checkNpmVulnerability(string $name, string $version): ?array
    {
        // 已知漏洞数据库（示例）
        $knownVulns = [
            'lodash' => [
                '<4.17.21' => [
                    'severity' => 'high',
                    'cve' => 'CVE-2021-23337',
                    'message' => 'lodash 命令注入漏洞',
                    'recommendation' => '升级到 4.17.21 或更高版本',
                ],
            ],
            'axios' => [
                '<0.21.1' => [
                    'severity' => 'high',
                    'cve' => 'CVE-2020-28168',
                    'message' => 'axios SSRF 漏洞',
                    'recommendation' => '升级到 0.21.1 或更高版本',
                ],
            ],
            'event-source' => [
                '<1.0.0' => [
                    'severity' => 'critical',
                    'cve' => 'CVE-2022-1650',
                    'message' => 'event-source 正则表达式拒绝服务漏洞',
                    'recommendation' => '升级到 1.0.0 或更高版本',
                ],
            ],
        ];
        
        if (isset($knownVulns[$name])) {
            foreach ($knownVulns[$name] as $versionRange => $vuln) {
                if ($this->versionMatches($version, $versionRange)) {
                    return $vuln;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 检查 Packagist 漏洞
     */
    private function checkPackagistVulnerability(string $name, string $version): ?array
    {
        $knownVulns = [
            'laravel/framework' => [
                '<8.22.1' => [
                    'severity' => 'critical',
                    'cve' => 'CVE-2021-21263',
                    'message' => 'Laravel 反序列化漏洞',
                    'recommendation' => '升级到 8.22.1 或更高版本',
                ],
            ],
            'symfony/http-foundation' => [
                '<5.2.4' => [
                    'severity' => 'high',
                    'cve' => 'CVE-2021-21424',
                    'message' => 'Symfony HTTP 头注入漏洞',
                    'recommendation' => '升级到 5.2.4 或更高版本',
                ],
            ],
        ];
        
        if (isset($knownVulns[$name])) {
            foreach ($knownVulns[$name] as $versionRange => $vuln) {
                if ($this->versionMatches($version, $versionRange)) {
                    return $vuln;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 检查 PyPI 漏洞
     */
    private function checkPyPIVulnerability(string $name, string $version): ?array
    {
        $knownVulns = [
            'django' => [
                '<2.2.18' => [
                    'severity' => 'critical',
                    'cve' => 'CVE-2021-28658',
                    'message' => 'Django 目录遍历漏洞',
                    'recommendation' => '升级到 2.2.18 或更高版本',
                ],
            ],
            'flask' => [
                '<1.0' => [
                    'severity' => 'medium',
                    'cve' => 'CVE-2018-1000656',
                    'message' => 'Flask 安全绕过漏洞',
                    'recommendation' => '升级到 1.0 或更高版本',
                ],
            ],
        ];
        
        if (isset($knownVulns[$name])) {
            foreach ($knownVulns[$name] as $versionRange => $vuln) {
                if ($this->versionMatches($version, $versionRange)) {
                    return $vuln;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 检查 Maven 漏洞
     */
    private function checkMavenVulnerability(string $groupId, string $artifactId, string $version): ?array
    {
        $key = "{$groupId}:{$artifactId}";
        
        $knownVulns = [
            'org.springframework:spring-webmvc' => [
                '<5.2.5' => [
                    'severity' => 'critical',
                    'cve' => 'CVE-2020-5408',
                    'message' => 'Spring RCE 漏洞',
                    'recommendation' => '升级到 5.2.5 或更高版本',
                ],
            ],
            'log4j:log4j' => [
                '<2.17.0' => [
                    'severity' => 'critical',
                    'cve' => 'CVE-2021-44228',
                    'message' => 'Log4j RCE 漏洞 (Log4Shell)',
                    'recommendation' => '升级到 2.17.0 或更高版本',
                ],
            ],
        ];
        
        if (isset($knownVulns[$key])) {
            foreach ($knownVulns[$key] as $versionRange => $vuln) {
                if ($this->versionMatches($version, $versionRange)) {
                    return $vuln;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 检查 Go 漏洞
     */
    private function checkGoVulnerability(string $module, string $version): ?array
    {
        $knownVulns = [
            'github.com/golang-jwt/jwt' => [
                '<3.2.2' => [
                    'severity' => 'high',
                    'cve' => 'CVE-2020-26160',
                    'message' => 'JWT 验证绕过漏洞',
                    'recommendation' => '升级到 3.2.2 或更高版本',
                ],
            ],
        ];
        
        if (isset($knownVulns[$module])) {
            foreach ($knownVulns[$module] as $versionRange => $vuln) {
                if ($this->versionMatches($version, $versionRange)) {
                    return $vuln;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 版本匹配
     */
    private function versionMatches(string $version, string $range): bool
    {
        // 简化版本比较
        $range = trim($range);
        if (strpos($range, '<') === 0) {
            $minVersion = ltrim($range, '<');
            return version_compare($version, $minVersion, '<');
        }
        if (strpos($range, '>') === 0) {
            $maxVersion = ltrim($range, '>');
            return version_compare($version, $maxVersion, '>');
        }
        return $version === $range;
    }
    
    /**
     * 获取文件内容
     */
    private function getFileContent(string $gitPath, string $file): ?string
    {
        $cmd = sprintf(
            'cd %s && git show HEAD:%s 2>/dev/null',
            escapeshellarg($gitPath),
            escapeshellarg($file)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return null;
        }
        
        return implode("\n", $output);
    }
    
    /**
     * 保存扫描结果
     */
    private function saveScanResults(int $repoId, string $type, array $results): void
    {
        // 删除旧结果
        Connection::execute(
            "DELETE FROM security_scans WHERE repo_id = ? AND scan_type = ?",
            [$repoId, $type]
        );
        
        // 保存新结果
        Connection::insert(
            "INSERT INTO security_scans (repo_id, scan_type, results, scanned_at) VALUES (?, ?, ?, NOW())",
            [$repoId, $type, json_encode($results)]
        );
    }
    
    /**
     * 获取扫描历史
     */
    public function getScanHistory(int $repoId): array
    {
        return Connection::query(
            "SELECT * FROM security_scans WHERE repo_id = ? ORDER BY scanned_at DESC LIMIT 10",
            [$repoId]
        );
    }
}
