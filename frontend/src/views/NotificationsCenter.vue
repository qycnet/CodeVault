<template>
  <div class="notifications-center">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>通知中心</h2>
          <div class="header-actions">
            <el-button @click="markAllRead" :disabled="unreadCount === 0">
              全部已读
            </el-button>
            <el-button @click="showSettingsDialog = true">
              <el-icon><Setting /></el-icon>
              设置
            </el-button>
          </div>
        </div>
      </template>
      
      <!-- 筛选器 -->
      <div class="filters">
        <el-radio-group v-model="filter" @change="fetchNotifications">
          <el-radio-button value="all">全部</el-radio-button>
          <el-radio-button value="unread">
            未读
            <el-badge v-if="unreadCount > 0" :value="unreadCount" class="filter-badge" />
          </el-radio-button>
          <el-radio-button value="mentioned">@提及</el-radio-button>
        </el-radio-group>
        
        <el-select v-model="typeFilter" placeholder="类型" clearable @change="fetchNotifications">
          <el-option value="issue" label="Issue" />
          <el-option value="pr" label="Pull Request" />
          <el-option value="commit" label="Commit" />
          <el-option value="release" label="Release" />
          <el-option value="security" label="安全" />
          <el-option value="workflow" label="工作流" />
        </el-select>
      </div>
      
      <!-- 通知列表 -->
      <div v-loading="loading" class="notifications-list">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          class="notification-item"
          :class="{ 'is-unread': !notification.read_at }"
          @click="handleNotificationClick(notification)"
        >
          <div class="notification-icon">
            <el-icon :size="24" :class="`icon-${notification.type}`">
              <component :is="getIcon(notification.type)" />
            </el-icon>
          </div>
          
          <div class="notification-content">
            <div class="notification-title">
              <span class="repo-name">{{ notification.repo_name }}</span>
              <span class="notification-type">{{ getTypeText(notification.type) }}</span>
            </div>
            
            <div class="notification-body">{{ notification.message }}</div>
            
            <div class="notification-meta">
              <span class="time">{{ formatTime(notification.created_at) }}</span>
              <span v-if="notification.reason" class="reason">
                {{ getReasonText(notification.reason) }}
              </span>
            </div>
          </div>
          
          <div class="notification-actions">
            <el-button
              v-if="!notification.read_at"
              type="primary"
              link
              @click.stop="markRead(notification)"
            >
              标记已读
            </el-button>
            <el-button
              type="danger"
              link
              @click.stop="unsubscribe(notification)"
            >
              取消订阅
            </el-button>
          </div>
        </div>
        
        <el-empty v-if="!loading && notifications.length === 0" description="暂无通知" />
        
        <div v-if="hasMore" class="load-more">
          <el-button @click="loadMore" :loading="loadingMore">
            加载更多
          </el-button>
        </div>
      </div>
    </el-card>
    
    <!-- 通知设置对话框 -->
    <el-dialog v-model="showSettingsDialog" title="通知设置" width="600px">
      <el-tabs v-model="settingsTab">
        <el-tab-pane label="通知类型" name="types">
          <el-form label-width="140px">
            <el-form-item label="Issue 通知">
              <el-switch v-model="settings.issue_notifications" />
            </el-form-item>
            <el-form-item label="Pull Request 通知">
              <el-switch v-model="settings.pr_notifications" />
            </el-form-item>
            <el-form-item label="Commit 通知">
              <el-switch v-model="settings.commit_notifications" />
            </el-form-item>
            <el-form-item label="Release 通知">
              <el-switch v-model="settings.release_notifications" />
            </el-form-item>
            <el-form-item label="安全警报">
              <el-switch v-model="settings.security_notifications" />
            </el-form-item>
            <el-form-item label="工作流通知">
              <el-switch v-model="settings.workflow_notifications" />
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="通知渠道" name="channels">
          <el-form label-width="140px">
            <el-form-item label="站内通知">
              <el-switch v-model="settings.web_notifications" />
            </el-form-item>
            <el-form-item label="邮件通知">
              <el-switch v-model="settings.email_notifications" />
            </el-form-item>
            <el-form-item label="邮件地址">
              <el-input v-model="settings.email" placeholder="your@email.com" />
            </el-form-item>
            <el-form-item label="即时推送">
              <el-switch v-model="settings.instant_push" />
              <div class="setting-desc">启用后将通过 WebSocket 实时推送通知</div>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="订阅管理" name="subscriptions">
          <div class="subscriptions-list">
            <div v-for="sub in subscriptions" :key="sub.id" class="subscription-item">
              <div class="sub-info">
                <span class="sub-repo">{{ sub.repo_name }}</span>
                <span class="sub-reason">{{ getReasonText(sub.reason) }}</span>
              </div>
              <el-button type="danger" link @click="removeSubscription(sub)">
                取消订阅
              </el-button>
            </div>
            
            <el-empty v-if="subscriptions.length === 0" description="暂无订阅" />
          </div>
        </el-tab-pane>
      </el-tabs>
      
      <template #footer>
        <el-button @click="showSettingsDialog = false">取消</el-button>
        <el-button type="primary" @click="saveSettings" :loading="savingSettings">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  Setting, ChatDotRound, Document, Merge, Upload, Lock, Timer
} from '@element-plus/icons-vue'
import api from '@/api/index'

