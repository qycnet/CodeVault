<template>
  <div class="actions-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>Actions / CI</h2>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            新建工作流
          </el-button>
        </div>
      </template>
      
      <!-- 工作流列表 -->
      <div v-loading="loading" class="workflows-list">
        <div v-for="workflow in workflows" :key="workflow.id" class="workflow-item">
          <div class="workflow-icon">
            <el-icon size="32"><Setting /></el-icon>
          </div>
          <div class="workflow-info">
            <div class="workflow-name">{{ workflow.name }}</div>
            <div class="workflow-desc">{{ workflow.description || '暂无描述' }}</div>
            <div class="workflow-meta">
              <el-tag v-if="workflow.schedule" type="warning" size="small">
                <el-icon><Clock /></el-icon>
                {{ workflow.schedule }}
              </el-tag>
              <el-tag :type="workflow.active ? 'success' : 'info'" size="small">
                {{ workflow.active ? '启用' : '禁用' }}
              </el-tag>
            </div>
          </div>
          <div class="workflow-stats">
            <div class="stat-item">
              <span class="stat-value">{{ workflow.runs_count || 0 }}</span>
              <span class="stat-label">运行次数</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ workflow.success_rate || 0 }}%</span>
              <span class="stat-label">成功率</span>
            </div>
          </div>
          <div class="workflow-actions">
            <el-button type="primary" link @click="runWorkflow(workflow)">
              <el-icon><VideoPlay /></el-icon>
              运行
            </el-button>
            <el-button type="primary" link @click="editWorkflow(workflow)">
              <el-icon><Edit /></el-icon>
              编辑
            </el-button>
            <el-button type="primary" link @click="viewLogs(workflow)">
              <el-icon><Document /></el-icon>
              日志
            </el-button>
            <el-dropdown @command="handleCommand($event, workflow)">
              <el-button type="primary" link>
                <el-icon><More /></el-icon>
              </el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="toggle">
                    {{ workflow.active ? '禁用' : '启用' }}
                  </el-dropdown-item>
                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>
        
        <el-empty v-if="!loading && workflows.length === 0" description="暂无工作流" />
      </div>
    </el-card>
    
    <!-- 创建/编辑工作流对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingWorkflow ? '编辑工作流' : '新建工作流'"
      width="900px"
      :close-on-click-modal="false"
    >
      <el-tabs v-model="editorTab">
        <!-- 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <el-form :model="newWorkflow" label-width="120px">
            <el-form-item label="工作流名称">
              <el-input v-model="newWorkflow.name" placeholder="CI Pipeline" />
            </el-form-item>
            
            <el-form-item label="描述">
              <el-input v-model="newWorkflow.description" type="textarea" :rows="2" />
            </el-form-item>
            
            <el-form-item label="触发条件">
              <el-checkbox-group v-model="newWorkflow.triggers">
                <el-checkbox value="push">Push</el-checkbox>
                <el-checkbox value="pull_request">Pull Request</el-checkbox>
                <el-checkbox value="schedule">定时触发</el-checkbox>
                <el-checkbox value="manual">手动触发</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
            
            <el-form-item v-if="newWorkflow.triggers.includes('schedule')" label="Cron 表达式">
              <el-input v-model="newWorkflow.schedule" placeholder="0 0 * * * (每天 00:00)">
                <template #append>
                  <el-popover placement="top" :width="400" trigger="click">
                    <template #reference>
                      <el-button>
                        <el-icon><QuestionFilled /></el-icon>
                      </el-button>
                    </template>
                    <div class="cron-help">
                      <h4>Cron 表达式说明</h4>
                      <pre>┌───────────── 分钟 (0 - 59)
