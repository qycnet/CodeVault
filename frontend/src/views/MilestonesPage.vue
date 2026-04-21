<template>
  <div class="milestones-page">
    <div class="page-header">
      <h2>里程碑</h2>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新建里程碑
      </el-button>
    </div>

    <div class="milestones-list">
      <div
        v-for="milestone in milestones"
        :key="milestone.id"
        class="milestone-item"
      >
        <div class="milestone-header">
          <h3>{{ milestone.title }}</h3>
          <div class="milestone-meta">
            <el-tag v-if="milestone.due_date" size="small">
              截止: {{ formatDate(milestone.due_date) }}
            </el-tag>
            <el-tag :type="milestone.is_closed ? 'info' : 'success'" size="small">
              {{ milestone.is_closed ? '已完成' : '进行中' }}
            </el-tag>
          </div>
        </div>
        
        <p class="milestone-description">{{ milestone.description || '暂无描述' }}</p>
        
        <div class="milestone-progress">
          <el-progress
            :percentage="getProgress(milestone)"
            :status="milestone.is_closed ? 'success' : undefined"
          />
          <span class="progress-text">
            {{ milestone.closed_issues || 0 }} / {{ milestone.total_issues || 0 }} Issue
          </span>
        </div>
        
        <div class="milestone-actions">
          <el-button size="small" @click="editMilestone(milestone)">编辑</el-button>
          <el-button
            size="small"
            :type="milestone.is_closed ? 'success' : 'warning'"
            @click="toggleMilestone(milestone)"
          >
            {{ milestone.is_closed ? '重新打开' : '关闭' }}
          </el-button>
          <el-popconfirm title="确定删除此里程碑？" @confirm="deleteMilestone(milestone.id)">
            <template #reference>
              <el-button size="small" type="danger">删除</el-button>
            </template>
          </el-popconfirm>
        </div>
      </div>
      
      <el-empty v-if="milestones.length === 0" description="暂无里程碑" />
    </div>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingMilestone ? '编辑里程碑' : '新建里程碑'"
      width="500px"
    >
      <el-form :model="milestoneForm" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="milestoneForm.title" placeholder="里程碑标题" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input
            v-model="milestoneForm.description"
            type="textarea"
            :rows="3"
            placeholder="里程碑描述（可选）"
          />
        </el-form-item>
        <el-form-item label="截止日期">
          <el-date-picker
            v-model="milestoneForm.due_date"
            type="date"
            placeholder="选择截止日期"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="saveMilestone">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import axios from 'axios'

const route = useRoute()
const repoId = computed(() => Number(route.params.repoId) || 0)

const milestones = ref<any[]>([])
const showCreateDialog = ref(false)
const editingMilestone = ref<any>(null)

const milestoneForm = reactive({
  title: '',
  description: '',
  due_date: ''
})

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('zh-CN')
}

function getProgress(milestone: any) {
  const total = milestone.total_issues || 0
  if (total === 0) return 0
  return Math.round((milestone.closed_issues || 0) / total * 100)
}

async function fetchMilestones() {
  try {
    const response = await axios.get('/api/repos/milestones', {
      params: { repo_id: repoId.value }
    })
    if (response.data.success) {
      milestones.value = response.data.milestones
    }
  } catch (error) {
    ElMessage.error('获取里程碑失败')
  }
}

function editMilestone(milestone: any) {
  editingMilestone.value = milestone
  milestoneForm.title = milestone.title
  milestoneForm.description = milestone.description || ''
  milestoneForm.due_date = milestone.due_date || ''
  showCreateDialog.value = true
}

async function saveMilestone() {
  if (!milestoneForm.title.trim()) {
    ElMessage.warning('请输入里程碑标题')
    return
  }

  try {
    const url = editingMilestone.value
      ? `/api/repos/milestones/${editingMilestone.value.id}`
      : '/api/repos/milestones'
    
    const method = editingMilestone.value ? 'put' : 'post'
    
    const response = await axios[method](url, {
      repo_id: repoId.value,
      ...milestoneForm
    })

    if (response.data.success) {
      ElMessage.success(editingMilestone.value ? '里程碑已更新' : '里程碑已创建')
      showCreateDialog.value = false
      editingMilestone.value = null
      Object.assign(milestoneForm, { title: '', description: '', due_date: '' })
      fetchMilestones()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

async function toggleMilestone(milestone: any) {
  try {
    await axios.put(`/api/repos/milestones/${milestone.id}`, {
      is_closed: !milestone.is_closed
    })
    fetchMilestones()
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

async function deleteMilestone(id: number) {
  try {
    await axios.delete(`/api/repos/milestones/${id}`)
    ElMessage.success('里程碑已删除')
    fetchMilestones()
  } catch (error) {
    ElMessage.error('删除失败')
  }
}

onMounted(fetchMilestones)
</script>

<style lang="scss" scoped>
.milestones-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  
  h2 {
    margin: 0;
  }
}

.milestones-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.milestone-item {
  padding: 16px 20px;
  background: #fff;
  border: 1px solid #d0d7de;
  border-radius: 6px;
}

.milestone-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  
  h3 {
    margin: 0;
    font-size: 16px;
  }
  
  .milestone-meta {
    display: flex;
    gap: 8px;
  }
}

.milestone-description {
  color: #57606a;
  font-size: 14px;
  margin: 8px 0;
}

.milestone-progress {
  margin: 12px 0;
  
  .progress-text {
    font-size: 12px;
    color: #57606a;
    margin-left: 8px;
  }
}

.milestone-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}
</style>
