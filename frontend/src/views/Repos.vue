<template>
  <div class="repos-page">
    <div class="page-header">
      <h1>我的仓库</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon class="el-icon--left"><Plus /></el-icon>
        新建仓库
      </el-button>
    </div>
    
    <div class="filter-bar">
      <el-input
        v-model="searchQuery"
        placeholder="搜索仓库..."
        :prefix-icon="Search"
        clearable
        class="search-input"
      />
      
      <el-select v-model="filterType" placeholder="类型" class="filter-select">
        <el-option label="全部" value="all" />
        <el-option label="公开" value="public" />
        <el-option label="私有" value="private" />
      </el-select>
      
      <el-select v-model="sortBy" placeholder="排序" class="filter-select">
        <el-option label="最近更新" value="updated" />
        <el-option label="名称" value="name" />
        <el-option label="星标数" value="stars" />
      </el-select>
    </div>
    
    <div class="repos-list" v-loading="loading">
      <el-empty v-if="filteredRepos.length === 0" description="暂无仓库">
        <el-button type="primary" @click="showCreateDialog = true">创建第一个仓库</el-button>
      </el-empty>
      
      <div v-else class="repo-item" v-for="repo in filteredRepos" :key="repo.id" @click="goToRepo(repo)">
        <div class="repo-main">
          <div class="repo-header">
            <div class="repo-name">
              <el-icon><Folder /></el-icon>
              <span class="name">{{ repo.owner }}/{{ repo.name }}</span>
              <el-tag :type="repo.isPrivate ? 'warning' : 'success'" size="small">
                {{ repo.isPrivate ? '私有' : '公开' }}
              </el-tag>
            </div>
            <div class="repo-stats">
              <span class="stat">
                <el-icon><Star /></el-icon>
                {{ repo.stars }}
              </span>
              <span class="stat">
                <el-icon><Share /></el-icon>
                {{ repo.forks }}
              </span>
            </div>
          </div>
          
          <p class="repo-desc">{{ repo.description || '暂无描述' }}</p>
          
          <div class="repo-meta">
            <span class="language" v-if="repo.language">
              <span class="language-dot" :style="{ background: getLanguageColor(repo.language) }"></span>
              {{ repo.language }}
            </span>
            <span class="updated">更新于 {{ formatTime(repo.updatedAt) }}</span>
          </div>
        </div>
        
        <div class="repo-actions">
          <el-button text @click.stop="handleStar(repo)">
            <el-icon><Star /></el-icon>
            星标
          </el-button>
          <el-button text @click.stop="handleFork(repo)">
            <el-icon><Share /></el-icon>
            复刻
          </el-button>
          <el-dropdown @command="handleCommand($event, repo)" trigger="click">
            <el-button text>
              <el-icon><MoreFilled /></el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="settings">设置</el-dropdown-item>
                <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </div>
    </div>
    
    <!-- 创建仓库对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      title="新建仓库"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="createFormRef"
        :model="createForm"
        :rules="createRules"
        label-position="top"
      >
        <el-form-item label="仓库名称" prop="name">
          <el-input v-model="createForm.name" placeholder="my-awesome-project" />
        </el-form-item>
        
        <el-form-item label="描述（可选）">
          <el-input
            v-model="createForm.description"
            type="textarea"
            :rows="3"
            placeholder="简单描述您的项目..."
          />
        </el-form-item>
        
        <el-form-item label="可见性">
          <el-radio-group v-model="createForm.isPrivate">
            <el-radio :value="false">
              <div class="radio-content">
                <el-icon><Unlock /></el-icon>
                <span>公开</span>
                <small>任何人都可以查看此仓库</small>
              </div>
            </el-radio>
            <el-radio :value="true">
              <div class="radio-content">
                <el-icon><Lock /></el-icon>
                <span>私有</span>
                <small>只有您可以查看此仓库</small>
              </div>
            </el-radio>
          </el-radio-group>
        </el-form-item>
        
        <el-form-item>
          <el-checkbox v-model="createForm.initReadme">
            使用 README 初始化此仓库
          </el-checkbox>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createRepo">
          创建仓库
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Search, Folder, Star, Share, MoreFilled, Lock, Unlock
} from '@element-plus/icons-vue'

const router = useRouter()

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const searchQuery = ref('')
const filterType = ref('all')
const sortBy = ref('updated')

const repos = ref([
  {
    id: 1,
    owner: 'codemaster',
    name: 'awesome-project',
    description: '一个很棒的开源项目，包含许多实用的工具和组件',
    language: 'JavaScript',
    stars: 128,
    forks: 32,
    isPrivate: false,
    updatedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000)
  },
  {
    id: 2,
    owner: 'codemaster',
    name: 'private-repo',
    description: '我的私有项目',
    language: 'Vue',
    stars: 5,
    forks: 0,
    isPrivate: true,
    updatedAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
  }
])

const createFormRef = ref(null)
const createForm = reactive({
  name: '',
  description: '',
  isPrivate: false,
  initReadme: true
})

const createRules = {
  name: [
    { required: true, message: '请输入仓库名称', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9._-]+$/, message: '只能包含字母、数字、点、下划线和连字符', trigger: 'blur' }
  ]
}

const filteredRepos = computed(() => {
  let result = repos.value
  
  // 搜索过滤
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(r => 
      r.name.toLowerCase().includes(query) ||
      r.description?.toLowerCase().includes(query)
    )
  }
  
  // 类型过滤
  if (filterType.value !== 'all') {
    result = result.filter(r => 
      filterType.value === 'private' ? r.isPrivate : !r.isPrivate
    )
  }
  
  // 排序
  result = [...result].sort((a, b) => {
    switch (sortBy.value) {
      case 'name':
        return a.name.localeCompare(b.name)
      case 'stars':
        return b.stars - a.stars
      default:
        return b.updatedAt - a.updatedAt
    }
  })
  
  return result
})

