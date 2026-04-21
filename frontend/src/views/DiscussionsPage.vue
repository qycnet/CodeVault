<template>
  <div class="discussions-page">
    <!-- 头部 -->
    <div class="page-header">
      <div class="header-left">
        <h1>
          <span class="emoji">💬</span>
          Discussions
        </h1>
        <p class="subtitle">社区讨论和问答</p>
      </div>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新建讨论
      </el-button>
    </div>

    <!-- 分类标签 -->
    <div class="categories-bar">
      <div
        v-for="cat in categories"
        :key="cat.id"
        :class="['category-tab', { active: selectedCategory === cat.slug }]"
        :style="{ borderColor: cat.color }"
        @click="selectedCategory = cat.slug"
      >
        <span class="emoji">{{ cat.emoji }}</span>
        <span class="name">{{ cat.name }}</span>
      </div>
    </div>

    <!-- 筛选和排序 -->
    <div class="filters-bar">
      <el-input
        v-model="searchQuery"
        placeholder="搜索讨论..."
        prefix-icon="Search"
        clearable
        @keyup.enter="loadDiscussions"
      />
      
      <el-select v-model="sortBy" placeholder="排序" @change="loadDiscussions">
        <el-option label="最新" value="created" />
        <el-option label="最多投票" value="votes" />
        <el-option label="最多回复" value="replies" />
        <el-option label="最近更新" value="updated" />
      </el-select>
      
      <el-select v-model="statusFilter" placeholder="状态" clearable @change="loadDiscussions">
        <el-option label="开放" value="open" />
        <el-option label="已回答" value="answered" />
        <el-option label="已关闭" value="closed" />
      </el-select>
    </div>

    <!-- 讨论列表 -->
    <div class="discussions-list" v-loading="loading">
      <div
        v-for="discussion in discussions"
        :key="discussion.id"
        class="discussion-item"
        @click="viewDiscussion(discussion.id)"
      >
        <!-- 投票 -->
        <div class="vote-section">
          <el-button
            :type="discussion.user_vote === 'up' ? 'primary' : 'default'"
            size="small"
            circle
            @click.stop="vote('discussion', discussion.id, 'up')"
          >
            <el-icon><CaretTop /></el-icon>
          </el-button>
          <span class="vote-count">{{ discussion.vote_count }}</span>
          <el-button
            :type="discussion.user_vote === 'down' ? 'danger' : 'default'"
            size="small"
            circle
            @click.stop="vote('discussion', discussion.id, 'down')"
          >
            <el-icon><CaretBottom /></el-icon>
          </el-button>
        </div>

        <!-- 内容 -->
        <div class="content-section">
          <div class="title-row">
            <span v-if="discussion.is_pinned" class="pin-badge">📌 置顶</span>
            <span v-if="discussion.is_locked" class="lock-badge">🔒 已锁定</span>
            <span v-if="discussion.status === 'answered'" class="answered-badge">✅ 已回答</span>
            <h3 class="title">{{ discussion.title }}</h3>
          </div>
          
          <div class="meta-row">
            <span
              class="category-badge"
              :style="{ backgroundColor: discussion.category_color + '20', color: discussion.category_color }"
            >
              {{ discussion.category_emoji }} {{ discussion.category_name }}
            </span>
            
            <span class="author">
              <el-avatar :size="20" :src="discussion.avatar_url" />
              {{ discussion.username }}
            </span>
            
            <span class="time">{{ formatTime(discussion.created_at) }}</span>
          </div>

          <div class="stats-row">
            <span><el-icon><ChatDotRound /></el-icon> {{ discussion.reply_count }} 回复</span>
            <span><el-icon><View /></el-icon> {{ discussion.view_count }} 浏览</span>
          </div>
        </div>
      </div>

      <el-empty v-if="!loading && discussions.length === 0" description="暂无讨论" />
    </div>

    <!-- 分页 -->
    <div class="pagination" v-if="totalPages > 1">
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="perPage"
        :total="total"
        layout="prev, pager, next"
        @current-change="loadDiscussions"
      />
    </div>

    <!-- 创建讨论对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      title="新建讨论"
      width="700px"
      :close-on-click-modal="false"
    >
      <el-form :model="newDiscussion" label-width="80px">
        <el-form-item label="标题" required>
          <el-input
            v-model="newDiscussion.title"
            placeholder="讨论标题（至少5个字符）"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>

        <el-form-item label="分类" required>
          <el-select v-model="newDiscussion.category_id" placeholder="选择分类">
            <el-option
              v-for="cat in categories"
              :key="cat.id"
              :label="cat.emoji + ' ' + cat.name"
              :value="cat.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="内容" required>
          <el-input
            v-model="newDiscussion.body"
            type="textarea"
            :rows="10"
            placeholder="支持 Markdown 格式..."
          />
        </el-form-item>

        <el-form-item label="标签">
          <el-select
            v-model="newDiscussion.labels"
            multiple
            filterable
            allow-create
            placeholder="添加标签"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createDiscussion" :loading="creating">
          创建
        </el-button>
      </template>
    </el-dialog>

    <!-- 讨论详情对话框 -->
    <el-dialog
      v-model="showDetailDialog"
      :title="currentDiscussion?.title"
      width="900px"
      class="discussion-detail-dialog"
    >
      <div v-if="currentDiscussion" class="discussion-detail">
        <!-- 头部信息 -->
        <div class="detail-header">
          <div class="author-info">
            <el-avatar :size="40" :src="currentDiscussion.avatar_url" />
            <div>
              <strong>{{ currentDiscussion.username }}</strong>
              <span class="time">{{ formatTime(currentDiscussion.created_at) }}</span>
            </div>
          </div>
          
          <div class="badges">
            <span
              class="category-badge"
              :style="{ backgroundColor: currentDiscussion.category_color + '20', color: currentDiscussion.category_color }"
            >
              {{ currentDiscussion.category_emoji }} {{ currentDiscussion.category_name }}
            </span>
            <span v-if="currentDiscussion.status === 'answered'" class="answered-badge">✅ 已回答</span>
          </div>
        </div>

        <!-- 内容 -->
        <div class="detail-body" v-html="currentDiscussion.body_html"></div>

        <!-- 操作按钮 -->
        <div class="detail-actions">
          <el-button
            :type="currentDiscussion.user_vote === 'up' ? 'primary' : 'default'"
            @click="vote('discussion', currentDiscussion.id, 'up')"
          >
            <el-icon><CaretTop /></el-icon> {{ currentDiscussion.vote_count }}
          </el-button>
          
          <el-button v-if="canEdit" @click="editDiscussion">编辑</el-button>
          <el-button v-if="canPin" @click="togglePin">
            {{ currentDiscussion.is_pinned ? '取消置顶' : '置顶' }}
          </el-button>
          <el-button v-if="canLock" @click="toggleLock">
            {{ currentDiscussion.is_locked ? '解锁' : '锁定' }}
          </el-button>
        </div>

        <!-- 回复列表 -->
        <div class="replies-section">
          <h4>回复 ({{ currentDiscussion.reply_count }})</h4>
          
          <div v-for="reply in replies" :key="reply.id" class="reply-item">
            <div v-if="reply.is_answer" class="answer-badge">✅ 答案</div>
            
            <div class="reply-header">
              <el-avatar :size="32" :src="reply.avatar_url" />
              <div>
                <strong>{{ reply.username }}</strong>
                <span class="time">{{ formatTime(reply.created_at) }}</span>
              </div>
            </div>
            
            <div class="reply-body" v-html="reply.body_html"></div>
            
            <div class="reply-actions">
              <el-button
                size="small"
                :type="reply.user_vote === 'up' ? 'primary' : 'default'"
                @click="vote('reply', reply.id, 'up')"
              >
                <el-icon><CaretTop /></el-icon> {{ reply.vote_count }}
              </el-button>
              
              <el-button
                v-if="!currentDiscussion.is_locked && canMarkAnswer"
                size="small"
                :type="reply.is_answer ? 'success' : 'default'"
                @click="markAsAnswer(reply.id)"
              >
                {{ reply.is_answer ? '取消答案' : '标记为答案' }}
              </el-button>
            </div>
          </div>
        </div>

        <!-- 回复输入 -->
        <div v-if="!currentDiscussion.is_locked" class="reply-input">
          <el-input
            v-model="newReply.body"
            type="textarea"
            :rows="4"
            placeholder="写下你的回复..."
          />
          <el-button type="primary" @click="createReply" :loading="replying">
            回复
          </el-button>
        </div>
        <el-alert v-else type="warning" :closable="false">
          此讨论已被锁定，无法回复
        </el-alert>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, CaretTop, CaretBottom, ChatDotRound, View } from '@element-plus/icons-vue'
