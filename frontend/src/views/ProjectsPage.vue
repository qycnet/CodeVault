<template>
  <div class="projects-page">
    <!-- 头部 -->
    <div class="page-header">
      <div class="header-left">
        <h1>
          <span class="emoji">📊</span>
          Projects
        </h1>
        <p class="subtitle">项目管理和看板</p>
      </div>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新建项目
      </el-button>
    </div>

    <!-- 项目列表 -->
    <div class="projects-grid" v-loading="loading">
      <div
        v-for="project in projects"
        :key="project.id"
        class="project-card"
        @click="openProject(project)"
      >
        <div class="project-header">
          <h3>{{ project.name }}</h3>
          <el-tag :type="project.state === 'open' ? 'success' : 'info'" size="small">
            {{ project.state === 'open' ? '进行中' : '已关闭' }}
          </el-tag>
        </div>
        
        <p class="project-desc">{{ project.description || '暂无描述' }}</p>
        
        <div class="project-progress">
          <el-progress :percentage="project.progress" :stroke-width="8" />
        </div>
        
        <div class="project-meta">
          <span><el-icon><FolderOpened /></el-icon> {{ project.card_count || 0 }} 卡片</span>
          <span class="time">{{ formatTime(project.created_at) }}</span>
        </div>
      </div>
      
      <el-empty v-if="!loading && projects.length === 0" description="暂无项目" />
    </div>

    <!-- 分页 -->
    <div class="pagination" v-if="totalPages > 1">
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="perPage"
        :total="total"
        layout="prev, pager, next"
        @current-change="loadProjects"
      />
    </div>

    <!-- 创建项目对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      title="新建项目"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form :model="newProject" label-width="80px">
        <el-form-item label="名称" required>
          <el-input v-model="newProject.name" placeholder="项目名称" maxlength="100" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newProject.description" type="textarea" :rows="3" placeholder="项目描述" />
        </el-form-item>
        
        <el-form-item label="可见性">
          <el-radio-group v-model="newProject.visibility">
            <el-radio label="public">公开</el-radio>
            <el-radio label="private">私有</el-radio>
            <el-radio label="admin">仅管理员</el-radio>
          </el-radio-group>
        </el-form-item>
        
        <el-form-item label="视图类型">
          <el-radio-group v-model="newProject.view_type">
            <el-radio label="kanban">看板</el-radio>
            <el-radio label="list">列表</el-radio>
            <el-radio label="roadmap">路线图</el-radio>
          </el-radio-group>
        </el-form-item>
        
        <el-form-item label="开始日期">
          <el-date-picker v-model="newProject.start_date" type="date" placeholder="选择日期" />
        </el-form-item>
        
        <el-form-item label="截止日期">
          <el-date-picker v-model="newProject.due_date" type="date" placeholder="选择日期" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createProject" :loading="creating">创建</el-button>
      </template>
    </el-dialog>

    <!-- 看板视图对话框 -->
    <el-dialog
      v-model="showBoardDialog"
      :title="currentProject?.name"
      width="95%"
      top="2vh"
      class="board-dialog"
      :close-on-click-modal="false"
    >
      <div class="kanban-board" v-if="currentProject">
        <!-- 工具栏 -->
        <div class="board-toolbar">
          <div class="left">
            <el-button size="small" @click="showAddColumnDialog = true">
              <el-icon><Plus /></el-icon> 添加列
            </el-button>
            <el-button size="small" @click="showAddCardDialog = true">
              <el-icon><Plus /></el-icon> 添加卡片
            </el-button>
          </div>
          <div class="right">
            <el-progress :percentage="currentProject.progress" :stroke-width="6" style="width: 200px" />
          </div>
        </div>

        <!-- 看板列 -->
        <div class="board-columns">
          <div
            v-for="column in columns"
            :key="column.id"
            class="board-column"
            :style="{ borderTopColor: column.color }"
          >
            <div class="column-header">
              <h4>{{ column.name }}</h4>
              <span class="count">{{ column.card_count }}</span>
              <el-dropdown trigger="click">
                <el-icon><MoreFilled /></el-icon>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item @click="editColumn(column)">编辑</el-dropdown-item>
                    <el-dropdown-item @click="deleteColumn(column.id)" divided>删除</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
            
            <div class="column-cards">
              <div
                v-for="card in columnCards[column.id]"
                :key="card.id"
                class="card-item"
                draggable="true"
                @dragstart="dragStart(card)"
                @dragover.prevent
                @drop="dropCard(column.id, $event)"
                @click="openCard(card)"
              >
                <div class="card-priority" :class="card.priority"></div>
                
                <div class="card-title">
                  <span v-if="card.issue_number" class="issue-ref">#{{ card.issue_number }}</span>
                  {{ card.title || card.issue_title || '未命名卡片' }}
                </div>
                
                <div class="card-labels">
                  <span
                    v-for="label in card.labels"
                    :key="label.id"
                    class="label-tag"
                    :style="{ backgroundColor: label.color }"
                  >
                    {{ label.name }}
                  </span>
                </div>
                
                <div class="card-footer">
                  <span v-if="card.due_date" class="due-date">
                    <el-icon><Clock /></el-icon>
                    {{ formatDate(card.due_date) }}
                  </span>
                  <el-avatar v-if="card.assignee_avatar" :size="20" :src="card.assignee_avatar" />
                </div>
              </div>
              
              <div class="add-card-btn" @click="openAddCardDialog(column.id)">
                <el-icon><Plus /></el-icon> 添加卡片
              </div>
            </div>
          </div>
        </div>
      </div>
    </el-dialog>

    <!-- 添加/编辑卡片对话框 -->
    <el-dialog
      v-model="showAddCardDialog"
      :title="editingCard ? '编辑卡片' : '添加卡片'"
      width="600px"
    >
      <el-form :model="newCard" label-width="80px">
        <el-form-item label="标题">
          <el-input v-model="newCard.title" placeholder="卡片标题" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newCard.body" type="textarea" :rows="4" placeholder="卡片描述" />
        </el-form-item>
        
        <el-form-item label="优先级">
          <el-select v-model="newCard.priority">
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="紧急" value="urgent" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="负责人">
          <el-select v-model="newCard.assigned_to" placeholder="选择负责人" clearable>
            <el-option
              v-for="member in members"
              :key="member.user_id"
              :label="member.username"
              :value="member.user_id"
            />
          </el-select>
        </el-form-item>
        
        <el-form-item label="截止日期">
          <el-date-picker v-model="newCard.due_date" type="date" placeholder="选择日期" />
        </el-form-item>
        
        <el-form-item label="预估工时">
          <el-input-number v-model="newCard.estimated_hours" :min="0" :step="0.5" />
        </el-form-item>
        
        <el-form-item label="关联Issue">
          <el-input v-model.number="newCard.issue_id" placeholder="Issue ID" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showAddCardDialog = false">取消</el-button>
        <el-button type="primary" @click="saveCard" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 添加列对话框 -->
    <el-dialog v-model="showAddColumnDialog" title="添加列" width="400px">
      <el-form :model="newColumn" label-width="60px">
        <el-form-item label="名称">
          <el-input v-model="newColumn.name" placeholder="列名称" />
        </el-form-item>
        <el-form-item label="颜色">
          <el-color-picker v-model="newColumn.color" />
        </el-form-item>
        <el-form-item label="WIP限制">
          <el-input-number v-model="newColumn.wip_limit" :min="0" placeholder="不限" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showAddColumnDialog = false">取消</el-button>
        <el-button type="primary" @click="saveColumn" :loading="saving">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, MoreFilled, FolderOpened, Clock } from '@element-plus/icons-vue'
