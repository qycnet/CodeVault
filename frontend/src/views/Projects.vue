<template>
  <div class="projects">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <h3>项目看板</h3>
          <el-button type="primary" @click="showCreateProject = true">
            <el-icon><Plus /></el-icon>
            新建项目
          </el-button>
        </div>
      </template>
      
      <el-tabs v-model="activeTab">
        <el-tab-pane label="看板视图" name="board">
          <div class="board-container">
            <div
              v-for="column in columns"
              :key="column.id"
              class="board-column"
            >
              <div class="column-header">
                <span class="column-title">{{ column.name }}</span>
                <el-badge :value="column.cards.length" type="primary" />
              </div>
              
              <div class="column-body">
                <div
                  v-for="card in column.cards"
                  :key="card.id"
                  class="card-item"
                  @click="viewCard(card)"
                >
                  <div class="card-title">{{ card.title }}</div>
                  <div class="card-meta">
                    <el-tag v-if="card.priority" :type="getPriorityType(card.priority)" size="small">
                      {{ card.priority }}
                    </el-tag>
                    <span class="card-number">#{{ card.number }}</span>
                  </div>
                  <div class="card-footer">
                    <el-avatar :size="24" :src="card.assignee?.avatar">
                      {{ card.assignee?.name?.charAt(0) }}
                    </el-avatar>
                    <span class="card-date">{{ formatDate(card.updated_at) }}</span>
                  </div>
                </div>
                
                <el-button class="add-card-btn" text @click="addCard(column.id)">
                  <el-icon><Plus /></el-icon>
                  添加卡片
                </el-button>
              </div>
            </div>
            
            <div class="add-column">
              <el-button @click="showAddColumn = true">
                <el-icon><Plus /></el-icon>
                添加列
              </el-button>
            </div>
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="列表视图" name="list">
          <el-table :data="allCards" style="width: 100%">
            <el-table-column prop="number" label="#" width="80" />
            <el-table-column prop="title" label="标题" min-width="200" />
            <el-table-column prop="status" label="状态" width="120">
              <template #default="{ row }">
                <el-tag>{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="priority" label="优先级" width="100">
              <template #default="{ row }">
                <el-tag v-if="row.priority" :type="getPriorityType(row.priority)" size="small">
                  {{ row.priority }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="assignee" label="负责人" width="120">
              <template #default="{ row }">
                <span v-if="row.assignee">{{ row.assignee.name }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="updated_at" label="更新时间" width="180" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
    
    <!-- 添加卡片对话框 -->
    <el-dialog v-model="showAddCard" title="添加卡片" width="500px">
      <el-form :model="newCard" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="newCard.title" placeholder="输入卡片标题" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newCard.description" type="textarea" :rows="3" />
        </el-form-item>
        
        <el-form-item label="优先级">
          <el-select v-model="newCard.priority" placeholder="选择优先级">
            <el-option label="高" value="high" />
            <el-option label="中" value="medium" />
            <el-option label="低" value="low" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="负责人">
          <el-select v-model="newCard.assignee_id" placeholder="选择负责人">
            <el-option label="任" value="1" />
            <el-option label="Steve" value="2" />
            <el-option label="Sentinel" value="3" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="关联 Issue">
          <el-input v-model="newCard.issue_id" placeholder="Issue ID（可选）" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showAddCard = false">取消</el-button>
        <el-button type="primary" @click="createCard">创建</el-button>
      </template>
    </el-dialog>
    
    <!-- 添加列对话框 -->
    <el-dialog v-model="showAddColumn" title="添加列" width="400px">
      <el-form :model="newColumn" label-width="80px">
        <el-form-item label="列名称">
          <el-input v-model="newColumn.name" placeholder="例如：待办、进行中、已完成" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showAddColumn = false">取消</el-button>
        <el-button type="primary" @click="createColumn">创建</el-button>
      </template>
    </el-dialog>
    
    <!-- 创建项目对话框 -->
    <el-dialog v-model="showCreateProject" title="新建项目" width="500px">
      <el-form :model="newProject" label-width="80px">
        <el-form-item label="项目名称">
          <el-input v-model="newProject.name" placeholder="输入项目名称" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newProject.description" type="textarea" :rows="3" />
        </el-form-item>
        
        <el-form-item label="模板">
          <el-select v-model="newProject.template" placeholder="选择模板">
            <el-option label="基础看板" value="basic" />
            <el-option label="敏捷开发" value="agile" />
            <el-option label="Bug 跟踪" value="bug" />
          </el-select>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateProject = false">取消</el-button>
        <el-button type="primary" @click="createProject">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()
const owner = route.params.owner as string
const repo = route.params.repo as string

const loading = ref(false)
const activeTab = ref('board')
const showAddCard = ref(false)
const showAddColumn = ref(false)
const showCreateProject = ref(false)
const currentColumnId = ref(0)

const columns = ref([
  {
    id: 1,
    name: '待办',
    cards: [
      { id: 1, number: 101, title: '实现用户认证功能', priority: 'high', status: 'todo', assignee: { name: '任', avatar: '' }, updated_at: '2026-04-20' },
      { id: 2, number: 102, title: '添加文件上传功能', priority: 'medium', status: 'todo', assignee: { name: 'Steve', avatar: '' }, updated_at: '2026-04-19' },
    ]
  },
  {
    id: 2,
    name: '进行中',
    cards: [
      { id: 3, number: 103, title: '优化数据库查询', priority: 'high', status: 'in_progress', assignee: { name: 'Sentinel', avatar: '' }, updated_at: '2026-04-20' },
    ]
  },
  {
    id: 3,
    name: '已完成',
    cards: [
      { id: 4, number: 104, title: '修复 XSS 漏洞', priority: 'high', status: 'done', assignee: { name: '任', avatar: '' }, updated_at: '2026-04-18' },
    ]
  }
])

const allCards = computed(() => {
  const cards: any[] = []
  columns.value.forEach(col => {
    cards.push(...col.cards.map(card => ({ ...card, status: col.name })))
  })
  return cards
})

const newCard = reactive({
  title: '',
  description: '',
  priority: 'medium',
  assignee_id: '',
  issue_id: ''
})

const newColumn = reactive({
  name: ''
})

const newProject = reactive({
  name: '',
  description: '',
  template: 'basic'
})

function getPriorityType(priority: string) {
  const types: Record<string, string> = {
    high: 'danger',
    medium: 'warning',
    low: 'info'
  }
  return types[priority] || 'info'
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('zh-CN')
}

function addCard(columnId: number) {
  currentColumnId.value = columnId
  showAddCard.value = true
}

async function createCard() {
  if (!newCard.title) {
    ElMessage.warning('请输入卡片标题')
    return
  }
  
  // 添加到对应列
  const column = columns.value.find(c => c.id === currentColumnId.value)
  if (column) {
    column.cards.push({
      id: Date.now(),
      number: Math.floor(Math.random() * 1000),
      title: newCard.title,
      priority: newCard.priority,
      status: 'todo',
      assignee: { name: '任', avatar: '' },
      updated_at: new Date().toISOString().split('T')[0]
    })
  }
  
  ElMessage.success('卡片创建成功')
  showAddCard.value = false
  
  // 重置表单
  newCard.title = ''
  newCard.description = ''
  newCard.priority = 'medium'
  newCard.assignee_id = ''
  newCard.issue_id = ''
}

async function createColumn() {
  if (!newColumn.name) {
    ElMessage.warning('请输入列名称')
    return
  }
  
  columns.value.push({
    id: Date.now(),
    name: newColumn.name,
    cards: []
  })
  
  ElMessage.success('列创建成功')
  showAddColumn.value = false
  newColumn.name = ''
}

async function createProject() {
  if (!newProject.name) {
    ElMessage.warning('请输入项目名称')
    return
  }
  
  ElMessage.success('项目创建成功')
  showCreateProject.value = false
  
  // 重置表单
  newProject.name = ''
  newProject.description = ''
  newProject.template = 'basic'
}

function viewCard(card: any) {
  ElMessage.info(`查看卡片: ${card.title}`)
}

onMounted(() => {
  // 加载项目数据
})
</script>

<style scoped>
.projects {
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

.board-container {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding: 16px 0;
}

.board-column {
  flex-shrink: 0;
  width: 300px;
  background: #f5f7fa;
  border-radius: 8px;
  padding: 12px;
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid #e4e7ed;
}

.column-title {
  font-weight: 600;
  color: #303133;
}

.column-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.card-item {
  background: #fff;
  border-radius: 6px;
  padding: 12px;
  cursor: pointer;
  transition: box-shadow 0.2s;
  border: 1px solid #e4e7ed;
}

.card-item:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.card-title {
  font-weight: 500;
  color: #303133;
  margin-bottom: 8px;
}

.card-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.card-number {
  font-size: 12px;
  color: #909399;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-date {
  font-size: 12px;
  color: #909399;
}

.add-card-btn {
  width: 100%;
  margin-top: 8px;
}

.add-column {
  flex-shrink: 0;
  width: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px dashed #dcdfe6;
  border-radius: 8px;
  min-height: 200px;
}
</style>