import api from '@/api'

const route = useRoute()

// 状态
const loading = ref(false)
const creating = ref(false)
const replying = ref(false)
const showCreateDialog = ref(false)
const showDetailDialog = ref(false)

const categories = ref<any[]>([])
const discussions = ref<any[]>([])
const replies = ref<any[]>([])
const currentDiscussion = ref<any>(null)

const selectedCategory = ref('')
const searchQuery = ref('')
const sortBy = ref('created')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = ref(20)
const total = ref(0)
const totalPages = ref(0)

const newDiscussion = ref({
  title: '',
  category_id: null as number | null,
  body: '',
  labels: [] as string[]
})

const newReply = ref({
  body: ''
})

// 计算属性
const repositoryId = computed(() => Number(route.params.id))
const canEdit = computed(() => currentDiscussion.value?.user_id === getCurrentUserId())
const canPin = computed(() => currentDiscussion.value?.is_repo_admin)
const canLock = computed(() => currentDiscussion.value?.is_repo_admin)
const canMarkAnswer = computed(() => 
  currentDiscussion.value?.user_id === getCurrentUserId() || currentDiscussion.value?.is_repo_admin
)

// 加载分类
async function loadCategories() {
  try {
    const res = await api.get('/discussions/categories', {
      params: { repository_id: repositoryId.value }
    })
    categories.value = res.data.data
  } catch (error) {
    console.error('加载分类失败:', error)
  }
}