const router = useRouter()

const loading = ref(false)
const loadingMore = ref(false)
const filter = ref('all')
const typeFilter = ref('')
const showSettingsDialog = ref(false)
const settingsTab = ref('types')
const savingSettings = ref(false)

const notifications = ref<any[]>([])
const subscriptions = ref<any[]>([])
const unreadCount = ref(0)
const hasMore = ref(false)
const page = ref(1)

const settings = reactive({
  issue_notifications: true,
  pr_notifications: true,
  commit_notifications: false,
  release_notifications: true,
  security_notifications: true,
  workflow_notifications: true,
  web_notifications: true,
  email_notifications: false,
  email: '',
  instant_push: true
})

let ws: WebSocket | null = null

const iconMap: Record<string, any> = {
  issue: ChatDotRound,
  pr: Merge,
  commit: Upload,
  release: Document,
  security: Lock,
  workflow: Timer
}

const typeTexts: Record<string, string> = {
  issue: 'Issue',
  pr: 'Pull Request',
  commit: 'Commit',
  release: 'Release',
  security: '安全警报',
  workflow: '工作流'
}

const reasonTexts: Record<string, string> = {
  comment: '评论了',
  mention: '提及了你',
  author: '你是作者',
  assign: '分配给你',
  review_requested: '请求你审查',
  subscribed: '你订阅了',
  team_mention: '提及了你的团队'
}

function getIcon(type: string) {
  return iconMap[type] || Document
}

function getTypeText(type: string) {
  return typeTexts[type] || type
}

function getReasonText(reason: string) {
  return reasonTexts[reason] || reason
}

