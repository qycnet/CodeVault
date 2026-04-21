<template>
  <div class="workflow-editor">
    <!-- 工具栏 -->
    <div class="toolbar">
      <el-button-group>
        <el-button @click="addStep('run')" :icon="Play">运行命令</el-button>
        <el-button @click="addStep('uses')" :icon="Connection">使用动作</el-button>
        <el-button @click="addStep('condition')" :icon="QuestionFilled">条件判断</el-button>
        <el-button @click="addStep('artifact')" :icon="Folder">上传产物</el-button>
      </el-button-group>
      
      <el-button-group style="margin-left: 16px;">
        <el-button @click="saveWorkflow" type="primary" :icon="Check">保存</el-button>
        <el-button @click="previewYaml" :icon="View">预览 YAML</el-button>
        <el-button @click="validateWorkflow" :icon="CircleCheck">验证</el-button>
      </el-button-group>
    </div>
    
    <!-- 主编辑区 -->
    <div class="editor-main">
      <!-- 步骤列表 -->
      <div class="steps-panel">
        <div class="panel-header">
          <h3>作业步骤</h3>
          <el-select v-model="selectedJob" placeholder="选择作业">
            <el-option
              v-for="(job, name) in workflow.jobs"
              :key="name"
              :label="name"
              :value="name"
            />
          </el-select>
          <el-button @click="addJob" size="small" :icon="Plus" style="margin-left: 8px;">添加作业</el-button>
        </div>
        
        <draggable
          v-model="currentSteps"
          item-key="id"
          class="steps-list"
          @end="onStepReorder"
        >
          <template #item="{ element, index }">
            <div class="step-item" :class="{ active: selectedStepIndex === index }" @click="selectStep(index)">
              <div class="step-header">
                <el-icon class="drag-handle"><Rank /></el-icon>
                <span class="step-name">{{ element.name || `Step ${index + 1}` }}</span>
                <el-button-group size="small">
                  <el-button @click.stop="moveStep(index, -1)" :disabled="index === 0" :icon="ArrowUp" />
                  <el-button @click.stop="moveStep(index, 1)" :disabled="index === currentSteps.length - 1" :icon="ArrowDown" />
                  <el-button @click.stop="removeStep(index)" type="danger" :icon="Delete" />
                </el-button-group>
              </div>
              <div class="step-preview">
                {{ getStepPreview(element) }}
              </div>
            </div>
          </template>
        </draggable>
      </div>
      
      <!-- 步骤编辑面板 -->
      <div class="step-editor" v-if="selectedStep">
        <h3>编辑步骤</h3>
        
        <el-form label-width="100px">
          <el-form-item label="步骤名称">
            <el-input v-model="selectedStep.name" placeholder="步骤名称" />
          </el-form-item>
          
          <el-form-item label="步骤类型">
            <el-radio-group v-model="selectedStep.type">
              <el-radio value="run">运行命令</el-radio>
              <el-radio value="uses">使用动作</el-radio>
            </el-radio-group>
          </el-form-item>
          
          <!-- 运行命令 -->
          <template v-if="selectedStep.type === 'run'">
            <el-form-item label="运行命令">
              <el-input
                v-model="selectedStep.run"
                type="textarea"
                :rows="5"
                placeholder="输入要运行的命令"
              />
            </el-form-item>
            
            <el-form-item label="Shell">
              <el-select v-model="selectedStep.shell">
                <el-option value="bash" />
                <el-option value="sh" />
                <el-option value="pwsh" />
              </el-select>
            </el-form-item>
            
            <el-form-item label="工作目录">
              <el-input v-model="selectedStep['working-directory']" placeholder="可选" />
            </el-form-item>
          </template>
          
          <!-- 使用动作 -->
          <template v-if="selectedStep.type === 'uses'">
            <el-form-item label="动作">
              <el-select v-model="selectedStep.uses" filterable allow-create>
                <el-option-group label="官方动作">
                  <el-option value="actions/checkout@v4" />
                  <el-option value="actions/setup-node@v4" />
                  <el-option value="actions/setup-php@v4" />
                  <el-option value="actions/setup-python@v4" />
                  <el-option value="actions/setup-go@v4" />
                  <el-option value="actions/cache@v4" />
                  <el-option value="actions/upload-artifact@v4" />
                  <el-option value="actions/download-artifact@v4" />
                </el-option-group>
              </el-select>
            </el-form-item>
            
            <el-form-item label="参数 (with)">
              <div class="with-params">
                <div v-for="(value, key) in selectedStep.with" :key="key" class="with-param">
                  <el-input v-model="selectedStep.with[key]" :placeholder="key" />
                  <el-button @click="removeWithParam(key)" :icon="Delete" />
                </div>
                <el-button @click="addWithParam" size="small" :icon="Plus">添加参数</el-button>
              </div>
            </el-form-item>
          </template>
          
          <!-- 条件 -->
          <el-form-item label="执行条件">
            <el-input v-model="selectedStep.if" placeholder="例如: success() || failure()" />
          </el-form-item>
          
          <!-- 环境变量 -->
          <el-form-item label="环境变量">
            <div class="env-vars">
              <div v-for="(value, key) in selectedStep.env" :key="key" class="env-var">
                <el-input v-model="selectedStep.env[key]" :placeholder="key" />
                <el-button @click="removeEnvVar(key)" :icon="Delete" />
              </div>
              <el-button @click="addEnvVar" size="small" :icon="Plus">添加环境变量</el-button>
            </div>
          </el-form-item>
          
          <!-- 继续执行 -->
          <el-form-item label="失败时继续">
            <el-switch v-model="selectedStep['continue-on-error']" />
          </el-form-item>
          
          <!-- 超时 -->
          <el-form-item label="超时(分钟)">
            <el-input-number v-model="selectedStep['timeout-minutes']" :min="1" :max="360" />
          </el-form-item>
        </el-form>
      </div>
      
      <!-- 触发器配置 -->
      <div class="triggers-panel">
        <h3>触发器</h3>
        
        <el-checkbox-group v-model="selectedTriggers">
          <el-checkbox value="push">Push</el-checkbox>
          <el-checkbox value="pull_request">Pull Request</el-checkbox>
          <el-checkbox value="schedule">定时</el-checkbox>
          <el-checkbox value="workflow_dispatch">手动触发</el-checkbox>
          <el-checkbox value="release">Release</el-checkbox>
        </el-checkbox-group>
        
        <!-- Push 配置 -->
        <div v-if="selectedTriggers.includes('push')" class="trigger-config">
          <h4>Push 分支</h4>
          <el-select v-model="workflow.on.push.branches" multiple filterable allow-create>
            <el-option value="main" />
            <el-option value="master" />
            <el-option value="develop" />
          </el-select>
        </div>
        
        <!-- PR 配置 -->
        <div v-if="selectedTriggers.includes('pull_request')" class="trigger-config">
          <h4>PR 分支</h4>
          <el-select v-model="workflow.on['pull_request'].branches" multiple filterable allow-create>
            <el-option value="main" />
            <el-option value="master" />
            <el-option value="develop" />
          </el-select>
        </div>
        
        <!-- 定时配置 -->
        <div v-if="selectedTriggers.includes('schedule')" class="trigger-config">
          <h4>Cron 表达式</h4>
          <div class="cron-inputs">
            <div v-for="(cron, index) in workflow.on.schedule" :key="index" class="cron-input">
              <el-input v-model="workflow.on.schedule[index].cron" placeholder="*/15 * * * *" />
              <el-button @click="removeSchedule(index)" :icon="Delete" />
              <span class="cron-desc">{{ getCronDescription(cron.cron) }}</span>
            </div>
            <el-button @click="addSchedule" size="small" :icon="Plus">添加定时</el-button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- YAML 预览对话框 -->
    <el-dialog v-model="showYamlPreview" title="YAML 预览" width="700px">
      <pre class="yaml-preview">{{ yamlOutput }}</pre>
      <template #footer>
        <el-button @click="copyYaml">复制</el-button>
        <el-button type="primary" @click="showYamlPreview = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import {
  Play, Connection, QuestionFilled, Folder, Check, View, CircleCheck,
  Plus, Delete, ArrowUp, ArrowDown, Rank
} from '@element-plus/icons-vue'
import draggable from 'vuedraggable'
import yaml from 'js-yaml'

