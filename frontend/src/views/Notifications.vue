<template>
  <div class="notification-center">
    <el-card>
      <template #header>
        <div class="notification-header">
          <h2>通知中心</h2>
          <div class="header-actions">
            <el-button
              v-if="unreadCount > 0"
              type="primary"
              link
              @click="markAllRead"
            >
              全部标记已读
            </el-button>
            <el-badge :value="unreadCount" :hidden="unreadCount === 0">
              <el-icon size="20"><Bell /></el-icon>
            </el-badge>
          </div>
        </div>
      </template>
      
      <!-- 通知类型筛选 -->
      <div class="notification-filters">
        <el-radio-group v-model="filterType" @change="fetchNotifications">
          <el-radio-button value="all">全部</el-radio-button>
          <el-radio-button value="unread">未读</el-radio-button>
          <el-radio-button value="mentions">提及</el-radio-button>
          <el-radio-button value="issues">Issues</el-radio-button>
          <el-radio-button value="pulls">Pull Requests</el-radio-button>
          <el-radio-button value="system">系统</el-radio-button>
        </el-radio-group>
      </div>
      
      <!-- 通知列表 -->
      <div v-loading="loading" class="notification-list">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          class="notification-item"
          :class="{ 'is-unread': !notification.is_read }"
          @click="handleNotificationClick(notification)"
        >
          <div class="notification-icon">
            <el-icon :size="24" :color="getIconColor(notification.type)">
              <component :is="getIcon(notification.type)" />
            </el-icon>
          </div>
          
          <div class="notification-content">
            <div class="notification-title">
              {{ notification.title }}
              <el-tag v-if="!notification.is_read" size="small" type="danger">
                未读
              </el-tag>
            </div>
            <div class="notification-body">{{ notification.body }}</div>
            <div class="notification-meta">
              <span class="source">{{ notification.source }}</span>
              <span class="time">{{ formatTime(notification.created_at) }}</span>
            </div>
          </div>
          
          <div class="notification-actions">
            <el-button
              type="primary"
              link
              size="small"
              @click.stop="markAsRead(notification)"
            >
              {{ notification.is_read ? '标记未读' : '标记已读' }}
            </el-button>
            <el-button
              type="danger"
              link
              size="small"
              @click.stop="deleteNotification(notification)"
            >
              删除
            </el-button>
          </div>
        </div>
        
        <el-empty
          v-if="!loading && notifications.length === 0"
          description="暂无通知"
        />
        
        <!-- 加载更多 -->
        <div v-if="hasMore" class="load-more">
          <el-button @click="loadMore" :loading="loadingMore">
            加载更多
          </el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Bell,
  ChatDotSquare,
  Document,
  Merge,
  Warning,
  InfoFilled,
  Star
} from '@element-plus/icons-vue'
import api from '@/api/index'

const router = useRouter()

const filterType = ref('all')
const loading = ref(false)
const loadingMore = ref(false)
const notifications = ref<any[]>([])
const page = ref(1)
const hasMore = ref(false)

const unreadCount = computed(() => {
  return notifications.value.filter(n => !n.is_read).length
})

function getIcon(type: string) {
  switch (type) {
    case 'issue': return Document
    case 'pull': return Merge
    case 'mention': return ChatDotSquare
    case 'system': return InfoFilled
    case 'star': return Star
    case 'warning': return Warning
    default: return Bell
  }
}

function getIconColor(type: string) {
  switch (type) {
    case 'issue': return '#409eff'
    case 'pull': return '#67c23a'
    case 'mention': return '#e6a23c'
    case 'system': return '#909399'
    case 'star': return '#f56c6c'
    case 'warning': return '#e6a23c'
    default: return '#909399'
  }
}

function formatTime(time: string) {
  const date = new Date(time)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  
  if (diff < 60000) return '刚刚'
  if (diff < 3600000) return `${Math.floor(diff / 60000)} 分钟前`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)} 小时前`
  if (diff < 2592000000) return `${Math.floor(diff / 86400000)} 天前`
  
  return date.toLocaleDateString('zh-CN')
}

async function fetchNotifications() {
  loading.value = true
  page.value = 1
  
  try {
    const res: any = await api.get('/notifications', {
      params: {
        type: filterType.value,
        page: page.value
      }
    })
    
    if (res.code === 200) {
      notifications.value = res.data.items || []
      hasMore.value = res.data.has_more || false
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
    const res: any = await api.get('/notifications', {
      params: {
        type: filterType.value,
        page: page.value
      }
    })
    
    if (res.code === 200) {
      notifications.value.push(...(res.data.items || []))
      hasMore.value = res.data.has_more || false
    }
  } catch (e) {
    console.error('Failed to load more:', e)
  } finally {
    loadingMore.value = false
  }
}

async function markAsRead(notification: any) {
  try {
    const res: any = await api.put(`/notifications/${notification.id}/read`, {
      is_read: !notification.is_read
    })
    
    if (res.code === 200) {
      notification.is_read = !notification.is_read
      ElMessage.success(notification.is_read ? '已标记为已读' : '已标记为未读')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function markAllRead() {
  try {
    await ElMessageBox.confirm('确定将所有通知标记为已读吗？', '确认', {
      type: 'warning'
    })
    
    const res: any = await api.put('/notifications/read-all')
    
    if (res.code === 200) {
      notifications.value.forEach(n => n.is_read = true)
      ElMessage.success('已全部标记为已读')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '操作失败')
    }
  }
}

async function deleteNotification(notification: any) {
  try {
    await ElMessageBox.confirm('确定删除此通知吗？', '删除确认', {
      type: 'warning'
    })
    
    const res: any = await api.delete(`/notifications/${notification.id}`)
    
    if (res.code === 200) {
      const index = notifications.value.findIndex(n => n.id === notification.id)
      if (index > -1) {
        notifications.value.splice(index, 1)
      }
      ElMessage.success('通知已删除')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function handleNotificationClick(notification: any) {
  // 标记为已读
  if (!notification.is_read) {
    markAsRead(notification)
  }
  
  // 跳转到相关页面
  if (notification.url) {
    router.push(notification.url)
  }
}

onMounted(() => {
  fetchNotifications()
})
</script>

<style scoped>
.notification-center {
  padding: 20px;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notification-header h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.notification-filters {
  margin-bottom: 20px;
}

.notification-list {
  min-height: 200px;
}

.notification-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.3s;
}

.notification-item:hover {
  background: #f5f7fa;
}

.notification-item.is-unread {
  background: #ecf5ff;
  border-color: #409eff;
}

.notification-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #f5f7fa;
  border-radius: 8px;
}

.notification-content {
  flex: 1;
}

.notification-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.notification-body {
  font-size: 14px;
  color: #606266;
  margin-bottom: 8px;
  line-height: 1.5;
}

.notification-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #909399;
}

.notification-actions {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.load-more {
  text-align: center;
  padding: 20px;
}
</style>
