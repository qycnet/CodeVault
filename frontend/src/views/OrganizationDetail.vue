<template>
  <div class="org-detail">
    <el-card v-loading="loading">
      <!-- 组织头部 -->
      <div class="org-header">
        <el-avatar :size="80" :src="org.avatar">
          {{ org.name?.charAt(0).toUpperCase() }}
        </el-avatar>
        <div class="org-info">
          <h1>{{ org.display_name || org.name }}</h1>
          <p class="org-desc">{{ org.description || '暂无描述' }}</p>
          <div class="org-meta">
            <span v-if="org.website">
              <el-icon><Link /></el-icon>
              <a :href="org.website" target="_blank">{{ org.website }}</a>
            </span>
            <span><el-icon><User /></el-icon> {{ org.members_count || 0 }} 成员</span>
            <span><el-icon><Folder /></el-icon> {{ org.repos_count || 0 }} 仓库</span>
          </div>
        </div>
        <div class="org-actions">
          <el-button v-if="org.role === 'owner'" type="primary" @click="showSettingsDialog = true">
            <el-icon><Setting /></el-icon>
            设置
          </el-button>
          <el-button v-if="org.role !== 'owner'" type="danger" @click="leaveOrg">
            离开组织
          </el-button>
        </div>
      </div>
      
      <el-divider />
      
      <!-- 标签页 -->
      <el-tabs v-model="activeTab">
        <!-- 仓库 -->
        <el-tab-pane label="仓库" name="repos">
          <div class="repos-list">
            <div
              v-for="repo in repos"
              :key="repo.id"
              class="repo-item"
              @click="goToRepo(repo)"
            >
              <div class="repo-icon">
                <el-icon size="24"><Folder /></el-icon>
              </div>
              <div class="repo-info">
                <div class="repo-name">{{ org.name }} / {{ repo.name }}</div>
                <div class="repo-desc">{{ repo.description || '暂无描述' }}</div>
              </div>
            </div>
            
            <el-empty v-if="repos.length === 0" description="暂无仓库" />
          </div>
        </el-tab-pane>
        
        <!-- 成员 -->
        <el-tab-pane label="成员" name="members">
          <div class="members-header">
            <el-input
              v-model="memberSearch"
              placeholder="搜索成员..."
              style="width: 300px"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
            <el-button
              v-if="org.role === 'owner'"
              type="primary"
              @click="showInviteDialog = true"
            >
              <el-icon><Plus /></el-icon>
              邀请成员
            </el-button>
          </div>
          
          <div class="members-list">
            <div v-for="member in filteredMembers" :key="member.id" class="member-item">
              <el-avatar :size="40" :src="member.avatar">
                {{ member.username?.charAt(0).toUpperCase() }}
              </el-avatar>
              <div class="member-info">
                <div class="member-name">{{ member.username }}</div>
                <div class="member-email">{{ member.email }}</div>
              </div>
              <div class="member-role">
                <el-select
                  v-if="org.role === 'owner'"
                  v-model="member.role"
                  @change="updateMemberRole(member)"
                >
                  <el-option value="owner" label="所有者" />
                  <el-option value="admin" label="管理员" />
                  <el-option value="member" label="成员" />
                </el-select>
                <el-tag v-else :type="getRoleType(member.role)">
                  {{ getRoleText(member.role) }}
                </el-tag>
              </div>
              <el-button
                v-if="org.role === 'owner' && member.id !== currentUserId"
                type="danger"
                link
                @click="removeMember(member)"
              >
                移除
              </el-button>
            </div>
          </div>
        </el-tab-pane>
        
        <!-- 团队 -->
        <el-tab-pane label="团队" name="teams">
          <div class="teams-header">
            <el-button
              v-if="org.role === 'owner' || org.role === 'admin'"
              type="primary"
              @click="showCreateTeamDialog = true"
            >
              <el-icon><Plus /></el-icon>
              创建团队
            </el-button>
          </div>
          
          <div class="teams-list">
            <div v-for="team in teams" :key="team.id" class="team-item" @click="goToTeam(team)">
              <div class="team-icon">
                <el-icon size="24"><UserFilled /></el-icon>
              </div>
              <div class="team-info">
                <div class="team-name">{{ team.name }}</div>
                <div class="team-desc">{{ team.description || '暂无描述' }}</div>
              </div>
              <div class="team-meta">
                <span>{{ team.members_count || 0 }} 成员</span>
                <span>{{ team.repos_count || 0 }} 仓库</span>
              </div>
            </div>
            
            <el-empty v-if="teams.length === 0" description="暂无团队" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
    
    <!-- 邀请成员对话框 -->
    <el-dialog v-model="showInviteDialog" title="邀请成员" width="500px">
      <el-form :model="invite" label-width="100px">
        <el-form-item label="用户名/邮箱">
          <el-input v-model="invite.username" placeholder="输入用户名或邮箱" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="invite.role">
            <el-option value="admin" label="管理员" />
            <el-option value="member" label="成员" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showInviteDialog = false">取消</el-button>
        <el-button type="primary" @click="inviteMember" :loading="inviting">
          邀请
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 创建团队对话框 -->
    <el-dialog v-model="showCreateTeamDialog" title="创建团队" width="500px">
      <el-form :model="newTeam" label-width="100px">
        <el-form-item label="团队名称">
          <el-input v-model="newTeam.name" placeholder="团队名称" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="newTeam.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="权限">
          <el-select v-model="newTeam.permission">
            <el-option value="read" label="只读" />
            <el-option value="write" label="读写" />
            <el-option value="admin" label="管理" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTeamDialog = false">取消</el-button>
        <el-button type="primary" @click="createTeam" :loading="creatingTeam">
          创建
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Setting, Link, User, Folder, Search, Plus, UserFilled } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import api from '@/api/index'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const loading = ref(false)
const activeTab = ref('repos')
const orgName = computed(() => route.params.org as string)

