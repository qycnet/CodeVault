<template>
  <div class="app" :class="{ 'dark-mode': isDarkMode }">
    <router-view />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'

const isDarkMode = ref(false)

// 从本地存储加载主题设置
onMounted(() => {
  const savedTheme = localStorage.getItem('theme')
  isDarkMode.value = savedTheme === 'dark'
})

// 监听主题变化
watch(isDarkMode, (value) => {
  localStorage.setItem('theme', value ? 'dark' : 'light')
  document.documentElement.setAttribute('data-theme', value ? 'dark' : 'light')
})

// 切换主题
function toggleTheme() {
  isDarkMode.value = !isDarkMode.value
}

// 暴露给全局
defineExpose({ isDarkMode, toggleTheme })
</script>

<style lang="scss">
// Dark Mode 样式
.dark-mode {
  --bg-primary: #0d1117;
  --bg-secondary: #161b22;
  --bg-tertiary: #21262d;
  --text-primary: #c9d1d9;
  --text-secondary: #8b949e;
  --border-color: #30363d;
  --accent-color: #58a6ff;
  
  background-color: var(--bg-primary);
  color: var(--text-primary);
  
  // Element Plus 暗色主题覆盖
  .el-card {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
  }
  
  .el-input__wrapper {
    background-color: var(--bg-tertiary);
    border-color: var(--border-color);
  }
  
  .el-button {
    &--default {
      background-color: var(--bg-tertiary);
      border-color: var(--border-color);
      color: var(--text-primary);
    }
  }
  
  .el-menu {
    background-color: var(--bg-secondary);
    border-color: var(--border-color);
  }
  
  .el-table {
    background-color: var(--bg-secondary);
    
    th, td {
      background-color: var(--bg-secondary);
      border-color: var(--border-color);
    }
    
    tr:hover > td {
      background-color: var(--bg-tertiary);
    }
  }
}
</style>
