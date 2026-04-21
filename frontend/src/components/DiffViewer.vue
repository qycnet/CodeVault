<template>
  <div class="diff-viewer">
    <div class="diff-header">
      <div class="file-path">
        <el-icon><Document /></el-icon>
        <span>{{ filePath }}</span>
      </div>
      <div class="diff-actions">
        <el-radio-group v-model="viewMode" size="small">
          <el-radio-button value="unified">统一视图</el-radio-button>
          <el-radio-button value="side-by-side">并排视图</el-radio-button>
        </el-radio-group>
      </div>
    </div>

    <!-- 统一视图 -->
    <div v-if="viewMode === 'unified'" class="diff-unified">
      <table class="diff-table">
        <tbody>
          <template v-for="(hunk, hunkIndex) in diff.hunks" :key="hunkIndex">
            <tr class="hunk-header">
              <td colspan="3">
                <span class="hunk-info">@@ -{{ hunk.old_start }} +{{ hunk.new_start }} @@</span>
              </td>
            </tr>
            <tr
              v-for="(line, lineIndex) in hunk.lines"
              :key="`${hunkIndex}-${lineIndex}`"
              :class="['diff-line', line.type]"
            >
              <td class="line-num old">{{ line.old_line || '' }}</td>
              <td class="line-num new">{{ line.new_line || '' }}</td>
              <td class="line-content">
                <span class="line-prefix">{{ getLinePrefix(line.type) }}</span>
                <span class="line-text">{{ line.content }}</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- 并排视图 -->
    <div v-else class="diff-side-by-side">
      <div class="diff-panels">
        <div class="diff-panel left">
          <div class="panel-header">旧版本 ({{ fromCommit?.slice(0, 7) }})</div>
          <div class="panel-content">
            <div
              v-for="(line, index) in sideBySide.left"
              :key="`left-${index}`"
              :class="['diff-line', line.type]"
            >
              <span class="line-num">{{ line.line || '' }}</span>
              <span class="line-content">{{ line.content }}</span>
            </div>
          </div>
        </div>
        <div class="diff-panel right">
          <div class="panel-header">新版本 ({{ toCommit?.slice(0, 7) }})</div>
          <div class="panel-content">
            <div
              v-for="(line, index) in sideBySide.right"
              :key="`right-${index}`"
              :class="['diff-line', line.type]"
            >
              <span class="line-num">{{ line.line || '' }}</span>
              <span class="line-content">{{ line.content }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Document } from '@element-plus/icons-vue'
import axios from 'axios'

interface Props {
  repoId: number
  fromCommit: string
  toCommit: string
  filePath: string
}

const props = defineProps<Props>()

const viewMode = ref<'unified' | 'side-by-side'>('unified')
const diff = ref<any>({ hunks: [] })
const sideBySide = ref<{ left: any[]; right: any[] }>({ left: [], right: [] })

const getLinePrefix = (type: string) => {
  switch (type) {
    case 'add': return '+'
    case 'delete': return '-'
    default: return ' '
  }
}

async function fetchDiff() {
  try {
    const response = await axios.get('/api/diff/file', {
      params: {
        repo_id: props.repoId,
        from: props.fromCommit,
        to: props.toCommit,
        file: props.filePath,
        view: viewMode.value
      }
    })
    
    if (response.data.success) {
      if (viewMode.value === 'unified') {
        diff.value = response.data.diff
      } else {
        sideBySide.value = response.data.diff
      }
    }
  } catch (error) {
    console.error('Failed to fetch diff:', error)
  }
}

watch([() => props.fromCommit, () => props.toCommit, () => props.filePath, viewMode], fetchDiff)

onMounted(fetchDiff)
</script>

<style lang="scss" scoped>
.diff-viewer {
  border: 1px solid #d0d7de;
  border-radius: 6px;
  overflow: hidden;
}

.diff-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 16px;
  background: #f6f8fa;
  border-bottom: 1px solid #d0d7de;
}

.file-path {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: monospace;
  font-size: 14px;
}

.diff-unified {
  overflow-x: auto;
}

.diff-table {
  width: 100%;
  border-collapse: collapse;
  font-family: monospace;
  font-size: 13px;
  
  .diff-line {
    &.add {
      background: #e6ffec;
      .line-content { background: #ccffd8; }
    }
    &.delete {
      background: #ffebe9;
      .line-content { background: #ffd7d5; }
    }
    &.context {
      background: #fff;
    }
  }
  
  .line-num {
    width: 50px;
    padding: 0 10px;
    text-align: right;
    color: #6e7781;
    background: #f6f8fa;
    border-right: 1px solid #d0d7de;
    user-select: none;
  }
  
  .line-content {
    padding: 0 10px;
    white-space: pre;
  }
  
  .line-prefix {
    display: inline-block;
    width: 1em;
    text-align: center;
  }
  
  .hunk-header {
    background: #f6f8fa;
    .hunk-info {
      color: #6e7781;
      font-size: 12px;
    }
  }
}

.diff-side-by-side {
  .diff-panels {
    display: flex;
  }
  
  .diff-panel {
    flex: 1;
    border-right: 1px solid #d0d7de;
    
    &:last-child {
      border-right: none;
    }
    
    .panel-header {
      padding: 8px 16px;
      background: #f6f8fa;
      border-bottom: 1px solid #d0d7de;
      font-size: 12px;
      color: #57606a;
    }
    
    .panel-content {
      font-family: monospace;
      font-size: 13px;
      max-height: 600px;
      overflow: auto;
    }
    
    .diff-line {
      display: flex;
      
      &.add {
        background: #e6ffec;
      }
      &.delete {
        background: #ffebe9;
      }
      &.empty {
        background: #f6f8fa;
      }
      
      .line-num {
        width: 50px;
        padding: 0 10px;
        text-align: right;
        color: #6e7781;
        background: #f6f8fa;
        border-right: 1px solid #d0d7de;
        user-select: none;
        flex-shrink: 0;
      }
      
      .line-content {
        flex: 1;
        padding: 0 10px;
        white-space: pre;
        overflow: hidden;
      }
    }
  }
}
</style>