import api from '@/api'

const route = useRoute()

// 状态
const loading = ref(false)
const creating = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const showBoardDialog = ref(false)
const showAddCardDialog = ref(false)
const showAddColumnDialog = ref(false)

const projects = ref<any[]>([])
const columns = ref<any[]>([])
const columnCards = ref<Record<number, any[]>>({})
const members = ref<any[]>([])
const currentProject = ref<any>(null)
const editingCard = ref<any>(null)
const draggingCard = ref<any>(null)

const currentPage = ref(1)
const perPage = ref(20)
const total = ref(0)
const totalPages = ref(0)

const newProject = reactive({
  name: '',
  description: '',
  visibility: 'public',
  view_type: 'kanban',
  start_date: null as Date | null,
  due_date: null as Date | null,
  repository_id: null as number | null
})

const newCard = reactive({
  title: '',
  body: '',
  priority: 'medium',
  assigned_to: null as number | null,
  due_date: null as Date | null,
  estimated_hours: null as number | null,
  issue_id: null as number | null,
  column_id: null as number | null
})

const newColumn = reactive({
  name: '',
  color: '#0366d6',
  wip_limit: null as number | null
})

// 计算属性
const repositoryId = computed(() => Number(route.params.id))

// 加载项目列表
async function loadProjects() {
  loading.value = true
  try {
    const res = await api.get('/projects', {
      params: {
        repository_id: repositoryId.value,
        page: currentPage.value,
        per_page: perPage.value
      }
    })
    
    projects.value = res.data.data.items
    total.value = res.data.data.total
    totalPages.value = res.data.data.total_pages
  } catch (error) {
    ElMessage.error('加载项目失败')
  } finally {
    loading.value = false
  }
}

