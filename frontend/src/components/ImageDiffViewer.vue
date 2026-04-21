<template>
  <div class="image-diff-viewer">
    <!-- 工具栏 -->
    <div class="diff-toolbar">
      <div class="toolbar-left">
        <el-radio-group v-model="diffMode" size="small">
          <el-radio-button label="side-by-side">并排对比</el-radio-button>
          <el-radio-button label="slider">滑动对比</el-radio-button>
          <el-radio-button label="overlay">叠加对比</el-radio-button>
          <el-radio-button label="diff">差异高亮</el-radio-button>
        </el-radio-group>
      </div>
      
      <div class="toolbar-right">
        <el-button size="small" @click="downloadDiff">
          <el-icon><Download /></el-icon>
          下载差异
        </el-button>
      </div>
    </div>
    
    <!-- 差异统计 -->
    <div class="diff-stats" v-if="diffResult">
      <el-tag :type="diffResult.diff.dimensions_changed ? 'warning' : 'success'">
        尺寸: {{ diffResult.image1.width }}x{{ diffResult.image1.height }} → {{ diffResult.image2.width }}x{{ diffResult.image2.height }}
      </el-tag>
      
      <el-tag :type="diffResult.diff.size_diff > 0 ? 'warning' : 'success'">
        大小: {{ diffResult.image1.size_human }} → {{ diffResult.image2.size_human }}
        ({{ diffResult.diff.size_diff > 0 ? '+' : '' }}{{ diffResult.diff.size_diff_percent }}%)
      </el-tag>
      
      <el-tag v-if="diffResult.diff.similarity !== undefined" :type="diffResult.diff.similarity > 90 ? 'success' : 'warning'">
        相似度: {{ diffResult.diff.similarity.toFixed(1) }}%
      </el-tag>
    </div>
    
    <!-- 并排对比 -->
    <div v-if="diffMode === 'side-by-side'" class="diff-side-by-side">
      <div class="image-panel">
        <div class="image-header">
          <span class="image-label">Before</span>
          <span class="image-info">{{ image1Info }}</span>
        </div>
        <div class="image-container">
          <img :src="image1Url" alt="Before" @load="onImageLoad(1, $event)" />
        </div>
      </div>
      
      <div class="image-panel">
        <div class="image-header">
          <span class="image-label">After</span>
          <span class="image-info">{{ image2Info }}</span>
        </div>
        <div class="image-container">
          <img :src="image2Url" alt="After" @load="onImageLoad(2, $event)" />
        </div>
      </div>
    </div>
    
    <!-- 滑动对比 -->
    <div v-if="diffMode === 'slider'" class="diff-slider">
      <div class="slider-container" ref="sliderContainer" @mousemove="onSliderMove" @mousedown="startSlider" @mouseup="stopSlider" @mouseleave="stopSlider">
        <img :src="image1Url" alt="Before" class="slider-image before" :style="{ clipPath: `inset(0 ${100 - sliderPosition}% 0 0)` }" />
        <img :src="image2Url" alt="After" class="slider-image after" :style="{ clipPath: `inset(0 0 0 ${sliderPosition}%)` }" />
        
        <div class="slider-handle" :style="{ left: sliderPosition + '%' }">
          <div class="handle-line"></div>
          <div class="handle-circle">
            <el-icon><DCaret /></el-icon>
          </div>
        </div>
      </div>
      
      <div class="slider-labels">
        <span class="label-before">Before</span>
        <span class="label-after">After</span>
      </div>
    </div>
    
    <!-- 叠加对比 -->
    <div v-if="diffMode === 'overlay'" class="diff-overlay">
      <div class="overlay-container">
        <img :src="image1Url" alt="Before" class="overlay-image before" />
        <img :src="image2Url" alt="After" class="overlay-image after" :style="{ opacity: overlayOpacity / 100 }" />
      </div>
      
      <div class="overlay-controls">
        <span>Before</span>
        <el-slider v-model="overlayOpacity" :min="0" :max="100" :format-tooltip="(val) => `${val}%`" />
        <span>After</span>
      </div>
    </div>
    
    <!-- 差异高亮 -->
    <div v-if="diffMode === 'diff'" class="diff-highlight">
      <div class="diff-container">
        <img v-if="diffImageUrl" :src="diffImageUrl" alt="Diff" />
        <div v-else class="diff-placeholder">
          <el-icon :size="48"><Picture /></el-icon>
          <p>正在生成差异图片...</p>
        </div>
      </div>
      
      <div class="diff-legend">
        <div class="legend-item">
          <span class="legend-color removed"></span>
          <span>删除区域</span>
        </div>
        <div class="legend-item">
          <span class="legend-color added"></span>
          <span>添加区域</span>
        </div>
        <div class="legend-item">
          <span class="legend-color unchanged"></span>
          <span>未变化</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Download, DCaret, Picture } from '@element-plus/icons-vue'

const props = defineProps<{
  image1Url: string
  image2Url: string
  image1Name?: string
  image2Name?: string
}>()

// 状态
const diffMode = ref('side-by-side')
const diffResult = ref<any>(null)
const diffImageUrl = ref<string | null>(null)
const sliderPosition = ref(50)
const overlayOpacity = ref(50)
const isDragging = ref(false)
const sliderContainer = ref<HTMLElement | null>(null)

