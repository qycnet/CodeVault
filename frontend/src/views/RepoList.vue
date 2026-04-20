<template>
  <div class="repo-list">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>我的仓库</span>
          <el-button type="primary" @click="showCreateDialog = true">
            新建仓库
          </el-button>
        </div>
      </template>
      
      <div class="search-bar">
        <el-input
          v-model="searchText"
          placeholder="搜索仓库..."
          prefix-icon="Search"
          clearable
          @input="handleSearch"
        />
      </div>
      
      <el-table :data="repos" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="仓库名称" min-width="200">
          <template #default="{ row }">
            <div class="repo-name">
              <router-link :to="`/repos/${row.owner_name}/${row.name}`">
                {{ row.owner_name }} / {{ row.name }}
              </router-link>
              <el-tag :type="row.is_private ? 'danger' : 'success'" size="small">
                {{ row.is_private ? '私有' : '公开' }}
              </el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" min-width="300" />
        <el-table-column prop="default_branch" label="默认分支" width="100">
          <template #default="{ row }">
            <el-tag size="small">{{ row.default_branch || 'main' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" label="更新时间" width="180" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="goToRepo(row)">
              查看
            </el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="fetchRepos"
          @current-change="fetchRepos"
        />
      </div>
    </el-card>
    
    <!-- 创建仓库对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建仓库" width="500px">
      <el-form :model="newRepo" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="仓库名称" prop="name">
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
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { repoApi } from '@/api/repo'
import type { Repository } from '@/api/types'

const router = useRouter()

const loading = ref(false)
const creating = ref(false)
const showCreateDialog = ref(false)
const repos = ref<Repository[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const searchText = ref('')
const formRef = ref()

const newRepo = reactive({
  name: '',
  description: '',
  is_private: false
})

const rules = {
  name: [
    { required: true, message: '请输入仓库名称', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9_-]+$/, message: '只能包含字母、数字、下划线和连字符', trigger: 'blur' }
  ]
}

async function fetchRepos() {
  loading.value = true
  try {
    const res = await repoApi.list({
      page: page.value,
      page_size: pageSize.value,
      search: searchText.value
    })
    if (res.code === 200) {
      repos.value = res.data.items
      total.value = res.data.total
    }
  } catch (e) {
    console.error('Failed to fetch repos:', e)
  } finally {
    loading.value = false
  }
}

async function createRepo() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
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

async function handleDelete(repo: Repository) {
  try {
    await ElMessageBox.confirm(
      `确定要删除仓库 ${repo.name} 吗？此操作不可恢复！`,
      '删除仓库',
      { type: 'warning' }
    )
    const res = await repoApi.delete(repo.id)
    if (res.code === 200) {
      ElMessage.success('仓库已删除')
      fetchRepos()
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

function goToRepo(repo: Repository) {
  router.push(`/repos/${repo.owner_name}/${repo.name}`)
}

function handleSearch() {
  page.value = 1
  fetchRepos()
}

onMounted(() => {
  fetchRepos()
})
</script>

<style scoped>
.repo-list {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-bar {
  margin-bottom: 16px;
}

.repo-name {
  display: flex;
  align-items: center;
  gap: 8px;
}

.repo-name a {
  color: #409eff;
  text-decoration: none;
  font-weight: 500;
}

.repo-name a:hover {
  text-decoration: underline;
}

.pagination {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
</style>
