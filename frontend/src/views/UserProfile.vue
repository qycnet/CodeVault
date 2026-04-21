<template>
  <div class="user-profile-page">
    <div class="profile-header">
      <el-avatar :size="100">{{ user.username?.charAt(0).toUpperCase() }}</el-avatar>
      <div class="profile-info">
        <h1>{{ user.username }}</h1>
        <p v-if="user.bio">{{ user.bio }}</p>
        <div class="profile-meta">
          <span v-if="user.location"><el-icon><Location /></el-icon> {{ user.location }}</span>
          <span v-if="user.website"><el-icon><Link /></el-icon> <a :href="user.website">{{ user.website }}</a></span>
          <span><el-icon><Calendar /></el-icon> 加入于 {{ formatDate(user.created_at) }}</span>
        </div>
      </div>
      <div class="profile-actions" v-if="isCurrentUser">
        <el-button @click="showEditDialog = true">编辑资料</el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab">
      <el-tab-pane :label="`仓库 (${repos.length})`" name="repos">
        <div class="repos-list">
          <div v-for="repo in repos" :key="repo.id" class="repo-item">
            <div class="repo-header">
              <router-link :to="`/repos/${user.username}/${repo.name}`" class="repo-name">
                {{ repo.name }}
              </router-link>
              <el-tag :type="repo.is_private ? 'warning' : 'success'" size="small">
                {{ repo.is_private ? '私有' : '公开' }}
              </el-tag>
            </div>
            <p class="repo-desc">{{ repo.description || '暂无描述' }}</p>
            <div class="repo-meta">
              <span>{{ repo.language || '未知' }}</span>
              <span>更新于 {{ formatTime(repo.updated_at) }}</span>
            </div>
          </div>
          <el-empty v-if="repos.length === 0" description="暂无仓库" />
        </div>
      </el-tab-pane>

      <el-tab-pane :label="`组织 (${orgs.length})`" name="orgs">
        <div class="orgs-list">
          <div v-for="org in orgs" :key="org.id" class="org-item">
            <el-avatar :size="48">{{ org.name?.charAt(0).toUpperCase() }}</el-avatar>
            <div class="org-info">
              <router-link :to="`/orgs/${org.name}`">{{ org.display_name || org.name }}</router-link>
              <p>{{ org.description || '暂无描述' }}</p>
            </div>
          </div>
          <el-empty v-if="orgs.length === 0" description="暂无组织" />
        </div>
      </el-tab-pane>

      <el-tab-pane label="Star" name="stars">
        <div class="stars-list">
          <div v-for="star in stars" :key="star.id" class="repo-item">
            <div class="repo-header">
              <router-link :to="`/repos/${star.owner}/${star.name}`" class="repo-name">
                {{ star.owner }} / {{ star.name }}
              </router-link>
            </div>
            <p class="repo-desc">{{ star.description || '暂无描述' }}</p>
          </div>
          <el-empty v-if="stars.length === 0" description="暂无 Star" />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 编辑资料对话框 -->
    <el-dialog v-model="showEditDialog" title="编辑资料" width="500px">
      <el-form :model="editForm" label-width="80px">
        <el-form-item label="用户名">
          <el-input v-model="editForm.username" disabled />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="editForm.email" />
        </el-form-item>
        <el-form-item label="简介">
          <el-input v-model="editForm.bio" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="位置">
          <el-input v-model="editForm.location" />
        </el-form-item>
        <el-form-item label="网站">
          <el-input v-model="editForm.website" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEditDialog = false">取消</el-button>
        <el-button type="primary" @click="saveProfile">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Location, Link, Calendar } from '@element-plus/icons-vue'
import axios from 'axios'

const route = useRoute()

const user = ref<any>({})
const repos = ref<any[]>([])
const orgs = ref<any[]>([])
const stars = ref<any[]>([])
const activeTab = ref('repos')
const showEditDialog = ref(false)

const currentUser = computed(() => {
  // 从 store 或 localStorage 获取当前用户
  return JSON.parse(localStorage.getItem('user') || '{}')
})

const isCurrentUser = computed(() => {
  return currentUser.value.username === route.params.username
})

const editForm = reactive({
  username: '',
  email: '',
  bio: '',
  location: '',
  website: ''
})

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('zh-CN')
}

function formatTime(date: string) {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const days = Math.floor(diff / 86400000)
  
  if (days < 1) return '今天'
  if (days < 7) return `${days} 天前`
  if (days < 30) return `${Math.floor(days / 7)} 周前`
  return d.toLocaleDateString('zh-CN')
}

async function fetchProfile() {
  const username = route.params.username as string
  
  try {
    // 获取用户信息
    const userRes = await axios.get(`/api/users/detail`, { params: { username } })
    if (userRes.data.success) {
      user.value = userRes.data.user
      
      if (isCurrentUser.value) {
        Object.assign(editForm, {
          username: user.value.username,
          email: user.value.email || '',
          bio: user.value.bio || '',
          location: user.value.location || '',
          website: user.value.website || ''
        })
      }
    }
    
    // 获取用户仓库
    const reposRes = await axios.get('/api/repos', { params: { username } })
    if (reposRes.data.success) {
      repos.value = reposRes.data.repos
    }
    
    // 获取用户组织
    const orgsRes = await axios.get('/api/orgs')
    if (orgsRes.data.success) {
      orgs.value = orgsRes.data.orgs
    }
    
    // 获取 Star
    if (isCurrentUser.value) {
      const starsRes = await axios.get('/api/user/stars')
      if (starsRes.data.success) {
        stars.value = starsRes.data.stars
      }
    }
  } catch (error) {
    ElMessage.error('获取用户信息失败')
  }
}

async function saveProfile() {
  try {
    const response = await axios.put('/api/user/profile', editForm)
    if (response.data.success) {
      ElMessage.success('资料已更新')
      showEditDialog.value = false
      fetchProfile()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

onMounted(fetchProfile)
</script>

<style lang="scss" scoped>
.user-profile-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 20px;
}

.profile-header {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  padding: 24px 0;
  border-bottom: 1px solid #d0d7de;
  margin-bottom: 24px;
}

.profile-info {
  flex: 1;
  
  h1 {
    margin: 0 0 8px 0;
    font-size: 24px;
  }
  
  p {
    color: #57606a;
    margin: 0 0 12px 0;
  }
  
  .profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 14px;
    color: #57606a;
    
    span {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    a {
      color: #0969da;
    }
  }
}

.repos-list, .orgs-list, .stars-list {
  margin-top: 16px;
}

.repo-item {
  padding: 16px;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  margin-bottom: 12px;
  
  .repo-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
  }
  
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
    margin: 0 0 8px 0;
  }
  
  .repo-meta {
    font-size: 12px;
    color: #57606a;
    
    span {
      margin-right: 16px;
    }
  }
}

.org-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  margin-bottom: 12px;
  
  .org-info {
    a {
      font-size: 16px;
      font-weight: 600;
      color: #0969da;
      text-decoration: none;
      
      &:hover {
        text-decoration: underline;
      }
    }
    
    p {
      color: #57606a;
      font-size: 14px;
      margin: 4px 0 0 0;
    }
  }
}
</style>
