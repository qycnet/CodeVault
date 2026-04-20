<template>
  <div class="file-browser">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <div class="breadcrumb">
            <el-breadcrumb separator="/">
              <el-breadcrumb-item :to="{ path: `/repos/${owner}/${repo}` }">
                {{ repo }}
              </el-breadcrumb-item>
              <el-breadcrumb-item
                v-for="(segment, index) in pathSegments"
                :key="index"
              >
                <router-link
                  :to="{
                    path: `/repos/${owner}/${repo}/tree/${currentBranch}/${pathSegments.slice(0, index + 1).join('/')}`
                  }"
                >
                  {{ segment }}
                </router-link>
              </el-breadcrumb-item>
            </el-breadcrumb>
          </div>
          
          <div class="header-actions">
            <el-button type="primary" size="small" @click="goToUpload">
              <el-icon class="el-icon--left"><Upload /></el-icon>
              上传文件
            </el-button>
            
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
      
      <!-- 文件列表 -->
      <el-table
        v-if="!isFile"
        :data="files"
        style="width: 100%"
        @row-click="onRowClick"
      >
        <el-table-column label="名称" min-width="300">
          <template #default="{ row }">
            <div class="file-name">
              <el-icon v-if="row.type === 'dir'" class="folder-icon">
                <Folder />
              </el-icon>
              <el-icon v-else class="file-icon">
                <Document />
              </el-icon>
              <span>{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="message" label="最新提交信息" min-width="300" />
        <el-table-column prop="time" label="时间" width="180" />
        <el-table-column prop="size" label="大小" width="120">
          <template #default="{ row }">
            {{ row.type === 'file' ? formatSize(row.size) : '-' }}
          </template>
        </el-table-column>
      </el-table>
      
      <!-- 文件内容 -->
      <div v-else class="file-content">
        <div class="file-header">
          <div class="file-info">
            <span class="lines">{{ fileData.lines }} 行</span>
            <span class="size">{{ formatSize(fileData.size) }}</span>
            <span class="encoding">{{ fileData.encoding || 'UTF-8' }}</span>
          </div>
          
          <div class="file-actions">
            <el-button size="small" @click="copyContent">
              <el-icon><CopyDocument /></el-icon>
              复制
            </el-button>
            <el-button size="small" @click="downloadFile">
              <el-icon><Download /></el-icon>
              下载
            </el-button>
            <el-button size="small" @click="viewRaw">
              <el-icon><View /></el-icon>
              原始
            </el-button>
          </div>
        </div>
        
        <div class="code-container">
          <pre class="line-numbers">{{ lineNumbers }}</pre>
          <pre class="code-content"><code :class="`language-${fileExtension}`">{{ fileData.content }}</code></pre>
        </div>
      </div>
      
      <el-empty v-if="!loading && files.length === 0 && !isFile" description="空目录" />
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Folder, Document, CopyDocument, Download, View, Upload } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()
const router = useRouter()

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)
const currentBranch = computed(() => route.params.branch as string || 'main')
const currentPath = computed(() => route.params.path ? (route.params.path as string[]).join('/') : '')

const loading = ref(false)
const files = ref<any[]>([])
const branches = ref<string[]>([])
const selectedBranch = ref('main')
const isFile = ref(false)
const fileData = ref<any>({})

const pathSegments = computed(() => {
  return currentPath.value ? currentPath.value.split('/').filter(Boolean) : []
})

const fileExtension = computed(() => {
  if (!fileData.value.name) return 'text'
  const ext = fileData.value.name.split('.').pop()?.toLowerCase()
  return ext || 'text'
})

const lineNumbers = computed(() => {
  const lines = fileData.value.lines || 0
  return Array.from({ length: lines }, (_, i) => i + 1).join('\n')
})

function formatSize(bytes: number) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  while (bytes >= 1024 && i < units.length - 1) {
    bytes /= 1024
    i++
  }
  return `${bytes.toFixed(i > 0 ? 1 : 0)} ${units[i]}`
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

async function fetchFiles() {
  loading.value = true
  try {
    const res: any = await api.get('/repos/tree', {
      params: {
        owner: owner.value,
        repo: repo.value,
        branch: currentBranch.value,
        path: currentPath.value
      }
    })
    
    if (res.code === 200) {
      if (res.data.type === 'file') {
        isFile.value = true
        fileData.value = res.data
        files.value = []
      } else {
        isFile.value = false
        files.value = res.data.files || []
        fileData.value = {}
      }
    }
  } catch (e) {
    console.error('Failed to fetch files:', e)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

function onBranchChange() {
  router.push({
    path: `/repos/${owner.value}/${repo.value}/tree/${selectedBranch.value}`
  })
}

function onRowClick(row: any) {
  const newPath = currentPath.value ? `${currentPath.value}/${row.name}` : row.name
  
  if (row.type === 'dir') {
    router.push({
      path: `/repos/${owner.value}/${repo.value}/tree/${currentBranch.value}/${newPath}`
    })
  } else {
    router.push({
      path: `/repos/${owner.value}/${repo.value}/blob/${currentBranch.value}/${newPath}`
    })
  }
}

function copyContent() {
  navigator.clipboard.writeText(fileData.value.content)
  ElMessage.success('已复制到剪贴板')
}

function downloadFile() {
  const blob = new Blob([fileData.value.content], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = fileData.value.name
  a.click()
  URL.revokeObjectURL(url)
}

function viewRaw() {
  const blob = new Blob([fileData.value.content], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank')
}

function goToUpload() {
  router.push({
    path: `/repos/${owner.value}/${repo.value}/upload`,
    query: {
      branch: currentBranch.value,
      path: currentPath.value
    }
  })
}

watch([currentBranch, currentPath], () => {
  fetchFiles()
})

onMounted(() => {
  selectedBranch.value = currentBranch.value
  fetchBranches()
  fetchFiles()
})
</script>

<style scoped>
.file-browser {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.breadcrumb {
  flex: 1;
}

.breadcrumb a {
  color: #409eff;
  text-decoration: none;
}

.breadcrumb a:hover {
  text-decoration: underline;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-name {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.folder-icon {
  color: #e6a23c;
  font-size: 18px;
}

.file-icon {
  color: #909399;
  font-size: 18px;
}

.file-content {
  margin-top: 0;
}

.file-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f5f7fa;
  border-radius: 4px 4px 0 0;
  border: 1px solid #e4e7ed;
  border-bottom: none;
}

.file-info {
  display: flex;
  gap: 16px;
  color: #606266;
  font-size: 13px;
}

.file-actions {
  display: flex;
  gap: 8px;
}

.code-container {
  display: flex;
  border: 1px solid #e4e7ed;
  border-radius: 0 0 4px 4px;
  overflow: hidden;
}

.line-numbers {
  padding: 16px 12px;
  margin: 0;
  background: #fafafa;
  color: #909399;
  text-align: right;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
  line-height: 1.6;
  user-select: none;
  border-right: 1px solid #e4e7ed;
  min-width: 50px;
}

.code-content {
  flex: 1;
  padding: 16px;
  margin: 0;
  overflow-x: auto;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
  line-height: 1.6;
  background: #fff;
}

.code-content code {
  display: block;
  white-space: pre;
}
</style>
