<template>
  <div class="notifications-page">
    <div class="page-header">
      <h2>通知</h2>
      <div class="header-actions">
        <el-badge :value="unreadCount" :hidden="unreadCount === 0">
          <el-button @click="markAllRead" :disabled="unreadCount === 0">
            全部标记已读
          </el-button>
        </el-badge>
        <el-switch v-model="unreadOnly" active-text="仅未读" @change="fetchNotifications" />
      </div>
    </div>

    <div class="notifications-list">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="['notification-item', { unread: !notification.is_read }]"
      >
        <div class="notification-icon">
          <el-icon :size="24">
            <component :is="getIcon(notification.type)" />
          </el-icon>
        </div>
        
        <div class="notification-content">
          <div class="notification-title">{{ notification.title }}</div>
          <div class="notification-meta">
            <span v-if="notification.repo_name" class="repo-name">
              {{ notification.repo_name }}
            </span>
            <span v-if="notification.actor_name" class="actor-name">
              {{ notification.actor_name }}
            </span>
            <span class="time">{{ formatTime(notification.created_at) }}</span>
          </div>
        </div>
        
        <div class="notification-actions">
          <el-button size="small" @click="viewNotification(notification)">
            查看
          </el-button>
          <el-button size="small" type="danger" @click="deleteNotification(notification.id)">
            删除
          </el-button>
        </div>
      </div>
      
      <el-empty v-if="notifications.length === 0" description="暂无通知" />
    </div>

    <div class="pagination" v-if="total > perPage">
      <el-pagination
        v-model:current-page="page"
        :page-size="perPage"
        :total="total"
        layout="prev, pager, next"
        @current-change="fetchNotifications"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  ChatDotRound,
  Document,
  Merge,
  Close,
  User
} from '@element-plus/icons-vue'
import axios from 'axios'

const router = useRouter()

const notifications = ref<any[]>([])
const unreadCount = ref(0)
const page = ref(1)
const perPage = ref(30)
const total = ref(0)
const unreadOnly = ref(false)

function getIcon(type: string) {
  switch (type) {
    case 'issue': return Document
    case 'pull_request': return Merge
    case 'mention': return User
    default: return ChatDotRound
  }
}

function formatTime(time: string) {
  const date = new Date(time)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return '刚刚'
  if (minutes < 60) return `${minutes} 分钟前`
  if (hours < 24) return `${hours} 小时前`
  if (days < 7) return `${days} 天前`
  
  return date.toLocaleDateString('zh-CN')
}

async function fetchNotifications() {
  try {
    const response = await axios.get('/api/notifications', {
      params: {
        page: page.value,
        per_page: perPage.value,
        unread_only: unreadOnly.value
      }
    })
    
    if (response.data.success) {
      notifications.value = response.data.notifications
      unreadCount.value = response.data.unread_count
    }
  } catch (error) {
    ElMessage.error('获取通知失败')
  }
}

async function markAllRead() {
  try {
    await axios.post('/api/notifications/read')
    ElMessage.success('已全部标记为已读')
    fetchNotifications()
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

async function deleteNotification(id: number) {
  try {
    await axios.delete('/api/notifications', { params: { id } })
    fetchNotifications()
  } catch (error) {
    ElMessage.error('删除失败')
  }
}

function viewNotification(notification: any) {
  // 标记为已读
  axios.post('/api/notifications/read', { id: notification.id })
  
  // 跳转到相关页面
  if (notification.pr_id) {
    router.push(`/repos/${notification.repo_name}/pulls/${notification.pr_id}`)
  } else if (notification.issue_id) {
    router.push(`/repos/${notification.repo_name}/issues/${notification.issue_id}`)
  }
}

onMounted(fetchNotifications)
</script>

<style lang="scss" scoped>
.notifications-page {
  max-width: 900px;
  margin: 0 auto;
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  
  h2 {
    margin: 0;
  }
  
  .header-actions {
    display: flex;
    gap: 16px;
    align-items: center;
  }
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notification-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #fff;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  transition: background 0.2s;
  
  &.unread {
    background: #f0f7ff;
    border-color: #0969da;
  }
  
  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }
}

.notification-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f6f8fa;
  border-radius: 50%;
  color: #57606a;
}

.notification-content {
  flex: 1;
  
  .notification-title {
    font-size: 14px;
    font-weight: 500;
    color: #24292f;
    margin-bottom: 4px;
  }
  
  .notification-meta {
    font-size: 12px;
    color: #57606a;
    
    span {
      margin-right: 12px;
    }
    
    .repo-name {
      color: #0969da;
    }
  }
}

.notification-actions {
  display: flex;
  gap: 8px;
}

.pagination {
  display: flex;
  justify-content: center;
  margin-top: 24px;
}
</style>
