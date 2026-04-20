<template>
  <div class="branch-protection-page">
    <div class="page-header">
      <h2>分支保护规则</h2>
      <p class="repo-path">
        <router-link :to="`/repos/${owner}/${repo}`">{{ owner }}/{{ repo }}</router-link>
      </p>
    </div>

    <el-card class="rules-card">
      <template #header>
        <div class="card-header">
          <span>保护规则列表</span>
          <el-button type="primary" size="small" @click="showCreateDialog">
            <el-icon class="el-icon--left"><Plus /></el-icon>
            添加规则
          </el-button>
        </div>
      </template>

      <el-table :data="rules" v-loading="loading">
        <el-table-column prop="branch_name" label="分支" width="200">
          <template #default="{ row }">
            <el-tag type="primary">{{ row.branch_name }}</el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="要求 PR" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.require_pr ? 'success' : 'info'" size="small">
              {{ row.require_pr ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column prop="required_reviewers" label="审查者数量" width="120" align="center" />
        
        <el-table-column label="状态检查" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.require_status_checks ? 'success' : 'info'" size="small">
              {{ row.require_status_checks ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="管理员限制" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.enforce_admins ? 'warning' : 'info'" size="small">
              {{ row.enforce_admins ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="允许强制推送" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="row.allow_force_pushes ? 'danger' : 'success'" size="small">
              {{ row.allow_force_pushes ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="允许删除" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.allow_deletions ? 'danger' : 'success'" size="small">
              {{ row.allow_deletions ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="editRule(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="deleteRule(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-empty v-if="!loading && rules.length === 0" description="暂无保护规则" />
    </el-card>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingRule ? '编辑保护规则' : '添加保护规则'"
      width="600px"
    >
      <el-form :model="form" label-width="140px">
        <el-form-item label="分支名称">
          <el-select
            v-model="form.branch_name"
            placeholder="选择要保护的分支"
            :disabled="!!editingRule"
          >
            <el-option
              v-for="branch in branches"
              :key="branch"
              :label="branch"
              :value="branch"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="要求 PR">
          <el-switch v-model="form.require_pr" />
          <div class="form-tip">要求所有更改必须通过 Pull Request 提交</div>
        </el-form-item>

        <el-form-item label="审查者数量">
          <el-input-number v-model="form.required_reviewers" :min="0" :max="10" />
          <div class="form-tip">合并 PR 前需要多少个审查者批准</div>
        </el-form-item>

        <el-form-item label="清除旧审批">
          <el-switch v-model="form.dismiss_stale_reviews" />
          <div class="form-tip">当有新提交时，自动清除之前的审查批准</div>
        </el-form-item>

        <el-form-item label="要求状态检查">
          <el-switch v-model="form.require_status_checks" />
          <div class="form-tip">要求 CI/CD 状态检查通过后才能合并</div>
        </el-form-item>

        <el-form-item label="限制管理员">
          <el-switch v-model="form.enforce_admins" />
          <div class="form-tip">对仓库管理员也强制执行这些规则</div>
        </el-form-item>

        <el-form-item label="允许强制推送">
          <el-switch v-model="form.allow_force_pushes" />
          <div class="form-tip warning">允许 git push --force，可能导致数据丢失</div>
        </el-form-item>

        <el-form-item label="允许删除分支">
          <el-switch v-model="form.allow_deletions" />
          <div class="form-tip warning">允许删除此分支</div>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveRule" :loading="saving">
          {{ editingRule ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import axios from 'axios'

const route = useRoute()

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const loading = ref(false)
const saving = ref(false)
const rules = ref<any[]>([])
const branches = ref<string[]>([])
const dialogVisible = ref(false)
const editingRule = ref<any>(null)

const form = reactive({
  branch_name: '',
  require_pr: true,
  required_reviewers: 0,
  dismiss_stale_reviews: false,
  require_status_checks: false,
  enforce_admins: false,
  allow_force_pushes: false,
  allow_deletions: false
})

// 获取仓库 ID
async function getRepoId(): Promise<number> {
  const response = await axios.get('/api/repos/detail', {
    params: {
      owner: owner.value,
      repo: repo.value
    }
  })
  
  if (!response.data.success) {
    throw new Error('获取仓库信息失败')
  }
  
  return response.data.repo.id
}

// 获取分支列表
async function fetchBranches() {
  try {
    const response = await axios.get('/api/repos/branches', {
      params: {
        owner: owner.value,
        repo: repo.value
      }
    })
    
    if (response.data.code === 200) {
      branches.value = response.data.data || []
    }
  } catch (error) {
    console.error('获取分支失败:', error)
  }
}

// 获取保护规则列表
async function fetchRules() {
  loading.value = true
  try {
    const repoId = await getRepoId()
    const response = await axios.get('/api/branch-protection', {
      params: { repo_id: repoId }
    })
    
    if (response.data.success) {
      rules.value = response.data.rules || []
    }
  } catch (error) {
    ElMessage.error('获取保护规则失败')
  } finally {
    loading.value = false
  }
}

// 显示创建对话框
function showCreateDialog() {
  editingRule.value = null
  Object.assign(form, {
    branch_name: '',
    require_pr: true,
    required_reviewers: 0,
    dismiss_stale_reviews: false,
    require_status_checks: false,
    enforce_admins: false,
    allow_force_pushes: false,
    allow_deletions: false
  })
  dialogVisible.value = true
}

// 编辑规则
function editRule(rule: any) {
  editingRule.value = rule
  Object.assign(form, {
    branch_name: rule.branch_name,
    require_pr: rule.require_pr,
    required_reviewers: rule.required_reviewers,
    dismiss_stale_reviews: rule.dismiss_stale_reviews,
    require_status_checks: rule.require_status_checks,
    enforce_admins: rule.enforce_admins,
    allow_force_pushes: rule.allow_force_pushes,
    allow_deletions: rule.allow_deletions
  })
  dialogVisible.value = true
}

// 保存规则
async function saveRule() {
  if (!form.branch_name) {
    ElMessage.warning('请选择分支')
    return
  }
  
  saving.value = true
  try {
    const repoId = await getRepoId()
    
    if (editingRule.value) {
      // 更新
      const response = await axios.put('/api/branch-protection', {
        rule_id: editingRule.value.id,
        ...form
      })
      
      if (response.data.success) {
        ElMessage.success('规则更新成功')
        dialogVisible.value = false
        fetchRules()
      } else {
        ElMessage.error(response.data.message || '更新失败')
      }
    } else {
      // 创建
      const response = await axios.post('/api/branch-protection', {
        repo_id: repoId,
        ...form
      })
      
      if (response.data.success) {
        ElMessage.success('规则创建成功')
        dialogVisible.value = false
        fetchRules()
      } else {
        ElMessage.error(response.data.message || '创建失败')
      }
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    saving.value = false
  }
}

// 删除规则
async function deleteRule(rule: any) {
  try {
    await ElMessageBox.confirm(
      `确定要删除分支 "${rule.branch_name}" 的保护规则吗？`,
      '确认删除',
      {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await axios.delete('/api/branch-protection', {
      params: { rule_id: rule.id }
    })
    
    if (response.data.success) {
      ElMessage.success('规则已删除')
      fetchRules()
    } else {
      ElMessage.error(response.data.message || '删除失败')
    }
  } catch (error) {
    // 用户取消
  }
}

onMounted(() => {
  fetchBranches()
  fetchRules()
})
</script>

<style lang="scss" scoped>
.branch-protection-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.page-header {
  margin-bottom: 24px;
  
  h2 {
    margin: 0 0 8px 0;
    color: #24292f;
  }
  
  .repo-path {
    color: #57606a;
    font-size: 14px;
    
    a {
      color: #0969da;
      text-decoration: none;
      
      &:hover {
        text-decoration: underline;
      }
    }
  }
}

.rules-card {
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  
  &.warning {
    color: #e6a23c;
  }
}

:deep(.el-table) {
  .el-button + .el-button {
    margin-left: 8px;
  }
}
</style>
