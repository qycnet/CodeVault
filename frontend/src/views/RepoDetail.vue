<template>
  <div class="repo-detail-page">
    <div class="repo-header">
      <div class="repo-info">
        <div class="repo-title">
          <router-link to="/repos" class="back-link">
            <el-icon><ArrowLeft /></el-icon>
          </router-link>
          <span class="owner">{{ owner }}</span>
          <span class="separator">/</span>
          <span class="name">{{ repoName }}</span>
          <el-tag :type="repo.isPrivate ? 'warning' : 'success'" size="small">
            {{ repo.isPrivate ? '私有' : '公开' }}
          </el-tag>
        </div>
        
        <div class="repo-actions">
          <el-button @click="handleStar">
            <el-icon class="el-icon--left"><Star /></el-icon>
            星标 {{ repo.stars }}
          </el-button>
          <el-button @click="handleFork">
            <el-icon class="el-icon--left"><Share /></el-icon>
            复刻 {{ repo.forks }}
          </el-button>
          <el-button type="primary" @click="handleClone">
            <el-icon class="el-icon--left"><Download /></el-icon>
            克隆
          </el-button>
        </div>
      </div>
      
      <p class="repo-description">{{ repo.description }}</p>
      
      <div class="repo-tabs">
        <el-tabs v-model="activeTab" @tab-change="handleTabChange">
          <el-tab-pane label="代码" name="code">
            <template #label>
              <span><el-icon><Document /></el-icon> 代码</span>
            </template>
          </el-tab-pane>
          <el-tab-pane label="问题" name="issues">
            <template #label>
              <span><el-icon><ChatDotRound /></el-icon> 问题 {{ repo.openIssues }}</span>
            </template>
          </el-tab-pane>
          <el-tab-pane label="合并请求" name="pulls">
            <template #label>
              <span><el-icon><GitMerge /></el-icon> 合并请求 {{ repo.openPRs }}</span>
            </template>
          </el-tab-pane>
          <el-tab-pane label="设置" name="settings">
            <template #label>
              <span><el-icon><Setting /></el-icon> 设置</span>
            </template>
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>
    
    <div class="repo-content">
      <!-- 代码视图 -->
      <div v-if="activeTab === 'code'" class="code-view">
        <div class="branch-selector">
          <el-select v-model="currentBranch" size="small">
            <el-option
              v-for="branch in branches"
              :key="branch"
              :label="branch"
              :value="branch"
            />
          </el-select>
          
          <div class="file-actions">
            <el-button size="small" type="primary">
              <el-icon class="el-icon--left"><Plus /></el-icon>
              添加文件
            </el-button>
          </div>
        </div>
        
        <div class="commit-info">
          <span class="commit-count">{{ commits.length }} 次提交</span>
          <span class="branch-count">{{ branches.length }} 个分支</span>
        </div>
        
        <div class="file-list">
          <div class="file-item" v-for="file in files" :key="file.name">
            <el-icon :class="file.type"><component :is="file.type === 'folder' ? 'Folder' : 'Document'" /></el-icon>
            <span class="file-name">{{ file.name }}</span>
            <span class="file-message">{{ file.message }}</span>
            <span class="file-time">{{ file.time }}</span>
          </div>
        </div>
        
        <div class="readme-section" v-if="readme">
          <div class="readme-header">
            <el-icon><Document /></el-icon>
            <span>README.md</span>
          </div>
          <div class="readme-content" v-html="readme"></div>
        </div>
      </div>
      
      <!-- 问题视图 -->
      <div v-else-if="activeTab === 'issues'" class="issues-view">
        <div class="view-header">
          <el-button type="primary" @click="showCreateIssue = true">
            <el-icon class="el-icon--left"><Plus /></el-icon>
            新建问题
          </el-button>
        </div>
        <el-empty description="暂无问题" />
      </div>
      
      <!-- 合并请求视图 -->
      <div v-else-if="activeTab === 'pulls'" class="pulls-view">
        <div class="view-header">
          <el-button type="primary" @click="showCreatePR = true">
            <el-icon class="el-icon--left"><Plus /></el-icon>
            新建合并请求
          </el-button>
        </div>
        <el-empty description="暂无合并请求" />
      </div>
      
      <!-- 设置视图 -->
      <div v-else-if="activeTab === 'settings'" class="settings-view">
        <el-card header="仓库设置">
          <el-form label-width="100px">
            <el-form-item label="仓库名称">
              <el-input v-model="repo.name" />
            </el-form-item>
            <el-form-item label="描述">
              <el-input v-model="repo.description" type="textarea" />
            </el-form-item>
            <el-form-item label="可见性">
              <el-radio-group v-model="repo.isPrivate">
                <el-radio :value="false">公开</el-radio>
                <el-radio :value="true">私有</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item>
              <el-button type="primary">保存更改</el-button>
            </el-form-item>
          </el-form>
        </el-card>
        
        <el-card header="危险区域" class="danger-zone">
          <el-button type="danger">删除此仓库</el-button>
        </el-card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  ArrowLeft, Star, Share, Download, Document, ChatDotRound,
  GitMerge, Setting, Plus, Folder
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()

