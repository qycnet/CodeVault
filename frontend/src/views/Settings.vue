<template>
  <div class="settings-page">
    <el-card>
      <template #header>
        <h2>设置</h2>
      </template>
      
      <el-tabs v-model="activeTab">
        <!-- 个人资料 -->
        <el-tab-pane label="个人资料" name="profile">
          <el-form
            ref="profileForm"
            :model="profile"
            :rules="profileRules"
            label-width="120px"
          >
            <el-form-item label="用户名" prop="username">
              <el-input v-model="profile.username" />
            </el-form-item>
            
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="profile.email" />
            </el-form-item>
            
            <el-form-item label="显示名称">
              <el-input v-model="profile.display_name" />
            </el-form-item>
            
            <el-form-item label="个人简介">
              <el-input
                v-model="profile.bio"
                type="textarea"
                :rows="4"
              />
            </el-form-item>
            
            <el-form-item label="头像">
              <el-upload
                class="avatar-uploader"
                action="/api/users/avatar"
                :show-file-list="false"
                :on-success="handleAvatarSuccess"
              >
                <el-avatar :size="80" :src="profile.avatar">
                  {{ profile.username?.charAt(0).toUpperCase() }}
                </el-avatar>
                <div class="avatar-tip">点击上传头像</div>
              </el-upload>
            </el-form-item>
            
            <el-form-item>
              <el-button type="primary" @click="saveProfile" :loading="saving">
                保存
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <!-- 安全设置 -->
        <el-tab-pane label="安全设置" name="security">
          <div class="security-section">
            <h3>修改密码</h3>
            <el-form
              ref="passwordForm"
              :model="password"
              :rules="passwordRules"
              label-width="120px"
            >
              <el-form-item label="当前密码" prop="current">
                <el-input
                  v-model="password.current"
                  type="password"
                  show-password
                />
              </el-form-item>
              
              <el-form-item label="新密码" prop="new">
                <el-input
                  v-model="password.new"
                  type="password"
                  show-password
                />
              </el-form-item>
              
              <el-form-item label="确认密码" prop="confirm">
                <el-input
                  v-model="password.confirm"
                  type="password"
                  show-password
                />
              </el-form-item>
              
              <el-form-item>
                <el-button type="primary" @click="changePassword" :loading="changingPassword">
                  修改密码
                </el-button>
              </el-form-item>
            </el-form>
          </div>
          
          <el-divider />
          
          <div class="security-section">
            <h3>两步验证</h3>
            <p class="section-desc">
              启用两步验证可以提高账户安全性
            </p>
            <el-switch
              v-model="profile.two_factor_enabled"
              @change="toggleTwoFactor"
            />
          </div>
          
          <el-divider />
          
          <div class="security-section">
            <h3>活跃会话</h3>
            <div v-for="session in sessions" :key="session.id" class="session-item">
              <div class="session-info">
                <div class="session-device">{{ session.device }}</div>
                <div class="session-meta">
                  {{ session.ip }} · {{ session.last_active }}
                </div>
              </div>
              <el-button
                v-if="!session.current"
                type="danger"
                link
                @click="revokeSession(session)"
              >
                撤销
              </el-button>
              <el-tag v-else size="small" type="success">当前</el-tag>
            </div>
          </div>
        </el-tab-pane>
        
        <!-- 通知设置 -->
        <el-tab-pane label="通知设置" name="notifications">
          <el-form label-width="200px">
            <el-form-item label="邮件通知">
              <el-switch v-model="notificationSettings.email" />
            </el-form-item>
            
            <el-form-item label="Issue 通知">
              <el-switch v-model="notificationSettings.issues" />
            </el-form-item>
            
            <el-form-item label="Pull Request 通知">
              <el-switch v-model="notificationSettings.pulls" />
            </el-form-item>
            
            <el-form-item label="评论通知">
              <el-switch v-model="notificationSettings.comments" />
            </el-form-item>
            
            <el-form-item label="提及通知">
              <el-switch v-model="notificationSettings.mentions" />
            </el-form-item>
            
            <el-form-item>
              <el-button type="primary" @click="saveNotificationSettings" :loading="savingNotifications">
                保存
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <!-- 外观设置 -->
        <el-tab-pane label="外观" name="appearance">
          <el-form label-width="120px">
            <el-form-item label="主题">
              <el-radio-group v-model="appearanceSettings.theme">
                <el-radio value="light">浅色</el-radio>
                <el-radio value="dark">深色</el-radio>
                <el-radio value="auto">跟随系统</el-radio>
              </el-radio-group>
            </el-form-item>
            
            <el-form-item label="语言">
              <el-select v-model="appearanceSettings.language">
                <el-option value="zh-CN" label="简体中文" />
                <el-option value="en-US" label="English" />
              </el-select>
            </el-form-item>
            
            <el-form-item label="代码主题">
              <el-select v-model="appearanceSettings.codeTheme">
                <el-option value="github" label="GitHub" />
                <el-option value="monokai" label="Monokai" />
                <el-option value="dracula" label="Dracula" />
              </el-select>
            </el-form-item>
            
            <el-form-item>
              <el-button type="primary" @click="saveAppearanceSettings" :loading="savingAppearance">
                保存
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useUserStore } from '@/stores/user'
import api from '@/api/index'

