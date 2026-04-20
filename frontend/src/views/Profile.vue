<template>
  <div class="profile-page">
    <div class="profile-header">
      <el-avatar :size="100" class="avatar">
        {{ userStore.username.charAt(0).toUpperCase() }}
      </el-avatar>
      <div class="user-info">
        <h1>{{ userStore.username }}</h1>
        <p>{{ profile.email }}</p>
      </div>
    </div>
    
    <el-tabs v-model="activeTab">
      <el-tab-pane label="个人资料" name="profile">
        <el-card>
          <el-form :model="profile" label-width="100px">
            <el-form-item label="用户名">
              <el-input v-model="profile.username" disabled />
            </el-form-item>
            <el-form-item label="邮箱">
              <el-input v-model="profile.email" />
            </el-form-item>
            <el-form-item label="个人简介">
              <el-input v-model="profile.bio" type="textarea" :rows="3" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary">保存更改</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>
      
      <el-tab-pane label="SSH 密钥" name="ssh">
        <el-card>
          <div class="ssh-header">
            <h3>SSH 密钥</h3>
            <el-button type="primary" size="small" @click="showAddSSH = true">
              <el-icon class="el-icon--left"><Plus /></el-icon>
              添加密钥
            </el-button>
          </div>
          
          <el-empty v-if="sshKeys.length === 0" description="暂无 SSH 密钥" />
          
          <div v-else class="ssh-list">
            <div v-for="key in sshKeys" :key="key.id" class="ssh-item">
              <div class="ssh-info">
                <h4>{{ key.title }}</h4>
                <p class="ssh-fingerprint">{{ key.fingerprint }}</p>
                <p class="ssh-date">添加于 {{ key.createdAt }}</p>
              </div>
              <el-button type="danger" text @click="deleteSSHKey(key)">删除</el-button>
            </div>
          </div>
        </el-card>
      </el-tab-pane>
      
      <el-tab-pane label="安全设置" name="security">
        <el-card header="修改密码">
          <el-form :model="passwordForm" label-width="100px">
            <el-form-item label="当前密码">
              <el-input v-model="passwordForm.oldPassword" type="password" show-password />
            </el-form-item>
            <el-form-item label="新密码">
              <el-input v-model="passwordForm.newPassword" type="password" show-password />
            </el-form-item>
            <el-form-item label="确认密码">
              <el-input v-model="passwordForm.confirmPassword" type="password" show-password />
            </el-form-item>
            <el-form-item>
              <el-button type="primary">更新密码</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>
    </el-tabs>
    
    <!-- 添加 SSH 密钥对话框 -->
    <el-dialog v-model="showAddSSH" title="添加 SSH 密钥" width="560px">
      <el-form :model="sshForm" label-position="top">
        <el-form-item label="标题">
          <el-input v-model="sshForm.title" placeholder="例如：我的笔记本电脑" />
        </el-form-item>
        <el-form-item label="密钥">
          <el-input
            v-model="sshForm.key"
            type="textarea"
            :rows="6"
            placeholder="粘贴您的 SSH 公钥..."
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddSSH = false">取消</el-button>
        <el-button type="primary" @click="addSSHKey">添加密钥</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useUserStore } from '@/store/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'

const userStore = useUserStore()

const activeTab = ref('profile')
const showAddSSH = ref(false)

const profile = reactive({
  username: userStore.username,
  email: 'user@example.com',
  bio: ''
})

const sshKeys = ref([
  {
    id: 1,
    title: '我的笔记本电脑',
    fingerprint: 'SHA256:abc123...',
    createdAt: '2026-04-01'
  }
])

const sshForm = reactive({
  title: '',
  key: ''
})

const passwordForm = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: ''
})

function addSSHKey() {
  if (!sshForm.title || !sshForm.key) {
    ElMessage.warning('请填写完整信息')
    return
  }
  
  sshKeys.value.push({
    id: Date.now(),
    title: sshForm.title,
    fingerprint: 'SHA256:' + sshForm.key.substring(0, 20) + '...',
    createdAt: new Date().toISOString().split('T')[0]
  })
  
  showAddSSH.value = false
  sshForm.title = ''
  sshForm.key = ''
  ElMessage.success('SSH 密钥添加成功')
}

function deleteSSHKey(key) {
  ElMessageBox.confirm(`确定要删除密钥 "${key.title}" 吗？`, '删除确认', {
    type: 'warning'
  }).then(() => {
    const index = sshKeys.value.findIndex(k => k.id === key.id)
    if (index > -1) {
      sshKeys.value.splice(index, 1)
      ElMessage.success('密钥已删除')
    }
  }).catch(() => {})
}
</script>

<style lang="scss" scoped>
.profile-page {
  max-width: 800px;
  margin: 0 auto;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-bottom: 32px;
  
  .avatar {
    background: linear-gradient(135deg, #409eff 0%, #67c23a 100%);
    font-size: 40px;
    color: #fff;
  }
  
  .user-info {
    h1 {
      margin: 0 0 8px;
      font-size: 28px;
    }
    
    p {
      margin: 0;
      color: #57606a;
    }
  }
}

.ssh-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  
  h3 {
    margin: 0;
  }
}

.ssh-list {
  .ssh-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border: 1px solid #d0d7de;
    border-radius: 6px;
    margin-bottom: 12px;
    
    .ssh-info {
      h4 {
        margin: 0 0 8px;
      }
      
      .ssh-fingerprint {
        margin: 0 0 4px;
        font-family: monospace;
        font-size: 12px;
        color: #57606a;
      }
      
      .ssh-date {
        margin: 0;
        font-size: 12px;
        color: #57606a;
      }
    }
  }
}
</style>
