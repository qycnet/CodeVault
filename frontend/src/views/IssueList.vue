<template>
  <div class="issue-list">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>Issues</span>
          <el-button type="primary" @click="showCreateDialog = true">
            新建 Issue
          </el-button>
        </div>
      </template>
      
      <el-table :data="issues" v-loading="loading" style="width: 100%">
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'open' ? 'success' : 'info'" size="small">
              {{ row.status === 'open' ? '开启' : '关闭' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="300">
          <template #default="{ row }">
            <router-link :to="`/repos/${owner}/${repo}/issues/${row.id}`">
              {{ row.title }}
            </router-link>
          </template>
        </el-table-column>
        <el-table-column prop="author_name" label="作者" width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180" />
      </el-table>
      
      <el-empty v-if="!loading && issues.length === 0" description="暂无 Issue" />
    </el-card>
    
    <!-- 创建 Issue 对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建 Issue" width="600px">
      <el-form :model="newIssue" :rules="rules" ref="formRef" label-width="80px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="newIssue.title" placeholder="Issue 标题" />
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input v-model="newIssue.content" type="textarea" :rows="8" placeholder="详细描述..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createIssue">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import api from '@/api/index'
import type { Issue } from '@/api/types'

const route = useRoute()
const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const issues = ref<Issue[]>([])
const formRef = ref()

const newIssue = reactive({
  title: '',
  content: ''
})

const rules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入内容', trigger: 'blur' }]
}

async function fetchIssues() {
  loading.value = true
  try {
    const res: any = await api.get('/issues', { params: { owner: owner.value, repo: repo.value } })
    if (res.code === 200) {
      issues.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch issues:', e)
  } finally {
    loading.value = false
  }
}

async function createIssue() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
  creating.value = true
  try {
    const res: any = await api.post('/issues', {
      ...newIssue,
      owner: owner.value,
      repo: repo.value
    })
    if (res.code === 200) {
      ElMessage.success('Issue 创建成功')
      showCreateDialog.value = false
      newIssue.title = ''
      newIssue.content = ''
      fetchIssues()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  fetchIssues()
})
</script>

<style scoped>
.issue-list {
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
</style>
