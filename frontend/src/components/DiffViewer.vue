<template>
  <div class="diff-viewer">
    <div class="diff-header">
      <span class="file-path">{{ filePath }}</span>
      <div class="diff-stats">
        <span class="additions">+{{ additions }}</span>
        <span class="deletions">-{{ deletions }}</span>
      </div>
    </div>
    
    <div class="diff-content">
      <table class="diff-table">
        <tbody>
          <tr
            v-for="(line, index) in lines"
            :key="index"
            :class="getLineClass(line)"
          >
            <td class="line-number old">{{ line.oldNumber || '' }}</td>
            <td class="line-number new">{{ line.newNumber || '' }}</td>
            <td class="line-content">
              <span class="line-prefix">{{ line.prefix }}</span>
              <span class="line-text">{{ line.content }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  diff: string
  filePath: string
  additions: number
  deletions: number
}

const props = defineProps<Props>()

interface DiffLine {
  oldNumber: number | null
  newNumber: number | null
  prefix: string
  content: string
  type: 'add' | 'delete' | 'context' | 'header'
}

const lines = computed(() => {
  if (!props.diff) return []
  
  const result: DiffLine[] = []
  const diffLines = props.diff.split('\n')
  
  let oldNumber = 0
  let newNumber = 0
  
  for (const line of diffLines) {
    // 解析 @@ -a,b +c,d @@ 格式的 hunk header
    if (line.startsWith('@@')) {
      const match = line.match(/@@ -(\d+),?\d* \+(\d+),?\d* @@/)
      if (match) {
        oldNumber = parseInt(match[1])
        newNumber = parseInt(match[2])
      }
      
      result.push({
        oldNumber: null,
        newNumber: null,
        prefix: '',
        content: line,
        type: 'header'
      })
      continue
    }
    
    // 跳过文件头
    if (line.startsWith('---') || line.startsWith('+++') || line.startsWith('diff --git')) {
      result.push({
        oldNumber: null,
        newNumber: null,
        prefix: '',
        content: line,
        type: 'context'
      })
      continue
    }
    
    // 删除的行
    if (line.startsWith('-')) {
      result.push({
        oldNumber: oldNumber++,
        newNumber: null,
        prefix: '-',
        content: line.substring(1),
        type: 'delete'
      })
      continue
    }
    
    // 新增的行
    if (line.startsWith('+')) {
      result.push({
        oldNumber: null,
        newNumber: newNumber++,
        prefix: '+',
        content: line.substring(1),
        type: 'add'
      })
      continue
    }
    
    // 上下文行
    if (line.startsWith(' ') || line === '') {
      result.push({
        oldNumber: oldNumber++,
        newNumber: newNumber++,
        prefix: ' ',
        content: line.substring(1),
        type: 'context'
      })
      continue
    }
    
    // 其他行
    result.push({
      oldNumber: null,
      newNumber: null,
      prefix: '',
      content: line,
      type: 'context'
    })
  }
  
  return result
})

function getLineClass(line: DiffLine) {
  return {
    'line-add': line.type === 'add',
    'line-delete': line.type === 'delete',
    'line-header': line.type === 'header'
  }
}
</script>

<style scoped>
.diff-viewer {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 16px;
}

.diff-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f5f7fa;
  border-bottom: 1px solid #e4e7ed;
}

.file-path {
  font-family: monospace;
  font-size: 14px;
  color: #303133;
}

.diff-stats {
  display: flex;
  gap: 12px;
  font-size: 12px;
}

.diff-stats .additions {
  color: #67c23a;
  font-weight: 500;
}

.diff-stats .deletions {
  color: #f56c6c;
  font-weight: 500;
}

.diff-content {
  overflow-x: auto;
}

.diff-table {
  width: 100%;
  border-collapse: collapse;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
}

.diff-table td {
  padding: 0 8px;
  height: 20px;
  vertical-align: top;
}

.line-number {
  width: 50px;
  text-align: right;
  color: #909399;
  background: #fafafa;
  border-right: 1px solid #e4e7ed;
  user-select: none;
}

.line-number.old {
  border-right: none;
}

.line-content {
  white-space: pre;
  padding-left: 12px;
}

.line-prefix {
  display: inline-block;
  width: 12px;
  color: #909399;
}

.line-text {
  color: #303133;
}

.line-add {
  background: #f0fff0;
}

.line-add .line-content {
  background: #e6ffec;
}

.line-delete {
  background: #fff0f0;
}

.line-delete .line-content {
  background: #ffebe9;
}

.line-header {
  background: #f5f7fa;
}

.line-header .line-content {
  color: #409eff;
  font-weight: 500;
}
</style>
