<template>
  <div class="commit-history">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <h3>提交历史</h3>
          <div class="branch-selector">
            <el-select v-model="selectedBranch" @change="onBranchChange">
              <el-option
                v-for="branch in branches"
                :key="branch"
                :label="branch"
                :value="branch"
              />
            </el-select>
          </div>
        </div>
      </template>
      
      <div class="commit-list">
        <div
          v-for="commit in commits"
          :key="commit.hash"
          class="commit-item"
          @click="viewCommit(commit.hash)"
        >
          <div class="commit-avatar">
            <el-avatar :size="40" :src="commit.author_avatar">
              {{ commit.author_name?.charAt(0).toUpperCase() }}
            </el-avatar>
          </div>
          
          <div class="commit-info">
            <div class="commit-title">{{ commit.message }}</div>
            <div class="commit-meta">
              <span class="author">{{ commit.author_name }}</span>
              <span class="hash">{{ commit.hash }}</span>
              <span class="time">{{ formatTime(commit.time) }}</span>
            </div>
          </div>
          
          <div class="commit-actions">
            <el-button size="small" @click.stop="copyHash(commit.hash)">
              <el-icon><CopyDocument /></el-icon>
            </el-button>
            <el-button size="small" @click.stop="browseCode(commit.hash)">
              浏览代码
            </el-button>
          </div>
        </div>
      </div>
      
      <el-empty v-if="!loading && commits.length === 0" description="暂无提交记录" />
      
      <div class="pagination" v-if="hasMore">
        <el-button @click="loadMore" :loading="loadingMore">
          加载更多
        </el-button>
      </div>
    </el-card>
    
    <!-- 提交详情对话框 -->
    <el-dialog v-model="showCommitDetail" title="提交详情" width="800px">
      <div class="commit-detail" v-if="selectedCommit">
        <div class="detail-row">
          <span class="label">提交哈希：</span>
          <span class="value hash">{{ selectedCommit.hash }}</span>
          <el-button size="small" @click="copyHash(selectedCommit.hash)">
            复制
          </el-button>
        </div>
        
        <div class="detail-row">
          <span class="label">作者：</span>
          <span class="value">{{ selectedCommit.author_name }} &lt;{{ selectedCommit.author_email }}&gt;</span>
        </div>
        
        <div class="detail-row">
          <span class="label">时间：</span>
          <span class="value">{{ selectedCommit.time }}</span>
        </div>
        
        <div class="detail-row">
          <span class="label">提交信息：</span>
          <div class="commit-message">{{ selectedCommit.full_message || selectedCommit.message }}</div>
        </div>
        
        <el-divider />
        
        <div class="files-changed">
          <h4>文件变更 ({{ selectedCommit.files?.length || 0 }})</h4>
          <div class="file-list">
            <div
              v-for="file in selectedCommit.files"
              :key="file.path"
              class="file-item"
            >
              <span :class="['status', file.status]">{{ file.status }}</span>
              <span class="path">{{ file.path }}</span>
              <span class="stats">
                <span class="additions">+{{ file.additions }}</span>
                <span class="deletions">-{{ file.deletions }}</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CopyDocument } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()
const router = useRouter()

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)
const currentBranch = computed(() => route.params.branch as string || 'main')

const loading = ref(false)
const loadingMore = ref(false)
const commits = ref<any[]>([])
const branches = ref<string[]>([])
const selectedBranch = ref('main')
const hasMore = ref(true)
const page = ref(0)
const pageSize = 30

const showCommitDetail = ref(false)
const selectedCommit = ref<any>(null)

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

async function fetchBranches() {
  try {
    const res: any = await api.get('/repos/branches', {
      params: { owner: owner.value, repo: repo.value }
    })
    if (res.code === 200) {
      branches.value = res.data || []
      if (branches.value.length > 0 && !branches.value.includes(selectedBranch.value)) {
        selectedBranch.value = branches.value[0]
      }
    }
  } catch (e) {
    console.error('Failed to fetch branches:', e)
  }
}

