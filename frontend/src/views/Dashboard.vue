<template>
  <div class="dashboard">
    <el-container>
      <el-header>
        <div class="header-content">
          <h1>CodeVault</h1>
          <div class="header-right">
            <el-dropdown @command="handleCommand">
              <span class="user-info">
                <el-avatar :size="32" icon="User" />
                <span>{{ userStore.username }}</span>
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="profile">个人主页</el-dropdown-item>
                  <el-dropdown-item command="ssh">SSH Keys</el-dropdown-item>
                  <el-dropdown-item divided command="logout">退出登录</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>
      </el-header>
      
      <el-container>
        <el-aside width="200px">
          <el-menu :default-active="activeMenu" router>
            <el-menu-item index="/">
              <el-icon><HomeFilled /></el-icon>
              <span>仪表盘</span>
            </el-menu-item>
            <el-menu-item index="/repos">
              <el-icon><Folder /></el-icon>
              <span>我的仓库</span>
            </el-menu-item>
            <el-menu-item index="/settings/ssh-keys">
              <el-icon><Key /></el-icon>
              <span>SSH Keys</span>
            </el-menu-item>
          </el-menu>
        </el-aside>
        
        <el-main>
          <div class="dashboard-content">
            <el-row :gutter="20">
              <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                  <div class="stat-value">{{ stats.repos }}</div>
                  <div class="stat-label">仓库数量</div>
                </el-card>
              </el-col>
              <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                  <div class="stat-value">{{ stats.issues }}</div>
                  <div class="stat-label">Issue</div>
                </el-card>
              </el-col>
              <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                  <div class="stat-value">{{ stats.pulls }}</div>
                  <div class="stat-label">Pull Request</div>
                </el-card>
              </el-col>
              <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                  <div class="stat-value">{{ stats.sshKeys }}</div>
                  <div class="stat-label">SSH Keys</div>
                </el-card>
              </el-col>
            </el-row>
            
            <el-card class="recent-repos">
              <template #header>
                <div class="card-header">
                  <span>最近仓库</span>
                  <el-button type="primary" size="small" @click="showCreateDialog = true">
                    新建仓库
                  </el-button>
                </div>
              </template>
              <el-table :data="recentRepos" style="width: 100%">
                <el-table-column prop="name" label="仓库名称">
                  <template #default="{ row }">
                    <router-link :to="`/repos/${row.owner_name}/${row.name}`">
                      {{ row.name }}
                    </router-link>
                  </template>
                </el-table-column>
                <el-table-column prop="description" label="描述" />
                <el-table-column prop="is_private" label="可见性" width="100">
                  <template #default="{ row }">
                    <el-tag :type="row.is_private ? 'danger' : 'success'" size="small">
                      {{ row.is_private ? '私有' : '公开' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="updated_at" label="更新时间" width="180" />
              </el-table>
            </el-card>
          </div>
        </el-main>
      </el-container>
    </el-container>
    
    <!-- 创建仓库对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建仓库" width="500px">
      <el-form :model="newRepo" label-width="80px">
        <el-form-item label="仓库名称" required>
          <el-input v-model="newRepo.name" placeholder="my-project" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="newRepo.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="可见性">
          <el-radio-group v-model="newRepo.is_private">
            <el-radio :value="false">公开</el-radio>
            <el-radio :value="true">私有</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createRepo">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { HomeFilled, Folder, Key } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { repoApi } from '@/api/repo'
import type { Repository } from '@/api/types'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const activeMenu = computed(() => route.path)
const showCreateDialog = ref(false)
const creating = ref(false)
const recentRepos = ref<Repository[]>([])

const stats = reactive({
  repos: 0,
  issues: 0,
  pulls: 0,
  sshKeys: 0
})

const newRepo = reactive({
  name: '',
  description: '',
  is_private: false
})

async function fetchRepos() {
  try {
    const res = await repoApi.list({ page_size: 5 })
    if (res.code === 200) {
      recentRepos.value = res.data.items
      stats.repos = res.data.total
    }
  } catch (e) {
    console.error('Failed to fetch repos:', e)
  }
}

async function createRepo() {
  if (!newRepo.name) {
    ElMessage.warning('请输入仓库名称')
    return
  }
  
  creating.value = true
  try {
    const res = await repoApi.create(newRepo)
    if (res.code === 200) {
      ElMessage.success('仓库创建成功')
      showCreateDialog.value = false
      newRepo.name = ''
      newRepo.description = ''
      fetchRepos()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

function handleCommand(command: string) {
  switch (command) {
    case 'profile':
      router.push('/profile')
      break
    case 'ssh':
      router.push('/settings/ssh-keys')
      break
    case 'logout':
      userStore.logout()
      router.push('/login')
      break
  }
}

onMounted(() => {
  userStore.fetchUser()
  fetchRepos()
})
</script>

<style scoped>
.dashboard {
  height: 100vh;
}

.el-header {
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
  padding: 0 20px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 100%;
}

.header-content h1 {
  margin: 0;
  color: #409eff;
  font-size: 20px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.el-aside {
  background: #fff;
  border-right: 1px solid #e4e7ed;
}

.el-main {
  background: #f5f7fa;
  padding: 20px;
}

.dashboard-content {
  max-width: 1200px;
  margin: 0 auto;
}

.stat-card {
  text-align: center;
}

.stat-value {
  font-size: 32px;
  font-weight: bold;
  color: #409eff;
}

.stat-label {
  color: #909399;
  margin-top: 8px;
}

.recent-repos {
  margin-top: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header a {
  color: #409eff;
  text-decoration: none;
}

.card-header a:hover {
  text-decoration: underline;
}
</style>