const org = ref<any>({})
const repos = ref<any[]>([])
const members = ref<any[]>([])
const teams = ref<any[]>([])

const memberSearch = ref('')
const showInviteDialog = ref(false)
const showCreateTeamDialog = ref(false)
const showSettingsDialog = ref(false)
const inviting = ref(false)
const creatingTeam = ref(false)

const invite = reactive({
  username: '',
  role: 'member'
})

const newTeam = reactive({
  name: '',
  description: '',
  permission: 'read'
})

const currentUserId = computed(() => userStore.user?.id)

const filteredMembers = computed(() => {
  if (!memberSearch.value) return members.value
  const search = memberSearch.value.toLowerCase()
  return members.value.filter(m =>
    m.username?.toLowerCase().includes(search) ||
    m.email?.toLowerCase().includes(search)
  )
})

function getRoleType(role: string) {
  switch (role) {
    case 'owner': return 'danger'
    case 'admin': return 'warning'
    default: return 'info'
  }
}

function getRoleText(role: string) {
  switch (role) {
    case 'owner': return '所有者'
    case 'admin': return '管理员'
    default: return '成员'
  }
}

async function fetchOrg() {
  loading.value = true
  try {
    const res: any = await api.get(`/organizations/${orgName.value}`)
    if (res.code === 200) {
      org.value = res.data
    }
  } catch (e) {
    console.error('Failed to fetch org:', e)
  } finally {
    loading.value = false
  }
}

async function fetchRepos() {
  try {
    const res: any = await api.get(`/organizations/${orgName.value}/repos`)
    if (res.code === 200) {
      repos.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch repos:', e)
  }
}

async function fetchMembers() {
  try {
    const res: any = await api.get(`/organizations/${orgName.value}/members`)
    if (res.code === 200) {
      members.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch members:', e)
  }
}

async function fetchTeams() {
  try {
    const res: any = await api.get(`/organizations/${orgName.value}/teams`)
    if (res.code === 200) {
      teams.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch teams:', e)
  }
}

async function inviteMember() {
  inviting.value = true
  try {
    const res: any = await api.post(`/organizations/${orgName.value}/members`, invite)
    if (res.code === 200) {
      ElMessage.success('邀请已发送')
      showInviteDialog.value = false
      fetchMembers()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '邀请失败')
  } finally {
    inviting.value = false
  }
}

async function updateMemberRole(member: any) {
  try {
    const res: any = await api.put(
      `/organizations/${orgName.value}/members/${member.id}`,
      { role: member.role }
    )
    if (res.code === 200) {
      ElMessage.success('角色已更新')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '更新失败')
  }
}

async function removeMember(member: any) {
  try {
    await ElMessageBox.confirm('确定移除此成员吗？', '确认', { type: 'warning' })
    
    const res: any = await api.delete(`/organizations/${orgName.value}/members/${member.id}`)
    if (res.code === 200) {
      ElMessage.success('成员已移除')
      fetchMembers()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '移除失败')
    }
  }
}

async function createTeam() {
  creatingTeam.value = true
  try {
    const res: any = await api.post(`/organizations/${orgName.value}/teams`, newTeam)
    if (res.code === 200) {
      ElMessage.success('团队创建成功')
      showCreateTeamDialog.value = false
      fetchTeams()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creatingTeam.value = false
  }
}

async function leaveOrg() {
  try {
    await ElMessageBox.confirm('确定离开此组织吗？', '确认', { type: 'warning' })
    
    const res: any = await api.delete(`/organizations/${orgName.value}/leave`)
    if (res.code === 200) {
      ElMessage.success('已离开组织')
      router.push('/orgs')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '操作失败')
    }
  }
}

function goToRepo(repo: any) {
  router.push(`/repos/${orgName.value}/${repo.name}`)
}

function goToTeam(team: any) {
  router.push(`/orgs/${orgName.value}/teams/${team.id}`)
}

onMounted(() => {
  fetchOrg()
  fetchRepos()
  fetchMembers()
  fetchTeams()
})
</script>

<style scoped>
.org-detail {
  padding: 20px;
}

.org-header {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}

.org-info {
  flex: 1;
}

.org-info h1 {
  margin: 0 0 8px 0;
  font-size: 24px;
  color: #303133;
}

.org-desc {
  color: #606266;
  margin-bottom: 12px;
}

.org-meta {
  display: flex;
  gap: 16px;
  font-size: 14px;
  color: #909399;
}

.org-meta span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.org-meta a {
  color: #409eff;
  text-decoration: none;
}

.org-actions {
  display: flex;
  gap: 12px;
}

.repos-list, .members-list, .teams-list {
  margin-top: 16px;
}

.repo-item, .team-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.3s;
}

.repo-item:hover, .team-item:hover {
  background: #f5f7fa;
}

.repo-icon, .team-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #f5f7fa;
  border-radius: 8px;
}

.repo-info, .team-info {
  flex: 1;
}

.repo-name, .team-name {
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
}

.repo-desc, .team-desc {
  font-size: 13px;
  color: #909399;
}

.team-meta {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #909399;
}

.members-header, .teams-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}

.member-item {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 12px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 8px;
}

.member-info {
  flex: 1;
}

.member-name {
  font-weight: 500;
  color: #303133;
}

.member-email {
  font-size: 13px;
  color: #909399;
}

.member-role {
  min-width: 120px;
}
</style>
