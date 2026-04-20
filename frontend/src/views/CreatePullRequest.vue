<template>
  <div class="create-pull-request">
    <el-card>
      <template #header>
        <h2>创建 Pull Request</h2>
      </template>
      
      <el-form
        ref="prForm"
        :model="pr"
        :rules="prRules"
        label-width="120px"
        v-loading="loading"
      >
        <!-- 源仓库和分支 -->
        <el-form-item label="源仓库">
          <el-select v-model="pr.source_repo" @change="loadSourceBranches">
            <el-option
              v-for="repo in repositories"
              :key="repo.id"
              :label="repo.full_name"
              :value="repo.full_name"
            />
          </el-select>
        </el-form-item>
        
        <el-form-item label="源分支">
          <el-select v-model="pr.source_branch" placeholder="选择源分支">
            <el-option
              v-for="branch in sourceBranches"
              :key="branch.name"
              :label="branch.name"
              :value="branch.name"
            />
          </el-select>
        </el-form-item>
        
        <!-- 目标仓库和分支 -->
        <el-form-item label="目标仓库">
          <el-select v-model="pr.target_repo" @change="loadTargetBranches">
            <el-option
              v-for="repo in repositories"
              :key="repo.id"
              :label="repo.full_name"
              :value="repo.full_name"
            />
          </el-select>
        </el-form-item>
        
        <el-form-item label="目标分支">
          <el-select v-model="pr.target_branch" placeholder="选择目标分支">
            <el-option
              v-for="branch in targetBranches"
              :key="branch.name"
              :label="branch.name"
              :value="branch.name"
            />
          </el-select>
        </el-form-item>
        
        <el-divider />
        
        <!-- PR 信息 -->
        <el-form-item label="标题" prop="title">
          <el-input
            v-model="pr.title"
            placeholder="Pull Request 标题"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input
            v-model="pr.description"
            type="textarea"
            :rows="8"
            placeholder="描述此 Pull Request 的变更内容..."
          />
        </el-form-item>
        
        <el-divider />
        
        <!-- 变更预览 -->
        <div v-if="changes.length > 0" class="changes-preview">
          <h3>变更预览</h3>
          <div class="changes-stats">
            <span class="additions">+{{ stats.additions }}</span>
            <span class="deletions">-{{ stats.deletions }}</span>
            <span>{{ stats.files }} 个文件变更</span>
          </div>
          
          <div class="file-changes">
            <div v-for="file in changes" :key="file.path" class="file-change">
              <div class="file-header">
                <span class="file-path">{{ file.path }}</span>
                <span class="file-stats">
                  <span class="additions">+{{ file.additions }}</span>
                  <span class="deletions">-{{ file.deletions }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>
        
        <el-form-item>
          <el-button type="primary" @click="createPR" :loading="creating">
            创建 Pull Request
          </el-button>
          <el-button @click="previewChanges" :loading="previewing">
            预览变更
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import api from '@/api/index'

const router = useRouter()
const route = useRoute()

const loading = ref(false)
const creating = ref(false)
const previewing = ref(false)

const repositories = ref<any[]>([])
const sourceBranches = ref<any[]>([])
const targetBranches = ref<any[]>([])
const changes = ref<any[]>([])

const pr = reactive({
  source_repo: '',
  source_branch: '',
  target_repo: '',
  target_branch: '',
  title: '',
  description: ''
})

const prRules = {
  title: [
    { required: true, message: '请输入标题', trigger: 'blur' },
    { min: 5, max: 100, message: '标题长度 5-100 字符', trigger: 'blur' }
  ]
}

const stats = computed(() => {
  return {
    additions: changes.value.reduce((sum, f) => sum + f.additions, 0),
    deletions: changes.value.reduce((sum, f) => sum + f.deletions, 0),
    files: changes.value.length
  }
})

async function loadRepositories() {
  loading.value = true
  try {
    const res: any = await api.get('/repos')
    if (res.code === 200) {
      repositories.value = res.data.items || []
      
      // 从 URL 参数获取默认仓库
      const owner = route.params.owner as string
      const repo = route.params.repo as string
      if (owner && repo) {
        pr.source_repo = `${owner}/${repo}`
        pr.target_repo = `${owner}/${repo}`
        await loadSourceBranches()
        await loadTargetBranches()
      }
    }
  } catch (e) {
    console.error('Failed to load repositories:', e)
  } finally {
    loading.value = false
  }
}

async function loadSourceBranches() {
  if (!pr.source_repo) return
  
  try {
    const [owner, repo] = pr.source_repo.split('/')
    const res: any = await api.get(`/repos/${owner}/${repo}/branches`)
    if (res.code === 200) {
      sourceBranches.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to load source branches:', e)
  }
}

async function loadTargetBranches() {
  if (!pr.target_repo) return
  
  try {
    const [owner, repo] = pr.target_repo.split('/')
    const res: any = await api.get(`/repos/${owner}/${repo}/branches`)
    if (res.code === 200) {
      targetBranches.value = res.data || []
      // 默认选择 main 或 master 分支
      const mainBranch = targetBranches.value.find(b => b.name === 'main' || b.name === 'master')
      if (mainBranch) {
        pr.target_branch = mainBranch.name
      }
    }
  } catch (e) {
    console.error('Failed to load target branches:', e)
  }
}

async function previewChanges() {
  if (!pr.source_repo || !pr.source_branch || !pr.target_repo || !pr.target_branch) {
    ElMessage.warning('请选择源分支和目标分支')
    return
  }
  
  previewing.value = true
  try {
    const res: any = await api.post('/pull-requests/preview', {
      source_repo: pr.source_repo,
      source_branch: pr.source_branch,
      target_repo: pr.target_repo,
      target_branch: pr.target_branch
    })
    
    if (res.code === 200) {
      changes.value = res.data.changes || []
      ElMessage.success(`找到 ${changes.value.length} 个文件变更`)
    }
  } catch (e: any) {
    ElMessage.error(e.message || '预览失败')
  } finally {
    previewing.value = false
  }
}

async function createPR() {
  creating.value = true
  try {
    const res: any = await api.post('/pull-requests', {
      source_repo: pr.source_repo,
      source_branch: pr.source_branch,
      target_repo: pr.target_repo,
      target_branch: pr.target_branch,
      title: pr.title,
      description: pr.description
    })
    
    if (res.code === 200) {
      ElMessage.success('Pull Request 创建成功')
      const [owner, repo] = pr.target_repo.split('/')
      router.push(`/repos/${owner}/${repo}/pulls/${res.data.id}`)
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  loadRepositories()
})
</script>

<style scoped>
.create-pull-request {
  padding: 20px;
}

.create-pull-request h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.changes-preview {
  margin-bottom: 24px;
}

.changes-preview h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  color: #303133;
}

.changes-stats {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
  font-size: 14px;
}

.changes-stats .additions {
  color: #67c23a;
  font-weight: 500;
}

.changes-stats .deletions {
  color: #f56c6c;
  font-weight: 500;
}

.file-changes {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  overflow: hidden;
}

.file-change {
  border-bottom: 1px solid #e4e7ed;
}

.file-change:last-child {
  border-bottom: none;
}

.file-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f5f7fa;
}

.file-path {
  font-family: monospace;
  font-size: 14px;
  color: #303133;
}

.file-stats {
  display: flex;
  gap: 12px;
  font-size: 12px;
}

.file-stats .additions {
  color: #67c23a;
}

.file-stats .deletions {
  color: #f56c6c;
}
</style>