// 加载讨论列表
async function loadDiscussions() {
  loading.value = true
  try {
    const res = await api.get('/discussions', {
      params: {
        repository_id: repositoryId.value,
        category: selectedCategory.value || undefined,
        q: searchQuery.value || undefined,
        sort: sortBy.value,
        status: statusFilter.value || undefined,
        page: currentPage.value,
        per_page: perPage.value
      }
    })
    
    discussions.value = res.data.data.items
    total.value = res.data.data.total
    totalPages.value = res.data.data.total_pages
  } catch (error) {
    ElMessage.error('加载讨论失败')
  } finally {
    loading.value = false
  }
}

// 查看讨论详情
async function viewDiscussion(id: number) {
  try {
    const res = await api.get(`/discussions/${id}`)
    currentDiscussion.value = res.data.data
    
    // 加载回复
    const repliesRes = await api.get(`/discussions/${id}/replies`)
    replies.value = repliesRes.data.data.items
    
    showDetailDialog.value = true
  } catch (error) {
    ElMessage.error('加载讨论详情失败')
  }
}

// 创建讨论
async function createDiscussion() {
  if (!newDiscussion.value.title || newDiscussion.value.title.length < 5) {
    ElMessage.warning('标题至少需要5个字符')
    return
  }
  
  if (!newDiscussion.value.category_id) {
    ElMessage.warning('请选择分类')
    return
  }
  
  if (!newDiscussion.value.body || newDiscussion.value.body.length < 10) {
    ElMessage.warning('内容至少需要10个字符')
    return
  }
  
  creating.value = true
  try {
    await api.post('/discussions', {
      repository_id: repositoryId.value,
      ...newDiscussion.value
    })
    
    ElMessage.success('讨论创建成功')
    showCreateDialog.value = false
    
    // 重置表单
    newDiscussion.value = {
      title: '',
      category_id: null,
      body: '',
      labels: []
    }
    
    loadDiscussions()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '创建失败')
  } finally {
    creating.value = false
  }
}

// 创建回复
async function createReply() {
  if (!newReply.value.body.trim()) {
    ElMessage.warning('请输入回复内容')
    return
  }
  
  replying.value = true
  try {
    await api.post(`/discussions/${currentDiscussion.value.id}/replies`, {
      body: newReply.value.body
    })
    
    ElMessage.success('回复成功')
    newReply.value.body = ''
    
    // 重新加载回复
    const res = await api.get(`/discussions/${currentDiscussion.value.id}/replies`)
    replies.value = res.data.data.items
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '回复失败')
  } finally {
    replying.value = false
  }
}

