<?php
/**
 * CodeVault - 邮件服务
 */

namespace CodeVault\Services;

class Mailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private bool $smtpAuth;
    private string $encryption;
    
    public function __construct()
    {
        $this->host = $_ENV['SMTP_HOST'] ?? 'localhost';
        $this->port = (int) ($_ENV['SMTP_PORT'] ?? 25);
        $this->username = $_ENV['SMTP_USER'] ?? '';
        $this->password = $_ENV['SMTP_PASS'] ?? '';
        $this->fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@codevault.local';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'CodeVault';
        $this->smtpAuth = !empty($this->username);
        $this->encryption = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
    }
    
    /**
     * 发送邮件
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $fromEmail = $options['from_email'] ?? $this->fromEmail;
        $fromName = $options['from_name'] ?? $this->fromName;
        $isHtml = $options['html'] ?? true;
        
        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$subject}";
        $headers[] = "MIME-Version: 1.0";
        
        if ($isHtml) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }
        
        // 使用 SMTP 发送
        if ($this->smtpAuth) {
            return $this->sendViaSMTP($to, $subject, $body, $headers);
        }
        
        // 使用 PHP mail() 函数
        return $this->sendViaMail($to, $subject, $body, $headers);
    }
    
    /**
     * 通过 SMTP 发送邮件
     */
    private function sendViaSMTP(string $to, string $subject, string $body, array $headers): bool
    {
        $socket = fsockopen(
            $this->encryption === 'ssl' ? 'ssl://' . $this->host : $this->host,
            $this->port,
            $errno,
            $errstr,
            30
        );
        
        if (!$socket) {
            error_log("SMTP connection failed: {$errstr} ({$errno})");
            return false;
        }
        
        // 读取欢迎消息
        $this->readSMTP($socket);
        
        // EHLO
        $this->sendSMTP($socket, "EHLO " . gethostname());
        $this->readSMTP($socket);
        
        // STARTTLS
        if ($this->encryption === 'tls' && $this->port === 587) {
            $this->sendSMTP($socket, "STARTTLS");
            $this->readSMTP($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendSMTP($socket, "EHLO " . gethostname());
            $this->readSMTP($socket);
        }
        
        // 认证
        if ($this->smtpAuth) {
            $this->sendSMTP($socket, "AUTH LOGIN");
            $this->readSMTP($socket);
            $this->sendSMTP($socket, base64_encode($this->username));
            $this->readSMTP($socket);
            $this->sendSMTP($socket, base64_encode($this->password));
            $response = $this->readSMTP($socket);
            
            if (strpos($response, '235') === false) {
                error_log("SMTP authentication failed");
                fclose($socket);
                return false;
            }
        }
        
        // MAIL FROM
        $this->sendSMTP($socket, "MAIL FROM: <{$this->fromEmail}>");
        $this->readSMTP($socket);
        
        // RCPT TO
        $this->sendSMTP($socket, "RCPT TO: <{$to}>");
        $this->readSMTP($socket);
        
        // DATA
        $this->sendSMTP($socket, "DATA");
        $this->readSMTP($socket);
        
        // 发送邮件内容
        $emailContent = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
        $this->sendSMTP($socket, $emailContent);
        $this->readSMTP($socket);
        
        // QUIT
        $this->sendSMTP($socket, "QUIT");
        fclose($socket);
        
        return true;
    }
    
    /**
     * 发送 SMTP 命令
     */
    private function sendSMTP($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }
    
    /**
     * 读取 SMTP 响应
     */
    private function readSMTP($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
    
    /**
     * 通过 PHP mail() 发送邮件
     */
    private function sendViaMail(string $to, string $subject, string $body, array $headers): bool
    {
        $headerStr = implode("\r\n", $headers);
        return mail($to, $subject, $body, $headerStr);
    }
    
    /**
     * 发送验证码邮件
     */
    public function sendVerificationCode(string $to, string $code): bool
    {
        $subject = 'CodeVault - 邮箱验证码';
        $body = $this->renderTemplate('verification', [
            'code' => $code,
            'expire_minutes' => 10,
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * 发送密码重置邮件
     */
    public function sendPasswordReset(string $to, string $token, string $resetUrl): bool
    {
        $subject = 'CodeVault - 密码重置';
        $body = $this->renderTemplate('password_reset', [
            'reset_url' => $resetUrl . '?token=' . $token,
            'expire_hours' => 24,
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * 发送通知邮件
     */
    public function sendNotification(string $to, string $subject, string $message, array $data = []): bool
    {
        $body = $this->renderTemplate('notification', [
            'subject' => $subject,
            'message' => $message,
            'data' => $data,
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * 发送 Issue 通知
     */
    public function sendIssueNotification(string $to, string $type, array $issue, array $repo): bool
    {
        $subjects = [
            'created' => "[{$repo['name']}] 新 Issue: {$issue['title']}",
            'updated' => "[{$repo['name']}] Issue 更新: {$issue['title']}",
            'closed' => "[{$repo['name']}] Issue 已关闭: {$issue['title']}",
            'commented' => "[{$repo['name']}] Issue 新评论: {$issue['title']}",
        ];
        
        $subject = $subjects[$type] ?? "[{$repo['name']}] Issue 通知";
        
        $body = $this->renderTemplate('issue_notification', [
            'type' => $type,
            'issue' => $issue,
            'repo' => $repo,
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * 发送 PR 通知
     */
    public function sendPRNotification(string $to, string $type, array $pr, array $repo): bool
    {
        $subjects = [
            'created' => "[{$repo['name']}] 新 Pull Request: {$pr['title']}",
            'updated' => "[{$repo['name']}] Pull Request 更新: {$pr['title']}",
            'merged' => "[{$repo['name']}] Pull Request 已合并: {$pr['title']}",
            'closed' => "[{$repo['name']}] Pull Request 已关闭: {$pr['title']}",
            'commented' => "[{$repo['name']}] Pull Request 新评论: {$pr['title']}",
        ];
        
        $subject = $subjects[$type] ?? "[{$repo['name']}] Pull Request 通知";
        
        $body = $this->renderTemplate('pr_notification', [
            'type' => $type,
            'pr' => $pr,
            'repo' => $repo,
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * 渲染邮件模板
     */
    private function renderTemplate(string $template, array $data): string
    {
        $templatePath = __DIR__ . "/../Templates/emails/{$template}.html";
        
        if (!file_exists($templatePath)) {
            // 使用默认模板
            return $this->renderDefaultTemplate($template, $data);
        }
        
        $content = file_get_contents($templatePath);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            $content = str_replace("{{{$key}}}", htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $content);
        }
        
        return $content;
    }
    
    /**
     * 渲染默认模板
     */
    private function renderDefaultTemplate(string $template, array $data): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #24292f; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #24292f; color: white; padding: 20px; text-align: center; }
        .content { background: #f6f8fa; padding: 20px; border-radius: 6px; margin-top: 20px; }
        .code { background: #fff; border: 1px solid #d0d7de; padding: 20px; font-size: 24px; text-align: center; letter-spacing: 4px; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; color: #57606a; font-size: 12px; margin-top: 20px; }
        a { color: #0969da; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CodeVault</h1>
        </div>
        <div class="content">';
        
        switch ($template) {
            case 'verification':
                $html .= '<h2>邮箱验证</h2>
                <p>您的验证码是：</p>
                <div class="code">' . ($data['code'] ?? '') . '</div>
                <p>验证码将在 ' . ($data['expire_minutes'] ?? 10) . ' 分钟后失效。</p>';
                break;
                
            case 'password_reset':
                $html .= '<h2>密码重置</h2>
                <p>您收到这封邮件是因为有人请求重置您的密码。</p>
                <p><a href="' . ($data['reset_url'] ?? '') . '">点击此处重置密码</a></p>
                <p>链接将在 ' . ($data['expire_hours'] ?? 24) . ' 小时后失效。</p>
                <p>如果您没有请求重置密码，请忽略此邮件。</p>';
                break;
                
            case 'notification':
                $html .= '<h2>' . htmlspecialchars($data['subject'] ?? '通知') . '</h2>
                <p>' . nl2br(htmlspecialchars($data['message'] ?? '')) . '</p>';
                break;
                
            case 'issue_notification':
                $issue = $data['issue'] ?? [];
                $repo = $data['repo'] ?? [];
                $html .= '<h2>Issue 通知</h2>
                <p><strong>仓库：</strong>' . htmlspecialchars($repo['name'] ?? '') . '</p>
                <p><strong>标题：</strong>' . htmlspecialchars($issue['title'] ?? '') . '</p>
                <p><strong>状态：</strong>' . htmlspecialchars($issue['status'] ?? 'open') . '</p>';
                break;
                
            case 'pr_notification':
                $pr = $data['pr'] ?? [];
                $repo = $data['repo'] ?? [];
                $html .= '<h2>Pull Request 通知</h2>
                <p><strong>仓库：</strong>' . htmlspecialchars($repo['name'] ?? '') . '</p>
                <p><strong>标题：</strong>' . htmlspecialchars($pr['title'] ?? '') . '</p>
                <p><strong>状态：</strong>' . htmlspecialchars($pr['status'] ?? 'open') . '</p>';
                break;
                
            default:
                $html .= '<p>' . htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE)) . '</p>';
        }
        
        $html .= '</div>
        <div class="footer">
            <p>此邮件由系统自动发送，请勿回复。</p>
            <p>&copy; ' . date('Y') . ' CodeVault. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
}
