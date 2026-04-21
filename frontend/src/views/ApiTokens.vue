<template>
  <div class="api-tokens-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <h2>API Tokens</h2>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            生成新 Token
          </el-button>
        </div>
      </template>
      
      <el-alert
        title="API Token 用于访问 CodeVault API"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 20px"
      >
        请妥善保管您的 Token，不要泄露给他人。Token 只在创建时显示一次。
      </el-alert>
      
      <!-- Token 列表 -->
      <div v-loading="loading" class="tokens-list">
        <el-table :data="tokens" style="width: 100%">
          <el-table-column label="名称" prop="name" width="200" />
          
          <el-table-column label="Token" width="300">
            <template #default="{ row }">
              <div class="token-display">
                <code v-if="row.showToken">{{ row.token }}</code>
                <code v-else>{{ maskToken(row.token) }}</code>
                <el-button
                  type="primary"
                  link
                  @click="row.showToken = !row.showToken"
                >
                  <el-icon>
                    <View v-if="!row.showToken" />
                    <Hide v-else />
                  </el-icon>
                </el-button>
              </div>
            </template>
          </el-table-column>
          
          <el-table-column label="权限" width="200">
            <template #default="{ row }">
              <el-tag
                v-for="scope in row.scopes"
                :key="scope"
                size="small"
                style="margin-right: 4px"
              >
                {{ getScopeText(scope) }}
              </el-tag>
            </template>
          </el-table-column>
          
          <el-table-column label="创建时间" width="180">
            <template #default="{ row }">
              {{ formatDate(row.created_at) }}
            </template>
          </el-table-column>
          
          <el-table-column label="过期时间" width="180">
            <template #default="{ row }">
              <span v-if="row.expires_at" :class="{ 'text-danger': isExpired(row.expires_at) }">
                {{ formatDate(row.expires_at) }}
              </span>
              <span v-else class="text-success">永不过期</span>
            </template>
          </el-table-column>
          
          <el-table-column label="最后使用" width="180">
            <template #default="{ row }">
              {{ row.last_used_at ? formatDate(row.last_used_at) : '从未使用' }}
            </template>
          </el-table-column>
          
          <el-table-column label="操作" fixed="right" width="100">
            <template #default="{ row }">
              <el-button type="danger" link @click="deleteToken(row)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        
        <el-empty v-if="!loading && tokens.length === 0" description="暂无 API Token" />
      </div>
    </el-card>
    
    <!-- 创建 Token 对话框 -->
    <el-dialog v-model="showCreateDialog" title="生成 API Token" width="500px">
      <el-form :model="newToken" :rules="tokenRules" ref="tokenForm" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="newToken.name" placeholder="CI Pipeline Token" />
        </el-form-item>
        
        <el-form-item label="权限" prop="scopes">
          <el-checkbox-group v-model="newToken.scopes">
            <div class="scope-item">
              <el-checkbox value="repo">
                <span class="scope-label">repo</span>
                <span class="scope-desc"> - 完整仓库访问权限</span>
              </el-checkbox>
            </div>
            <div class="scope-item">
              <el-checkbox value="repo:read">
                <span class="scope-label">repo:read</span>
                <span class="scope-desc"> - 只读仓库访问权限</span>
              </el-checkbox>
            </div>
            <div class="scope-item">
              <el-checkbox value="repo:write">
                <span class="scope-label">repo:write</span>
                <span class="scope-desc"> - 读写仓库权限</span>
              </el-checkbox>
            </div>
            <div class="scope-item">
              <el-checkbox value="user">
                <span class="scope-label">user</span>
                <span class="scope-desc"> - 用户信息读写权限</span>
              </el-checkbox>
            </div>
            <div class="scope-item">
              <el-checkbox value="user:read">
                <span class="scope-label">user:read</span>
                <span class="scope-desc"> - 只读用户信息权限</span>
              </el-checkbox>
            </div>
            <div class="scope-item">
              <el-checkbox value="admin">
                <span class="scope-label">admin</span>
                <span class="scope-desc"> - 管理员权限</span>
              </el-checkbox>
            </div>
          </el-checkbox-group>
        </el-form-item>
        
        <el-form-item label="过期时间">
          <el-select v-model="newToken.expiration">
            <el-option value="7d" label="7 天" />
            <el-option value="30d" label="30 天" />
            <el-option value="90d" label="90 天" />
            <el-option value="1y" label="1 年" />
            <el-option value="never" label="永不过期" />
          </el-select>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createToken" :loading="creating">
          生成
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 显示新 Token 对话框 -->
    <el-dialog v-model="showTokenDialog" title="Token 已生成" width="500px" :close-on-click-modal="false">
      <el-alert
        title="请立即复制您的 Token"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 20px"
      >
        Token 只会显示一次，关闭后将无法再次查看。
      </el-alert>
      
      <div class="new-token-display">
        <code>{{ newTokenValue }}</code>
        <el-button type="primary" @click="copyToken">
          <el-icon><CopyDocument /></el-icon>
          复制
        </el-button>
      </div>
      
      <template #footer>
        <el-button type="primary" @click="showTokenDialog = false">
          我已保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, View, Hide, CopyDocument } from '@element-plus/icons-vue'
