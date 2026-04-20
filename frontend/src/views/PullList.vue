<template>
  <div class="pull-list">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>Pull Requests</span>
          <el-button type="primary" @click="showCreateDialog = true">
            新建 Pull Request
          </el-button>
        </div>
      </template>
      
      <el-table :data="pulls" v-loading="loading" style="width: 100%">
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="300">
          <template #default="{ row }">
            <router-link :to="`/repos/${owner}/${repo}/pulls/${row.id}`">
              {{ row.title }}
            </router-link>
          </template>
        </el-table-column>
        <el-table-column label="分支" width="200">
          <template #default="{ row }">
            <span class="branch">{{ row.source_branch }}</span>
            →
            <span class="branch">{{ row.target_branch }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="author_name" label="作者" width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180" />
      </el-table>
      
      <el-empty v-if="!loading && pulls.length === 0" description="暂无 Pull Request" />
    </el-card>
    
    <!-- 创建 PR 对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建 Pull Request" width="600px">
      <el-form :model="newPR" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="newPR.title" placeholder="PR 标题" />
        </el-form-item>
        <el-form-item label="源分支" prop="source_branch">
          <el-select v-model="newPR.source_branch" placeholder="选择源分支">
            <el-option v-for="b in branches" :key="b" :label="b" :value="b" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标分支" prop="target_branch">
          <el-select v-model="newPR.target_branch" placeholder="选择目标分支">
            <el-option v-for="b in branches" :key="b" :label="b" :value="b" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="newPR.description" type="textarea" :rows="6" placeholder="详细描述..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createPR">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import api from '@/api/index'
import { repoApi } from '@/api/repo'
import type { PullRequest } from '@/api/types'

const route = useRoute()
const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const pulls = ref<PullRequest[]>([])
const branches = ref<string[]>([])
const formRef = ref()

const newPR = reactive({
  title: '',
  description: '',
  source_branch: '',
  target_branch: 'main'
})

const rules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
  source_branch: [{ required: true, message: '请选择源分支', trigger: 'change' }],
  target_branch: [{ required: true, message: '请选择目标分支', trigger: 'change' }]
}

function getStatusType(status: string) {
  switch (status) {
    case 'open': return 'success'
    case 'merged': return 'primary'
    case 'closed': return 'info'
    default: return 'info'
  }
}

function getStatusText(status: string) {
  switch (status) {
    case 'open': return '开启'
    case 'merged': return '已合并'
    case 'closed': return '已关闭'
    default: return status
  }
}

async function fetchPulls() {
  loading.value = true
  try {
    const res: any = await api.get('/pull-requests', { params: { owner: owner.value, repo: repo.value } })
    if (res.code === 200) {
      pulls.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch PRs:', e)
  } finally {
    loading.value = false
  }
}

async function fetchBranches() {
  try {
    const res = await repoApi.branches(owner.value, repo.value)
    if (res.code === 200) {
      branches.value = res.data
    }
  } catch (e) {
    console.error('Failed to fetch branches:', e)
  }
}

async function createPR() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
  creating.value = true
  try {
    const res: any = await api.post('/pull-requests', {
      ...newPR,
      owner: owner.value,
      repo: repo.value
    })
    if (res.code === 200) {
      ElMessage.success('Pull Request 创建成功')
      showCreateDialog.value = false
      newPR.title = ''
      newPR.description = ''
      newPR.source_branch = ''
      fetchPulls()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  fetchPulls()
  fetchBranches()
})
</script>

<style scoped>
.pull-list {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header a {
  color: #409eff;
  text-decoration: none;
}

.card-header a:hover {
  text-decoration: underline;
}

.branch {
  font-family: monospace;
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
}
</style>
