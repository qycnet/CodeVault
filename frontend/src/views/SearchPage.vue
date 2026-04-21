<template>
  <div class="search-page">
    <div class="search-header">
      <el-input
        v-model="searchQuery"
        placeholder="搜索仓库、Issue、代码..."
        size="large"
        clearable
        @keyup.enter="search"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
        <template #append>
          <el-button type="primary" @click="search">搜索</el-button>
        </template>
      </el-input>
    </div>

    <el-tabs v-model="activeTab" @tab-change="search">
      <el-tab-pane label="仓库" name="repositories">
        <div class="search-results">
          <div v-for="repo in results.repositories" :key="repo.id" class="result-item">
            <div class="result-title">
              <router-link :to="`/repos/${repo.owner_name}/${repo.name}`">
                {{ repo.owner_name }} / {{ repo.name }}
              </router-link>
            </div>
            <div class="result-desc">{{ repo.description || '暂无描述' }}</div>
            <div class="result-meta">
              <span>{{ repo.is_private ? '私有' : '公开' }}</span>
              <span>更新于 {{ formatTime(repo.updated_at) }}</span>
            </div>
          </div>
          <el-empty v-if="results.repositories.length === 0" description="未找到相关仓库" />
        </div>
      </el-tab-pane>

      <el-tab-pane label="Issue" name="issues">
        <div class="search-results">
          <div v-for="issue in results.issues" :key="issue.id" class="result-item">
            <div class="result-title">
              <el-tag :type="issue.status === 'open' ? 'success' : 'info'" size="small">
                {{ issue.status === 'open' ? '开启' : '关闭' }}
              </el-tag>
              <router-link :to="`/repos/${issue.repo_name}/issues/${issue.id}`">
                {{ issue.title }}
              </router-link>
            </div>
            <div class="result-meta">
              <span>{{ issue.repo_name }}</span>
              <span>{{ issue.author_name }}</span>
              <span>{{ formatTime(issue.created_at) }}</span>
            </div>
          </div>
          <el-empty v-if="results.issues.length === 0" description="未找到相关 Issue" />
        </div>
      </el-tab-pane>

      <el-tab-pane label="代码" name="code">
        <div class="search-results">
          <div v-for="(item, index) in results.code" :key="index" class="result-item code-result">
            <div class="result-title">
              <el-icon><Document /></el-icon>
              <span>{{ item.repo_name || '' }}{{ item.file }}</span>
              <span class="line-num">:{{ item.line }}</span>
            </div>
            <pre class="code-content" v-html="item.highlight"></pre>
          </div>
          <el-empty v-if="results.code.length === 0" description="未找到相关代码" />
        </div>
      </el-tab-pane>

      <el-tab-pane label="用户" name="users">
        <div class="search-results">
          <div v-for="user in results.users" :key="user.id" class="result-item user-result">
            <el-avatar :size="40">{{ user.username?.charAt(0).toUpperCase() }}</el-avatar>
            <div class="user-info">
              <div class="result-title">{{ user.username }}</div>
              <div class="result-meta">{{ user.email }}</div>
            </div>
          </div>
          <el-empty v-if="results.users.length === 0" description="未找到相关用户" />
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Search, Document } from '@element-plus/icons-vue'
import axios from 'axios'

const route = useRoute()

const searchQuery = ref('')
const activeTab = ref('repositories')

const results = reactive({
  repositories: [] as any[],
  issues: [] as any[],
  code: [] as any[],
  users: [] as any[]
})

function formatTime(time: string) {
  return new Date(time).toLocaleDateString('zh-CN')
}

async function search() {
  if (!searchQuery.value.trim()) return
  
  const apis: Record<string, string> = {
    repositories: '/api/search/repositories',
    issues: '/api/search/issues',
    code: '/api/search/code',
    users: '/api/search/users'
  }
  
  try {
    const response = await axios.get(apis[activeTab.value], {
      params: { q: searchQuery.value }
    })
    
    if (response.data.success) {
      results[activeTab.value as keyof typeof results] = response.data.items
    }
  } catch (error) {
    console.error('Search failed:', error)
  }
}

onMounted(() => {
  if (route.query.q) {
    searchQuery.value = route.query.q as string
    search()
  }
})
</script>

<style lang="scss" scoped>
.search-page {
  max-width: 900px;
  margin: 0 auto;
  padding: 20px;
}

.search-header {
  margin-bottom: 24px;
}

.search-results {
  margin-top: 16px;
}

.result-item {
  padding: 16px;
  border-bottom: 1px solid #d0d7de;
  
  &:last-child {
    border-bottom: none;
  }
  
  .result-title {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 4px;
    
    a {
      color: #0969da;
      text-decoration: none;
      
      &:hover {
        text-decoration: underline;
      }
    }
  }
  
  .result-desc {
    color: #57606a;
    font-size: 14px;
    margin: 4px 0;
  }
  
  .result-meta {
    font-size: 12px;
    color: #57606a;
    
    span {
      margin-right: 16px;
    }
  }
}

.code-result {
  .result-title {
    font-family: monospace;
    font-size: 14px;
    
    .line-num {
      color: #0969da;
    }
  }
  
  .code-content {
    background: #f6f8fa;
    padding: 8px 12px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 13px;
    overflow-x: auto;
    margin: 8px 0 0 0;
    
    :deep(mark) {
      background: #fff3cd;
      padding: 0 2px;
      border-radius: 2px;
    }
  }
}

.user-result {
  display: flex;
  align-items: center;
  gap: 12px;
  
  .user-info {
    flex: 1;
  }
}
</style>
