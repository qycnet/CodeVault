<template>
  <div class="webhooks-page">
    <div class="page-header">
      <h2>Webhooks</h2>
      <el-button type="primary" @click="showCreateDialog = true">
        添加 Webhook
      </el-button>
    </div>

    <div class="webhooks-list">
      <div v-for="webhook in webhooks" :key="webhook.id" class="webhook-item">
        <div class="webhook-header">
          <el-tag :type="webhook.is_active ? 'success' : 'info'">
            {{ webhook.is_active ? '已启用' : '已禁用' }}
          </el-tag>
          <span class="webhook-url">{{ webhook.url }}</span>
        </div>
        <div class="webhook-events">
          <el-tag v-for="event in parseEvents(webhook.events)" :key="event" size="small">
            {{ event }}
          </el-tag>
        </div>
        <div class="webhook-actions">
          <el-switch v-model="webhook.is_active" @change="toggleWebhook(webhook)" />
          <el-button size="small" @click="editWebhook(webhook)">编辑</el-button>
          <el-button size="small" type="danger" @click="deleteWebhook(webhook.id)">删除</el-button>
        </div>
      </div>
      <el-empty v-if="webhooks.length === 0" description="暂无 Webhook" />
    </div>

    <!-- 创建 Webhook 对话框 -->
    <el-dialog v-model="showCreateDialog" title="添加 Webhook" width="500px">
      <el-form :model="webhookForm" label-width="80px">
        <el-form-item label="URL">
          <el-input v-model="webhookForm.url" placeholder="https://example.com/webhook" />
        </el-form-item>
        <el-form-item label="Secret">
          <el-input v-model="webhookForm.secret" placeholder="可选，用于签名验证" />
        </el-form-item>
        <el-form-item label="事件">
          <el-checkbox-group v-model="webhookForm.events">
            <el-checkbox label="push">Push</el-checkbox>
            <el-checkbox label="pull_request">Pull Request</el-checkbox>
            <el-checkbox label="issue">Issue</el-checkbox>
            <el-checkbox label="release">Release</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createWebhook">添加</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import axios from 'axios'

const route = useRoute()

const webhooks = ref<any[]>([])
const showCreateDialog = ref(false)

const webhookForm = reactive({
  url: '',
  secret: '',
  events: ['push'],
  repo_id: 0
})

function parseEvents(eventsJson: string) {
  try {
    return JSON.parse(eventsJson) || []
  } catch {
    return []
  }
}

async function fetchWebhooks() {
  try {
    const response = await axios.get('/api/webhooks', {
      params: { repo_id: webhookForm.repo_id }
    })
    
    if (response.data.success) {
      webhooks.value = response.data.webhooks
    }
  } catch (error) {
    console.error('Failed to fetch webhooks:', error)
  }
}

async function createWebhook() {
  try {
    const response = await axios.post('/api/webhooks', webhookForm)
    
    if (response.data.success) {
      ElMessage.success('Webhook 添加成功')
      showCreateDialog.value = false
      fetchWebhooks()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '添加失败')
  }
}

async function toggleWebhook(webhook: any) {
  try {
    await axios.put('/api/webhooks', {
      id: webhook.id,
      is_active: webhook.is_active
    })
    ElMessage.success(webhook.is_active ? '已启用' : '已禁用')
  } catch (error) {
    webhook.is_active = !webhook.is_active
    ElMessage.error('操作失败')
  }
}

async function deleteWebhook(id: number) {
  try {
    await ElMessageBox.confirm('确定删除此 Webhook？')
    await axios.delete('/api/webhooks', { params: { id } })
    ElMessage.success('已删除')
    fetchWebhooks()
  } catch (error) {
    // 取消操作
  }
}

function editWebhook(webhook: any) {
  Object.assign(webhookForm, {
    id: webhook.id,
    url: webhook.url,
    secret: webhook.secret,
    events: parseEvents(webhook.events)
  })
  showCreateDialog.value = true
}

onMounted(fetchWebhooks)
</script>

<style lang="scss" scoped>
.webhooks-page {
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

.webhook-item {
  border: 1px solid #d0d7de;
  border-radius: 6px;
  padding: 16px;
  margin-bottom: 12px;
  
  .webhook-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
    
    .webhook-url {
      font-family: monospace;
      color: #0969da;
    }
  }
  
  .webhook-events {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
  }
  
  .webhook-actions {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}
</style>
