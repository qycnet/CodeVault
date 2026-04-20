<template>
  <div class="search-page">
    <el-card>
      <template #header>
        <div class="search-header">
          <h2>搜索</h2>
        </div>
      </template>
      
      <!-- 搜索表单 -->
      <div class="search-form">
        <el-input
          v-model="searchQuery"
          placeholder="搜索仓库、Issue、Pull Request..."
          size="large"
          clearable
          @keyup.enter="handleSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
          <template #append>
            <el-button type="primary" @click="handleSearch">
              搜索
            </el-button>
          </template>
        </el-input>
        
        <!-- 搜索类型选择 -->
        <div class="search-filters">
          <el-radio-group v-model="searchType" @change="handleSearch">
            <el-radio-button value="all">全部</el-radio-button>
            <el-radio-button value="repositories">仓库</el-radio-button>
            <el-radio-button value="issues">Issues</el-radio-button>
            <el-radio-button value="pulls">Pull Requests</el-radio-button>
            <el-radio-button value="users">用户</el-radio-button>
          </el-radio-group>
        </div>
      </div>
      
      <!-- 搜索结果 -->
      <div v-loading="loading" class="search-results">
        <!-- 仓库结果 -->
        <div v-if="searchType === 'all' || searchType === 'repositories'" class="result-section">
          <h3 v-if="results.repositories.length > 0">
            仓库 ({{ results.repositories.length }})
          </h3>
          <div
            v-for="repo in results.repositories"
            :key="repo.id"
            class="result-item"
            @click="goToRepo(repo)"
          >
            <div class="result-icon">
              <el-icon size="24"><Folder /></el-icon>
            </div>
            <div class="result-content">
              <div class="result-title">
                {{ repo.owner }} / {{ repo.name }}
              </div>
              <div class="result-desc">{{ repo.description || '暂无描述' }}</div>
              <div class="result-meta">
                <span><el-icon><Star /></el-icon> {{ repo.stars || 0 }}</span>
                <span><el-icon><View /></el-icon> {{ repo.watchers || 0 }}</span>
                <span>{{ repo.language || 'Unknown' }}</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Issue 结果 -->
        <div v-if="searchType === 'all' || searchType === 'issues'" class="result-section">
          <h3 v-if="results.issues.length > 0">
            Issues ({{ results.issues.length }})
          </h3>
          <div
            v-for="issue in results.issues"
            :key="issue.id"
            class="result-item"
            @click="goToIssue(issue)"
          >
            <div class="result-icon">
              <el-icon size="24" :color="issue.status === 'open' ? '#67c23a' : '#909399'">
                <CircleCheck v-if="issue.status === 'closed'" />
                <CircleClose v-else />
              </el-icon>
            </div>
            <div class="result-content">
              <div class="result-title">
                {{ issue.title }}
                <el-tag size="small" :type="issue.status === 'open' ? 'success' : 'info'">
                  {{ issue.status === 'open' ? '开启' : '关闭' }}
                </el-tag>
              </div>
              <div class="result-desc">
                {{ issue.repo_name }} #{{ issue.id }}
              </div>
              <div class="result-meta">
                <span>{{ issue.author_name }}</span>
                <span>{{ issue.created_at }}</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Pull Request 结果 -->
        <div v-if="searchType === 'all' || searchType === 'pulls'" class="result-section">
          <h3 v-if="results.pulls.length > 0">
            Pull Requests ({{ results.pulls.length }})
          </h3>
          <div
            v-for="pull in results.pulls"
            :key="pull.id"
            class="result-item"
            @click="goToPull(pull)"
          >
            <div class="result-icon">
              <el-icon size="24" :color="getPullColor(pull.status)">
                <Merge v-if="pull.status === 'merged'" />
                <CircleCheck v-else-if="pull.status === 'closed'" />
                <CircleClose v-else />
              </el-icon>
            </div>
            <div class="result-content">
              <div class="result-title">
                {{ pull.title }}
                <el-tag size="small" :type="getPullType(pull.status)">
                  {{ getPullText(pull.status) }}
                </el-tag>
              </div>
              <div class="result-desc">
                {{ pull.repo_name }} #{{ pull.id }}
              </div>
              <div class="result-meta">
                <span>{{ pull.author_name }}</span>
                <span>{{ pull.created_at }}</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- 用户结果 -->
        <div v-if="searchType === 'all' || searchType === 'users'" class="result-section">
          <h3 v-if="results.users.length > 0">
            用户 ({{ results.users.length }})
          </h3>
          <div
            v-for="user in results.users"
            :key="user.id"
            class="result-item"
            @click="goToUser(user)"
          >
            <div class="result-icon">
              <el-avatar :size="40" :src="user.avatar">
                {{ user.username?.charAt(0).toUpperCase() }}
              </el-avatar>
            </div>
            <div class="result-content">
              <div class="result-title">{{ user.username }}</div>
              <div class="result-desc">{{ user.email }}</div>
            </div>
          </div>
        </div>
        
        <!-- 无结果 -->
        <el-empty
          v-if="!loading && hasSearched && totalResults === 0"
          description="未找到相关结果"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Folder, Star, View, CircleCheck, CircleClose, Merge } from '@element-plus/icons-vue'