│ ┌───────────── 小时 (0 - 23)
│ │ ┌───────────── 日 (1 - 31)
│ │ │ ┌───────────── 月 (1 - 12)
│ │ │ │ ┌───────────── 星期 (0 - 6)
│ │ │ │ │
* * * * *</pre>
                      <h4>常用示例</h4>
                      <ul>
                        <li><code>0 0 * * *</code> - 每天 00:00</li>
                        <li><code>0 */6 * * *</code> - 每 6 小时</li>
                        <li><code>0 9 * * 1</code> - 每周一 09:00</li>
                        <li><code>0 0 1 * *</code> - 每月 1 日 00:00</li>
                      </ul>
                    </div>
                  </el-popover>
                </template>
              </el-input>
              <div class="cron-preview" v-if="newWorkflow.schedule">
                下次运行: {{ getNextRun(newWorkflow.schedule) }}
              </div>
            </el-form-item>
            
            <el-form-item label="分支过滤">
              <el-select v-model="newWorkflow.branches" multiple placeholder="选择分支">
                <el-option v-for="branch in branches" :key="branch" :label="branch" :value="branch" />
              </el-select>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <!-- 可视化编辑器 -->
        <el-tab-pane label="可视化编辑" name="visual">
          <div class="visual-editor">
            <div class="editor-toolbar">
              <el-button @click="addStep('checkout')">
                <el-icon><Download /></el-icon>
                检出代码
              </el-button>
              <el-button @click="addStep('install')">
                <el-icon><Box /></el-icon>
                安装依赖
              </el-button>
              <el-button @click="addStep('build')">
                <el-icon><Tools /></el-icon>
                构建
              </el-button>
              <el-button @click="addStep('test')">
                <el-icon><Check /></el-icon>
                测试
              </el-button>
              <el-button @click="addStep('deploy')">
                <el-icon><Upload /></el-icon>
                部署
              </el-button>
              <el-button @click="addStep('custom')">
                <el-icon><Plus /></el-icon>
                自定义
              </el-button>
            </div>
            
            <div class="steps-container">
              <div
                v-for="(step, index) in newWorkflow.steps"
                :key="index"
                class="step-item"
                draggable="true"
                @dragstart="dragStart(index)"
                @dragover.prevent
                @drop="dropStep(index)"
              >
                <div class="step-header">
                  <el-icon><Rank /></el-icon>
                  <span class="step-name">{{ step.name }}</span>
                  <el-button type="danger" link @click="removeStep(index)">
                    <el-icon><Close /></el-icon>
                  </el-button>
                </div>
                <div class="step-content">
                  <el-form label-width="80px" size="small">
                    <el-form-item label="命令">
                      <el-input v-model="step.command" type="textarea" :rows="2" placeholder="npm run build" />
                    </el-form-item>
                    <el-form-item label="工作目录">
                      <el-input v-model="step.working_dir" placeholder="./" />
                    </el-form-item>
                    <el-form-item label="环境变量">
                      <el-input v-model="step.env" type="textarea" :rows="2" placeholder="NODE_ENV=production" />
                    </el-form-item>
                    <el-form-item label="超时(分钟)">
                      <el-input-number v-model="step.timeout" :min="1" :max="120" />
                    </el-form-item>
                    <el-form-item label="失败时继续">
                      <el-switch v-model="step.continue_on_error" />
                    </el-form-item>
                  </el-form>
                </div>
              </div>
              
              <el-empty v-if="newWorkflow.steps.length === 0" description="拖拽上方按钮添加步骤" />
            </div>
          </div>
        </el-tab-pane>
        
        <!-- YAML 编辑 -->
        <el-tab-pane label="YAML" name="yaml">
          <el-input
            v-model="yamlContent"
            type="textarea"
            :rows="20"
            placeholder="name: CI Pipeline
on:
  push:
    branches: [main]
  schedule:
    - cron: '0 0 * * *'

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - run: npm install
      - run: npm run build"
            class="yaml-editor"
          />
        </el-tab-pane>
      </el-tabs>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="saveWorkflow" :loading="saving">
          保存
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 日志查看对话框 -->
    <el-dialog v-model="showLogsDialog" title="运行日志" width="900px">
      <div class="logs-container">
        <div class="logs-header">
          <el-select v-model="selectedRun" placeholder="选择运行记录" @change="loadRunLogs">
            <el-option
              v-for="run in workflowRuns"
              :key="run.id"
              :label="`#${run.id} - ${formatDate(run.created_at)}`"
              :value="run.id"
            >
              <span :class="`run-status status-${run.status}`">{{ run.status }}</span>
              <span style="margin-left: 8px">#{{ run.id }}</span>
              <span style="margin-left: 8px; color: #909399">{{ formatDate(run.created_at) }}</span>
            </el-option>
          </el-select>
          <el-button :icon="Refresh" @click="loadRunLogs">刷新</el-button>
        </div>
        
        <div class="logs-content" ref="logsContainer">
          <pre v-if="logs.length > 0">{{ logs.join('\n') }}</pre>
          <el-empty v-else description="暂无日志" />
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Setting, Clock, VideoPlay, Edit, Document, More,
  QuestionFilled, Download, Box, Tools, Check, Upload,
  Rank, Close, Refresh
} from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()