// 工作流数据
const workflow = ref({
  name: '',
  on: {
    push: { branches: ['main'] },
    'pull_request': { branches: ['main'] },
    schedule: [] as Array<{ cron: string }>,
    'workflow_dispatch': {}
  },
  jobs: {
    build: {
      'runs-on': 'ubuntu-latest',
      steps: [] as any[]
    }
  }
})

const selectedJob = ref('build')
const selectedStepIndex = ref(-1)
const showYamlPreview = ref(false)

// 选中的触发器
const selectedTriggers = ref(['push', 'pull_request'])

// 当前作业的步骤
const currentSteps = computed({
  get: () => workflow.value.jobs[selectedJob.value]?.steps || [],
  set: (val) => {
    if (workflow.value.jobs[selectedJob.value]) {
      workflow.value.jobs[selectedJob.value].steps = val
    }
  }
})

// 选中的步骤
const selectedStep = computed(() => {
  if (selectedStepIndex.value >= 0 && selectedStepIndex.value < currentSteps.value.length) {
    return currentSteps.value[selectedStepIndex.value]
  }
  return null
})

// 添加作业
function addJob() {
  const jobName = `job_${Object.keys(workflow.value.jobs).length + 1}`
  workflow.value.jobs[jobName] = {
    'runs-on': 'ubuntu-latest',
    steps: []
  }
  selectedJob.value = jobName
}