// 创建项目
async function createProject() {
  if (!newProject.name) {
    ElMessage.warning('请输入项目名称')
    return
  }
  
  creating.value = true
  try {
    const data = { ...newProject, repository_id: repositoryId.value }
    await api.post('/projects', data)
    
    ElMessage.success('项目创建成功')
    showCreateDialog.value = false
    
    // 重置表单
    Object.assign(newProject, {
      name: '',
      description: '',
      visibility: 'public',
      view_type: 'kanban',
      start_date: null,
      due_date: null
    })
    
    loadProjects()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '创建失败')
  } finally {
    creating.value = false
  }
}

// 打开项目看板
async function openProject(project: any) {
  currentProject.value = project
  showBoardDialog.value = true
  
  // 加载列
  const res = await api.get(`/projects/${project.id}/columns`)
  columns.value = res.data.data
  
  // 加载每列的卡片
  for (const column of columns.value) {
    const cardsRes = await api.get(`/projects/columns/${column.id}/cards`)
    columnCards.value[column.id] = cardsRes.data.data
  }
  
  // 加载成员
  const membersRes = await api.get(`/projects/${project.id}/members`)
  members.value = membersRes.data.data
}

// 打开添加卡片对话框
function openAddCardDialog(columnId: number) {
  editingCard.value = null
  newCard.column_id = columnId
  Object.assign(newCard, {
    title: '',
    body: '',
    priority: 'medium',
    assigned_to: null,
    due_date: null,
    estimated_hours: null,
    issue_id: null
  })
  showAddCardDialog.value = true
}

