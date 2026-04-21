<template>
  <div class="org-page">
    <div class="org-header">
      <el-avatar :size="80">{{ org.name?.charAt(0).toUpperCase() }}</el-avatar>
      <div class="org-info">
        <h1>{{ org.display_name || org.name }}</h1>
        <p>{{ org.description || '暂无描述' }}</p>
      </div>
      <div class="org-actions" v-if="isOrgAdmin">
        <el-button @click="showSettingsDialog = true">设置</el-button>
        <el-button type="primary" @click="showInviteDialog = true">邀请成员</el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab">
      <el-tab-pane :label="`仓库 (${repos.length})`" name="repos">
        <div class="repos-list">
          <div v-for="repo in repos" :key="repo.id" class="repo-item">
            <router-link :to="`/repos/${org.name}/${repo.name}`" class="repo-name">
              {{ repo.name }}
            </router-link>
            <p class="repo-desc">{{ repo.description || '暂无描述' }}</p>
          </div>
          <el-empty v-if="repos.length === 0" description="暂无仓库" />
        </div>
      </el-tab-pane>

      <el-tab-pane :label="`成员 (${members.length})`" name="members">
        <div class="members-list">
          <div v-for="member in members" :key="member.id" class="member-item">
            <el-avatar :size="40">{{ member.username?.charAt(0).toUpperCase() }}</el-avatar>
            <div class="member-info">
              <span class="username">{{ member.username }}</span>
              <el-tag :type="getRoleType(member.role)" size="small">{{ getRoleName(member.role) }}</el-tag>
            </div>
            <div class="member-actions" v-if="isOrgAdmin && member.user_id !== currentUserId">
              <el-select v-model="member.role" size="small" @change="updateMemberRole(member)">
                <el-option value="admin" label="管理员" />
                <el-option value="member" label="成员" />
              </el-select>
              <el-button size="small" type="danger" @click="removeMember(member)">移除</el-button>
            </div>
          </div>
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 邀请成员对话框 -->
    <el-dialog v-model="showInviteDialog" title="邀请成员" width="400px">
      <el-form :model="inviteForm" label-width="80px">
        <el-form-item label="用户名">
          <el-input v-model="inviteForm.username" placeholder="输入用户名" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="inviteForm.role">
            <el-option value="admin" label="管理员" />
            <el-option value="member" label="成员" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showInviteDialog = false">取消</el-button>
        <el-button type="primary" @click="inviteMember">邀请</el-button>
      </template>
    </el-dialog>

    <!-- 组织设置对话框 -->
    <el-dialog v-model="showSettingsDialog" title="组织设置" width="500px">
      <el-form :model="settingsForm" label-width="80px">
        <el-form-item label="名称">
          <el-input v-model="settingsForm.display_name" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="settingsForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showSettingsDialog = false">取消</el-button>
        <el-button type="primary" @click="saveSettings">保存</el-button>
        <el-button type="danger" @click="deleteOrg">删除组织</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const org = ref<any>({})
const repos = ref<any[]>([])
const members = ref<any[]>([])
const activeTab = ref('repos')
const showInviteDialog = ref(false)
const showSettingsDialog = ref(false)

const currentUser = computed(() => JSON.parse(localStorage.getItem('user') || '{}'))
const currentUserId = computed(() => currentUser.value.id)

const isOrgAdmin = computed(() => {
  const member = members.value.find((m: any) => m.user_id === currentUserId.value)
  return member?.role === 'admin'
})

const inviteForm = reactive({
  username: '',
  role: 'member'
})

const settingsForm = reactive({
  display_name: '',
  description: ''
})

function getRoleType(role: string) {
  return role === 'admin' ? 'danger' : 'info'
}

function getRoleName(role: string) {
  return role === 'admin' ? '管理员' : '成员'
}

async function fetchOrg() {
  const orgName = route.params.org as string
  
  try {
    const orgRes = await axios.get(`/api/orgs/${orgName}`)
    if (orgRes.data.success) {
      org.value = orgRes.data.org
      Object.assign(settingsForm, {
        display_name: org.value.display_name || '',
        description: org.value.description || ''
      })
    }
    
    const reposRes = await axios.get('/api/repos', { params: { org: orgName } })
    if (reposRes.data.success) {
      repos.value = reposRes.data.repos
    }
    
    const membersRes = await axios.get(`/api/orgs/${orgName}/members`)
    if (membersRes.data.success) {
      members.value = membersRes.data.members
    }
  } catch (error) {
    ElMessage.error('获取组织信息失败')
  }
}

async function inviteMember() {
  try {
    const response = await axios.post(`/api/orgs/${org.value.name}/members`, inviteForm)
    if (response.data.success) {
      ElMessage.success('邀请成功')
      showInviteDialog.value = false
      fetchOrg()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '邀请失败')
  }
}

async function updateMemberRole(member: any) {
  try {
    await axios.put(`/api/orgs/${org.value.name}/members/${member.username}`, {
      role: member.role
    })
    ElMessage.success('角色已更新')
  } catch (error) {
    ElMessage.error('更新失败')
  }
}

async function removeMember(member: any) {
  try {
    await ElMessageBox.confirm(`确定移除成员 ${member.username}？`)
    await axios.delete(`/api/orgs/${org.value.name}/members/${member.username}`)
    ElMessage.success('成员已移除')
    fetchOrg()
  } catch (error) {
    // 取消操作
  }
}

async function saveSettings() {
  try {
    await axios.put(`/api/orgs/${org.value.name}`, settingsForm)
    ElMessage.success('设置已保存')
    showSettingsDialog.value = false
    fetchOrg()
  } catch (error) {
    ElMessage.error('保存失败')
  }
}

async function deleteOrg() {
  try {
    await ElMessageBox.confirm('确定删除此组织？此操作不可恢复！', '警告', { type: 'warning' })
    await axios.delete(`/api/orgs/${org.value.name}`)
    ElMessage.success('组织已删除')
    router.push('/')
  } catch (error) {
    // 取消操作
  }
}

onMounted(fetchOrg)
</script>

<style lang="scss" scoped>
.org-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 20px;
}

.org-header {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  padding: 24px 0;
  border-bottom: 1px solid #d0d7de;
  margin-bottom: 24px;
}

.org-info {
  flex: 1;
  
  h1 {
    margin: 0 0 8px 0;
  }
  
  p {
    color: #57606a;
    margin: 0;
  }
}

.repos-list, .members-list {
  margin-top: 16px;
}

.repo-item {
  padding: 16px;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  margin-bottom: 12px;
  
  .repo-name {
    font-size: 16px;
    font-weight: 600;
    color: #0969da;
    text-decoration: none;
    
    &:hover {
      text-decoration: underline;
    }
  }
  
  .repo-desc {
    color: #57606a;
    font-size: 14px;
    margin: 8px 0 0 0;
  }
}

.member-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  margin-bottom: 8px;
  
  .member-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    
    .username {
      font-weight: 500;
    }
  }
  
  .member-actions {
    display: flex;
    gap: 8px;
  }
}
</style>