// 添加步骤
function addStep(type: string) {
  const step: any = {
    id: Date.now(),
    name: '',
    type,
    if: '',
    env: {},
    'continue-on-error': false,
    'timeout-minutes': 60
  }
  
  if (type === 'run') {
    step.run = ''
    step.shell = 'bash'
  } else if (type === 'uses') {
    step.uses = ''
    step.with = {}
  }
  
  currentSteps.value.push(step)
  selectedStepIndex.value = currentSteps.value.length - 1
}

// 选择步骤
function selectStep(index: number) {
  selectedStepIndex.value = index
}

// 移动步骤
function moveStep(index: number, direction: number) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= currentSteps.value.length) return
  
  const steps = [...currentSteps.value]
  const temp = steps[index]
  steps[index] = steps[newIndex]
  steps[newIndex] = temp
  currentSteps.value = steps
  selectedStepIndex.value = newIndex
}

// 删除步骤
function removeStep(index: number) {
  currentSteps.value.splice(index, 1)
  if (selectedStepIndex.value >= currentSteps.value.length) {
    selectedStepIndex.value = currentSteps.value.length - 1
  }
}

// 步骤重排序
function onStepReorder() {
  // vuedraggable 已自动更新
}

// 获取步骤预览
function getStepPreview(step: any): string {
  if (step.run) {
    return `$ ${step.run.split('\n')[0]}${step.run.includes('\n') ? '...' : ''}`
  }
  if (step.uses) {
    return `uses: ${step.uses}`
  }
  return '未配置'
}

// 添加 with 参数
function addWithParam() {
  if (!selectedStep.value) return
  if (!selectedStep.value.with) selectedStep.value.with = {}
  const key = `param_${Object.keys(selectedStep.value.with).length + 1}`
  selectedStep.value.with[key] = ''
}

// 移除 with 参数
function removeWithParam(key: string) {
  if (!selectedStep.value) return
  delete selectedStep.value.with[key]
}

// 添加环境变量
function addEnvVar() {
  if (!selectedStep.value) return
  if (!selectedStep.value.env) selectedStep.value.env = {}
  const key = `VAR_${Object.keys(selectedStep.value.env).length + 1}`
  selectedStep.value.env[key] = ''
}

// 移除环境变量
function removeEnvVar(key: string) {
  if (!selectedStep.value) return
  delete selectedStep.value.env[key]
}

// 添加定时
function addSchedule() {
  workflow.value.on.schedule.push({ cron: '0 0 * * *' })
}

// 移除定时
function removeSchedule(index: number) {
  workflow.value.on.schedule.splice(index, 1)
}

// 获取 Cron 描述
function getCronDescription(cron: string): string {
  const descriptions: Record<string, string> = {
    '* * * * *': '每分钟',
    '*/5 * * * *': '每 5 分钟',
    '*/15 * * * *': '每 15 分钟',
    '0 * * * *': '每小时',
    '0 0 * * *': '每天午夜',
    '0 2 * * *': '每天凌晨 2 点',
    '0 9 * * *': '每天上午 9 点',
    '0 9 * * 1-5': '工作日上午 9 点'
  }
  return descriptions[cron] || ''
}

