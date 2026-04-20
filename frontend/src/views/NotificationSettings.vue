<template>
  <div class="notification-settings-page">
    <div class="page-header">
      <h2>通知设置</h2>
      <p>配置您的邮件通知偏好</p>
    </div>

    <el-card v-loading="loading">
      <el-form :model="form" label-width="180px">
        <el-form-item label="启用邮件通知">
          <el-switch v-model="form.email_enabled" />
          <div class="form-tip">接收邮件通知</div>
        </el-form-item>

        <el-divider>通知类型</el-divider>

        <el-form-item label="Issue 通知">
          <el-switch v-model="form.notify_issue" />
          <div class="form-tip">当您的 Issue 被创建、更新或关闭时通知</div>
        </el-form-item>

        <el-form-item label="Pull Request 通知">
          <el-switch v-model="form.notify_pr" />
          <div class="form-tip">当您的 PR 被创建、合并或关闭时通知</div>
        </el-form-item>

        <el-form-item label="评论通知">
          <el-switch v-model="form.notify_comment" />
          <div class="form-tip">当有人评论您的 Issue 或 PR 时通知</div>
        </el-form-item>

        <el-form-item label="@ 提及通知">
          <el-switch v-model="form.notify_mention" />
          <div class="form-tip">当有人在评论中 @ 您时通知</div>
        </el-form-item>

        <el-form-item label="关注的仓库通知">
          <el-switch v-model="form.notify_watch" />
          <div class="form-tip">当您关注的仓库有新活动时通知</div>
        </el-form-item>

        <el-divider>摘要邮件</el-divider>

        <el-form-item label="启用摘要邮件">
          <el-switch v-model="form.digest_enabled" />
          <div class="form-tip">定期发送活动摘要，而非即时通知</div>
        </el-form-item>

        <el-form-item label="摘要频率" v-if="form.digest_enabled">
          <el-radio-group v-model="form.digest_frequency">
            <el-radio value="daily">每天</el-radio>
            <el-radio value="weekly">每周</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-divider></el-divider>

        <el-form-item>
          <el-button type="primary" @click="saveSettings" :loading="saving">
            保存设置
          </el-button>
          <el-button @click="sendTestEmail" :loading="sendingTest">
            发送测试邮件
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- SMTP 配置提示 -->
    <el-card class="smtp-config-card">
      <template #header>
        <span>SMTP 配置（管理员）</span>
      </template>
      
      <el-alert
        type="info"
        :closable="false"
        show-icon
      >
        <template #title>
          邮件服务需要配置 SMTP 服务器
        </template>
        <p>请在服务器环境变量中配置以下参数：</p>
        <ul>
          <li><code>SMTP_HOST</code> - SMTP 服务器地址</li>
          <li><code>SMTP_PORT</code> - SMTP 端口（默认 25）</li>
          <li><code>SMTP_USER</code> - SMTP 用户名</li>
          <li><code>SMTP_PASS</code> - SMTP 密码</li>
          <li><code>SMTP_ENCRYPTION</code> - 加密方式（tls/ssl）</li>
          <li><code>MAIL_FROM</code> - 发件人邮箱</li>
          <li><code>MAIL_FROM_NAME</code> - 发件人名称</li>
        </ul>
      </el-alert>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const sendingTest = ref(false)

const form = reactive({
  email_enabled: true,
  notify_issue: true,
  notify_pr: true,
  notify_comment: true,
  notify_mention: true,
  notify_watch: true,
  digest_enabled: false,
  digest_frequency: 'daily'
})

async function fetchSettings() {
  loading.value = true
  try {
    const response = await axios.get('/api/notification/settings')
    
    if (response.data.success) {
      Object.assign(form, response.data.settings)
    }
  } catch (error) {
    ElMessage.error('获取设置失败')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    const response = await axios.put('/api/notification/settings', form)
    
    if (response.data.success) {
      ElMessage.success('设置已保存')
    } else {
      ElMessage.error(response.data.message || '保存失败')
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function sendTestEmail() {
  sendingTest.value = true
  try {
    const response = await axios.post('/api/notification/test')
    
    if (response.data.success) {
      ElMessage.success('测试邮件已发送，请检查收件箱')
    } else {
      ElMessage.error(response.data.message || '发送失败')
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '发送失败')
  } finally {
    sendingTest.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<style lang="scss" scoped>
.notification-settings-page {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.page-header {
  margin-bottom: 24px;
  
  h2 {
    margin: 0 0 8px 0;
    color: #24292f;
  }
  
  p {
    color: #57606a;
    margin: 0;
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.smtp-config-card {
  margin-top: 24px;
  
  ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
    
    li {
      margin: 4px 0;
      
      code {
        background: #f5f7fa;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: monospace;
        color: #e6a23c;
      }
    }
  }
}
</style>