// 投票
async function vote(type: string, id: number, voteType: string) {
  try {
    await api.post(`/discussions/${type}/${id}/vote`, { vote: voteType })
    loadDiscussions()
    if (currentDiscussion.value) {
      viewDiscussion(currentDiscussion.value.id)
    }
  } catch (error) {
    console.error('投票失败:', error)
  }
}

// 标记为答案
async function markAsAnswer(replyId: number) {
  try {
    await api.post(`/discussions/${currentDiscussion.value.id}/replies/${replyId}/answer`)
    viewDiscussion(currentDiscussion.value.id)
    ElMessage.success('已标记为答案')
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

// 置顶
async function togglePin() {
  try {
    await api.post(`/discussions/${currentDiscussion.value.id}/pin`)
    viewDiscussion(currentDiscussion.value.id)
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

// 锁定
async function toggleLock() {
  try {
    await api.post(`/discussions/${currentDiscussion.value.id}/lock`)
    viewDiscussion(currentDiscussion.value.id)
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

// 格式化时间
function formatTime(time: string): string {
  const date = new Date(time)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  
  if (diff < 60000) return '刚刚'
  if (diff < 3600000) return `${Math.floor(diff / 60000)} 分钟前`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)} 小时前`
  if (diff < 604800000) return `${Math.floor(diff / 86400000)} 天前`
  
  return date.toLocaleDateString('zh-CN')
}

// 获取当前用户ID
function getCurrentUserId(): number | null {
  // 从 store 或 localStorage 获取
  return null
}

// 监听分类变化
watch(selectedCategory, () => {
  currentPage.value = 1
  loadDiscussions()
})

// 初始化
onMounted(() => {
  loadCategories()
  loadDiscussions()
})
</script>

<style scoped>
.discussions-page {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.subtitle {
  color: #666;
  margin: 4px 0 0;
}

.categories-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  overflow-x: auto;
  padding-bottom: 8px;
}

.category-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 20px;
  border: 2px solid transparent;
  background: #f6f8fa;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.category-tab:hover {
  background: #f0f4f8;
}

.category-tab.active {
  background: #fff;
  border-color: currentColor;
}

.filters-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.filters-bar .el-input {
  flex: 1;
  max-width: 300px;
}

.discussions-list {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e1e4e8;
}

.discussion-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #e1e4e8;
  cursor: pointer;
  transition: background 0.2s;
}

.discussion-item:last-child {
  border-bottom: none;
}

.discussion-item:hover {
  background: #f6f8fa;
}

.vote-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 50px;
}

.vote-count {
  font-weight: 600;
  font-size: 16px;
}

.content-section {
  flex: 1;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.pin-badge,
.lock-badge,
.answered-badge {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 12px;
  background: #f6f8fa;
}

.answered-badge {
  background: #dcfce7;
  color: #16a34a;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.meta-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
  font-size: 13px;
  color: #666;
}

.category-badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
}

.author {
  display: flex;
  align-items: center;
  gap: 4px;
}

.stats-row {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #666;
}

.stats-row span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

/* 详情对话框 */
.discussion-detail {
  padding: 20px 0;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.author-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-info strong {
  display: block;
}

.author-info .time {
  font-size: 13px;
  color: #666;
}

.badges {
  display: flex;
  gap: 8px;
}

.detail-body {
  padding: 20px 0;
  border-top: 1px solid #e1e4e8;
  border-bottom: 1px solid #e1e4e8;
  line-height: 1.6;
}

.detail-actions {
  display: flex;
  gap: 8px;
  padding: 16px 0;
}

.replies-section {
  margin-top: 24px;
}

.replies-section h4 {
  margin-bottom: 16px;
}

.reply-item {
  padding: 16px;
  border: 1px solid #e1e4e8;
  border-radius: 8px;
  margin-bottom: 12px;
  position: relative;
}

.reply-item .answer-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: #dcfce7;
  color: #16a34a;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
}

.reply-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.reply-header strong {
  display: block;
}

.reply-header .time {
  font-size: 12px;
  color: #666;
}

.reply-body {
  line-height: 1.6;
}

.reply-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.reply-input {
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* 暗色主题 */
:root.dark .discussions-list,
:root.dark .reply-item {
  background: #1c1c1e;
  border-color: #3a3a3c;
}

:root.dark .discussion-item:hover,
:root.dark .category-tab {
  background: #2c2c2e;
}

:root.dark .category-tab.active {
  background: #3a3a3c;
}
</style>