import api from '@/api/index'

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const showTokenDialog = ref(false)
const newTokenValue = ref('')

const tokens = ref<any[]>([])

const newToken = reactive({
  name: '',
  scopes: ['repo:read'] as string[],
  expiration: '30d'
})

const tokenRules = {
  name: [
    { required: true, message: '请输入 Token 名称', trigger: 'blur' }
  ],
  scopes: [
    { type: 'array', min: 1, message: '请至少选择一个权限', trigger: 'change' }
  ]
}

const scopeTexts: Record<string, string> = {
  repo: '仓库完整',
  'repo:read': '仓库只读',
  'repo:write': '仓库读写',
  user: '用户完整',
  'user:read': '用户只读',
  admin: '管理员'
}

function getScopeText(scope: string) {
  return scopeTexts[scope] || scope
}

function formatDate(date: string) {
  return new Date(date).toLocaleString('zh-CN')
}

function maskToken(token: string) {
  if (!token) return ''
  return token.substring(0, 8) + '****' + token.substring(token.length - 4)
}

function isExpired(date: string) {
  return new Date(date) < new Date()
}

async function fetchTokens() {
  loading.value = true
  try {
    const res: any = await api.get('/user/tokens')
    if (res.code === 200) {
      tokens.value = (res.data || []).map((t: any) => ({ ...t, showToken: false }))
    }
  } catch (e) {
    console.error('Failed to fetch tokens:', e)
  } finally {
    loading.value = false
  }
}

async function createToken() {
  creating.value = true
  try {
    const res: any = await api.post('/user/tokens', newToken)
    if (res.code === 200) {
      newTokenValue.value = res.data.token
      showCreateDialog.value = false
      showTokenDialog.value = true
      fetchTokens()
      
      // 重置表单
      newToken.name = ''
      newToken.scopes = ['repo:read']
      newToken.expiration = '30d'
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  } finally {
    creating.value = false
  }
}

async function deleteToken(token: any) {
  try {
    await ElMessageBox.confirm('确定删除此 Token 吗？删除后使用此 Token 的应用将无法访问 API。', '删除确认', {
      type: 'warning'
    })
    
    const res: any = await api.delete(`/user/tokens/${token.id}`)
    if (res.code === 200) {
      ElMessage.success('Token 已删除')
      fetchTokens()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function copyToken() {
  navigator.clipboard.writeText(newTokenValue.value)
  ElMessage.success('已复制到剪贴板')
}

onMounted(() => {
  fetchTokens()
})
</script>

<style scoped>
.api-tokens-page {
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

.tokens-list {
  min-height: 200px;
}

.token-display {
  display: flex;
  align-items: center;
  gap: 8px;
}

.token-display code {
  font-family: monospace;
  font-size: 13px;
  color: #606266;
}

.text-danger {
  color: #f56c6c;
}

.text-success {
  color: #67c23a;
}

.scope-item {
  margin-bottom: 8px;
}

.scope-label {
  font-weight: 500;
  color: #303133;
}

.scope-desc {
  color: #909399;
  font-size: 13px;
}

.new-token-display {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 8px;
}

.new-token-display code {
  flex: 1;
  font-family: monospace;
  font-size: 14px;
  color: #303133;
  word-break: break-all;
}
</style>