const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const showLogsDialog = ref(false)
const editorTab = ref('basic')
const editingWorkflow = ref<any>(null)
const selectedRun = ref<number | null>(null)
const logsContainer = ref<HTMLElement | null>(null)

const workflows = ref<any[]>([])
const branches = ref<string[]>(['main', 'develop'])
const workflowRuns = ref<any[]>([])
const logs = ref<string[]>([])

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const newWorkflow = reactive({
  name: '',
  description: '',
  triggers: ['push'] as string[],
  schedule: '',
  branches: ['main'] as string[],
  steps: [] as any[]
})

const yamlContent = ref('')

let ws: WebSocket | null = null
let draggedIndex = ref<number | null>(null)

function formatDate(date: string) {
  return new Date(date).toLocaleString('zh-CN')
}

function getNextRun(cron: string) {
  // 简单的下次运行时间计算
  try {
    const parts = cron.split(' ')
    if (parts.length !== 5) return '无效的 Cron 表达式'
    
    const now = new Date()
    const next = new Date(now)
    next.setHours(next.getHours() + 1, 0, 0, 0)
    
    return next.toLocaleString('zh-CN')
  } catch {
    return '无效的 Cron 表达式'
  }
}

function addStep(type: string) {
  const stepTemplates: Record<string, any> = {
    checkout: { name: '检出代码', command: 'git checkout $BRANCH', working_dir: '', env: '', timeout: 10, continue_on_error: false },
    install: { name: '安装依赖', command: 'npm install', working_dir: './', env: '', timeout: 30, continue_on_error: false },
    build: { name: '构建', command: 'npm run build', working_dir: './', env: 'NODE_ENV=production', timeout: 30, continue_on_error: false },
    test: { name: '测试', command: 'npm test', working_dir: './', env: '', timeout: 60, continue_on_error: false },
    deploy: { name: '部署', command: 'npm run deploy', working_dir: './', env: '', timeout: 30, continue_on_error: false },
    custom: { name: '自定义步骤', command: '', working_dir: './', env: '', timeout: 30, continue_on_error: false }
  }
  
  newWorkflow.steps.push({ ...stepTemplates[type] })
}

function removeStep(index: number) {
  newWorkflow.steps.splice(index, 1)
}

function dragStart(index: number) {
  draggedIndex.value = index
}

function dropStep(index: number) {
  if (draggedIndex.value !== null && draggedIndex.value !== index) {
    const step = newWorkflow.steps.splice(draggedIndex.value, 1)[0]
    newWorkflow.steps.splice(index, 0, step)
  }
  draggedIndex.value = null
}

async function fetchWorkflows() {
  loading.value = true
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/actions/workflows`)
    if (res.code === 200) {
      workflows.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch workflows:', e)
  } finally {
    loading.value = false
  }
}

async function saveWorkflow() {
  saving.value = true
  try {
    const url = editingWorkflow.value
      ? `/repos/${owner.value}/${repo.value}/actions/workflows/${editingWorkflow.value.id}`
      : `/repos/${owner.value}/${repo.value}/actions/workflows`
    
    const method = editingWorkflow.value ? 'put' : 'post'
    const res: any = await api[method](url, newWorkflow)
    
    if (res.code === 200) {
      ElMessage.success('保存成功')
      showCreateDialog.value = false
      resetForm()
      fetchWorkflows()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

function editWorkflow(workflow: any) {
  editingWorkflow.value = workflow
  Object.assign(newWorkflow, workflow)
  showCreateDialog.value = true
}

async function runWorkflow(workflow: any) {
  try {
    const res: any = await api.post(`/repos/${owner.value}/${repo.value}/actions/workflows/${workflow.id}/run`)
    if (res.code === 200) {
      ElMessage.success('工作流已触发')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '触发失败')
  }
}

async function viewLogs(workflow: any) {
  editingWorkflow.value = workflow
  
  // 加载运行记录
  const res: any = await api.get(`/repos/${owner.value}/${repo.value}/actions/workflows/${workflow.id}/runs`)
  if (res.code === 200) {
    workflowRuns.value = res.data || []
    if (workflowRuns.value.length > 0) {
      selectedRun.value = workflowRuns.value[0].id
      loadRunLogs()
    }
  }
  
  showLogsDialog.value = true
}

async function loadRunLogs() {
  if (!selectedRun.value) return
  
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/actions/runs/${selectedRun.value}/logs`)
    if (res.code === 200) {
      logs.value = res.data || []
      
      // 连接 WebSocket 实时日志
      connectWebSocket(selectedRun.value)
    }
  } catch (e) {
    console.error('Failed to load logs:', e)
  }
}

