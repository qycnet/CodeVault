<template>
  <div class="repo-detail">
    <el-card v-loading="loading">
      <template #header>
        <div class="repo-header">
          <div class="repo-title">
            <h2>{{ owner }} / {{ repoName }}</h2>
            <el-tag :type="repo?.is_private ? 'danger' : 'success'">
              {{ repo?.is_private ? '私有' : '公开' }}
            </el-tag>
          </div>
          <div class="repo-actions">
            <el-button @click="cloneDialogVisible = true">克隆</el-button>
          </div>
        </div>
      </template>
      
      <el-tabs v-model="activeTab">
        <el-tab-pane label="代码" name="code">
          <div class="branch-selector">
            <el-select v-model="currentBranch" @change="fetchFiles">
              <el-option
                v-for="branch in branches"
                :key="branch"
                :label="branch"
                :value="branch"
              />
            </el-select>
          </div>
          
          <el-table :data="files" style="width: 100%">
            <el-table-column prop="name" label="名称">
              <template #default="{ row }">
                <el-icon v-if="row.type === 'dir'"><Folder /></el-icon>
                <el-icon v-else><Document /></el-icon>
                <span style="margin-left: 8px">{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="message" label="提交信息" />
            <el-table-column prop="time" label="时间" width="180" />
          </el-table>
        </el-tab-pane>
        
        <el-tab-pane label="Issues" name="issues">
          <div class="tab-header">
            <el-button type="primary" @click="goToIssues">查看 Issues</el-button>
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="Pull Requests" name="pulls">
          <div class="tab-header">
            <el-button type="primary" @click="goToPulls">查看 Pull Requests</el-button>
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="提交" name="commits">
          <el-table :data="commits" style="width: 100%">
            <el-table-column prop="hash" label="SHA" width="100">
              <template #default="{ row }">
                <span class="commit-hash">{{ row.hash?.substring(0, 7) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="message" label="提交信息" />
            <el-table-column prop="author" label="作者" width="120" />
            <el-table-column prop="time" label="时间" width="180" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
    
    <!-- 克隆对话框 -->
    <el-dialog v-model="cloneDialogVisible" title="克隆仓库" width="500px">
      <el-form label-width="100px">
        <el-form-item label="SSH">
          <el-input
            :model-value="`git clone ssh://git@codevault.local:2222/${owner}/${repoName}.git`"
            readonly
          >
            <template #append>
              <el-button @click="copyCloneUrl('ssh')">复制</el-button>
            </template>
          </el-input>
        </el-form-item>
      </el-form>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Folder, Document } from '@element-plus/icons-vue'
import { repoApi } from '@/api/repo'
import type { Repository } from '@/api/types'

const route = useRoute()
const router = useRouter()

const owner = computed(() => route.params.owner as string)
const repoName = computed(() => route.params.repo as string)

const loading = ref(false)
const repo = ref<Repository | null>(null)
const activeTab = ref('code')
const branches = ref<string[]>([])
const currentBranch = ref('main')
const files = ref<any[]>([])
const commits = ref<any[]>([])
const cloneDialogVisible = ref(false)

async function fetchRepo() {
  loading.value = true
  try {
    const res = await repoApi.detail(owner.value, repoName.value)
    if (res.code === 200) {
      repo.value = res.data
    }
  } catch (e) {
    ElMessage.error('获取仓库信息失败')
  } finally {
    loading.value = false
  }
}

async function fetchBranches() {
  try {
    const res = await repoApi.branches(owner.value, repoName.value)
    if (res.code === 200) {
      branches.value = res.data
      if (branches.value.length > 0) {
        currentBranch.value = branches.value[0]
      }
    }
  } catch (e) {
    console.error('Failed to fetch branches:', e)
  }
}

async function fetchFiles() {
  // TODO: 实现文件列表获取
  files.value = []
}

async function fetchCommits() {
  try {
    const res = await repoApi.log(owner.value, repoName.value, currentBranch.value)
    if (res.code === 200) {
      commits.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch commits:', e)
  }
}

function goToIssues() {
  router.push(`/repos/${owner.value}/${repoName.value}/issues`)
}

function goToPulls() {
  router.push(`/repos/${owner.value}/${repoName.value}/pulls`)
}

function copyCloneUrl(_type: string) {
  const url = `git clone ssh://git@codevault.local:2222/${owner.value}/${repoName.value}.git`
  navigator.clipboard.writeText(url)
  ElMessage.success('已复制到剪贴板')
}

onMounted(() => {
  fetchRepo()
  fetchBranches()
  fetchCommits()
})
</script>

<style scoped>
.repo-detail {
  padding: 20px;
}

.repo-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.repo-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.repo-title h2 {
  margin: 0;
}

.branch-selector {
  margin-bottom: 16px;
}

.tab-header {
  padding: 20px;
  text-align: center;
}

.commit-hash {
  font-family: monospace;
  color: #409eff;
}
</style>
