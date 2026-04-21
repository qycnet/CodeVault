<?php
/**
 * CodeVault - WebSocket 日志推送服务
 * 实时推送工作流执行日志
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class LogStreamService
{
    private string $logDir;
    private array $connections = [];
    
    public function __construct()
    {
        $this->logDir = $_ENV['WORKSPACE_DIR'] ?? '/tmp/codevault-actions';
    }
    
    /**
     * 启动 WebSocket 服务器
     */
    public function startServer(int $port = 8081): void
    {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, '0.0.0.0', $port);
        socket_listen($server);
        
        echo "WebSocket server started on port {$port}\n";
        
        while (true) {
            $read = array_merge([$server], $this->connections);
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0, 200000) < 1) {
                continue;
            }
            
            // 新连接
            if (in_array($server, $read)) {
                $client = socket_accept($server);
                $this->handshake($client);
                $this->connections[] = $client;
                unset($read[array_search($server, $read)]);
            }
            
            // 处理消息
            foreach ($read as $conn) {
                $data = socket_read($conn, 1024);
                
                if ($data === false || $data === '') {
                    $this->closeConnection($conn);
                    continue;
                }
                
                $this->handleMessage($conn, $data);
            }
        }
    }
    
    /**
     * WebSocket 握手
     */
    private function handshake($client): bool
    {
        $request = socket_read($client, 5000);
        
        if (!preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $request, $matches)) {
            return false;
        }
        
        $key = trim($matches[1]);
        $acceptKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
        
        socket_write($client, $response);
        
        return true;
    }
    
    /**
     * 处理客户端消息
     */
    private function handleMessage($conn, string $data): void
    {
        $message = $this->decode($data);
        
        if (!$message) {
            return;
        }
        
        $payload = json_decode($message, true);
        
        if (!$payload) {
            return;
        }
        
        $action = $payload['action'] ?? '';
        $runId = $payload['run_id'] ?? 0;
        
        switch ($action) {
            case 'subscribe':
                // 订阅运行日志
                $this->subscribeToRun($conn, $runId);
                break;
                
            case 'unsubscribe':
                $this->unsubscribeFromRun($conn, $runId);
                break;
        }
    }
    
    /**
     * 订阅运行日志
     */
    private function subscribeToRun($conn, int $runId): void
    {
        // 发送现有日志
        $logs = $this->getRunLogs($runId);
        
        foreach ($logs as $log) {
            $this->send($conn, [
                'type' => 'log',
                'run_id' => $runId,
                'data' => $log,
            ]);
        }
        
        // 发送状态
        $run = Connection::queryOne(
            "SELECT status FROM workflow_runs WHERE id = ?",
            [$runId]
        );
        
        $this->send($conn, [
            'type' => 'status',
            'run_id' => $runId,
            'status' => $run['status'] ?? 'unknown',
        ]);
    }
    
    /**
     * 取消订阅
     */
    private function unsubscribeFromRun($conn, int $runId): void
    {
        // 简单实现：发送确认
        $this->send($conn, [
            'type' => 'unsubscribed',
            'run_id' => $runId,
        ]);
    }
    
    /**
     * 获取运行日志
     */
    private function getRunLogs(int $runId): array
    {
        $run = Connection::queryOne(
            "SELECT logs FROM workflow_runs WHERE id = ?",
            [$runId]
        );
        
        if (!$run || empty($run['logs'])) {
            return [];
        }
        
        return explode("\n", $run['logs']);
    }
    
    /**
     * 广播日志到所有订阅者
     */
    public function broadcastLog(int $runId, string $logLine): void
    {
        $message = json_encode([
            'type' => 'log',
            'run_id' => $runId,
            'data' => $logLine,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        foreach ($this->connections as $conn) {
            $this->sendRaw($conn, $message);
        }
    }
    
    /**
     * 广播状态更新
     */
    public function broadcastStatus(int $runId, string $status): void
    {
        $message = json_encode([
            'type' => 'status',
            'run_id' => $runId,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        foreach ($this->connections as $conn) {
            $this->sendRaw($conn, $message);
        }
    }
    
    /**
     * 发送消息
     */
    private function send($conn, array $data): void
    {
        $this->sendRaw($conn, json_encode($data));
    }
    
    /**
     * 发送原始消息
     */
    private function sendRaw($conn, string $message): void
    {
        $frame = $this->encode($message);
        @socket_write($conn, $frame);
    }
    
    /**
     * 编码 WebSocket 消息
     */
    private function encode(string $message): string
    {
        $length = strlen($message);
        $frame = chr(0x81); // FIN + text frame
        
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        return $frame . $message;
    }
    
    /**
     * 解码 WebSocket 消息
     */
    private function decode(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }
        
        $opcode = ord($data[0]) & 0x0F;
        $masked = (ord($data[1]) & 0x80) !== 0;
        $length = ord($data[1]) & 0x7F;
        
        $offset = 2;
        
        if ($length === 126) {
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            $length = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }
        
        if ($masked) {
            $mask = substr($data, $offset, 4);
            $offset += 4;
        }
        
        $payload = substr($data, $offset, $length);
        
        if ($masked) {
            for ($i = 0; $i < $length; $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }
        
        return $payload;
    }
    
    /**
     * 关闭连接
     */
    private function closeConnection($conn): void
    {
        $key = array_search($conn, $this->connections);
        if ($key !== false) {
            unset($this->connections[$key]);
        }
        @socket_close($conn);
    }
    
    /**
     * 流式写入日志（供 ActionsRunner 调用）
     */
    public function streamLog(int $runId, string $logLine): void
    {
        // 写入数据库
        Connection::execute(
            "UPDATE workflow_runs SET logs = CONCAT(COALESCE(logs, ''), ?) WHERE id = ?",
            [$logLine . "\n", $runId]
        );
        
        // 广播到 WebSocket
        $this->broadcastLog($runId, $logLine);
    }
    
    /**
     * 更新运行状态（供 ActionsRunner 调用）
     */
    public function updateStatus(int $runId, string $status): void
    {
        Connection::execute(
            "UPDATE workflow_runs SET status = ? WHERE id = ?",
            [$status, $runId]
        );
        
        $this->broadcastStatus($runId, $status);
    }
}
