<template>
  <div class="organizations-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>我的组织</h2>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            创建组织
          </el-button>
        </div>
      </template>
      
      <!-- 组织列表 -->
      <div v-loading="loading" class="org-list">
        <div
          v-for="org in organizations"
          :key="org.id"
          class="org-item"
          @click="goToOrg(org)"
        >
          <el-avatar :size="60" :src="org.avatar">
            {{ org.name?.charAt(0).toUpperCase() }}
          </el-avatar>
          <div class="org-info">
            <div class="org-name">{{ org.display_name || org.name }}</div>
            <div class="org-desc">{{ org.description || '暂无描述' }}</div>
            <div class="org-meta">
              <span><el-icon><User /></el-icon> {{ org.members_count || 0 }} 成员</span>
              <span><el-icon><Folder /></el-icon> {{ org.repos_count || 0 }} 仓库</span>
            </div>
          </div>
          <div class="org-actions">
            <el-tag :type="org.role === 'owner' ? 'danger' : 'primary'">
              {{ org.role === 'owner' ? '所有者' : '成员' }}
            </el-tag>
          </div>
        </div>
        
        <el-empty v-if="!loading && organizations.length === 0" description="暂无组织" />
      </div>
    </el-card>
    
    <!-- 创建组织对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      title="创建组织"
      width="500px"
    >
      <el-form
        ref="createForm"
        :model="newOrg"
        :rules="orgRules"
        label-width="100px"
      >
        <el-form-item label="组织名称" prop="name">
          <el-input v-model="newOrg.name" placeholder="组织名称（唯一标识）" />
        </el-form-item>
        
        <el-form-item label="显示名称">
          <el-input v-model="newOrg.display_name" placeholder="显示名称" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input
            v-model="newOrg.description"
            type="textarea"
            :rows="3"
            placeholder="组织描述..."
          />
        </el-form-item>
        
        <el-form-item label="网站">
          <el-input v-model="newOrg.website" placeholder="https://example.com" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createOrg" :loading="creating">
          创建
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, User, Folder } from '@element-plus/icons-vue'
import api from '@/api/index'

const router = useRouter()

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)

const organizations = ref<any[]>([])

const newOrg = reactive({
  name: '',
  display_name: '',
  description: '',
  website: ''
})

const orgRules = {
  name: [
    { required: true, message: '请输入组织名称', trigger: 'blur' },
    { min: 2, max: 50, message: '组织名称长度 2-50 字符', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9_-]+$/, message: '只能包含字母、数字、下划线和连字符', trigger: 'blur' }
  ]
}

async function fetchOrganizations() {
  loading.value = true
  try {
    const res: any = await api.get('/organizations')
    if (res.code === 200) {
      organizations.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch organizations:', e)
  } finally {
    loading.value = false
  }
}

async function createOrg() {
  creating.value = true
  try {
    const res: any = await api.post('/organizations', newOrg)
    if (res.code === 200) {
      ElMessage.success('组织创建成功')
      showCreateDialog.value = false
      newOrg.name = ''
      newOrg.display_name = ''
      newOrg.description = ''
      newOrg.website = ''
      fetchOrganizations()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

function goToOrg(org: any) {
  router.push(`/orgs/${org.name}`)
}

onMounted(() => {
  fetchOrganizations()
})
</script>

<style scoped>
.organizations-page {
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

.org-list {
  min-height: 200px;
}

.org-item {
  display: flex;
  gap: 20px;
  padding: 20px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 16px;
  cursor: pointer;
  transition: all 0.3s;
}

.org-item:hover {
  background: #f5f7fa;
  border-color: #409eff;
}

.org-info {
  flex: 1;
}

.org-name {
  font-size: 18px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
}

.org-desc {
  font-size: 14px;
  color: #606266;
  margin-bottom: 8px;
}

.org-meta {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #909399;
}

.org-meta span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.org-actions {
  display: flex;
  align-items: center;
}
</style>
