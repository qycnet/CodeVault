<template>
  <div class="pull-detail">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <div class="title-section">
            <el-tag :type="getStatusType(pr.status)" size="large">
              {{ getStatusText(pr.status) }}
            </el-tag>
            <h2>{{ pr.title }}</h2>
            <span class="pr-number">#{{ pr.id }}</span>
          </div>
          <div class="actions">
            <el-button
              v-if="pr.status === 'open'"
              type="success"
              :loading="merging"
              @click="handleMerge"
            >
              合并 Pull Request
            </el-button>
            <el-button
              v-if="pr.status === 'open'"
              type="danger"
              @click="handleClose"
            >
              关闭
            </el-button>
            <el-button
              v-if="pr.status === 'closed'"
              type="primary"
              @click="handleReopen"
            >
              重新打开
            </el-button>
          </div>
        </div>
      </template>
      
      <div class="pr-info">
        <div class="info-row">
          <span class="label">作者：</span>
          <el-avatar :size="24" :src="pr.author_avatar" />
          <span class="value">{{ pr.author_name }}</span>
        </div>
        <div class="info-row">
          <span class="label">分支：</span>
          <span class="branch">{{ pr.source_branch }}</span>
          <el-icon><Right /></el-icon>
          <span class="branch">{{ pr.target_branch }}</span>
        </div>
        <div class="info-row">
          <span class="label">创建时间：</span>
          <span class="value">{{ pr.created_at }}</span>
        </div>
      </div>
      
      <el-divider />
      
      <div class="description">
        <h3>描述</h3>
        <div class="content" v-html="renderedDescription"></div>
      </div>
      
      <el-divider />
      
      <!-- 评论区域 -->
      <div class="comments-section">
        <h3>评论 ({{ comments.length }})</h3>
        
        <div class="comment-list">
          <div v-for="comment in comments" :key="comment.id" class="comment-item">
            <div class="comment-header">
              <el-avatar :size="32" :src="comment.author_avatar" />
              <span class="author">{{ comment.author_name }}</span>
              <span class="time">{{ comment.created_at }}</span>
            </div>
            <div class="comment-content">{{ comment.content }}</div>
          </div>
        </div>
        
        <el-empty v-if="comments.length === 0" description="暂无评论" />
        
        <!-- 发表评论 -->
        <div class="new-comment">
          <el-input
            v-model="newComment"
            type="textarea"
            :rows="4"
            placeholder="发表评论..."
          />
          <el-button
            type="primary"
            :loading="submittingComment"
            :disabled="!newComment.trim()"
            @click="submitComment"
          >
            发表评论
          </el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Right } from '@element-plus/icons-vue'
import DOMPurify from 'dompurify'
import api from '@/api/index'

const route = useRoute()
const router = useRouter()

const prId = computed(() => Number(route.params.id))
const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const loading = ref(false)
const merging = ref(false)
const submittingComment = ref(false)
const pr = ref<any>({})
const comments = ref<any[]>([])
const newComment = ref('')

const renderedDescription = computed(() => {
  // 使用 DOMPurify 进行 XSS 防护
  return DOMPurify.sanitize(pr.value.description || '暂无描述')
})

function getStatusType(status: string) {
  switch (status) {
    case 'open': return 'success'
    case 'merged': return 'primary'
    case 'closed': return 'info'
    default: return 'info'
  }
}

function getStatusText(status: string) {
  switch (status) {
    case 'open': return '开启'
    case 'merged': return '已合并'
    case 'closed': return '已关闭'
    default: return status
  }
}

async function fetchPR() {
  loading.value = true
  try {
    const res: any = await api.get(`/pull-requests/detail?id=${prId.value}`)
    if (res.code === 200) {
      pr.value = res.data
    }
  } catch (e) {
    console.error('Failed to fetch PR:', e)
  } finally {
    loading.value = false
  }
}

async function fetchComments() {
  try {
    const res: any = await api.get('/comments', {
      params: {
        parent_type: 'pull_request',
        parent_id: prId.value
      }
    })
    if (res.code === 200) {
      comments.value = res.data.items || []
    }
  } catch (e) {
    console.error('Failed to fetch comments:', e)
  }
}

async function handleMerge() {
  try {
    await ElMessageBox.confirm(
      '确定要合并此 Pull Request 吗？',
      '合并确认',
      { type: 'warning' }
    )
    
    merging.value = true
    const res: any = await api.post(`/pull-requests/merge?id=${prId.value}`)
    if (res.code === 200) {
      ElMessage.success('Pull Request 已合并')
      fetchPR()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '合并失败')
    }
  } finally {
    merging.value = false
  }
}

async function handleClose() {
  try {
    await ElMessageBox.confirm(
      '确定要关闭此 Pull Request 吗？',
      '关闭确认',
      { type: 'warning' }
    )
    
    const res: any = await api.post(`/pull-requests/close?id=${prId.value}`)
    if (res.code === 200) {
      ElMessage.success('Pull Request 已关闭')
      fetchPR()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '关闭失败')
    }
  }
}

async function handleReopen() {
  try {
    const res: any = await api.post(`/pull-requests/reopen?id=${prId.value}`)
    if (res.code === 200) {
      ElMessage.success('Pull Request 已重新打开')
      fetchPR()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function submitComment() {
  if (!newComment.value.trim()) return
  
  submittingComment.value = true
  try {
    const res: any = await api.post('/comments', {
      parent_type: 'pull_request',
      parent_id: prId.value,
      content: newComment.value
    })
    if (res.code === 200) {
      ElMessage.success('评论发表成功')
      newComment.value = ''
      fetchComments()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '评论失败')
  } finally {
    submittingComment.value = false
  }
}

onMounted(() => {
  fetchPR()
  fetchComments()
})
</script>

<style scoped>
.pull-detail {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-section {
  display: flex;
  align-items: center;
  gap: 12px;
}

.title-section h2 {
  margin: 0;
  font-size: 20px;
}

.pr-number {
  color: #909399;
  font-size: 14px;
}

.pr-info {
  margin-bottom: 20px;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.label {
  color: #606266;
  font-weight: 500;
}

.value {
  color: #303133;
}

.branch {
  font-family: monospace;
  background: #f5f7fa;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 13px;
}

.description h3,
.comments-section h3 {
  margin-bottom: 16px;
  font-size: 16px;
  color: #303133;
}

.description .content {
  color: #606266;
  line-height: 1.6;
}

.comment-list {
  margin-bottom: 24px;
}

.comment-item {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.comment-header .author {
  font-weight: 500;
  color: #303133;
}

.comment-header .time {
  color: #909399;
  font-size: 13px;
  margin-left: auto;
}

.comment-content {
  color: #606266;
  line-height: 1.6;
}

.new-comment {
  margin-top: 16px;
}

.new-comment .el-textarea {
  margin-bottom: 12px;
}
</style>
