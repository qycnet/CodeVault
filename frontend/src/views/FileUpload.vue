<template>
  <div class="file-upload-page">
    <div class="upload-header">
      <h2>上传文件</h2>
      <p class="repo-path">
        <router-link :to="`/repos/${owner}/${repo}`">{{ owner }}/{{ repo }}</router-link>
        / {{ currentBranch }}
        <span v-if="currentPath">/ {{ currentPath }}</span>
      </p>
    </div>

    <el-card class="upload-card">
      <el-form :model="form" label-width="100px">
        <el-form-item label="目标分支">
          <el-select v-model="form.branch" placeholder="选择分支">
            <el-option
              v-for="branch in branches"
              :key="branch"
              :label="branch"
              :value="branch"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="目标路径">
          <el-input
            v-model="form.path"
            placeholder="留空表示根目录，例如: src/components"
          />
        </el-form-item>

        <el-form-item label="提交信息">
          <el-input
            v-model="form.message"
            placeholder="描述本次上传的内容"
          />
        </el-form-item>

        <el-form-item label="选择文件">
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :on-change="handleFileChange"
            :on-remove="handleFileRemove"
            :file-list="fileList"
            :limit="10"
            :multiple="true"
            drag
          >
            <el-icon class="el-icon--upload"><upload-filled /></el-icon>
            <div class="el-upload__text">
              拖拽文件到此处，或 <em>点击上传</em>
            </div>
            <template #tip>
              <div class="el-upload__tip">
                支持上传多个文件，单个文件不超过 10MB<br>
                不允许上传: php, exe, bat, cmd, sh 等可执行文件
              </div>
            </template>
          </el-upload>
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            @click="handleUpload"
            :loading="uploading"
            :disabled="fileList.length === 0"
          >
            <el-icon class="el-icon--left"><Upload /></el-icon>
            开始上传
          </el-button>
          <el-button @click="handleCancel">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 上传结果 -->
    <el-card v-if="uploadResult" class="result-card">
      <template #header>
        <span>上传结果</span>
      </template>
      
      <div v-if="uploadResult.success">
        <el-alert
          :title="uploadResult.message"
          type="success"
          show-icon
          :closable="false"
        />
        
        <div class="uploaded-files" v-if="uploadResult.uploaded">
          <h4>已上传文件：</h4>
          <ul>
            <li v-for="file in uploadResult.uploaded" :key="file.path">
              {{ file.name }} ({{ formatSize(file.size) }}) - {{ file.path }}
            </li>
          </ul>
        </div>
        
        <div v-if="uploadResult.errors && uploadResult.errors.length > 0">
          <el-alert
            v-for="(error, index) in uploadResult.errors"
            :key="index"
            :title="error"
            type="warning"
            show-icon
            :closable="false"
            style="margin-top: 8px"
          />
        </div>
      </div>
      
      <el-alert
        v-else
        :title="uploadResult.message"
        type="error"
        show-icon
        :closable="false"
      />
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { UploadFilled, Upload } from '@element-plus/icons-vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)
const currentBranch = computed(() => route.query.branch as string || 'main')
const currentPath = computed(() => route.query.path as string || '')

const uploadRef = ref()
const fileList = ref<any[]>([])
const branches = ref<string[]>(['main'])
const uploading = ref(false)
const uploadResult = ref<any>(null)

const form = reactive({
  branch: currentBranch.value,
  path: currentPath.value,
  message: '上传文件'
})

// 获取分支列表
async function fetchBranches() {
  try {
    const response = await axios.get('/api/repos/branches', {
      params: {
        owner: owner.value,
        repo: repo.value
      }
    })
    
    if (response.data.code === 200) {
      branches.value = response.data.data
    }
  } catch (error) {
    console.error('获取分支失败:', error)
  }
}

// 文件选择变化
function handleFileChange(file: any, list: any[]) {
  fileList.value = list
}

// 文件移除
function handleFileRemove(file: any, list: any[]) {
  fileList.value = list
}

// 格式化文件大小
function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

// 上传文件
async function handleUpload() {
  if (fileList.value.length === 0) {
    ElMessage.warning('请选择要上传的文件')
    return
  }
  
  uploading.value = true
  uploadResult.value = null
  
  try {
    // 先获取仓库 ID
    const repoResponse = await axios.get('/api/repos/detail', {
      params: {
        owner: owner.value,
        repo: repo.value
      }
    })
    
    if (!repoResponse.data.success) {
      throw new Error('获取仓库信息失败')
    }
    
    const repoId = repoResponse.data.repo.id
    
    // 创建 FormData
    const formData = new FormData()
    formData.append('repo_id', repoId.toString())
    formData.append('branch', form.branch)
    formData.append('path', form.path)
    formData.append('message', form.message)
    
    // 添加文件
    if (fileList.value.length === 1) {
      formData.append('file', fileList.value[0].raw)
      
      // 单文件上传
      const response = await axios.post('/api/files/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      
      uploadResult.value = response.data
    } else {
      // 多文件上传
      fileList.value.forEach(file => {
        formData.append('files[]', file.raw)
      })
      
      const response = await axios.post('/api/files/upload-multiple', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      
      uploadResult.value = response.data
    }
    
    if (uploadResult.value.success) {
      ElMessage.success('文件上传成功')
      
      // 清空文件列表
      fileList.value = []
      uploadRef.value?.clearFiles()
      
      // 跳转到文件浏览页
      setTimeout(() => {
        router.push({
          path: `/repos/${owner.value}/${repo.value}/tree/${form.branch}`,
          query: { path: form.path }
        })
      }, 1500)
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '上传失败')
    uploadResult.value = {
      success: false,
      message: error.response?.data?.message || '上传失败'
    }
  } finally {
    uploading.value = false
  }
}

// 取消上传
function handleCancel() {
  router.back()
}

onMounted(() => {
  fetchBranches()
  form.branch = currentBranch.value
  form.path = currentPath.value
})
</script>

<style lang="scss" scoped>
.file-upload-page {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.upload-header {
  margin-bottom: 24px;
  
  h2 {
    margin: 0 0 8px 0;
    color: #24292f;
  }
  
  .repo-path {
    color: #57606a;
    font-size: 14px;
    
    a {
      color: #0969da;
      text-decoration: none;
      
      &:hover {
        text-decoration: underline;
      }
    }
  }
}

.upload-card {
  margin-bottom: 24px;
}

.result-card {
  margin-top: 24px;
  
  .uploaded-files {
    margin-top: 16px;
    
    h4 {
      margin: 0 0 8px 0;
      color: #24292f;
    }
    
    ul {
      margin: 0;
      padding-left: 20px;
      
      li {
        margin: 4px 0;
        color: #57606a;
      }
    }
  }
}

:deep(.el-upload-dragger) {
  padding: 40px;
}

:deep(.el-icon--upload) {
  font-size: 48px;
  color: #c0c4cc;
  margin-bottom: 16px;
}

:deep(.el-upload__text) {
  color: #606266;
  
  em {
    color: #0969da;
    font-style: normal;
  }
}

:deep(.el-upload__tip) {
  color: #909399;
  font-size: 12px;
  margin-top: 8px;
}
</style>