const owner = computed(() => route.params.owner)
const repoName = computed(() => route.params.name)

const activeTab = ref('code')
const currentBranch = ref('main')

const repo = reactive({
  name: repoName.value,
  isPrivate: false,
  stars: 128,
  forks: 32,
  openIssues: 5,
  openPRs: 2,
  description: '一个很棒的开源项目，包含许多实用的工具和组件'
})

const branches = ref(['main', 'develop', 'feature/new-feature'])

const commits = ref([
  { id: 'abc123', message: '初始提交', author: 'codemaster', time: '2 天前' }
])

const files = ref([
  { name: 'src', type: 'folder', message: '添加源代码目录', time: '2 天前' },
  { name: 'public', type: 'folder', message: '添加静态资源', time: '2 天前' },
  { name: '.gitignore', type: 'file', message: '添加 gitignore', time: '2 天前' },
  { name: 'README.md', type: 'file', message: '更新 README', time: '2 天前' },
  { name: 'package.json', type: 'file', message: '初始化项目', time: '2 天前' }
])

const readme = ref(`
<h1>CodeVault</h1>
<p>一个现代化的代码仓库管理平台</p>
<h2>功能特性</h2>
<ul>
<li>Git 仓库托管</li>
<li>合并请求 (Pull Request)</li>
<li>问题追踪 (Issues)</li>
</ul>
`)

const showCreateIssue = ref(false)
const showCreatePR = ref(false)

function handleTabChange(tab) {
  // 切换标签时的处理
}

function handleStar() {
  repo.stars++
  ElMessage.success('已星标')
}

function handleFork() {
  repo.forks++
  ElMessage.success('已复刻')
}

function handleClone() {
  ElMessage.success('克隆地址已复制到剪贴板')
}
</script>

<style lang="scss" scoped>
.repo-detail-page {
  max-width: 1200px;
  margin: 0 auto;
}

.repo-header {
  background: #fff;
  border: 1px solid #d0d7de;
  border-radius: 8px;
  padding: 16px 20px;
  margin-bottom: 24px;
}

.repo-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.repo-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 20px;
  
  .back-link {
    color: #57606a;
    text-decoration: none;
    
    &:hover {
      color: #0969da;
    }
  }
  
  .owner {
    color: #0969da;
    font-weight: 600;
  }
  
  .separator {
    color: #57606a;
  }
  
  .name {
    color: #0969da;
    font-weight: 600;
  }
}

.repo-actions {
  display: flex;
  gap: 8px;
}

.repo-description {
  color: #57606a;
  margin: 16px 0;
}

.repo-tabs {
  margin-top: 16px;
  
  :deep(.el-tabs__item) {
    display: flex;
    align-items: center;
    gap: 6px;
  }
}

.repo-content {
  background: #fff;
  border: 1px solid #d0d7de;
  border-radius: 8px;
  min-height: 400px;
}

.code-view {
  padding: 16px;
}

.branch-selector {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #d0d7de;
}

.commit-info {
  display: flex;
  gap: 16px;
  padding: 12px 16px;
  background: #f6f8fa;
  border: 1px solid #d0d7de;
  border-bottom: none;
  font-size: 14px;
  color: #57606a;
}

.file-list {
  border: 1px solid #d0d7de;
  border-radius: 0 0 6px 6px;
}

.file-item {
  display: flex;
  align-items: center;
  padding: 8px 16px;
  border-bottom: 1px solid #d0d7de;
  font-size: 14px;
  
  &:last-child {
    border-bottom: none;
  }
  
  &:hover {
    background: #f6f8fa;
  }
  
  .el-icon {
    margin-right: 8px;
    
    &.folder {
      color: #54aeff;
    }
    
    &.file {
      color: #57606a;
    }
  }
  
  .file-name {
    font-weight: 500;
    color: #24292f;
    width: 200px;
  }
  
  .file-message {
    flex: 1;
    color: #57606a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .file-time {
    color: #57606a;
    font-size: 12px;
  }
}

.readme-section {
  margin-top: 24px;
  border: 1px solid #d0d7de;
  border-radius: 6px;
}

.readme-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f6f8fa;
  border-bottom: 1px solid #d0d7de;
  font-weight: 600;
}

.readme-content {
  padding: 24px;
  line-height: 1.6;
}

.view-header {
  padding: 16px;
  border-bottom: 1px solid #d0d7de;
}

.settings-view {
  padding: 24px;
  
  .el-card {
    margin-bottom: 24px;
  }
  
  .danger-zone {
    border-color: #cf222e;
  }
}
</style>
