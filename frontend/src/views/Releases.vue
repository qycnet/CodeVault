<template>
  <div class="releases-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>版本发布</h2>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            创建发布
          </el-button>
        </div>
      </template>
      
      <!-- 发布列表 -->
      <div v-loading="loading" class="releases-list">
        <div v-for="release in releases" :key="release.id" class="release-item">
          <div class="release-header">
            <div class="release-title">
              <el-tag :type="release.is_prerelease ? 'warning' : 'success'">
                {{ release.tag_name }}
              </el-tag>
              <h3>{{ release.name }}</h3>
            </div>
            <div class="release-meta">
              <span v-if="release.is_latest" class="latest-badge">最新</span>
              <span v-if="release.is_prerelease" class="prerelease-badge">预发布</span>
              <span class="release-date">{{ formatDate(release.created_at) }}</span>
            </div>
          </div>
          
          <div class="release-body" v-html="renderMarkdown(release.body)"></div>
          
          <div class="release-assets">
            <h4>资源文件</h4>
            <div class="asset-list">
              <div v-for="asset in release.assets" :key="asset.id" class="asset-item">
                <el-icon><Document /></el-icon>
                <span class="asset-name">{{ asset.name }}</span>
                <span class="asset-size">{{ formatSize(asset.size) }}</span>
                <el-button type="primary" link @click="downloadAsset(asset)">
                  下载
                </el-button>
              </div>
            </div>
          </div>
          
          <div class="release-actions">
            <el-button type="primary" link @click="editRelease(release)">
              编辑
            </el-button>
            <el-button type="danger" link @click="deleteRelease(release)">
              删除
            </el-button>
          </div>
        </div>
        
        <el-empty v-if="!loading && releases.length === 0" description="暂无发布版本" />
      </div>
    </el-card>
    
    <!-- 创建发布对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingRelease ? '编辑发布' : '创建发布'"
      width="600px"
    >
      <el-form
        ref="releaseForm"
        :model="newRelease"
        :rules="releaseRules"
        label-width="120px"
      >
        <el-form-item label="标签版本" prop="tag_name">
          <el-input v-model="newRelease.tag_name" placeholder="v1.0.0" />
        </el-form-item>
        
        <el-form-item label="发布标题" prop="name">
          <el-input v-model="newRelease.name" placeholder="版本 1.0.0" />
        </el-form-item>
        
        <el-form-item label="目标分支">
          <el-select v-model="newRelease.target_branch">
            <el-option
              v-for="branch in branches"
              :key="branch.name"
              :label="branch.name"
              :value="branch.name"
            />
          </el-select>
        </el-form-item>
        
        <el-form-item label="发布说明">
          <el-input
            v-model="newRelease.body"
            type="textarea"
            :rows="8"
            placeholder="描述此版本的变更内容..."
          />
        </el-form-item>
        
        <el-form-item label="预发布">
          <el-switch v-model="newRelease.is_prerelease" />
        </el-form-item>
        
        <el-form-item label="设为最新">
          <el-switch v-model="newRelease.is_latest" />
        </el-form-item>
        
        <el-form-item label="附件">
          <el-upload
            :action="uploadUrl"
            :on-success="handleUploadSuccess"
            :file-list="fileList"
            multiple
          >
            <el-button type="primary">上传文件</el-button>
          </el-upload>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createRelease" :loading="creating">
          {{ editingRelease ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Document } from '@element-plus/icons-vue'
import DOMPurify from 'dompurify'
import api from '@/api/index'

const route = useRoute()

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const editingRelease = ref<any>(null)

const releases = ref<any[]>([])
const branches = ref<any[]>([])
const fileList = ref<any[]>([])

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)
const uploadUrl = computed(() => `/api/repos/${owner.value}/${repo.value}/releases/upload`)

const newRelease = reactive({
  tag_name: '',
  name: '',
  target_branch: 'main',
  body: '',
  is_prerelease: false,
  is_latest: false,
  assets: [] as any[]
})

const releaseRules = {
  tag_name: [
    { required: true, message: '请输入标签版本', trigger: 'blur' }
  ],
  name: [
    { required: true, message: '请输入发布标题', trigger: 'blur' }
  ]
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function renderMarkdown(content: string) {
  if (!content) return ''
  // 简单的 Markdown 渲染
  const html = content
    .replace(/\n/g, '<br>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    .replace(/## (.+)/g, '<h3>$1</h3>')
    .replace(/# (.+)/g, '<h2>$1</h2>')
  
  return DOMPurify.sanitize(html)
}

async function fetchReleases() {
  loading.value = true
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/releases`)
    if (res.code === 200) {
      releases.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch releases:', e)
  } finally {
    loading.value = false
  }
}

async function fetchBranches() {
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/branches`)
    if (res.code === 200) {
      branches.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch branches:', e)
  }
}

function handleUploadSuccess(res: any) {
  if (res.code === 200) {
    newRelease.assets.push(res.data)
    ElMessage.success('文件上传成功')
  }
}

async function createRelease() {
  creating.value = true
  try {
    const url = editingRelease.value
      ? `/repos/${owner.value}/${repo.value}/releases/${editingRelease.value.id}`
      : `/repos/${owner.value}/${repo.value}/releases`
    
    const method = editingRelease.value ? 'put' : 'post'
    const res: any = await api[method](url, newRelease)
    
    if (res.code === 200) {
      ElMessage.success(editingRelease.value ? '发布已更新' : '发布创建成功')
      showCreateDialog.value = false
      resetForm()
      fetchReleases()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    creating.value = false
  }
}

function editRelease(release: any) {
  editingRelease.value = release
  Object.assign(newRelease, release)
  fileList.value = release.assets?.map((a: any) => ({ name: a.name, url: a.url })) || []
  showCreateDialog.value = true
}

async function deleteRelease(release: any) {
  try {
    await ElMessageBox.confirm('确定删除此发布吗？', '删除确认', { type: 'warning' })
    
    const res: any = await api.delete(`/repos/${owner.value}/${repo.value}/releases/${release.id}`)
    if (res.code === 200) {
      ElMessage.success('发布已删除')
      fetchReleases()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function downloadAsset(asset: any) {
  window.open(asset.url, '_blank')
}

function resetForm() {
  editingRelease.value = null
  newRelease.tag_name = ''
  newRelease.name = ''
  newRelease.target_branch = 'main'
  newRelease.body = ''
  newRelease.is_prerelease = false
  newRelease.is_latest = false
  newRelease.assets = []
  fileList.value = []
}

onMounted(() => {
  fetchReleases()
  fetchBranches()
})
</script>

<style scoped>
.releases-page {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.releases-list {
  min-height: 200px;
}

.release-item {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 16px;
}

.release-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.release-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.release-title h3 {
  margin: 0;
  font-size: 18px;
  color: #303133;
}

.release-meta {
  display: flex;
  gap: 8px;
  align-items: center;
}

.latest-badge {
  background: #67c23a;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.prerelease-badge {
  background: #e6a23c;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.release-date {
  color: #909399;
  font-size: 13px;
}

.release-body {
  color: #606266;
  line-height: 1.6;
  margin-bottom: 16px;
}

.release-assets {
  background: #f5f7fa;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 16px;
}

.release-assets h4 {
  margin: 0 0 12px 0;
  font-size: 14px;
  color: #303133;
}

.asset-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.asset-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  background: white;
  border-radius: 4px;
}

.asset-name {
  flex: 1;
  font-family: monospace;
  font-size: 13px;
}

.asset-size {
  color: #909399;
  font-size: 12px;
}

.release-actions {
  display: flex;
  gap: 12px;
}
</style>
