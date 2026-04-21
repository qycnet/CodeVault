<template>
  <div class="releases-page">
    <div class="page-header">
      <h2>Releases</h2>
      <el-button type="primary" @click="showCreateDialog = true" v-if="isOwner">
        新建 Release
      </el-button>
    </div>

    <div class="releases-list">
      <div v-for="release in releases" :key="release.id" class="release-item">
        <div class="release-header">
          <el-tag :type="release.prerelease ? 'warning' : 'success'">
            {{ release.tag_name }}
          </el-tag>
          <span class="release-title">{{ release.title }}</span>
          <span class="release-meta">
            {{ release.author_name }} · {{ formatTime(release.created_at) }}
          </span>
        </div>
        <div class="release-body" v-html="renderMarkdown(release.body)"></div>
        <div class="release-actions">
          <el-button size="small" @click="downloadSource(release.tag_name)">
            下载源码
          </el-button>
          <el-button size="small" v-if="isOwner" @click="editRelease(release)">
            编辑
          </el-button>
          <el-button size="small" type="danger" v-if="isOwner" @click="deleteRelease(release.id)">
            删除
          </el-button>
        </div>
      </div>
      <el-empty v-if="releases.length === 0" description="暂无 Release" />
    </div>

    <!-- 创建 Release 对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建 Release" width="600px">
      <el-form :model="releaseForm" label-width="100px">
        <el-form-item label="Tag 版本">
          <el-input v-model="releaseForm.tag_name" placeholder="v1.0.0">
            <template #prepend>v</template>
          </el-input>
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="releaseForm.title" placeholder="Release 标题" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="releaseForm.body" type="textarea" :rows="6" placeholder="支持 Markdown 格式" />
        </el-form-item>
        <el-form-item label="预发布">
          <el-switch v-model="releaseForm.prerelease" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createRelease">发布</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import axios from 'axios'

const route = useRoute()

const releases = ref<any[]>([])
const isOwner = ref(false)
const showCreateDialog = ref(false)

const releaseForm = reactive({
  tag_name: '',
  title: '',
  body: '',
  prerelease: false,
  repo_id: 0
})

function formatTime(time: string) {
  return new Date(time).toLocaleDateString('zh-CN')
}

function renderMarkdown(text: string) {
  if (!text) return ''
  // 简单的 Markdown 渲染
  return text
    .replace(/\n/g, '<br>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`(.+?)`/g, '<code>$1</code>')
}

async function fetchReleases() {
  const owner = route.params.owner as string
  const repo = route.params.repo as string
  
  try {
    const response = await axios.get('/api/releases', {
      params: { repo_id: releaseForm.repo_id }
    })
    
    if (response.data.success) {
      releases.value = response.data.releases
    }
  } catch (error) {
    console.error('Failed to fetch releases:', error)
  }
}

async function createRelease() {
  try {
    const response = await axios.post('/api/releases', {
      ...releaseForm,
      tag_name: releaseForm.tag_name.startsWith('v') ? releaseForm.tag_name : 'v' + releaseForm.tag_name
    })
    
    if (response.data.success) {
      ElMessage.success('Release 发布成功')
      showCreateDialog.value = false
      fetchReleases()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '发布失败')
  }
}

async function deleteRelease(id: number) {
  try {
    await ElMessageBox.confirm('确定删除此 Release？')
    await axios.delete('/api/releases', { params: { id } })
    ElMessage.success('已删除')
    fetchReleases()
  } catch (error) {
    // 取消操作
  }
}

function editRelease(release: any) {
  Object.assign(releaseForm, {
    id: release.id,
    tag_name: release.tag_name,
    title: release.title,
    body: release.body,
    prerelease: release.prerelease
  })
  showCreateDialog.value = true
}

function downloadSource(tag: string) {
  // 下载源码压缩包
  window.open(`/api/repos/archive?repo_id=${releaseForm.repo_id}&tag=${tag}`)
}

onMounted(fetchReleases)
</script>

<style lang="scss" scoped>
.releases-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  
  h2 {
    margin: 0;
  }
}

.release-item {
  border: 1px solid #d0d7de;
  border-radius: 6px;
  padding: 16px;
  margin-bottom: 16px;
  
  .release-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    
    .release-title {
      font-size: 16px;
      font-weight: 600;
    }
    
    .release-meta {
      color: #57606a;
      font-size: 12px;
    }
  }
  
  .release-body {
    color: #24292f;
    line-height: 1.6;
    margin-bottom: 12px;
  }
  
  .release-actions {
    display: flex;
    gap: 8px;
  }
}
</style>
