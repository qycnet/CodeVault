<?php
/**
 * CodeVault - Issue 控制器
 * 处理 Issue 的 CRUD 操作
 */

namespace CodeVault\Controllers;

use CodeVault\Models\Issue;
use CodeVault\Models\Repository;
use CodeVault\Services\Session;

class IssueController
{
    /**
     * 获取仓库的 Issue 列表
     */
    public function list(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $status = $data['status'] ?? null;
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 检查仓库访问权限
        if (!Repository::canAccess($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $issues = Issue::findByRepoId($repoId, $status);
        
        return [
            'success' => true,
            'issues' => array_map(function ($issue) {
                return [
                    'id' => $issue['id'],
                    'title' => $issue['title'],
                    'status' => $issue['status'],
                    'author_name' => $issue['author_name'],
                    'created_at' => $issue['created_at'],
                    'updated_at' => $issue['updated_at'],
                ];
            }, $issues),
        ];
    }
    
    /**
     * 创建 Issue
     */
    public function create(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        if (empty($title)) {
            return ['success' => false, 'message' => '标题不能为空'];
        }
        
        if (strlen($title) > 255) {
            return ['success' => false, 'message' => '标题不能超过255个字符'];
        }
        
        // 检查仓库访问权限
        if (!Repository::canAccess($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $issueId = Issue::create([
            'repo_id' => $repoId,
            'user_id' => $user['id'],
            'title' => $title,
            'content' => $content,
            'status' => 'open',
        ]);
        
        return [
            'success' => true,
            'message' => 'Issue 创建成功',
            'issue_id' => $issueId,
        ];
    }
    
    /**
     * 获取 Issue 详情
     */
    public function detail(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $issueId = (int) ($data['issue_id'] ?? 0);
        
        if ($issueId <= 0) {
            return ['success' => false, 'message' => '无效的 Issue ID'];
        }
        
        $issue = Issue::findById($issueId);
        if (!$issue) {
            return ['success' => false, 'message' => 'Issue 不存在'];
        }
        
        // 检查仓库访问权限
        if (!Repository::canAccess($issue['repo_id'], $user['id'])) {
            return ['success' => false, 'message' => '无权访问此 Issue'];
        }
        
        return [
            'success' => true,
            'issue' => [
                'id' => $issue['id'],
                'repo_id' => $issue['repo_id'],
                'repo_name' => $issue['repo_name'],
                'title' => $issue['title'],
                'content' => $issue['content'],
                'status' => $issue['status'],
                'author_name' => $issue['author_name'],
                'created_at' => $issue['created_at'],
                'updated_at' => $issue['updated_at'],
            ],
        ];
    }
    
    /**
     * 更新 Issue
     */
    public function update(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $issueId = (int) ($data['issue_id'] ?? 0);
        
        if ($issueId <= 0) {
            return ['success' => false, 'message' => '无效的 Issue ID'];
        }
        
        $issue = Issue::findById($issueId);
        if (!$issue) {
            return ['success' => false, 'message' => 'Issue 不存在'];
        }
        
        // 只有作者可以修改
        if ($issue['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权修改此 Issue'];
        }
        
        $updateData = [];
        if (isset($data['title'])) {
            $updateData['title'] = trim($data['title']);
        }
        if (isset($data['content'])) {
            $updateData['content'] = trim($data['content']);
        }
        if (isset($data['status']) && in_array($data['status'], ['open', 'closed'])) {
            $updateData['status'] = $data['status'];
        }
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => '没有需要更新的内容'];
        }
        
        $affected = Issue::update($issueId, $user['id'], $updateData);
        
        return $affected > 0
            ? ['success' => true, 'message' => 'Issue 更新成功']
            : ['success' => false, 'message' => '更新失败'];
    }
    
    /**
     * 关闭 Issue
     */
    public function close(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $issueId = (int) ($data['issue_id'] ?? 0);
        
        if ($issueId <= 0) {
            return ['success' => false, 'message' => '无效的 Issue ID'];
        }
        
        $issue = Issue::findById($issueId);
        if (!$issue) {
            return ['success' => false, 'message' => 'Issue 不存在'];
        }
        
        // 仓库所有者或 Issue 作者可以关闭
        $isRepoOwner = Repository::isOwner($issue['repo_id'], $user['id']);
        $isIssueAuthor = $issue['user_id'] === $user['id'];
        
        if (!$isRepoOwner && !$isIssueAuthor) {
            return ['success' => false, 'message' => '无权关闭此 Issue'];
        }
        
        $affected = Issue::close($issueId, $user['id']);
        
        return $affected > 0
            ? ['success' => true, 'message' => 'Issue 已关闭']
            : ['success' => false, 'message' => '关闭失败'];
    }
    
    /**
     * 删除 Issue
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $issueId = (int) ($data['issue_id'] ?? 0);
        
        if ($issueId <= 0) {
            return ['success' => false, 'message' => '无效的 Issue ID'];
        }
        
        $issue = Issue::findById($issueId);
        if (!$issue) {
            return ['success' => false, 'message' => 'Issue 不存在'];
        }
        
        // 只有作者可以删除
        if ($issue['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权删除此 Issue'];
        }
        
        $affected = Issue::delete($issueId, $user['id']);
        
        return $affected > 0
            ? ['success' => true, 'message' => 'Issue 已删除']
            : ['success' => false, 'message' => '删除失败'];
    }
}