async function fetchCommits(append = false) {
  if (append) {
    loadingMore.value = true
  } else {
    loading.value = true
  }
  
  try {
    const res: any = await api.get('/repos/commits', {
      params: {
        owner: owner.value,
        repo: repo.value,
        branch: currentBranch.value,
        page: page.value,
        per_page: pageSize
      }
    })
    
    if (res.code === 200) {
      const newCommits = res.data || []
      
      if (append) {
        commits.value = [...commits.value, ...newCommits]
      } else {
        commits.value = newCommits
      }
      
      hasMore.value = newCommits.length === pageSize
    }
  } catch (e) {
    console.error('Failed to fetch commits:', e)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

async function loadMore() {
  page.value++
  await fetchCommits(true)
}

function onBranchChange() {
  page.value = 0
  router.push({
    path: `/repos/${owner.value}/${repo.value}/commits/${selectedBranch.value}`
  })
}

async function viewCommit(hash: string) {
  try {
    const res: any = await api.get('/repos/commit', {
      params: {
        owner: owner.value,
        repo: repo.value,
        hash
      }
    })
    
    if (res.code === 200) {
      selectedCommit.value = res.data
      showCommitDetail.value = true
    }
  } catch (e) {
    console.error('Failed to fetch commit:', e)
    ElMessage.error('加载提交详情失败')
  }
}

function copyHash(hash: string) {
  navigator.clipboard.writeText(hash)
  ElMessage.success('已复制到剪贴板')
}

function browseCode(hash: string) {
  router.push({
    path: `/repos/${owner.value}/${repo.value}/tree/${hash}`
  })
}

watch(currentBranch, () => {
  selectedBranch.value = currentBranch.value
  page.value = 0
  fetchCommits()
})

onMounted(() => {
  selectedBranch.value = currentBranch.value
  fetchBranches()
  fetchCommits()
})
</script>

<style scoped>
.commit-history {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
}

.commit-list {
  margin-top: 16px;
}

.commit-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #e4e7ed;
  cursor: pointer;
  transition: background-color 0.2s;
}

.commit-item:hover {
  background-color: #f5f7fa;
}

.commit-item:last-child {
  border-bottom: none;
}

.commit-avatar {
  flex-shrink: 0;
}

.commit-info {
  flex: 1;
  min-width: 0;
}

.commit-title {
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.commit-meta {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: #909399;
}

.commit-meta .hash {
  font-family: monospace;
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 4px;
}

.commit-actions {
  display: flex;
  gap: 8px;
}

.pagination {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.commit-detail {
  padding: 16px 0;
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.detail-row .label {
  font-weight: 500;
  color: #606266;
  min-width: 80px;
}

.detail-row .value {
  color: #303133;
}

.detail-row .hash {
  font-family: monospace;
  background: #f5f7fa;
  padding: 4px 8px;
  border-radius: 4px;
}

.commit-message {
  flex: 1;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  white-space: pre-wrap;
  word-break: break-word;
}

.files-changed h4 {
  margin-bottom: 12px;
}

.file-list {
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  overflow: hidden;
}

.file-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border-bottom: 1px solid #e4e7ed;
}

.file-item:last-child {
  border-bottom: none;
}

.file-item .status {
  font-size: 12px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 4px;
}

.file-item .status.added {
  background: #e6f7e6;
  color: #67c23a;
}

.file-item .status.modified {
  background: #fff7e6;
  color: #e6a23c;
}

.file-item .status.deleted {
  background: #fee;
  color: #f56c6c;
}

.file-item .path {
  flex: 1;
  font-family: monospace;
  font-size: 13px;
}

.file-item .stats {
  display: flex;
  gap: 8px;
  font-size: 12px;
}

.file-item .additions {
  color: #67c23a;
}

.file-item .deletions {
  color: #f56c6c;
}
</style>