const languageColors = {
  JavaScript: '#f1e05a',
  Vue: '#41b883',
  TypeScript: '#3178c6',
  Python: '#3572A5',
  Java: '#b07219',
  Go: '#00ADD8',
  Rust: '#dea584',
  PHP: '#4F5D95'
}

function getLanguageColor(lang) {
  return languageColors[lang] || '#ccc'
}

function formatTime(date) {
  const now = new Date()
  const diff = now - date
  const days = Math.floor(diff / (24 * 60 * 60 * 1000))
  
  if (days === 0) return '今天'
  if (days === 1) return '昨天'
  if (days < 7) return `${days} 天前`
  if (days < 30) return `${Math.floor(days / 7)} 周前`
  if (days < 365) return `${Math.floor(days / 30)} 月前`
  return `${Math.floor(days / 365)} 年前`
}

function goToRepo(repo) {
  router.push(`/repo/${repo.owner}/${repo.name}`)
}

async function createRepo() {
  const valid = await createFormRef.value.validate().catch(() => false)
  if (!valid) return
  
  creating.value = true
  
  try {
    // 调用创建仓库 API
    const res = await fetch('/api/repos', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      },
      body: JSON.stringify({
        name: createForm.name,
        description: createForm.description,
        is_private: createForm.isPrivate,
        init_readme: createForm.initReadme
      })
    })
    
    const data = await res.json()
    
    if (data.code === 200 || res.ok) {
      const newRepo = {
        id: data.data?.id || Date.now(),
        owner: data.data?.owner || localStorage.getItem('username') || 'me',
        name: createForm.name,
        description: createForm.description,
        language: null,
        stars: 0,
        forks: 0,
        isPrivate: createForm.isPrivate,
        updatedAt: new Date()
      }
      
      repos.value.unshift(newRepo)
      showCreateDialog.value = false
      ElMessage.success('仓库创建成功')
      
      // 重置表单
      createFormRef.value.resetFields()
      
      // 跳转到新仓库
      router.push(`/repos/${newRepo.owner}/${newRepo.name}`)
    } else {
      throw new Error(data.message || '创建失败')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '创建失败')
  } finally {
    creating.value = false
  }
}

function handleStar(repo) {
  repo.stars++
  ElMessage.success('已星标')
}

function handleFork(repo) {
  repo.forks++
  ElMessage.success('已复刻')
}

function handleCommand(command, repo) {
  switch (command) {
    case 'settings':
      router.push(`/repo/${repo.owner}/${repo.name}/settings`)
      break
    case 'delete':
      ElMessageBox.confirm(
        `确定要删除仓库 ${repo.owner}/${repo.name} 吗？此操作不可恢复。`,
        '删除仓库',
        {
          confirmButtonText: '删除',
          cancelButtonText: '取消',
          type: 'warning'
        }
      ).then(() => {
        const index = repos.value.findIndex(r => r.id === repo.id)
        if (index > -1) {
          repos.value.splice(index, 1)
          ElMessage.success('仓库已删除')
        }
      }).catch(() => {})
      break
  }
}

onMounted(async () => {
  // 加载仓库列表
  loading.value = true
  try {
    const res = await fetch('/api/repos', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    })
    
    const data = await res.json()
    
    if (data.code === 200 || res.ok) {
      repos.value = (data.data || data.repos || []).map((repo: any) => ({
        id: repo.id,
        owner: repo.owner?.username || repo.owner || 'me',
        name: repo.name,
        description: repo.description,
        language: repo.language,
        stars: repo.stars || repo.star_count || 0,
        forks: repo.forks || repo.fork_count || 0,
        isPrivate: repo.is_private || repo.isPrivate || false,
        updatedAt: new Date(repo.updated_at || repo.updatedAt || Date.now())
      }))
    }
  } catch (e) {
    console.error('Failed to load repos:', e)
    // 保持模拟数据作为后备
  } finally {
    loading.value = false
  }
})
</script>

<style lang="scss" scoped>
.repos-page {
  max-width: 1000px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  
  h1 {
    font-size: 24px;
    font-weight: 600;
    color: #24292f;
    margin: 0;
  }
}

.filter-bar {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  
  .search-input {
    flex: 1;
  }
  
  .filter-select {
    width: 140px;
  }
}

.repos-list {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #d0d7de;
}

.repo-item {
  display: flex;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #d0d7de;
  cursor: pointer;
  transition: background 0.2s;
  
  &:last-child {
    border-bottom: none;
  }
  
  &:hover {
    background: #f6f8fa;
  }
}

.repo-main {
  flex: 1;
  min-width: 0;
}

.repo-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.repo-name {
  display: flex;
  align-items: center;
  gap: 8px;
  
  .name {
    font-size: 16px;
    font-weight: 600;
    color: #0969da;
    
    &:hover {
      text-decoration: underline;
    }
  }
}

.repo-stats {
  display: flex;
  gap: 16px;
  
  .stat {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #57606a;
    font-size: 12px;
  }
}

.repo-desc {
  font-size: 14px;
  color: #57606a;
  margin: 0 0 8px;
  line-height: 1.5;
}

.repo-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #57606a;
  
  .language {
    display: flex;
    align-items: center;
    gap: 6px;
    
    .language-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
    }
  }
}

.repo-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 16px;
}

.radio-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  
  small {
    color: #57606a;
    font-weight: normal;
  }
}
</style>