import api from '@/api/index'

const router = useRouter()

const searchQuery = ref('')
const searchType = ref('all')
const loading = ref(false)
const hasSearched = ref(false)

const results = ref({
  repositories: [] as any[],
  issues: [] as any[],
  pulls: [] as any[],
  users: [] as any[]
})

const totalResults = computed(() => {
  return results.value.repositories.length +
    results.value.issues.length +
    results.value.pulls.length +
    results.value.users.length
})

function getPullColor(status: string) {
  switch (status) {
    case 'open': return '#67c23a'
    case 'merged': return '#409eff'
    case 'closed': return '#909399'
    default: return '#909399'
  }
}

function getPullType(status: string) {
  switch (status) {
    case 'open': return 'success'
    case 'merged': return 'primary'
    case 'closed': return 'info'
    default: return 'info'
  }
}

function getPullText(status: string) {
  switch (status) {
    case 'open': return '开启'
    case 'merged': return '已合并'
    case 'closed': return '已关闭'
    default: return status
  }
}

async function handleSearch() {
  if (!searchQuery.value.trim()) return
  
  loading.value = true
  hasSearched.value = true
  
  try {
    const res: any = await api.get('/search', {
      params: {
        q: searchQuery.value,
        type: searchType.value
      }
    })
    
    if (res.code === 200) {
      results.value = res.data
    }
  } catch (e) {
    console.error('Search failed:', e)
  } finally {
    loading.value = false
  }
}

function goToRepo(repo: any) {
  router.push(`/repos/${repo.owner}/${repo.name}`)
}

function goToIssue(issue: any) {
  router.push(`/repos/${issue.repo_owner}/${issue.repo_name}/issues/${issue.id}`)
}

function goToPull(pull: any) {
  router.push(`/repos/${pull.repo_owner}/${pull.repo_name}/pulls/${pull.id}`)
}

function goToUser(user: any) {
  router.push(`/users/${user.username}`)
}

onMounted(() => {
  // 可以从 URL 参数读取搜索词
  const query = router.currentRoute.value.query.q as string
  if (query) {
    searchQuery.value = query
    handleSearch()
  }
})
</script>

<style scoped>
.search-page {
  padding: 20px;
}

.search-header h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.search-form {
  margin-bottom: 24px;
}

.search-filters {
  margin-top: 16px;
}

.search-results {
  min-height: 200px;
}

.result-section {
  margin-bottom: 32px;
}

.result-section h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  color: #303133;
  border-bottom: 1px solid #e4e7ed;
  padding-bottom: 8px;
}

.result-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.3s;
}

.result-item:hover {
  background: #f5f7fa;
  border-color: #409eff;
}

.result-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #f5f7fa;
  border-radius: 8px;
}

.result-content {
  flex: 1;
}

.result-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.result-desc {
  font-size: 14px;
  color: #606266;
  margin-bottom: 8px;
}

.result-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #909399;
}

.result-meta span {
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>