// 保存卡片
async function saveCard() {
  saving.value = true
  try {
    if (editingCard.value) {
      await api.put(`/projects/cards/${editingCard.value.id}`, newCard)
    } else {
      await api.post(`/projects/columns/${newCard.column_id}/cards`, newCard)
    }
    
    ElMessage.success('保存成功')
    showAddCardDialog.value = false
    openProject(currentProject.value)
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

// 保存列
async function saveColumn() {
  if (!newColumn.name) {
    ElMessage.warning('请输入列名称')
    return
  }
  
  saving.value = true
  try {
    await api.post(`/projects/${currentProject.value.id}/columns`, newColumn)
    
    ElMessage.success('列创建成功')
    showAddColumnDialog.value = false
    
    Object.assign(newColumn, { name: '', color: '#0366d6', wip_limit: null })
    openProject(currentProject.value)
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '创建失败')
  } finally {
    saving.value = false
  }
}

// 删除列
async function deleteColumn(columnId: number) {
  try {
    await ElMessageBox.confirm('确定删除此列？列中的卡片也会被删除。', '确认删除', {
      type: 'warning'
    })
    
    await api.delete(`/projects/columns/${columnId}`)
    ElMessage.success('删除成功')
    openProject(currentProject.value)
  } catch (error) {
    // 用户取消
  }
}

// 拖拽开始
function dragStart(card: any) {
  draggingCard.value = card
}

// 放下卡片
async function dropCard(targetColumnId: number, event: DragEvent) {
  if (!draggingCard.value) return
  
  try {
    await api.post(`/projects/cards/${draggingCard.value.id}/move`, {
      column_id: targetColumnId,
      position: 0
    })
    
    openProject(currentProject.value)
  } catch (error) {
    ElMessage.error('移动失败')
  }
  
  draggingCard.value = null
}

// 打开卡片详情
function openCard(card: any) {
  editingCard.value = card
  Object.assign(newCard, {
    title: card.title,
    body: card.body,
    priority: card.priority,
    assigned_to: card.assigned_to,
    due_date: card.due_date ? new Date(card.due_date) : null,
    estimated_hours: card.estimated_hours,
    issue_id: card.issue_id,
    column_id: card.column_id
  })
  showAddCardDialog.value = true
}

// 格式化时间
function formatTime(time: string): string {
  const date = new Date(time)
  return date.toLocaleDateString('zh-CN')
}

// 格式化日期
function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('zh-CN')
}

// 初始化
onMounted(() => {
  loadProjects()
})
</script>

<style scoped>
.projects-page {
  padding: 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.subtitle {
  color: #666;
  margin: 4px 0 0;
}

.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.project-card {
  background: #fff;
  border: 1px solid #e1e4e8;
  border-radius: 8px;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s;
}

.project-card:hover {
  border-color: #0366d6;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.project-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.project-header h3 {
  margin: 0;
  font-size: 18px;
}

.project-desc {
  color: #666;
  font-size: 14px;
  margin-bottom: 16px;
  line-height: 1.5;
}

.project-progress {
  margin-bottom: 12px;
}

.project-meta {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #666;
}

.project-meta span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination {
  display: flex;
  justify-content: center;
  margin-top: 24px;
}

/* 看板样式 */
.board-dialog :deep(.el-dialog__body) {
  padding: 0;
  height: calc(90vh - 120px);
  overflow: hidden;
}

.kanban-board {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.board-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  border-bottom: 1px solid #e1e4e8;
  background: #f6f8fa;
}

.board-columns {
  flex: 1;
  display: flex;
  gap: 16px;
  padding: 16px 20px;
  overflow-x: auto;
}

.board-column {
  flex: 0 0 300px;
  background: #f6f8fa;
  border-radius: 8px;
  border-top: 4px solid;
  display: flex;
  flex-direction: column;
  max-height: 100%;
}

.column-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid #e1e4e8;
}

.column-header h4 {
  margin: 0;
  flex: 1;
}

.column-header .count {
  background: #e1e4e8;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
}

.column-cards {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.card-item {
  background: #fff;
  border: 1px solid #e1e4e8;
  border-radius: 6px;
  padding: 12px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.card-item:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.card-priority {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  border-radius: 4px 0 0 4px;
}

.card-priority.low { background: #6b7280; }
.card-priority.medium { background: #3b82f6; }
.card-priority.high { background: #f59e0b; }
.card-priority.urgent { background: #ef4444; }

.card-title {
  font-weight: 500;
  margin-bottom: 8px;
}

.issue-ref {
  color: #0366d6;
  font-size: 12px;
}

.card-labels {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 8px;
}

.label-tag {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  color: #fff;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.due-date {
  font-size: 12px;
  color: #666;
  display: flex;
  align-items: center;
  gap: 4px;
}

.add-card-btn {
  text-align: center;
  padding: 8px;
  color: #666;
  cursor: pointer;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.add-card-btn:hover {
  background: #e1e4e8;
}

/* 暗色主题 */
:root.dark .project-card,
:root.dark .card-item {
  background: #1c1c1e;
  border-color: #3a3a3c;
}

:root.dark .board-column {
  background: #2c2c2e;
}

:root.dark .board-toolbar {
  background: #2c2c2e;
  border-color: #3a3a3c;
}
</style>