function connectWebSocket(runId: number) {
  if (ws) {
    ws.close()
  }
  
  const wsUrl = `wss://api.codevault.io/repos/${owner.value}/${repo.value}/actions/runs/${runId}/logs/stream`
  
  try {
    ws = new WebSocket(wsUrl)
    
    ws.onmessage = (event) => {
      logs.value.push(event.data)
      
      // 自动滚动到底部
      if (logsContainer.value) {
        logsContainer.value.scrollTop = logsContainer.value.scrollHeight
      }
    }
    
    ws.onerror = (error) => {
      console.error('WebSocket error:', error)
    }
  } catch (e) {
    console.error('WebSocket connection failed:', e)
  }
}

async function handleCommand(command: string, workflow: any) {
  switch (command) {
    case 'toggle':
      const res: any = await api.put(
        `/repos/${owner.value}/${repo.value}/actions/workflows/${workflow.id}`,
        { active: !workflow.active }
      )
      if (res.code === 200) {
        workflow.active = !workflow.active
        ElMessage.success(workflow.active ? '已启用' : '已禁用')
      }
      break
    
    case 'delete':
      try {
        await ElMessageBox.confirm('确定删除此工作流吗？', '删除确认', { type: 'warning' })
        
        const delRes: any = await api.delete(
          `/repos/${owner.value}/${repo.value}/actions/workflows/${workflow.id}`
        )
        if (delRes.code === 200) {
          ElMessage.success('已删除')
          fetchWorkflows()
        }
      } catch (e: any) {
        if (e !== 'cancel') {
          ElMessage.error(e.message || '删除失败')
        }
      }
      break
  }
}

function resetForm() {
  editingWorkflow.value = null
  newWorkflow.name = ''
  newWorkflow.description = ''
  newWorkflow.triggers = ['push']
  newWorkflow.schedule = ''
  newWorkflow.branches = ['main']
  newWorkflow.steps = []
  yamlContent.value = ''
  editorTab.value = 'basic'
}

onMounted(() => {
  fetchWorkflows()
})

onUnmounted(() => {
  if (ws) {
    ws.close()
  }
})
</script>

<style scoped>
.actions-page {
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

.workflows-list {
  min-height: 200px;
}

.workflow-item {
  display: flex;
  gap: 20px;
  padding: 20px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 16px;
  align-items: center;
}

.workflow-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 60px;
  height: 60px;
  background: #f5f7fa;
  border-radius: 12px;
  color: #409eff;
}

.workflow-info {
  flex: 1;
}

.workflow-name {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
}

.workflow-desc {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.workflow-meta {
  display: flex;
  gap: 8px;
}

.workflow-stats {
  display: flex;
  gap: 24px;
}

.stat-item {
  text-align: center;
}

.stat-value {
  display: block;
  font-size: 20px;
  font-weight: 600;
  color: #303133;
}

.stat-label {
  font-size: 12px;
  color: #909399;
}

.workflow-actions {
  display: flex;
  gap: 8px;
}

.cron-help h4 {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #303133;
}

.cron-help pre {
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  font-size: 12px;
  overflow-x: auto;
}

.cron-help ul {
  padding-left: 20px;
  margin: 8px 0 0 0;
}

.cron-help li {
  margin-bottom: 4px;
}

.cron-help code {
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
}

.cron-preview {
  margin-top: 8px;
  font-size: 13px;
  color: #67c23a;
}

.visual-editor {
  min-height: 400px;
}

.editor-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 8px;
}

.steps-container {
  min-height: 300px;
}

.step-item {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 12px;
  overflow: hidden;
}

.step-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f5f7fa;
  cursor: move;
}

.step-name {
  flex: 1;
  font-weight: 500;
  color: #303133;
}

.step-content {
  padding: 16px;
}

.logs-container {
  min-height: 400px;
}

.logs-header {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.logs-content {
  background: #1e1e1e;
  border-radius: 8px;
  padding: 16px;
  max-height: 500px;
  overflow-y: auto;
}

.logs-content pre {
  color: #d4d4d4;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}

.run-status {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.status-success {
  background: #f0f9eb;
  color: #67c23a;
}

.status-failed {
  background: #fef0f0;
  color: #f56c6c;
}

.status-running {
  background: #ecf5ff;
  color: #409eff;
}

.yaml-editor :deep(textarea) {
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
}
</style>
