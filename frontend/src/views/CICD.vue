<template>
  <div class="ci-cd">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <h3>CI/CD 工作流</h3>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            新建工作流
          </el-button>
        </div>
      </template>
      
      <el-tabs v-model="activeTab">
        <el-tab-pane label="工作流" name="workflows">
          <el-table :data="workflows" style="width: 100%">
            <el-table-column prop="name" label="工作流名称" min-width="200" />
            <el-table-column prop="trigger" label="触发条件" width="150" />
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'">
                  {{ row.status === 'active' ? '启用' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="last_run" label="最后运行" width="180" />
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="runWorkflow(row)">运行</el-button>
                <el-button size="small" @click="editWorkflow(row)">编辑</el-button>
                <el-button size="small" type="danger" @click="deleteWorkflow(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
        
        <el-tab-pane label="运行记录" name="runs">
          <el-table :data="runs" style="width: 100%">
            <el-table-column prop="workflow_name" label="工作流" min-width="150" />
            <el-table-column prop="commit" label="提交" width="120">
              <template #default="{ row }">
                <span class="commit-hash">{{ row.commit }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="getStatusType(row.status)">
                  {{ getStatusText(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="duration" label="耗时" width="100" />
            <el-table-column prop="triggered_by" label="触发者" width="120" />
            <el-table-column prop="created_at" label="开始时间" width="180" />
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="viewLogs(row)">日志</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
    
    <!-- 创建工作流对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建工作流" width="800px">
      <el-form :model="workflow" label-width="120px">
        <el-form-item label="工作流名称">
          <el-input v-model="workflow.name" placeholder="例如：CI、Deploy" />
        </el-form-item>
        
        <el-form-item label="触发条件">
          <el-checkbox-group v-model="workflow.triggers">
            <el-checkbox label="push">Push</el-checkbox>
            <el-checkbox label="pull_request">Pull Request</el-checkbox>
            <el-checkbox label="schedule">定时触发</el-checkbox>
            <el-checkbox label="manual">手动触发</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        
        <el-form-item label="分支过滤">
          <el-input v-model="workflow.branches" placeholder="例如：main, develop" />
        </el-form-item>
        
        <el-form-item label="工作流定义">
          <el-input
            v-model="workflow.yaml"
            type="textarea"
            :rows="15"
            placeholder="name: CI
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Install dependencies
        run: npm install
      - name: Run tests
        run: npm test"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createWorkflow">创建</el-button>
      </template>
    </el-dialog>
    
    <!-- 日志查看对话框 -->
    <el-dialog v-model="showLogsDialog" title="运行日志" width="900px">
      <div class="logs-container">
        <pre class="logs">{{ currentLogs }}</pre>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()
const owner = route.params.owner as string
const repo = route.params.repo as string

const loading = ref(false)
const activeTab = ref('workflows')
const showCreateDialog = ref(false)
const showLogsDialog = ref(false)
const currentLogs = ref('')

const workflows = ref([
  { id: 1, name: 'CI', trigger: 'push, pull_request', status: 'active', last_run: '2026-04-20 20:30' },
  { id: 2, name: 'Deploy', trigger: 'push (main)', status: 'active', last_run: '2026-04-20 19:45' },
])

const runs = ref([
  { id: 1, workflow_name: 'CI', commit: 'a1b2c3d', status: 'success', duration: '2m 30s', triggered_by: '任', created_at: '2026-04-20 20:30' },
  { id: 2, workflow_name: 'CI', commit: 'e4f5g6h', status: 'failed', duration: '1m 15s', triggered_by: '任', created_at: '2026-04-20 19:30' },
  { id: 3, workflow_name: 'Deploy', commit: 'a1b2c3d', status: 'success', duration: '5m 20s', triggered_by: '任', created_at: '2026-04-20 20:35' },
])

const workflow = reactive({
  name: '',
  triggers: ['push'],
  branches: 'main',
  yaml: ''
})

function getStatusType(status: string) {
  const types: Record<string, string> = {
    success: 'success',
    failed: 'danger',
    running: 'warning',
    pending: 'info'
  }
  return types[status] || 'info'
}

function getStatusText(status: string) {
  const texts: Record<string, string> = {
    success: '成功',
    failed: '失败',
    running: '运行中',
    pending: '等待中'
  }
  return texts[status] || status
}

async function createWorkflow() {
  if (!workflow.name) {
    ElMessage.warning('请输入工作流名称')
    return
  }
  
  try {
    const res: any = await api.post('/repos/ci/workflow', {
      owner,
      repo,
      name: workflow.name,
      triggers: workflow.triggers,
      branches: workflow.branches,
      yaml: workflow.yaml
    })
    
    if (res.code === 200) {
      ElMessage.success('工作流创建成功')
      showCreateDialog.value = false
      fetchWorkflows()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  }
}

async function runWorkflow(row: any) {
  try {
    await ElMessageBox.confirm('确定要运行此工作流吗？', '运行工作流')
    
    const res: any = await api.post('/repos/ci/run', {
      owner,
      repo,
      workflow_id: row.id
    })
    
    if (res.code === 200) {
      ElMessage.success('工作流已触发')
      activeTab.value = 'runs'
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '运行失败')
    }
  }
}

function editWorkflow(row: any) {
  workflow.name = row.name
  workflow.triggers = row.trigger.split(', ')
  showCreateDialog.value = true
}

async function deleteWorkflow(row: any) {
  try {
    await ElMessageBox.confirm('确定要删除此工作流吗？', '删除工作流', { type: 'warning' })
    
    const res: any = await api.delete('/repos/ci/workflow', {
      data: { owner, repo, id: row.id }
    })
    
    if (res.code === 200) {
      ElMessage.success('工作流已删除')
      fetchWorkflows()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function viewLogs(row: any) {
  currentLogs.value = `[2026-04-20 20:30:01] Starting workflow: CI
[2026-04-20 20:30:02] Running on: ubuntu-latest
[2026-04-20 20:30:03] Step 1: Checkout code
[2026-04-20 20:30:05] ✓ Code checked out successfully
[2026-04-20 20:30:06] Step 2: Install dependencies
[2026-04-20 20:30:45] ✓ Dependencies installed
[2026-04-20 20:30:46] Step 3: Run tests
[2026-04-20 20:32:15] ✓ All tests passed
[2026-04-20 20:32:16] Workflow completed successfully`
  showLogsDialog.value = true
}

async function fetchWorkflows() {
  loading.value = true
  try {
    const res: any = await api.get('/repos/ci/workflows', {
      params: { owner, repo }
    })
    if (res.code === 200) {
      workflows.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch workflows:', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // fetchWorkflows()
})
</script>

<style scoped>
.ci-cd {
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

.commit-hash {
  font-family: monospace;
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 4px;
}

.logs-container {
  background: #1e1e1e;
  border-radius: 6px;
  padding: 16px;
  max-height: 500px;
  overflow: auto;
}

.logs {
  color: #d4d4d4;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
  line-height: 1.5;
  margin: 0;
  white-space: pre-wrap;
}
</style>
