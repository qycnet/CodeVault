<template>
  <div class="notification-center">
    <!-- 头部 -->
    <div class="notification-header">
      <h2>通知中心</h2>
      <div class="header-actions">
        <el-badge :value="unreadCount" :hidden="unreadCount === 0" class="badge-item">
          <el-button @click="markAllRead" :disabled="unreadCount === 0">全部已读</el-button>
        </el-badge>
        <el-select v-model="filterType" placeholder="筛选类型" clearable style="width: 150px;">
          <el-option v-for="(label, type) in notificationTypes" :key="type" :label="label" :value="type" />
        </el-select>
      </div>
    </div>
    
    <!-- 通知分组 -->
    <div class="notification-groups">
      <!-- 今天 -->
      <div class="notification-group" v-if="todayNotifications.length > 0">
        <h3 class="group-title">今天</h3>
        <div class="notification-list">
          <div
            v-for="notification in todayNotifications"
            :key="notification.id"
            class="notification-item"
            :class="{ unread: !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <div class="notification-icon" :class="notification.type">
              <el-icon v-if="notification.type.includes('issue')"><Document /></el-icon>
              <el-icon v-else-if="notification.type.includes('pr')"><Merge /></el-icon>
              <el-icon v-else-if="notification.type.includes('workflow')"><Cpu /></el-icon>
              <el-icon v-else-if="notification.type.includes('security')"><Warning /></el-icon>
              <el-icon v-else><Bell /></el-icon>
            </div>
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-meta">
                <span class="repo-name" v-if="notification.repo_name">
                  {{ notification.repo_owner }}/{{ notification.repo_name }}
                </span>
                <span class="time">{{ formatTime(notification.created_at) }}</span>
              </div>
            </div>
            <div class="notification-actions">
              <el-button v-if="!notification.read_at" size="small" @click.stop="markRead(notification.id)">
                标记已读
              </el-button>
              <el-button size="small" type="danger" @click.stop="deleteNotification(notification.id)">
                删除
              </el-button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- 昨天 -->
      <div class="notification-group" v-if="yesterdayNotifications.length > 0">
        <h3 class="group-title">昨天</h3>
        <div class="notification-list">
          <div
            v-for="notification in yesterdayNotifications"
            :key="notification.id"
            class="notification-item"
            :class="{ unread: !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <div class="notification-icon" :class="notification.type">
              <el-icon v-if="notification.type.includes('issue')"><Document /></el-icon>
              <el-icon v-else-if="notification.type.includes('pr')"><Merge /></el-icon>
              <el-icon v-else-if="notification.type.includes('workflow')"><Cpu /></el-icon>
              <el-icon v-else-if="notification.type.includes('security')"><Warning /></el-icon>
              <el-icon v-else><Bell /></el-icon>
            </div>
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-meta">
                <span class="repo-name" v-if="notification.repo_name">
                  {{ notification.repo_owner }}/{{ notification.repo_name }}
                </span>
                <span class="time">{{ formatTime(notification.created_at) }}</span>
              </div>
            </div>
            <div class="notification-actions">
              <el-button v-if="!notification.read_at" size="small" @click.stop="markRead(notification.id)">
                标记已读
              </el-button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- 更早 -->
      <div class="notification-group" v-if="olderNotifications.length > 0">
        <h3 class="group-title">更早</h3>
        <div class="notification-list">
          <div
            v-for="notification in olderNotifications"
            :key="notification.id"
            class="notification-item"
            :class="{ unread: !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <div class="notification-icon" :class="notification.type">
              <el-icon v-if="notification.type.includes('issue')"><Document /></el-icon>
              <el-icon v-else-if="notification.type.includes('pr')"><Merge /></el-icon>
              <el-icon v-else-if="notification.type.includes('workflow')"><Cpu /></el-icon>
              <el-icon v-else-if="notification.type.includes('security')"><Warning /></el-icon>
              <el-icon v-else><Bell /></el-icon>
            </div>
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-meta">
                <span class="repo-name" v-if="notification.repo_name">
                  {{ notification.repo_owner }}/{{ notification.repo_name }}
                </span>
                <span class="time">{{ formatTime(notification.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="load-more" v-if="hasMore">
          <el-button @click="loadMore" :loading="loading">加载更多</el-button>
        </div>
      </div>
      
      <!-- 空状态 -->
      <el-empty v-if="notifications.length === 0 && !loading" description="暂无通知" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Document, Merge, Cpu, Warning, Bell } from '@element-plus/icons-vue'

const router = useRouter()

interface Notification {
  id: number
  type: string
  title: string
  content: any
  repo_id: number | null
  repo_name: string | null
  repo_owner: string | null
  entity_type: string | null
  entity_id: number | null
  read_at: string | null
  created_at: string
}

const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const filterType = ref('')
const loading = ref(false)
const hasMore = ref(true)
const offset = ref(0)

const notificationTypes: Record<string, string> = {
  issue_created: 'Issue 创建',
  issue_closed: 'Issue 关闭',
  issue_assigned: 'Issue 分配',
  issue_mentioned: 'Issue 提及',
  pr_created: 'PR 创建',
  pr_merged: 'PR 合并',
  pr_closed: 'PR 关闭',
  pr_reviewed: 'PR 审查',
  pr_commented: 'PR 评论',
  push: '代码推送',
  release: 'Release 发布',
  workflow_success: '工作流成功',
  workflow_failed: '工作流失败',
  security_alert: '安全告警',
}

// WebSocket 连接
let ws: WebSocket | null = null

// 分组通知
const todayNotifications = computed(() => {
  const today = new Date().toDateString()
  return notifications.value.filter(n => new Date(n.created_at).toDateString() === today)
})

const yesterdayNotifications = computed(() => {
  const yesterday = new Date(Date.now() - 86400000).toDateString()
  return notifications.value.filter(n => new Date(n.created_at).toDateString() === yesterday)
})

const olderNotifications = computed(() => {
  const today = new Date().toDateString()
  const yesterday = new Date(Date.now() - 86400000).toDateString()
  return notifications.value.filter(n => {
    const date = new Date(n.created_at).toDateString()
    return date !== today && date !== yesterday
  })
})

// 加载通知
async function loadNotifications() {
  loading.value = true
  
  try {
    const params = new URLSearchParams({
      limit: '50',
      offset: offset.value.toString(),
    })
    
    if (filterType.value) {
      params.append('type', filterType.value)
    }
    
    const response = await fetch(`/api/notifications?${params}`)
    const result = await response.json()
    
    if (result.success) {
      if (offset.value === 0) {
        notifications.value = result.notifications
      } else {
        notifications.value.push(...result.notifications)
      }
      hasMore.value = result.notifications.length === 50
    }
  } catch (error) {
    ElMessage.error('加载通知失败')
  } finally {
    loading.value = false
  }
}

// 加载未读数
async function loadUnreadCount() {
  try {
    const response = await fetch('/api/notifications/unread-count')
    const result = await response.json()
    
    if (result.success) {
      unreadCount.value = result.count
    }
  } catch (error) {
    console.error('加载未读数失败', error)
  }
}

// 加载更多
function loadMore() {
  offset.value += 50
  loadNotifications()
}

// 标记已读
async function markRead(id: number) {
  try {
    const response = await fetch(`/api/notifications/${id}/read`, { method: 'POST' })
    const result = await response.json()
    
    if (result.success) {
      const notification = notifications.value.find(n => n.id === id)
      if (notification) {
        notification.read_at = new Date().toISOString()
      }
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

// 全部标记已读
async function markAllRead() {
  try {
    const response = await fetch('/api/notifications/read-all', { method: 'POST' })
    const result = await response.json()
    
    if (result.success) {
      notifications.value.forEach(n => {
        if (!n.read_at) {
          n.read_at = new Date().toISOString()
        }
      })
      unreadCount.value = 0
      ElMessage.success('已全部标记为已读')
    }
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

// 删除通知
async function deleteNotification(id: number) {
  try {
    const response = await fetch(`/api/notifications/${id}`, { method: 'DELETE' })
    const result = await response.json()
    
    if (result.success) {
      const index = notifications.value.findIndex(n => n.id === id)
      if (index !== -1) {
        const notification = notifications.value[index]
        if (!notification.read_at) {
          unreadCount.value = Math.max(0, unreadCount.value - 1)
        }
        notifications.value.splice(index, 1)
      }
    }
  } catch (error) {
    ElMessage.error('删除失败')
  }
}

// 点击通知
function handleNotificationClick(notification: Notification) {
  // 标记已读
  if (!notification.read_at) {
    markRead(notification.id)
  }
  
  // 跳转到相关页面
  if (notification.repo_name && notification.repo_owner) {
    const repoPath = `/${notification.repo_owner}/${notification.repo_name}`
    
    if (notification.entity_type === 'issue') {
      router.push(`${repoPath}/issues/${notification.content?.issue_number || notification.entity_id}`)
    } else if (notification.entity_type === 'pull_request') {
      router.push(`${repoPath}/pull/${notification.content?.pr_number || notification.entity_id}`)
    } else if (notification.entity_type === 'workflow_run') {
      router.push(`${repoPath}/actions/runs/${notification.entity_id}`)
    } else {
      router.push(repoPath)
    }
  }
}

// 格式化时间
function formatTime(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return '刚刚'
  if (minutes < 60) return `${minutes} 分钟前`
  if (hours < 24) return `${hours} 小时前`
  if (days < 7) return `${days} 天前`
  
  return date.toLocaleDateString()
}

// 连接 WebSocket
function connectWebSocket() {
  const wsUrl = (import.meta.env.VITE_WS_URL || 'ws://localhost:8081').replace('http', 'ws')
  
  ws = new WebSocket(wsUrl)
  
  ws.onopen = () => {
    console.log('WebSocket connected')
  }
  
  ws.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data)
      
      if (data.type === 'notification') {
        // 新通知
        notifications.value.unshift(data.notification)
        unreadCount.value++
        
        // 显示桌面通知
        if (Notification.permission === 'granted') {
          new window.Notification(data.notification.title, {
            body: data.notification.content?.repo || '',
            icon: '/logo.png',
          })
        }
      }
    } catch (error) {
      console.error('WebSocket message parse error', error)
    }
  }
  
  ws.onerror = (error) => {
    console.error('WebSocket error', error)
  }
  
  ws.onclose = () => {
    // 重连
    setTimeout(connectWebSocket, 5000)
  }
}

// 请求桌面通知权限
async function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    await Notification.requestPermission()
  }
}

// 监听筛选变化
watch(filterType, () => {
  offset.value = 0
  loadNotifications()
})

onMounted(() => {
  loadNotifications()
  loadUnreadCount()
  connectWebSocket()
  requestNotificationPermission()
})

onUnmounted(() => {
  if (ws) {
    ws.close()
  }
})
</script>

<style scoped>
.notification-center {
  max-width: 800px;
  margin: 0 auto;
  padding: 24px;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.notification-header h2 {
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.notification-groups {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.notification-group {
  background: var(--el-bg-color);
  border-radius: 8px;
  overflow: hidden;
}

.group-title {
  padding: 12px 16px;
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--el-text-color-secondary);
  background: var(--el-fill-color-light);
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.notification-list {
  display: flex;
  flex-direction: column;
}

.notification-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  cursor: pointer;
  transition: background 0.2s;
}

.notification-item:hover {
  background: var(--el-fill-color-light);
}

.notification-item.unread {
  background: var(--el-color-primary-light-9);
}

.notification-item.unread:hover {
  background: var(--el-color-primary-light-8);
}

.notification-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--el-fill-color);
  color: var(--el-text-color-secondary);
}

.notification-icon.issue_created,
.notification-icon.issue_closed,
.notification-icon.issue_assigned,
.notification-icon.issue_mentioned {
  background: var(--el-color-success-light-9);
  color: var(--el-color-success);
}

.notification-icon.pr_created,
.notification-icon.pr_merged,
.notification-icon.pr_reviewed {
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
}

.notification-icon.workflow_failed,
.notification-icon.security_alert {
  background: var(--el-color-danger-light-9);
  color: var(--el-color-danger);
}

.notification-icon.workflow_success {
  background: var(--el-color-success-light-9);
  color: var(--el-color-success);
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-weight: 500;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notification-meta {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.repo-name {
  color: var(--el-color-primary);
}

.notification-actions {
  display: flex;
  gap: 8px;
}

.load-more {
  padding: 16px;
  text-align: center;
}
</style>
