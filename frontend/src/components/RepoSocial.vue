<template>
  <div class="repo-social">
    <div class="social-actions">
      <!-- Star -->
      <div class="social-item">
        <el-button
          :type="isStarred ? 'warning' : 'default'"
          @click="toggleStar"
          :loading="starring"
        >
          <el-icon><Star /></el-icon>
          {{ isStarred ? 'Unstar' : 'Star' }}
        </el-button>
        <el-badge :value="starCount" class="social-count" />
      </div>
      
      <!-- Fork -->
      <div class="social-item">
        <el-button @click="showForkDialog = true">
          <el-icon><Fork /></el-icon>
          Fork
        </el-button>
        <el-badge :value="forkCount" class="social-count" />
      </div>
      
      <!-- Watch -->
      <div class="social-item">
        <el-dropdown @command="toggleWatch">
          <el-button :type="isWatching ? 'primary' : 'default'">
            <el-icon><View /></el-icon>
            {{ getWatchText() }}
          </el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="releases">
                <el-icon><Bell /></el-icon>
                仅发布
              </el-dropdown-item>
              <el-dropdown-item command="all">
                <el-icon><Bell /></el-icon>
                所有活动
              </el-dropdown-item>
              <el-dropdown-item command="none" divided>
                <el-icon><Close /></el-icon>
                不关注
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
        <el-badge :value="watchCount" class="social-count" />
      </div>
    </div>
    
    <!-- Fork 对话框 -->
    <el-dialog v-model="showForkDialog" title="Fork 仓库" width="500px">
      <el-form label-width="100px">
        <el-form-item label="目标位置">
          <el-select v-model="forkTarget">
            <el-option
              v-for="option in forkOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </el-select>
        </el-form-item>
        
        <el-form-item label="仓库名称">
          <el-input v-model="forkName" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showForkDialog = false">取消</el-button>
        <el-button type="primary" @click="createFork" :loading="forking">
          创建 Fork
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Star, View, Bell, Close } from '@element-plus/icons-vue'
import api from '@/api/index'

const route = useRoute()
const router = useRouter()

const starring = ref(false)
const forking = ref(false)
const showForkDialog = ref(false)

const isStarred = ref(false)
const isWatching = ref(false)
const watchType = ref('none')
const starCount = ref(0)
const forkCount = ref(0)
const watchCount = ref(0)

const forkTarget = ref('')
const forkName = ref('')

const owner = computed(() => route.params.owner as string)
const repo = computed(() => route.params.repo as string)

const forkOptions = computed(() => {
  // 从 API 获取的用户和组织列表
  const options = [
    { label: '我的账户', value: 'me' }
  ]
  
  // 添加用户所属的组织
  if (userOrgs.value.length > 0) {
    userOrgs.value.forEach((org: any) => {
      options.push({
        label: org.name || org.username,
        value: org.username
      })
    })
  }
  
  return options
})

const userOrgs = ref<any[]>([])

async function fetchUserOrgs() {
  try {
    const res: any = await api.get('/user/orgs')
    if (res.code === 200 && res.data) {
      userOrgs.value = res.data
    }
  } catch (e) {
    console.error('Failed to fetch user orgs:', e)
  }
}

function getWatchText() {
  if (!isWatching.value) return 'Watch'
  switch (watchType.value) {
    case 'releases': return 'Watching releases'
    case 'all': return 'Watching'
    default: return 'Watch'
  }
}

async function fetchSocialData() {
  try {
    const res: any = await api.get(`/repos/${owner.value}/${repo.value}/social`)
    if (res.code === 200) {
      isStarred.value = res.data.is_starred
      isWatching.value = res.data.is_watching
      watchType.value = res.data.watch_type || 'none'
      starCount.value = res.data.stars || 0
      forkCount.value = res.data.forks || 0
      watchCount.value = res.data.watchers || 0
      forkName.value = repo.value
    }
  } catch (e) {
    console.error('Failed to fetch social data:', e)
  }
}

async function toggleStar() {
  starring.value = true
  try {
    const res: any = await api[isStarred.value ? 'delete' : 'post'](
      `/repos/${owner.value}/${repo.value}/star`
    )
    if (res.code === 200) {
      isStarred.value = !isStarred.value
      starCount.value += isStarred.value ? 1 : -1
      ElMessage.success(isStarred.value ? '已 Star' : '已 Unstar')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    starring.value = false
  }
}

async function toggleWatch(type: string) {
  try {
    const res: any = await api.put(
      `/repos/${owner.value}/${repo.value}/watch`,
      { type }
    )
    if (res.code === 200) {
      if (type === 'none') {
        isWatching.value = false
        watchCount.value--
      } else {
        if (!isWatching.value) {
          watchCount.value++
        }
        isWatching.value = true
        watchType.value = type
      }
      ElMessage.success('关注设置已更新')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function createFork() {
  forking.value = true
  try {
    const res: any = await api.post(`/repos/${owner.value}/${repo.value}/fork`, {
      target: forkTarget.value,
      name: forkName.value
    })
    if (res.code === 200) {
      ElMessage.success('Fork 创建成功')
      showForkDialog.value = false
      forkCount.value++
      router.push(`/repos/${res.data.owner}/${res.data.name}`)
    }
  } catch (e: any) {
    ElMessage.error(e.message || 'Fork 失败')
  } finally {
    forking.value = false
  }
}

onMounted(() => {
  fetchSocialData()
  fetchUserOrgs()
})
</script>

<style scoped>
.repo-social {
  display: inline-flex;
}

.social-actions {
  display: flex;
  gap: 12px;
}

.social-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.social-count {
  margin-left: 4px;
}
</style>
