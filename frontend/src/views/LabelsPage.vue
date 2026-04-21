<template>
  <div class="labels-page">
    <div class="page-header">
      <h2>标签管理</h2>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新建标签
      </el-button>
    </div>

    <div class="labels-list">
      <div
        v-for="label in labels"
        :key="label.id"
        class="label-item"
      >
        <div class="label-badge" :style="{ backgroundColor: label.color }">
          {{ label.name }}
        </div>
        <div class="label-actions">
          <el-button size="small" @click="editLabel(label)">编辑</el-button>
          <el-popconfirm title="确定删除此标签？" @confirm="deleteLabel(label.id)">
            <template #reference>
              <el-button size="small" type="danger">删除</el-button>
            </template>
          </el-popconfirm>
        </div>
      </div>
      
      <el-empty v-if="labels.length === 0" description="暂无标签" />
    </div>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingLabel ? '编辑标签' : '新建标签'"
      width="400px"
    >
      <el-form :model="labelForm" label-width="80px">
        <el-form-item label="名称">
          <el-input v-model="labelForm.name" placeholder="标签名称" />
        </el-form-item>
        <el-form-item label="颜色">
          <div class="color-picker">
            <el-color-picker v-model="labelForm.color" />
            <el-input v-model="labelForm.color" placeholder="#999999" style="width: 120px" />
          </div>
        </el-form-item>
        <el-form-item label="预览">
          <span class="label-badge" :style="{ backgroundColor: labelForm.color }">
            {{ labelForm.name || '标签预览' }}
          </span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="saveLabel">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import axios from 'axios'

const route = useRoute()
const repoId = computed(() => Number(route.params.repoId) || 0)

const labels = ref<any[]>([])
const showCreateDialog = ref(false)
const editingLabel = ref<any>(null)

const labelForm = reactive({
  name: '',
  color: '#999999'
})

async function fetchLabels() {
  try {
    const response = await axios.get('/api/repos/labels', {
      params: { repo_id: repoId.value }
    })
    if (response.data.success) {
      labels.value = response.data.labels
    }
  } catch (error) {
    ElMessage.error('获取标签失败')
  }
}

function editLabel(label: any) {
  editingLabel.value = label
  labelForm.name = label.name
  labelForm.color = label.color
  showCreateDialog.value = true
}

async function saveLabel() {
  if (!labelForm.name.trim()) {
    ElMessage.warning('请输入标签名称')
    return
  }

  try {
    const url = editingLabel.value
      ? `/api/repos/labels/${editingLabel.value.id}`
      : '/api/repos/labels'
    
    const method = editingLabel.value ? 'put' : 'post'
    
    const response = await axios[method](url, {
      repo_id: repoId.value,
      ...labelForm
    })

    if (response.data.success) {
      ElMessage.success(editingLabel.value ? '标签已更新' : '标签已创建')
      showCreateDialog.value = false
      editingLabel.value = null
      labelForm.name = ''
      labelForm.color = '#999999'
      fetchLabels()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

async function deleteLabel(id: number) {
  try {
    const response = await axios.delete(`/api/repos/labels/${id}`)
    if (response.data.success) {
      ElMessage.success('标签已删除')
      fetchLabels()
    }
  } catch (error) {
    ElMessage.error('删除失败')
  }
}

onMounted(fetchLabels)
</script>

<style lang="scss" scoped>
.labels-page {
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

.labels-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.label-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #fff;
  border: 1px solid #d0d7de;
  border-radius: 6px;
}

.label-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 16px;
  color: #fff;
  font-size: 12px;
  font-weight: 500;
}

.label-actions {
  display: flex;
  gap: 8px;
}

.color-picker {
  display: flex;
  gap: 12px;
  align-items: center;
}
</style>