const userStore = useUserStore()

const activeTab = ref('profile')
const saving = ref(false)
const changingPassword = ref(false)
const savingNotifications = ref(false)
const savingAppearance = ref(false)

const profile = reactive({
  username: '',
  email: '',
  display_name: '',
  bio: '',
  avatar: '',
  two_factor_enabled: false
})

const profileRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 20, message: '用户名长度 3-20 字符', trigger: 'blur' }
  ],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }
  ]
}

const password = reactive({
  current: '',
  new: '',
  confirm: ''
})

const passwordRules = {
  current: [{ required: true, message: '请输入当前密码', trigger: 'blur' }],
  new: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, message: '密码至少 8 位', trigger: 'blur' }
  ],
  confirm: [
    { required: true, message: '请确认新密码', trigger: 'blur' },
    {
      validator: (_rule: any, value: string, callback: any) => {
        if (value !== password.new) {
          callback(new Error('两次密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

const notificationSettings = reactive({
  email: true,
  issues: true,
  pulls: true,
  comments: true,
  mentions: true
})

const appearanceSettings = reactive({
  theme: 'light',
  language: 'zh-CN',
  codeTheme: 'github'
})

const sessions = ref<any[]>([])

async function loadProfile() {
  try {
    const res: any = await api.get('/users/profile')
    if (res.code === 200) {
      Object.assign(profile, res.data)
    }
  } catch (e) {
    console.error('Failed to load profile:', e)
  }
}

async function saveProfile() {
  saving.value = true
  try {
    const res: any = await api.put('/users/profile', profile)
    if (res.code === 200) {
      ElMessage.success('保存成功')
      userStore.setUser(res.data)
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

function handleAvatarSuccess(res: any) {
  if (res.code === 200) {
    profile.avatar = res.data.url
    ElMessage.success('头像上传成功')
  }
}

async function changePassword() {
  changingPassword.value = true
  try {
    const res: any = await api.put('/users/password', {
      current: password.current,
      new: password.new
    })
    if (res.code === 200) {
      ElMessage.success('密码修改成功')
      password.current = ''
      password.new = ''
      password.confirm = ''
    }
  } catch (e: any) {
    ElMessage.error(e.message || '修改失败')
  } finally {
    changingPassword.value = false
  }
}

async function toggleTwoFactor(enabled: boolean) {
  try {
    const res: any = await api.put('/users/two-factor', { enabled })
    if (res.code === 200) {
      ElMessage.success(enabled ? '两步验证已启用' : '两步验证已禁用')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
    profile.two_factor_enabled = !enabled
  }
}

async function loadSessions() {
  try {
    const res: any = await api.get('/users/sessions')
    if (res.code === 200) {
      sessions.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to load sessions:', e)
  }
}

async function revokeSession(session: any) {
  try {
    await ElMessageBox.confirm('确定撤销此会话吗？', '确认', { type: 'warning' })
    
    const res: any = await api.delete(`/users/sessions/${session.id}`)
    if (res.code === 200) {
      const index = sessions.value.findIndex(s => s.id === session.id)
      if (index > -1) {
        sessions.value.splice(index, 1)
      }
      ElMessage.success('会话已撤销')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '撤销失败')
    }
  }
}

async function saveNotificationSettings() {
  savingNotifications.value = true
  try {
    const res: any = await api.put('/users/notification-settings', notificationSettings)
    if (res.code === 200) {
      ElMessage.success('保存成功')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    savingNotifications.value = false
  }
}

async function saveAppearanceSettings() {
  savingAppearance.value = true
  try {
    const res: any = await api.put('/users/appearance-settings', appearanceSettings)
    if (res.code === 200) {
      ElMessage.success('保存成功')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    savingAppearance.value = false
  }
}

onMounted(() => {
  loadProfile()
  loadSessions()
})
</script>

<style scoped>
.settings-page {
  padding: 20px;
}

.settings-page h2 {
  margin: 0;
  font-size: 20px;
  color: #303133;
}

.security-section h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  color: #303133;
}

.section-desc {
  color: #909399;
  font-size: 14px;
  margin-bottom: 12px;
}

.session-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 8px;
}

.session-device {
  font-weight: 500;
  color: #303133;
}

.session-meta {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.avatar-uploader {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.avatar-tip {
  font-size: 12px;
  color: #909399;
}
</style>