// 图片信息
const image1Info = computed(() => {
  if (!diffResult.value) return props.image1Name || 'Before'
  const { width, height, size_human } = diffResult.value.image1
  return `${width}x${height} · ${size_human}`
})

const image2Info = computed(() => {
  if (!diffResult.value) return props.image2Name || 'After'
  const { width, height, size_human } = diffResult.value.image2
  return `${width}x${height} · ${size_human}`
})

// 加载差异结果
async function loadDiffResult() {
  try {
    const response = await fetch(`/api/diff/images?image1=${encodeURIComponent(props.image1Url)}&image2=${encodeURIComponent(props.image2Url)}`)
    const data = await response.json()
    
    if (data.success) {
      diffResult.value = data
      
      if (data.view?.diff_path) {
        diffImageUrl.value = data.view.diff_path
      }
    }
  } catch (error) {
    console.error('加载差异结果失败:', error)
  }
}

// 图片加载完成
function onImageLoad(index: number, event: Event) {
  const img = event.target as HTMLImageElement
  console.log(`Image ${index} loaded:`, img.naturalWidth, 'x', img.naturalHeight)
}

// 滑动控制
function startSlider() {
  isDragging.value = true
}

function stopSlider() {
  isDragging.value = false
}

function onSliderMove(event: MouseEvent) {
  if (!isDragging.value || !sliderContainer.value) return
  
  const rect = sliderContainer.value.getBoundingClientRect()
  const x = event.clientX - rect.left
  sliderPosition.value = Math.max(0, Math.min(100, (x / rect.width) * 100))
}

// 下载差异
function downloadDiff() {
  if (diffImageUrl.value) {
    const link = document.createElement('a')
    link.href = diffImageUrl.value
    link.download = 'diff.png'
    link.click()
  }
}

onMounted(() => {
  loadDiffResult()
})
</script>

<style scoped lang="scss">
.image-diff-viewer {
  background: var(--bg-color, #fff);
  border-radius: 8px;
  overflow: hidden;
}

.diff-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color, #e1e4e8);
}

.diff-stats {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  background: var(--bg-secondary, #f6f8fa);
  flex-wrap: wrap;
}

/* 并排对比 */
.diff-side-by-side {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: var(--border-color, #e1e4e8);
}

.image-panel {
  background: var(--bg-color, #fff);
}

.image-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg-secondary, #f6f8fa);
  border-bottom: 1px solid var(--border-color, #e1e4e8);
}

.image-label {
  font-weight: 600;
  color: var(--text-color, #24292e);
}

.image-info {
  font-size: 12px;
  color: var(--text-secondary, #586069);
}

.image-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  padding: 16px;
  
  img {
    max-width: 100%;
    max-height: 600px;
    object-fit: contain;
  }
}

/* 滑动对比 */
.diff-slider {
  position: relative;
}

.slider-container {
  position: relative;
  overflow: hidden;
  cursor: ew-resize;
  min-height: 400px;
  
  &:hover .slider-handle {
    transform: translateX(-50%) scale(1.1);
  }
}

.slider-image {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  
  &.before {
    z-index: 1;
  }
  
  &.after {
    z-index: 2;
  }
}

.slider-handle {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--primary-color, #0366d6);
  z-index: 10;
  transform: translateX(-50%);
  transition: transform 0.2s;
}

.handle-line {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 50%;
  width: 2px;
  background: inherit;
}

.handle-circle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 40px;
  height: 40px;
  background: var(--primary-color, #0366d6);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  padding: 12px 16px;
  background: var(--bg-secondary, #f6f8fa);
}

/* 叠加对比 */
.diff-overlay {
  position: relative;
}

.overlay-container {
  position: relative;
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.overlay-image {
  max-width: 100%;
  max-height: 600px;
  object-fit: contain;
  
  &.before {
    position: relative;
  }
  
  &.after {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }
}

.overlay-controls {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: var(--bg-secondary, #f6f8fa);
  
  .el-slider {
    flex: 1;
  }
}

/* 差异高亮 */
.diff-highlight {
  position: relative;
}

.diff-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  padding: 16px;
  
  img {
    max-width: 100%;
    max-height: 600px;
  }
}

.diff-placeholder {
  text-align: center;
  color: var(--text-secondary, #586069);
}

.diff-legend {
  display: flex;
  justify-content: center;
  gap: 24px;
  padding: 12px 16px;
  background: var(--bg-secondary, #f6f8fa);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  
  &.removed {
    background: #f56c6c;
  }
  
  &.added {
    background: #67c23a;
  }
  
  &.unchanged {
    background: #909399;
  }
}

/* 暗色主题 */
:root.dark {
  .diff-toolbar,
  .image-panel,
  .diff-slider,
  .diff-overlay,
  .diff-highlight {
    background: #1c1c1e;
  }
  
  .diff-stats,
  .image-header,
  .slider-labels,
  .overlay-controls,
  .diff-legend {
    background: #2c2c2e;
  }
}

/* 移动端适配 */
@media (max-width: 768px) {
  .diff-toolbar {
    flex-direction: column;
    gap: 12px;
  }
  
  .diff-side-by-side {
    grid-template-columns: 1fr;
  }
  
  .diff-stats {
    flex-direction: column;
  }
  
  .diff-legend {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
