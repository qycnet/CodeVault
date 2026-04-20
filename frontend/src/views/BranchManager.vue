<template>
  <div class="branch-manager">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <h3>分支管理</h3>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            新建分支
          </el-button>
        </div>
      </template>
      
      <el-table :data="branches" style="width: 100%">
        <el-table-column label="分支名称" min-width="200">
          <template #default="{ row }">
            <div class="branch-name">
              <el-icon v-if="row.is_default" class="default-icon">
                <Star />
              </el-icon>
              <span class="name">{{ row.name }}</span>
              <el-tag v-if="row.is_default" size="small" type="success">默认</el-tag>
              <el-tag v-if="row.is_protected" size="small" type="warning">受保护</el-tag>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column label="最新提交" min-width="300">
          <template #default="{ row }">
            <div class="commit-info" v-if="row.last_commit">
              <span class="hash">{{ row.last_commit.hash }}</span>
              <span class="message">{{ row.last_commit.message }}</span>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="last_commit.time" label="更新时间" width="180" />
        
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button
              size="small"
              @click="switchBranch(row.name)"
              :disabled="row.is_current"
            >
              {{ row.is_current ? '当前' : '切换' }}
            </el-button>
            <el-button
              size="small"
              @click="viewCommits(row.name)"
            >
              提交
            </el-button>
            <el-button
              v-if="!row.is_default && !row.is_protected"
              size="small"
              type="danger"
              @click="deleteBranch(row.name)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <el-empty v-if="!loading && branches.length === 0" description="暂无分支" />
    </el-card>
    
    <!-- 创建分支对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建分支" width="500px">
      <el-form :model="newBranch" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="分支名称" prop="name">
          <el-input v-model="newBranch.name" placeholder="输入分支名称" />
        </el-form-item>
        
        <el-form-item label="基于分支" prop="source">
          <el-select v-model="newBranch.source" placeholder="选择源分支">
            <el-option
              v-for="branch in branches"
              :key="branch.name"
              :label="branch.name"
              :value="branch.name"
            />
          </el-select>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createBranch">
          创建
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Star } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()
const router = useRouter()

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const branches = ref<any[]>([])
const formRef = ref()

const newBranch = reactive({
  name: '',
  source: 'main'
})

const rules = {
  name: [
    { required: true, message: '请输入分支名称', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9_\-/.]+$/, message: '分支名称只能包含字母、数字、下划线、连字符、斜杠和点', trigger: 'blur' }
  ],
  source: [
    { required: true, message: '请选择源分支', trigger: 'change' }
  ]
}

async function fetchBranches() {
  loading.value = true
  try {
    const res: any = await api.get('/repos/branches', {
      params: { owner: owner.value, repo: repo.value }
    })
    
    if (res.code === 200) {
      // 获取每个分支的详细信息
      const branchNames = res.data || []
      const branchDetails = await Promise.all(
        branchNames.map(async (name: string) => {
          try {
            const commitRes: any = await api.get('/repos/branch/commit', {
              params: {
                owner: owner.value,
                repo: repo.value,
                branch: name
              }
            })
            
            return {
              name,
              is_default: name === 'main' || name === 'master',
              is_protected: name === 'main' || name === 'master',
              is_current: false,
              last_commit: commitRes.code === 200 ? commitRes.data : null
            }
          } catch (e) {
            return {
              name,
              is_default: name === 'main' || name === 'master',
              is_protected: name === 'main' || name === 'master',
              is_current: false,
              last_commit: null
            }
          }
        })
      )
      
      branches.value = branchDetails
    }
  } catch (e) {
    console.error('Failed to fetch branches:', e)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

async function createBranch() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
  creating.value = true
  try {
    const res: any = await api.post('/repos/branch', {
      owner: owner.value,
      repo: repo.value,
      name: newBranch.name,
      source: newBranch.source
    })
    
    if (res.code === 200) {
      ElMessage.success('分支创建成功')
      showCreateDialog.value = false
      newBranch.name = ''
      fetchBranches()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

function switchBranch(branchName: string) {
  router.push({
    path: `/repos/${owner.value}/${repo.value}/tree/${branchName}`
  })
}

function viewCommits(branchName: string) {
  router.push({
    path: `/repos/${owner.value}/${repo.value}/commits/${branchName}`
  })
}

async function deleteBranch(branchName: string) {
  try {
    await ElMessageBox.confirm(
      `确定要删除分支 "${branchName}" 吗？此操作不可撤销。`,
      '删除分支',
      { type: 'warning' }
    )
    
    const res: any = await api.delete('/repos/branch', {
      data: {
        owner: owner.value,
        repo: repo.value,
        name: branchName
      }
    })
    
    if (res.code === 200) {
      ElMessage.success('分支已删除')
      fetchBranches()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

onMounted(() => {
  fetchBranches()
})
</script>

<style scoped>
.branch-manager {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
}

.branch-name {
  display: flex;
  align-items: center;
  gap: 8px;
}

.branch-name .name {
  font-family: monospace;
  font-weight: 500;
}

.default-icon {
  color: #e6a23c;
}

.commit-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.commit-info .hash {
  font-family: monospace;
  font-size: 13px;
  color: #409eff;
  background: #ecf5ff;
  padding: 2px 6px;
  border-radius: 4px;
}

.commit-info .message {
  color: #606266;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px;
}
</style>
