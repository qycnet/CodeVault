<template>
  <div class="comment-section">
    <div class="comment-header">
      <h3>{{ title }}</h3>
      <span class="count">{{ comments.length }} 条评论</span>
    </div>
    
    <!-- 评论列表 -->
    <div class="comment-list">
      <div
        v-for="comment in comments"
        :key="comment.id"
        class="comment-item"
        :class="{ 'is-inline': comment.line_number }"
      >
        <div class="comment-avatar">
          <el-avatar :size="32" :src="comment.author_avatar">
            {{ comment.author_name?.charAt(0).toUpperCase() }}
          </el-avatar>
        </div>
        
        <div class="comment-body">
          <div class="comment-meta">
            <span class="author">{{ comment.author_name }}</span>
            <span v-if="comment.line_number" class="line-info">
              行 {{ comment.line_number }}
            </span>
            <span class="time">{{ formatTime(comment.created_at) }}</span>
            <span v-if="comment.updated_at" class="edited">(已编辑)</span>
          </div>
          
          <div class="comment-content" v-html="renderContent(comment.content)"></div>
          
          <div class="comment-actions">
            <el-button type="primary" link size="small" @click="replyTo(comment)">
              回复
            </el-button>
            <el-button
              v-if="canEdit(comment)"
              type="primary"
              link
              size="small"
              @click="editComment(comment)"
            >
              编辑
            </el-button>
            <el-button
              v-if="canDelete(comment)"
              type="danger"
              link
              size="small"
              @click="deleteComment(comment)"
            >
              删除
            </el-button>
          </div>
        </div>
      </div>
    </div>
    
    <el-empty v-if="comments.length === 0 && !loading" description="暂无评论" />
    
    <!-- 发表评论 -->
    <div class="comment-form">
      <el-avatar :size="32" class="form-avatar">
        {{ currentUser?.username?.charAt(0).toUpperCase() }}
      </el-avatar>
      
      <div class="form-input">
        <el-input
          v-model="newComment"
          type="textarea"
          :rows="4"
          :placeholder="placeholder"
          :disabled="submitting"
        />
        
        <div class="form-actions">
          <el-button
            type="primary"
            :loading="submitting"
            :disabled="!newComment.trim()"
            @click="submitComment"
          >
            发表评论
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useUserStore } from '@/stores/user'
import DOMPurify from 'dompurify'
import api from '@/api/index'

interface Props {
  parentType: 'issue' | 'pull_request'
  parentId: number
  title?: string
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '评论',
  placeholder: '发表评论...'
})

const emit = defineEmits(['commented', 'deleted'])

const userStore = useUserStore()
const currentUser = computed(() => userStore.user)

const loading = ref(false)
const submitting = ref(false)
const comments = ref<any[]>([])
const newComment = ref('')

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

function renderContent(content: string) {
  // 简单的 Markdown 渲染（实际项目应使用 marked 等库）
  const html = content
    .replace(/\n/g, '<br>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
  
  // 使用 DOMPurify 进行 XSS 防护
  return DOMPurify.sanitize(html)
}

function canEdit(comment: any) {
  return comment.author_id === currentUser.value?.id
}

function canDelete(comment: any) {
  return comment.author_id === currentUser.value?.id
}

async function fetchComments() {
  loading.value = true
  try {
    const res: any = await api.get('/comments', {
      params: {
        parent_type: props.parentType,
        parent_id: props.parentId
      }
    })
    if (res.code === 200) {
      comments.value = res.data.items || []
    }
  } catch (e) {
    console.error('Failed to fetch comments:', e)
  } finally {
    loading.value = false
  }
}

async function submitComment() {
  if (!newComment.value.trim()) return
  
  submitting.value = true
  try {
    const res: any = await api.post('/comments', {
      parent_type: props.parentType,
      parent_id: props.parentId,
      content: newComment.value
    })
    if (res.code === 200) {
      ElMessage.success('评论发表成功')
      newComment.value = ''
      fetchComments()
      emit('commented')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '评论失败')
  } finally {
    submitting.value = false
  }
}

function replyTo(comment: any) {
  newComment.value = `@${comment.author_name} `
}

function editComment(comment: any) {
  ElMessageBox.prompt('编辑评论', '编辑', {
    confirmButtonText: '保存',
    cancelButtonText: '取消',
    inputValue: comment.content,
    inputType: 'textarea'
  }).then(async ({ value }) => {
    try {
      const res: any = await api.put(`/comments?id=${comment.id}`, {
        content: value
      })
      if (res.code === 200) {
        ElMessage.success('评论已更新')
        fetchComments()
      }
    } catch (e: any) {
      ElMessage.error(e.message || '更新失败')
    }
  }).catch(() => {})
}

async function deleteComment(comment: any) {
  try {
    await ElMessageBox.confirm('确定要删除此评论吗？', '删除确认', {
      type: 'warning'
    })
    
    const res: any = await api.delete(`/comments?id=${comment.id}`)
    if (res.code === 200) {
      ElMessage.success('评论已删除')
      fetchComments()
      emit('deleted')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

onMounted(() => {
  fetchComments()
})

defineExpose({
  fetchComments
})
</script>

<style scoped>
.comment-section {
  margin-top: 24px;
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.comment-header h3 {
  margin: 0;
  font-size: 16px;
  color: #303133;
}

.comment-header .count {
  color: #909399;
  font-size: 14px;
}

.comment-list {
  margin-bottom: 24px;
}

.comment-item {
  display: flex;
  gap: 12px;
  padding: 16px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 12px;
}

.comment-item.is-inline {
  background: #f5f7fa;
}

.comment-avatar {
  flex-shrink: 0;
}

.comment-body {
  flex: 1;
}

.comment-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.comment-meta .author {
  font-weight: 500;
  color: #303133;
}

.comment-meta .line-info {
  font-size: 12px;
  color: #409eff;
  background: #ecf5ff;
  padding: 2px 8px;
  border-radius: 4px;
}

.comment-meta .time {
  color: #909399;
  font-size: 13px;
}

.comment-meta .edited {
  color: #909399;
  font-size: 12px;
}

.comment-content {
  color: #606266;
  line-height: 1.6;
  margin-bottom: 8px;
}

.comment-content :deep(code) {
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
}

.comment-actions {
  display: flex;
  gap: 8px;
}

.comment-form {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 8px;
}

.form-avatar {
  flex-shrink: 0;
}

.form-input {
  flex: 1;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}
</style>
