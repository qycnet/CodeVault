<?php
/**
 * CodeVault - SSH Key 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Models\SshKey;
use CodeVault\Services\Session;

class SshKeyController
{
    /**
     * 获取当前用户的所有SSH Key
     */
    public function list(): array
    {
        $user = Session::user();
        
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $keys = SshKey::findByUserId($user['id']);
        
        // 隐藏完整的public_key，只显示部分
        foreach ($keys as &$key) {
            $key['public_key_preview'] = substr($key['public_key'], 0, 50) . '...';
            unset($key['public_key']);
        }
        
        return [
            'success' => true,
            'keys' => $keys,
        ];
    }
    
    /**
     * 添加SSH Key
     */
    public function add(array $data): array
    {
        $user = Session::user();
        
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $keyName = trim($data['key_name'] ?? '');
        $publicKey = trim($data['public_key'] ?? '');
        
        // 验证参数
        if (empty($keyName)) {
            return ['success' => false, 'message' => '请输入Key名称'];
        }
        
        if (strlen($keyName) > 100) {
            return ['success' => false, 'message' => 'Key名称不能超过100字符'];
        }
        
        if (empty($publicKey)) {
            return ['success' => false, 'message' => '请输入公钥内容'];
        }
        
        // 验证SSH Key格式
        if (!SshKey::validateKey($publicKey)) {
            return ['success' => false, 'message' => '无效的SSH公钥格式'];
        }
        
        try {
            $keyId = SshKey::create($user['id'], $keyName, $publicKey);
            
            return [
                'success' => true,
                'message' => 'SSH Key添加成功',
                'key_id' => $keyId,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 删除SSH Key
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $keyId = (int) ($data['key_id'] ?? 0);
        
        if ($keyId <= 0) {
            return ['success' => false, 'message' => '无效的Key ID'];
        }
        
        $affected = SshKey::delete($keyId, $user['id']);
        
        if ($affected > 0) {
            return ['success' => true, 'message' => 'SSH Key已删除'];
        }
        
        return ['success' => false, 'message' => 'SSH Key不存在或无权删除'];
    }
    
    /**
     * 获取SSH Key详情
     */
    public function detail(array $data): array
    {
        $user = Session::user();
        
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $keyId = (int) ($data['key_id'] ?? 0);
        
        $key = SshKey::findById($keyId);
        
        if (!$key || $key['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => 'SSH Key不存在'];
        }
        
        return [
            'success' => true,
            'key' => $key,
        ];
    }
}