function formatTime(date: string) {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  
  if (diff < 60000) return '刚刚'
  if (diff < 3600000) return `${Math.floor(diff / 60000)} 分钟前`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)} 小时前`
  if (diff < 604800000) return `${Math.floor(diff / 86400000)} 天前`
  
  return d.toLocaleDateString('zh-CN')
}

async function fetchNotifications() {
  loading.value = true
  page.value = 1
  
  try {
    const params: any = {
      page: page.value,
      per_page: 20
    }
    
    if (filter.value === 'unread') {
      params.unread = true
    } else if (filter.value === 'mentioned') {
      params.mentioned = true
    }
    
    if (typeFilter.value) {
      params.type = typeFilter.value
    }
    
    const res: any = await api.get('/notifications', { params })
    
    if (res.code === 200) {
      notifications.value = res.data || []
      hasMore.value = res.pagination?.has_more || false
      unreadCount.value = res.unread_count || 0
    }
  } catch (e) {
    console.error('Failed to fetch notifications:', e)
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  loadingMore.value = true
  page.value++
  
  try {
    const params: any = {
      page: page.value,
      per_page: 20
    }
    
    if (filter.value === 'unread') {
      params.unread = true
    }
    
    const res: any = await api.get('/notifications', { params })
    
    if (res.code === 200) {
      notifications.value.push(...(res.data || []))
      hasMore.value = res.pagination?.has_more || false
    }
  } catch (e) {
    console.error('Failed to load more:', e)
  } finally {
    loadingMore.value = false
  }
}

async function markRead(notification: any) {
  try {
    const res: any = await api.put(`/notifications/${notification.id}/read`)
    if (res.code === 200) {
      notification.read_at = new Date().toISOString()
      unreadCount.value--
    }
  } catch (e) {
    console.error('Failed to mark read:', e)
  }
}

async function markAllRead() {
  try {
    const res: any = await api.put('/notifications/read-all')
    if (res.code === 200) {
      notifications.value.forEach(n => {
        if (!n.read_at) {
          n.read_at = new Date().toISOString()
        }
      })
      unreadCount.value = 0
      ElMessage.success('已全部标记为已读')
    }
  } catch (e) {
    console.error('Failed to mark all read:', e)
  }
}

async function unsubscribe(notification: any) {
  try {
    const res: any = await api.delete(`/notifications/${notification.id}/subscription`)
    if (res.code === 200) {
      ElMessage.success('已取消订阅')
    }
  } catch (e) {
    console.error('Failed to unsubscribe:', e)
  }
}

function handleNotificationClick(notification: any) {
  // 标记已读
  if (!notification.read_at) {
    markRead(notification)
  }
  
  // 跳转到相关页面
  if (notification.url) {
    router.push(notification.url)
  }
}

async function fetchSettings() {
  try {
    const res: any = await api.get('/user/notification-settings')
    if (res.code === 200) {
      Object.assign(settings, res.data)
    }
  } catch (e) {
    console.error('Failed to fetch settings:', e)
  }
}

async function fetchSubscriptions() {
  try {
    const res: any = await api.get('/user/subscriptions')
    if (res.code === 200) {
      subscriptions.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch subscriptions:', e)
  }
}

async function saveSettings() {
  savingSettings.value = true
  try {
    const res: any = await api.put('/user/notification-settings', settings)
    if (res.code === 200) {
      ElMessage.success('设置已保存')
      showSettingsDialog.value = false
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    savingSettings.value = false
  }
}

async function removeSubscription(sub: any) {
  try {
    const res: any = await api.delete(`/user/subscriptions/${sub.id}`)
    if (res.code === 200) {
      subscriptions.value = subscriptions.value.filter(s => s.id !== sub.id)
      ElMessage.success('已取消订阅')
    }
  } catch (e) {
    console.error('Failed to remove subscription:', e)
  }
}

function connectWebSocket() {
  if (!settings.instant_push) return
  
  const wsUrl = `wss://api.codevault.io/notifications/stream`
  
  try {
    ws = new WebSocket(wsUrl)
    
    ws.onmessage = (event) => {
      const notification = JSON.parse(event.data)
      
      // 添加到列表顶部
      notifications.value.unshift(notification)
      unreadCount.value++
      
      // 显示桌面通知
      if (Notification.permission === 'granted') {
        new Notification(notification.title, {
          body: notification.message,
          icon: '/logo.png'
        })
      }
    }
    
    ws.onerror = (error) => {
      console.error('WebSocket error:', error)
    }
  } catch (e) {
    console.error('WebSocket connection failed:', e)
  }
}

function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission()
  }
}

onMounted(() => {
  fetchNotifications()
  fetchSettings()
  fetchSubscriptions()
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
.notifications-center {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.filters {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}

.filter-badge {
  margin-left: 4px;
}

.notifications-list {
  min-height: 400px;
}

.notification-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #e4e7ed;
  cursor: pointer;
  transition: background 0.3s;
}

.notification-item:hover {
  background: #f5f7fa;
}

.notification-item.is-unread {
  background: #ecf5ff;
}

.notification-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
  border-radius: 50%;
}

.icon-issue { color: #409eff; }
.icon-pr { color: #67c23a; }
.icon-commit { color: #909399; }
.icon-release { color: #e6a23c; }
.icon-security { color: #f56c6c; }
.icon-workflow { color: #909399; }

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title {
  display: flex;
  gap: 8px;
  margin-bottom: 4px;
}

.repo-name {
  font-weight: 500;
  color: #303133;
}

.notification-type {
  font-size: 12px;
  color: #909399;
}

.notification-body {
  color: #606266;
  margin-bottom: 8px;
  word-break: break-word;
}

.notification-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #909399;
}

.notification-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.load-more {
  text-align: center;
  padding: 20px;
}

.setting-desc {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.subscriptions-list {
  max-height: 400px;
  overflow-y: auto;
}

.subscription-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid #e4e7ed;
}

.sub-info {
  display: flex;
  gap: 12px;
}

.sub-repo {
  font-weight: 500;
  color: #303133;
}

.sub-reason {
  font-size: 13px;
  color: #909399;
}
</style>
