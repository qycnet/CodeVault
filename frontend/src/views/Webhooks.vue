<template>
  <div class="webhooks-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>Webhooks</h2>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            添加 Webhook
          </el-button>
        </div>
      </template>
      
      <div class="webhooks-info">
        <el-alert
          title="Webhooks 允许外部服务在特定事件发生时接收通知"
          type="info"
          :closable="false"
          show-icon
        >
          当指定的事件发生时，我们会向您配置的 URL 发送 POST 请求
        </el-alert>
      </div>
      
      <!-- Webhook 列表 -->
      <div v-loading="loading" class="webhooks-list">
        <div v-for="webhook in webhooks" :key="webhook.id" class="webhook-item">
          <div class="webhook-header">
            <div class="webhook-url">
              <el-icon><Link /></el-icon>
              <span>{{ webhook.url }}</span>
            </div>
            <div class="webhook-status">
              <el-tag :type="webhook.active ? 'success' : 'info'">
                {{ webhook.active ? '启用' : '禁用' }}
              </el-tag>
            </div>
          </div>
          
          <div class="webhook-events">
            <el-tag
              v-for="event in webhook.events"
              :key="event"
              size="small"
              style="margin-right: 4px"
            >
              {{ getEventText(event) }}
            </el-tag>
          </div>
          
          <div class="webhook-meta">
            <span>最近触发: {{ webhook.last_triggered || '从未' }}</span>
          </div>
          
          <div class="webhook-actions">
            <el-button type="primary" link @click="testWebhook(webhook)">
              测试
            </el-button>
            <el-button type="primary" link @click="editWebhook(webhook)">
              编辑
            </el-button>
            <el-button type="danger" link @click="deleteWebhook(webhook)">
              删除
            </el-button>
          </div>
        </div>
        
        <el-empty v-if="!loading && webhooks.length === 0" description="暂无 Webhook" />
      </div>
    </el-card>
    
    <!-- 创建/编辑 Webhook 对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingWebhook ? '编辑 Webhook' : '添加 Webhook'"
      width="600px"
    >
      <el-form
        ref="webhookForm"
        :model="newWebhook"
        :rules="webhookRules"
        label-width="120px"
      >
        <el-form-item label="Payload URL" prop="url">
          <el-input v-model="newWebhook.url" placeholder="https://example.com/webhook" />
        </el-form-item>
        
        <el-form-item label="Content Type">
          <el-select v-model="newWebhook.content_type">
            <el-option value="application/json" label="application/json" />
            <el-option value="application/x-www-form-urlencoded" label="application/x-www-form-urlencoded" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="Secret">
          <el-input
            v-model="newWebhook.secret"
            placeholder="用于验证请求来源（可选）"
            show-password
          />
        </el-form-item>
        
        <el-form-item label="触发事件">
          <el-checkbox-group v-model="newWebhook.events">
            <el-checkbox value="push">Push</el-checkbox>
            <el-checkbox value="pull_request">Pull Request</el-checkbox>
            <el-checkbox value="issues">Issues</el-checkbox>
            <el-checkbox value="issue_comment">Issue 评论</el-checkbox>
            <el-checkbox value="release">Release</el-checkbox>
            <el-checkbox value="star">Star</el-checkbox>
            <el-checkbox value="fork">Fork</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        
        <el-form-item label="启用">
          <el-switch v-model="newWebhook.active" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="saveWebhook" :loading="saving">
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
import { Plus, Link } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()

const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const editingWebhook = ref<any>(null)

const webhooks = ref<any[]>([])

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const newWebhook = reactive({
  url: '',
  content_type: 'application/json',
  secret: '',
  events: ['push'] as string[],
  active: true
})

const webhookRules = {
  url: [
    { required: true, message: '请输入 Payload URL', trigger: 'blur' },
    { type: 'url', message: '请输入有效的 URL', trigger: 'blur' }
  ]
}

const eventTexts: Record<string, string> = {
  push: 'Push',
  pull_request: 'Pull Request',
  issues: 'Issues',
  issue_comment: 'Issue 评论',
  release: 'Release',
  star: 'Star',
  fork: 'Fork'
}

function getEventText(event: string) {
  return eventTexts[event] || event
}

async function fetchWebhooks() {
  loading.value = true
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/webhooks`)
    if (res.code === 200) {
      webhooks.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch webhooks:', e)
  } finally {
    loading.value = false
  }
}

function editWebhook(webhook: any) {
  editingWebhook.value = webhook
  Object.assign(newWebhook, webhook)
  showCreateDialog.value = true
}

async function saveWebhook() {
  saving.value = true
  try {
    const url = editingWebhook.value
      ? `/repos/${owner.value}/${repo.value}/webhooks/${editingWebhook.value.id}`
      : `/repos/${owner.value}/${repo.value}/webhooks`
    
    const method = editingWebhook.value ? 'put' : 'post'
    const res: any = await api[method](url, newWebhook)
    
    if (res.code === 200) {
      ElMessage.success(editingWebhook.value ? 'Webhook 已更新' : 'Webhook 添加成功')
      showCreateDialog.value = false
      resetForm()
      fetchWebhooks()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function testWebhook(webhook: any) {
  try {
    const res: any = await api.post(`/repos/${owner.value}/${repo.value}/webhooks/${webhook.id}/test`)
    if (res.code === 200) {
      ElMessage.success('测试请求已发送')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '测试失败')
  }
}

async function deleteWebhook(webhook: any) {
  try {
    await ElMessageBox.confirm('确定删除此 Webhook 吗？', '删除确认', { type: 'warning' })
    
    const res: any = await api.delete(`/repos/${owner.value}/${repo.value}/webhooks/${webhook.id}`)
    if (res.code === 200) {
      ElMessage.success('Webhook 已删除')
      fetchWebhooks()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function resetForm() {
  editingWebhook.value = null
  newWebhook.url = ''
  newWebhook.content_type = 'application/json'
  newWebhook.secret = ''
  newWebhook.events = ['push']
  newWebhook.active = true
}

onMounted(() => {
  fetchWebhooks()
})
</script>

<style scoped>
.webhooks-page {
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

.webhooks-info {
  margin-bottom: 20px;
}

.webhooks-list {
  min-height: 200px;
}

.webhook-item {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
}

.webhook-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.webhook-url {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: monospace;
  color: #303133;
}

.webhook-events {
  margin-bottom: 12px;
}

.webhook-meta {
  font-size: 13px;
  color: #909399;
  margin-bottom: 12px;
}

.webhook-actions {
  display: flex;
  gap: 12px;
}
</style>
