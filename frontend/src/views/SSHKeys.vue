<template>
  <div class="ssh-keys">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>SSH Keys</span>
          <el-button type="primary" @click="showAddDialog = true">
            添加 SSH Key
          </el-button>
        </div>
      </template>
      
      <el-table :data="sshKeys" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="名称" width="200" />
        <el-table-column prop="fingerprint" label="指纹" min-width="300">
          <template #default="{ row }">
            <code class="fingerprint">{{ row.fingerprint }}</code>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="添加时间" width="180" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button type="danger" link size="small" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <el-empty v-if="!loading && sshKeys.length === 0" description="暂无 SSH Key" />
    </el-card>
    
    <!-- 添加 SSH Key 对话框 -->
    <el-dialog v-model="showAddDialog" title="添加 SSH Key" width="600px">
      <el-form :model="newKey" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="newKey.name" placeholder="我的笔记本" />
        </el-form-item>
        <el-form-item label="公钥" prop="public_key">
          <el-input
            v-model="newKey.public_key"
            type="textarea"
            :rows="6"
            placeholder="ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAAB..."
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddDialog = false">取消</el-button>
        <el-button type="primary" :loading="adding" @click="addKey">添加</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '@/api/index'
import type { SSHKey } from '@/api/types'

const loading = ref(false)
const adding = ref(false)
const showAddDialog = ref(false)
const sshKeys = ref<SSHKey[]>([])
const formRef = ref()

const newKey = reactive({
  name: '',
  public_key: ''
})

const rules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  public_key: [
    { required: true, message: '请输入公钥', trigger: 'blur' },
    { pattern: /^ssh-/, message: '请输入有效的 SSH 公钥', trigger: 'blur' }
  ]
}

async function fetchKeys() {
  loading.value = true
  try {
    const res: any = await api.get('/ssh-keys')
    if (res.code === 200) {
      sshKeys.value = res.data || []
    }
  } catch (e) {
    console.error('Failed to fetch SSH keys:', e)
  } finally {
    loading.value = false
  }
}

async function addKey() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
  adding.value = true
  try {
    const res: any = await api.post('/ssh-keys', newKey)
    if (res.code === 200) {
      ElMessage.success('SSH Key 添加成功')
      showAddDialog.value = false
      newKey.name = ''
      newKey.public_key = ''
      fetchKeys()
    }
  } catch (e: any) {
    ElMessage.error(e.message || '添加失败')
  } finally {
    adding.value = false
  }
}

async function handleDelete(key: SSHKey) {
  try {
    await ElMessageBox.confirm(
      `确定要删除 SSH Key "${key.name}" 吗？`,
      '删除 SSH Key',
      { type: 'warning' }
    )
    const res: any = await api.delete('/ssh-keys', { data: { id: key.id } })
    if (res.code === 200) {
      ElMessage.success('SSH Key 已删除')
      fetchKeys()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

onMounted(() => {
  fetchKeys()
})
</script>

<style scoped>
.ssh-keys {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.fingerprint {
  font-size: 12px;
  background: #f5f7fa;
  padding: 4px 8px;
  border-radius: 4px;
}
</style>
