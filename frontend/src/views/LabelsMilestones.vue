<template>
  <div class="labels-milestones-page">
    <el-tabs v-model="activeTab">
      <!-- 标签 -->
      <el-tab-pane label="标签" name="labels">
        <div class="tab-header">
          <el-button type="primary" @click="showLabelDialog = true">
            <el-icon><Plus /></el-icon>
            新建标签
          </el-button>
        </div>
        
        <div class="labels-list">
          <div v-for="label in labels" :key="label.id" class="label-item">
            <div class="label-color">
              <span
                class="color-dot"
                :style="{ background: label.color }"
              ></span>
            </div>
            <div class="label-info">
              <div class="label-name">{{ label.name }}</div>
              <div class="label-desc">{{ label.description || '暂无描述' }}</div>
            </div>
            <div class="label-count">
              {{ label.issues_count || 0 }} 个 Issue
            </div>
            <div class="label-actions">
              <el-button type="primary" link @click="editLabel(label)">
                编辑
              </el-button>
              <el-button type="danger" link @click="deleteLabel(label)">
                删除
              </el-button>
            </div>
          </div>
          
          <el-empty v-if="labels.length === 0" description="暂无标签" />
        </div>
      </el-tab-pane>
      
      <!-- 里程碑 -->
      <el-tab-pane label="里程碑" name="milestones">
        <div class="tab-header">
          <el-button type="primary" @click="showMilestoneDialog = true">
            <el-icon><Plus /></el-icon>
            新建里程碑
          </el-button>
        </div>
        
        <div class="milestones-list">
          <div v-for="milestone in milestones" :key="milestone.id" class="milestone-item">
            <div class="milestone-header">
              <h3>{{ milestone.title }}</h3>
              <el-tag :type="getMilestoneStatus(milestone).type">
                {{ getMilestoneStatus(milestone).text }}
              </el-tag>
            </div>
            
            <div class="milestone-desc">{{ milestone.description }}</div>
            
            <div class="milestone-progress">
              <el-progress
                :percentage="getProgress(milestone)"
                :stroke-width="8"
              />
              <span class="progress-text">
                {{ milestone.closed_issues || 0 }} / {{ milestone.total_issues || 0 }} 完成
              </span>
            </div>
            
            <div class="milestone-meta">
              <span v-if="milestone.due_date">
                截止日期: {{ formatDate(milestone.due_date) }}
              </span>
            </div>
            
            <div class="milestone-actions">
              <el-button type="primary" link @click="editMilestone(milestone)">
                编辑
              </el-button>
              <el-button type="danger" link @click="deleteMilestone(milestone)">
                删除
              </el-button>
            </div>
          </div>
          
          <el-empty v-if="milestones.length === 0" description="暂无里程碑" />
        </div>
      </el-tab-pane>
    </el-tabs>
    
    <!-- 标签对话框 -->
    <el-dialog v-model="showLabelDialog" :title="editingLabel ? '编辑标签' : '新建标签'" width="500px">
      <el-form :model="newLabel" label-width="100px">
        <el-form-item label="标签名称">
          <el-input v-model="newLabel.name" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newLabel.description" type="textarea" :rows="3" />
        </el-form-item>
        
        <el-form-item label="颜色">
          <el-color-picker v-model="newLabel.color" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showLabelDialog = false">取消</el-button>
        <el-button type="primary" @click="saveLabel" :loading="savingLabel">保存</el-button>
      </template>
    </el-dialog>
    
    <!-- 里程碑对话框 -->
    <el-dialog v-model="showMilestoneDialog" :title="editingMilestone ? '编辑里程碑' : '新建里程碑'" width="500px">
      <el-form :model="newMilestone" label-width="100px">
        <el-form-item label="标题">
          <el-input v-model="newMilestone.title" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newMilestone.description" type="textarea" :rows="3" />
        </el-form-item>
        
        <el-form-item label="截止日期">
          <el-date-picker
            v-model="newMilestone.due_date"
            type="date"
            placeholder="选择日期"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showMilestoneDialog = false">取消</el-button>
        <el-button type="primary" @click="saveMilestone" :loading="savingMilestone">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()

const activeTab = ref('labels')
const showLabelDialog = ref(false)
const showMilestoneDialog = ref(false)
const editingLabel = ref<any>(null)
const editingMilestone = ref<any>(null)
const savingLabel = ref(false)
const savingMilestone = ref(false)