// YAML 输出
const yamlOutput = computed(() => {
  const output: any = {
    name: workflow.value.name,
    on: {}
  }
  
  selectedTriggers.value.forEach(trigger => {
    if (trigger === 'schedule' && workflow.value.on.schedule.length > 0) {
      output.on.schedule = workflow.value.on.schedule
    } else if (workflow.value.on[trigger]) {
      output.on[trigger] = workflow.value.on[trigger]
    }
  })
  
  output.jobs = {}
  Object.entries(workflow.value.jobs).forEach(([name, job]) => {
    output.jobs[name] = {
      'runs-on': job['runs-on'],
      steps: job.steps.map((step: any) => {
        const s: any = { name: step.name }
        if (step.run) s.run = step.run
        if (step.uses) s.uses = step.uses
        if (step.with && Object.keys(step.with).length > 0) s.with = step.with
        if (step.if) s.if = step.if
        if (step.env && Object.keys(step.env).length > 0) s.env = step.env
        if (step['continue-on-error']) s['continue-on-error'] = true
        if (step['timeout-minutes'] !== 60) s['timeout-minutes'] = step['timeout-minutes']
        return s
      })
    }
  })
  
  return yaml.dump(output, { indent: 2, lineWidth: -1 })
})

// 保存工作流
async function saveWorkflow() {
  try {
    const response = await fetch('/api/workflows', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        repo_id: 1, // 从路由获取
        name: workflow.value.name,
        config: yamlOutput.value
      })
    })
    
    const result = await response.json()
    
    if (result.success) {
      ElMessage.success('工作流保存成功')
    } else {
      ElMessage.error(result.message || '保存失败')
    }
  } catch (error) {
    ElMessage.error('保存失败')
  }
}

// 预览 YAML
function previewYaml() {
  showYamlPreview.value = true
}

// 验证工作流
function validateWorkflow() {
  const errors: string[] = []
  
  if (!workflow.value.name) {
    errors.push('工作流名称不能为空')
  }
  
  if (selectedTriggers.value.length === 0) {
    errors.push('至少选择一个触发器')
  }
  
  Object.entries(workflow.value.jobs).forEach(([jobName, job]) => {
    if (job.steps.length === 0) {
      errors.push(`作业 ${jobName} 没有步骤`)
    }
    
    job.steps.forEach((step, index) => {
      if (!step.run && !step.uses) {
        errors.push(`作业 ${jobName} 步骤 ${index + 1} 缺少命令或动作`)
      }
    })
  })
  
  if (errors.length > 0) {
    ElMessage.error(errors.join('\n'))
  } else {
    ElMessage.success('工作流配置有效')
  }
}

// 复制 YAML
function copyYaml() {
  navigator.clipboard.writeText(yamlOutput.value)
  ElMessage.success('已复制到剪贴板')
}
</script>

<style scoped>
.workflow-editor {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.toolbar {
  padding: 16px;
  background: var(--el-bg-color);
  border-bottom: 1px solid var(--el-border-color);
}

.editor-main {
  flex: 1;
  display: grid;
  grid-template-columns: 300px 1fr 250px;
  gap: 16px;
  padding: 16px;
  overflow: hidden;
}

.steps-panel {
  background: var(--el-bg-color);
  border-radius: 8px;
  padding: 16px;
  overflow-y: auto;
}

.panel-header {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 8px;
}

.panel-header h3 {
  margin: 0;
  flex: 1;
}

.steps-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.step-item {
  background: var(--el-fill-color-light);
  border-radius: 6px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.step-item:hover {
  background: var(--el-fill-color);
}

.step-item.active {
  background: var(--el-color-primary-light-9);
  border: 1px solid var(--el-color-primary);
}

.step-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.drag-handle {
  cursor: move;
  color: var(--el-text-color-secondary);
}

.step-name {
  flex: 1;
  font-weight: 500;
}

.step-preview {
  margin-top: 8px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  font-family: monospace;
}

.step-editor {
  background: var(--el-bg-color);
  border-radius: 8px;
  padding: 16px;
  overflow-y: auto;
}

.step-editor h3 {
  margin: 0 0 16px 0;
}

.with-params, .env-vars {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.with-param, .env-var {
  display: flex;
  gap: 8px;
}

.triggers-panel {
  background: var(--el-bg-color);
  border-radius: 8px;
  padding: 16px;
  overflow-y: auto;
}

.triggers-panel h3 {
  margin: 0 0 16px 0;
}

.trigger-config {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--el-border-color-lighter);
}

.trigger-config h4 {
  margin: 0 0 8px 0;
  font-size: 14px;
}

.cron-inputs {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cron-input {
  display: flex;
  gap: 8px;
  align-items: center;
}

.cron-desc {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.yaml-preview {
  background: var(--el-fill-color-light);
  padding: 16px;
  border-radius: 6px;
  overflow-x: auto;
  font-family: monospace;
  font-size: 13px;
  line-height: 1.6;
}
</style>
