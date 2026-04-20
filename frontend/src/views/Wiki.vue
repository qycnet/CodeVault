<template>
  <div class="wiki-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>Wiki</h2>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            新建页面
          </el-button>
        </div>
      </template>
      
      <div class="wiki-container">
        <!-- 侧边栏 -->
        <div class="wiki-sidebar">
          <h3>页面列表</h3>
          <div class="page-list">
            <div
              v-for="page in pages"
              :key="page.id"
              class="page-item"
              :class="{ 'is-active': currentPage?.id === page.id }"
              @click="selectPage(page)"
            >
              <el-icon><Document /></el-icon>
              <span>{{ page.title }}</span>
            </div>
          </div>
        </div>
        
        <!-- 内容区 -->
        <div class="wiki-content">
          <div v-if="currentPage" class="page-content">
            <div class="page-header">
              <h1>{{ currentPage.title }}</h1>
              <div class="page-actions">
                <el-button type="primary" link @click="editPage(currentPage)">
                  <el-icon><Edit /></el-icon>
                  编辑
                </el-button>
                <el-button type="danger" link @click="deletePage(currentPage)">
                  <el-icon><Delete /></el-icon>
                  删除
                </el-button>
              </div>
            </div>
            
            <div class="page-meta">
              <span>最后更新: {{ formatDate(currentPage.updated_at) }}</span>
              <span>作者: {{ currentPage.author_name }}</span>
            </div>
            
            <el-divider />
            
            <div class="page-body" v-html="renderMarkdown(currentPage.content)"></div>
          </div>
          
          <el-empty v-else description="选择一个页面查看内容" />
        </div>
      </div>
    </el-card>
    
    <!-- 创建/编辑页面对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingPage ? '编辑页面' : '新建页面'"
      width="800px"
    >
      <el-form
        ref="pageForm"
        :model="newPage"
        :rules="pageRules"
        label-width="100px"
      >
        <el-form-item label="页面标题" prop="title">
          <el-input v-model="newPage.title" placeholder="页面标题" />
        </el-form-item>
        
        <el-form-item label="页面内容">
          <el-input
            v-model="newPage.content"
            type="textarea"
            :rows="15"
            placeholder="支持 Markdown 格式..."
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="savePage" :loading="saving">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Document, Edit, Delete } from '@element-plus/icons-vue'
import DOMPurify from 'dompurify'
import api from '@/api/index'

const route = useRoute()

const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const editingPage = ref<any>(null)
const currentPage = ref<any>(null)

const pages = ref<any[]>([])

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const newPage = reactive({
  title: '',
  content: ''
})

const pageRules = {
  title: [
    { required: true, message: '请输入页面标题', trigger: 'blur' }
  ]
}

function formatDate(date: string) {
  return new Date(date).toLocaleString('zh-CN')
}

function renderMarkdown(content: string) {
  if (!content) return ''
  
  // 简单的 Markdown 渲染
  const html = content
    .replace(/\n/g, '<br>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/```([\s\S]+?)```/g, '<pre><code>$1</code></pre>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    .replace(/## (.+)/g, '<h3>$1</h3>')
    .replace(/# (.+)/g, '<h2>$1</h2>')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
  
  return DOMPurify.sanitize(html)
}

async function fetchPages() {
  loading.value = true
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/wiki`)
    if (res.code === 200) {
      pages.value = res.data || []
      if (pages.value.length > 0 && !currentPage.value) {
        currentPage.value = pages.value[0]
      }
    }
  } catch (e) {
    console.error('Failed to fetch wiki pages:', e)
  } finally {
    loading.value = false
  }
}

function selectPage(page: any) {
  currentPage.value = page
}

function editPage(page: any) {
  editingPage.value = page
  newPage.title = page.title
  newPage.content = page.content
  showCreateDialog.value = true
}

async function savePage() {
  saving.value = true
  try {
    const url = editingPage.value
      ? `/repos/${owner.value}/${repo.value}/wiki/${editingPage.value.id}`
      : `/repos/${owner.value}/${repo.value}/wiki`
    
    const method = editingPage.value ? 'put' : 'post'
    const res: any = await api[method](url, newPage)
    
    if (res.code === 200) {
      ElMessage.success(editingPage.value ? '页面已更新' : '页面创建成功')
      showCreateDialog.value = false
      resetForm()
      fetchPages()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function deletePage(page: any) {
  try {
    await ElMessageBox.confirm('确定删除此页面吗？', '删除确认', { type: 'warning' })
    
    const res: any = await api.delete(`/repos/${owner.value}/${repo.value}/wiki/${page.id}`)
    if (res.code === 200) {
      ElMessage.success('页面已删除')
      if (currentPage.value?.id === page.id) {
        currentPage.value = null
      }
      fetchPages()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function resetForm() {
  editingPage.value = null
  newPage.title = ''
  newPage.content = ''
}

onMounted(() => {
  fetchPages()
})
</script>

<style scoped>
.wiki-page {
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

.wiki-container {
  display: flex;
  gap: 24px;
  min-height: 500px;
}

.wiki-sidebar {
  width: 250px;
  flex-shrink: 0;
}

.wiki-sidebar h3 {
  margin: 0 0 16px 0;
  font-size: 14px;
  color: #909399;
}

.page-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s;
  color: #606266;
}

.page-item:hover {
  background: #f5f7fa;
}

.page-item.is-active {
  background: #ecf5ff;
  color: #409eff;
}

.wiki-content {
  flex: 1;
  min-width: 0;
}

.page-content {
  padding: 0 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.page-actions {
  display: flex;
  gap: 8px;
}

.page-meta {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #909399;
}

.page-body {
  color: #606266;
  line-height: 1.8;
}

.page-body :deep(h2) {
  margin: 24px 0 12px 0;
  font-size: 20px;
  color: #303133;
}

.page-body :deep(h3) {
  margin: 20px 0 10px 0;
  font-size: 16px;
  color: #303133;
}

.page-body :deep(code) {
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
}

.page-body :deep(pre) {
  background: #f5f7fa;
  padding: 12px;
  border-radius: 8px;
  overflow-x: auto;
}

.page-body :deep(a) {
  color: #409eff;
  text-decoration: none;
}
</style>
