<template>
  <div class="admin-panel">
    <el-container>
      <el-aside width="220px">
        <el-menu
          :default-active="activeMenu"
          @select="handleMenuSelect"
        >
          <el-menu-item index="dashboard">
            <el-icon><DataAnalysis /></el-icon>
            <span>仪表盘</span>
          </el-menu-item>
          
          <el-menu-item index="users">
            <el-icon><User /></el-icon>
            <span>用户管理</span>
          </el-menu-item>
          
          <el-menu-item index="repos">
            <el-icon><Folder /></el-icon>
            <span>仓库管理</span>
          </el-menu-item>
          
          <el-menu-item index="issues">
            <el-icon><Document /></el-icon>
            <span>Issue 管理</span>
          </el-menu-item>
          
          <el-menu-item index="settings">
            <el-icon><Setting /></el-icon>
            <span>系统设置</span>
          </el-menu-item>
          
          <el-menu-item index="logs">
            <el-icon><List /></el-icon>
            <span>操作日志</span>
          </el-menu-item>
          
          <el-menu-item index="monitor">
            <el-icon><Monitor /></el-icon>
            <span>系统监控</span>
          </el-menu-item>
        </el-menu>
      </el-aside>
      
      <el-main>
        <!-- 仪表盘 -->
        <div v-if="activeMenu === 'dashboard'" class="dashboard">
          <h2>系统概览</h2>
          
          <el-row :gutter="20">
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-icon" style="background: #409eff;">
                    <el-icon><User /></el-icon>
                  </div>
                  <div class="stat-info">
                    <div class="stat-value">{{ stats.users }}</div>
                    <div class="stat-label">用户总数</div>
                  </div>
                </div>
              </el-card>
            </el-col>
            
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-icon" style="background: #67c23a;">
                    <el-icon><Folder /></el-icon>
                  </div>
                  <div class="stat-info">
                    <div class="stat-value">{{ stats.repos }}</div>
                    <div class="stat-label">仓库总数</div>
                  </div>
                </div>
              </el-card>
            </el-col>
            
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-icon" style="background: #e6a23c;">
                    <el-icon><Document /></el-icon>
                  </div>
                  <div class="stat-info">
                    <div class="stat-value">{{ stats.issues }}</div>
                    <div class="stat-label">Issue 总数</div>
                  </div>
                </div>
              </el-card>
            </el-col>
            
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-card">
                  <div class="stat-icon" style="background: #f56c6c;">
                    <el-icon><Connection /></el-icon>
                  </div>
                  <div class="stat-info">
                    <div class="stat-value">{{ stats.prs }}</div>
                    <div class="stat-label">PR 总数</div>
                  </div>
                </div>
              </el-card>
            </el-col>
          </el-row>
          
          <el-row :gutter="20" style="margin-top: 20px;">
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>最近注册用户</span>
                </template>
                <el-table :data="recentUsers" style="width: 100%">
                  <el-table-column prop="username" label="用户名" />
                  <el-table-column prop="email" label="邮箱" />
                  <el-table-column prop="created_at" label="注册时间" />
                </el-table>
              </el-card>
            </el-col>
            
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>最近创建仓库</span>
                </template>
                <el-table :data="recentRepos" style="width: 100%">
                  <el-table-column prop="name" label="仓库名" />
                  <el-table-column prop="owner" label="所有者" />
                  <el-table-column prop="created_at" label="创建时间" />
                </el-table>
              </el-card>
            </el-col>
          </el-row>
        </div>
        
        <!-- 用户管理 -->
        <div v-if="activeMenu === 'users'" class="users-management">
          <h2>用户管理</h2>
          
          <el-card>
            <template #header>
              <div class="card-header">
                <el-input
                  v-model="userSearch"
                  placeholder="搜索用户"
                  style="width: 300px;"
                  clearable
                >
                  <template #prefix>
                    <el-icon><Search /></el-icon>
                  </template>
                </el-input>
                
                <el-button type="primary">
                  <el-icon><Plus /></el-icon>
                  添加用户
                </el-button>
              </div>
            </template>
            
            <el-table :data="users" style="width: 100%">
              <el-table-column prop="id" label="ID" width="80" />
              <el-table-column prop="username" label="用户名" />
              <el-table-column prop="email" label="邮箱" />
              <el-table-column prop="role" label="角色" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.role === 'admin' ? 'danger' : 'primary'">
                    {{ row.role === 'admin' ? '管理员' : '用户' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="status" label="状态" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.status === 'active' ? 'success' : 'info'">
                    {{ row.status === 'active' ? '正常' : '禁用' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="注册时间" width="180" />
              <el-table-column label="操作" width="200" fixed="right">
                <template #default="{ row }">
                  <el-button size="small" @click="editUser(row)">编辑</el-button>
                  <el-button size="small" type="warning" @click="toggleUserStatus(row)">
                    {{ row.status === 'active' ? '禁用' : '启用' }}
                  </el-button>
                  <el-button size="small" type="danger" @click="deleteUser(row)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
        
        <!-- 仓库管理 -->
        <div v-if="activeMenu === 'repos'" class="repos-management">
          <h2>仓库管理</h2>
          
          <el-card>
            <el-table :data="repos" style="width: 100%">
              <el-table-column prop="id" label="ID" width="80" />
              <el-table-column prop="name" label="仓库名" />
              <el-table-column prop="owner" label="所有者" />
              <el-table-column prop="is_private" label="类型" width="100">
                <template #default="{ row }">
                  <el-tag :type="row.is_private ? 'warning' : 'success'">
                    {{ row.is_private ? '私有' : '公开' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="size" label="大小" width="100" />
              <el-table-column prop="created_at" label="创建时间" width="180" />
              <el-table-column label="操作" width="200" fixed="right">
                <template #default="{ row }">
                  <el-button size="small" @click="viewRepo(row)">查看</el-button>
                  <el-button size="small" type="danger" @click="deleteRepo(row)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
        
        <!-- 系统设置 -->
        <div v-if="activeMenu === 'settings'" class="system-settings">
          <h2>系统设置</h2>
          
          <el-card>
            <el-form :model="settings" label-width="150px">
              <el-form-item label="站点名称">
                <el-input v-model="settings.site_name" />
              </el-form-item>
              
              <el-form-item label="站点描述">
                <el-input v-model="settings.site_description" type="textarea" />
              </el-form-item>
              
              <el-form-item label="允许注册">
                <el-switch v-model="settings.allow_register" />
              </el-form-item>
              
              <el-form-item label="邮箱验证">
                <el-switch v-model="settings.email_verification" />
              </el-form-item>
              
              <el-form-item label="最大仓库大小">
                <el-input-number v-model="settings.max_repo_size" :min="10" :max="10000" />
                <span style="margin-left: 10px;">MB</span>
              </el-form-item>
              
              <el-form-item label="CORS 白名单">
                <el-input
                  v-model="settings.cors_whitelist"
                  type="textarea"
                  :rows="3"
                  placeholder="每行一个域名"
                />
              </el-form-item>
              
              <el-form-item>
                <el-button type="primary" @click="saveSettings">保存设置</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </div>
        
        <!-- 操作日志 -->
        <div v-if="activeMenu === 'logs'" class="operation-logs">
          <h2>操作日志</h2>
          
          <el-card>
            <el-table :data="logs" style="width: 100%">
              <el-table-column prop="id" label="ID" width="80" />
              <el-table-column prop="user" label="用户" width="120" />
              <el-table-column prop="action" label="操作" />
              <el-table-column prop="target" label="目标" />
              <el-table-column prop="ip" label="IP 地址" width="140" />
              <el-table-column prop="created_at" label="时间" width="180" />
            </el-table>
          </el-card>
        </div>
        
        <!-- 系统监控 -->
        <div v-if="activeMenu === 'monitor'" class="system-monitor">
          <h2>系统监控</h2>
          
          <el-row :gutter="20">
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>服务器状态</span>
                </template>
                <div class="monitor-item">
                  <span>CPU 使用率</span>
                  <el-progress :percentage="35" :color="'#67c23a'" />
                </div>
                <div class="monitor-item">
                  <span>内存使用率</span>
                  <el-progress :percentage="62" :color="'#e6a23c'" />
                </div>
                <div class="monitor-item">
                  <span>磁盘使用率</span>
                  <el-progress :percentage="48" :color="'#409eff'" />
                </div>
              </el-card>
            </el-col>
            
            <el-col :span="12">
              <el-card>
                <template #header>
                  <span>服务状态</span>
                </template>
                <el-table :data="services" style="width: 100%">
                  <el-table-column prop="name" label="服务" />
                  <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                      <el-tag :type="row.status === 'running' ? 'success' : 'danger'">
                        {{ row.status === 'running' ? '运行中' : '已停止' }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column prop="port" label="端口" width="100" />
                </el-table>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-main>
    </el-container>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  DataAnalysis, User, Folder, Document, Setting, List, Monitor,
  Search, Plus, Connection
} from '@element-plus/icons-vue'

const activeMenu = ref('dashboard')
const userSearch = ref('')

const stats = reactive({
  users: 156,
  repos: 89,
  issues: 342,
  prs: 67
})

const recentUsers = ref([
  { username: 'user1', email: 'user1@example.com', created_at: '2026-04-20 20:30' },
  { username: 'user2', email: 'user2@example.com', created_at: '2026-04-20 19:15' },
  { username: 'user3', email: 'user3@example.com', created_at: '2026-04-20 18:00' },
])

const recentRepos = ref([
  { name: 'CodeVault', owner: '任', created_at: '2026-04-20 20:00' },
  { name: 'project-alpha', owner: 'Steve', created_at: '2026-04-20 18:30' },
  { name: 'web-app', owner: 'Sentinel', created_at: '2026-04-20 17:00' },
])

const users = ref([
  { id: 1, username: '任', email: 'ren@example.com', role: 'admin', status: 'active', created_at: '2026-04-01' },
  { id: 2, username: 'Steve', email: 'steve@example.com', role: 'user', status: 'active', created_at: '2026-04-05' },
  { id: 3, username: 'Sentinel', email: 'sentinel@example.com', role: 'user', status: 'active', created_at: '2026-04-10' },
])

const repos = ref([
  { id: 1, name: 'CodeVault', owner: '任', is_private: false, size: '15 MB', created_at: '2026-04-20' },
  { id: 2, name: 'private-repo', owner: 'Steve', is_private: true, size: '8 MB', created_at: '2026-04-19' },
])

const settings = reactive({
  site_name: 'CodeVault',
  site_description: '中文版代码仓库管理系统',
  allow_register: true,
  email_verification: true,
  max_repo_size: 500,
  cors_whitelist: 'http://localhost\nhttp://localhost:8000'
})

const logs = ref([
  { id: 1, user: '任', action: '创建仓库', target: 'CodeVault', ip: '192.168.1.100', created_at: '2026-04-20 20:00' },
  { id: 2, user: 'Steve', action: '创建 Issue', target: '#123', ip: '192.168.1.101', created_at: '2026-04-20 19:30' },
  { id: 3, user: 'Sentinel', action: '合并 PR', target: '#45', ip: '192.168.1.102', created_at: '2026-04-20 19:00' },
])

const services = ref([
  { name: 'Web Server', status: 'running', port: 80 },
  { name: 'MySQL', status: 'running', port: 3306 },
  { name: 'Redis', status: 'running', port: 6379 },
])

function handleMenuSelect(index: string) {
  activeMenu.value = index
}

function editUser(row: any) {
  ElMessage.info(`编辑用户: ${row.username}`)
}

function toggleUserStatus(row: any) {
  row.status = row.status === 'active' ? 'disabled' : 'active'
  ElMessage.success(`用户状态已更新`)
}

async function deleteUser(row: any) {
  try {
    await ElMessageBox.confirm('确定要删除此用户吗？', '删除用户', { type: 'warning' })
    ElMessage.success('用户已删除')
  } catch (e) {
    // 取消删除
  }
}

function viewRepo(row: any) {
  ElMessage.info(`查看仓库: ${row.name}`)
}

async function deleteRepo(row: any) {
  try {
    await ElMessageBox.confirm('确定要删除此仓库吗？此操作不可撤销！', '删除仓库', { type: 'warning' })
    ElMessage.success('仓库已删除')
  } catch (e) {
    // 取消删除
  }
}

function saveSettings() {
  ElMessage.success('设置已保存')
}
</script>

<style scoped>
.admin-panel {
  padding: 20px;
}

.el-aside {
  background: #fff;
  border-right: 1px solid #e4e7ed;
}

.el-menu {
  border-right: none;
}

.dashboard h2,
.users-management h2,
.repos-management h2,
.system-settings h2,
.operation-logs h2,
.system-monitor h2 {
  margin-bottom: 20px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 24px;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.monitor-item {
  margin-bottom: 16px;
}

.monitor-item:last-child {
  margin-bottom: 0;
}

.monitor-item span {
  display: block;
  margin-bottom: 8px;
  color: #606266;
}
</style>