const labels = ref<any[]>([])
const milestones = ref<any[]>([])

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const newLabel = reactive({
  name: '',
  description: '',
  color: '#409eff'
})

const newMilestone = reactive({
  title: '',
  description: '',
  due_date: null as Date | null
})

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('zh-CN')
}

function getMilestoneStatus(milestone: any) {
  if (milestone.closed_at) {
    return { type: 'success', text: '已完成' }
  }
  if (milestone.due_date && new Date(milestone.due_date) < new Date()) {
    return { type: 'danger', text: '已过期' }
  }
  return { type: 'info', text: '进行中' }
}

function getProgress(milestone: any) {
  if (!milestone.total_issues) return 0
  return Math.round((milestone.closed_issues / milestone.total_issues) * 100)
}

async function fetchLabels() {
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/labels`)
    if (res.code === 200) {
      labels.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch labels:', e)
  }
}

async function fetchMilestones() {
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/milestones`)
    if (res.code === 200) {
      milestones.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch milestones:', e)
  }
}

function editLabel(label: any) {
  editingLabel.value = label
  Object.assign(newLabel, label)
  showLabelDialog.value = true
}

async function saveLabel() {
  savingLabel.value = true
  try {
    const url = editingLabel.value
      ? `/repos/${owner.value}/${repo.value}/labels/${editingLabel.value.id}`
      : `/repos/${owner.value}/${repo.value}/labels`
    
    const method = editingLabel.value ? 'put' : 'post'
    const res: any = await api[method](url, newLabel)
    
    if (res.code === 200) {
      ElMessage.success('保存成功')
      showLabelDialog.value = false
      fetchLabels()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    savingLabel.value = false
  }
}

async function deleteLabel(label: any) {
  try {
    await ElMessageBox.confirm('确定删除此标签吗？', '删除确认', { type: 'warning' })
    
    const res: any = await api.delete(`/repos/${owner.value}/${repo.value}/labels/${label.id}`)
    if (res.code === 200) {
      ElMessage.success('标签已删除')
      fetchLabels()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function editMilestone(milestone: any) {
  editingMilestone.value = milestone
  Object.assign(newMilestone, milestone)
  showMilestoneDialog.value = true
}

async function saveMilestone() {
  savingMilestone.value = true
  try {
    const url = editingMilestone.value
      ? `/repos/${owner.value}/${repo.value}/milestones/${editingMilestone.value.id}`
      : `/repos/${owner.value}/${repo.value}/milestones`
    
    const method = editingMilestone.value ? 'put' : 'post'
    const res: any = await api[method](url, newMilestone)
    
    if (res.code === 200) {
      ElMessage.success('保存成功')
      showMilestoneDialog.value = false
      fetchMilestones()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    savingMilestone.value = false
  }
}

async function deleteMilestone(milestone: any) {
  try {
    await ElMessageBox.confirm('确定删除此里程碑吗？', '删除确认', { type: 'warning' })
    
    const res: any = await api.delete(`/repos/${owner.value}/${repo.value}/milestones/${milestone.id}`)
    if (res.code === 200) {
      ElMessage.success('里程碑已删除')
      fetchMilestones()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

onMounted(() => {
  fetchLabels()
  fetchMilestones()
})
</script>

<style scoped>
.labels-milestones-page {
  padding: 20px;
}

.tab-header {
  margin-bottom: 20px;
}

.label-item, .milestone-item {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
}

.label-item {
  display: flex;
  align-items: center;
  gap: 16px;
}

.label-color {
  flex-shrink: 0;
}

.color-dot {
  display: inline-block;
  width: 24px;
  height: 24px;
  border-radius: 50%;
}

.label-info {
  flex: 1;
}

.label-name {
  font-weight: 500;
  color: #303133;
}

.label-desc {
  font-size: 13px;
  color: #909399;
}

.label-count {
  color: #909399;
  font-size: 13px;
}

.label-actions {
  display: flex;
  gap: 8px;
}

.milestone-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.milestone-header h3 {
  margin: 0;
  font-size: 16px;
  color: #303133;
}

.milestone-desc {
  color: #606266;
  margin-bottom: 12px;
}

.milestone-progress {
  margin-bottom: 12px;
}

.progress-text {
  font-size: 13px;
  color: #909399;
  margin-left: 8px;
}

.milestone-meta {
  font-size: 13px;
  color: #909399;
  margin-bottom: 12px;
}

.milestone-actions {
  display: flex;
  gap: 8px;
}
</style>
