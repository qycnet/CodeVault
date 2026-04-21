<template>
  <div class="mobile-layout" :class="{ 'dark': isDarkMode }">
    <!-- 移动端头部 -->
    <header class="mobile-header" v-if="showHeader">
      <button class="header-btn" @click="toggleDrawer">
        <el-icon><Menu /></el-icon>
      </button>
      
      <h1 class="header-title">{{ title }}</h1>
      
      <button class="header-btn" @click="toggleSearch">
        <el-icon><Search /></el-icon>
      </button>
    </header>
    
    <!-- 搜索栏 -->
    <div class="mobile-search-bar" v-if="showSearch">
      <el-input
        v-model="searchQuery"
        placeholder="搜索仓库、Issue、用户..."
        prefix-icon="Search"
        clearable
        @input="onSearch"
      />
    </div>
    
    <!-- 主内容区域 -->
    <main class="mobile-content" :class="{ 'with-tabs': showTabs, 'with-bottom-nav': showBottomNav }">
      <!-- 下拉刷新指示器 -->
      <div class="pull-refresh-indicator" :class="{ 'visible': isPulling }">
        <el-icon :class="{ 'rotating': isRefreshing }"><Refresh /></el-icon>
        <span>{{ pullText }}</span>
      </div>
      
      <!-- 内容插槽 -->
      <div class="content-wrapper" ref="contentWrapper" @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd">
        <slot></slot>
        
        <!-- 无限滚动加载器 -->
        <div class="infinite-scroll-loader" v-if="hasMore && isLoading">
          <el-icon class="rotating"><Loading /></el-icon>
          <span>加载中...</span>
        </div>
        
        <!-- 没有更多数据 -->
        <div class="no-more-data" v-if="!hasMore && !isLoading">
          <span>没有更多数据了</span>
        </div>
      </div>
    </main>
    
    <!-- 标签栏 -->
    <nav class="mobile-tabs" v-if="showTabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="mobile-tab"
        :class="{ 'active': activeTab === tab.id }"
        @click="onTabClick(tab.id)"
      >
        {{ tab.label }}
        <span class="tab-badge" v-if="tab.badge">{{ tab.badge }}</span>
      </button>
    </nav>
    
    <!-- 底部导航 -->
    <nav class="mobile-bottom-nav" v-if="showBottomNav">
      <button
        v-for="item in navItems"
        :key="item.id"
        class="nav-item"
        :class="{ 'active': activeNav === item.id }"
        @click="onNavClick(item.id)"
      >
        <el-icon><component :is="item.icon" /></el-icon>
        <span>{{ item.label }}</span>
      </button>
    </nav>
    
    <!-- 侧边抽屉 -->
    <div class="drawer-overlay" :class="{ 'open': isDrawerOpen }" @click="closeDrawer"></div>
    <aside class="mobile-drawer" :class="{ 'open': isDrawerOpen }">
      <div class="drawer-header">
        <img :src="userAvatar" :alt="userName" class="drawer-avatar" />
        <div class="drawer-user-info">
          <div class="drawer-username">{{ userName }}</div>
          <div class="drawer-email">{{ userEmail }}</div>
        </div>
      </div>
      
      <div class="drawer-menu">
        <button v-for="item in drawerMenuItems" :key="item.id" class="drawer-menu-item" @click="onDrawerMenuClick(item.id)">
          <el-icon><component :is="item.icon" /></el-icon>
          <span>{{ item.label }}</span>
          <el-badge v-if="item.badge" :value="item.badge" />
        </button>
      </div>
      
      <div class="drawer-footer">
        <button class="drawer-menu-item" @click="toggleDarkMode">
          <el-icon><component :is="isDarkMode ? 'Sunny' : 'Moon'" /></el-icon>
          <span>{{ isDarkMode ? '浅色模式' : '深色模式' }}</span>
        </button>
        
        <button class="drawer-menu-item" @click="logout">
          <el-icon><SwitchButton /></el-icon>
          <span>退出登录</span>
        </button>
      </div>
    </aside>
    
    <!-- 快速操作按钮 -->
    <button class="fab" v-if="showFab" @click="onFabClick">
      <el-icon><Plus /></el-icon>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Menu, Search, Refresh, Loading, Plus,
  House, Document, ChatDotRound, User,
  Sunny, Moon, SwitchButton, Bell, Setting,
  Star, Folder, Clock
} from '@element-plus/icons-vue'

const props = defineProps<{
  title?: string
  showHeader?: boolean
  showSearch?: boolean
  showTabs?: boolean
  showBottomNav?: boolean
  showFab?: boolean
  tabs?: Array<{ id: string; label: string; badge?: number }>
  navItems?: Array<{ id: string; label: string; icon: any }>
  hasMore?: boolean
}>()

const emit = defineEmits<{
  (e: 'search', query: string): void
  (e: 'tab-change', tabId: string): void
  (e: 'nav-change', navId: string): void
  (e: 'refresh'): void
  (e: 'load-more'): void
  (e: 'fab-click'): void
}>()

const router = useRouter()

// 状态
const searchQuery = ref('')
const activeTab = ref('')
const activeNav = ref('home')
const isDrawerOpen = ref(false)
const isDarkMode = ref(false)
const isLoading = ref(false)
const isRefreshing = ref(false)
const isPulling = ref(false)
const startY = ref(0)
const pullDistance = ref(0)
const contentWrapper = ref<HTMLElement | null>(null)

// 用户信息
const userName = ref('用户名')
const userEmail = ref('user@example.com')
const userAvatar = ref('/default-avatar.png')

// 默认导航项
const navItems = props.navItems || [
  { id: 'home', label: '首页', icon: House },
  { id: 'repos', label: '仓库', icon: Folder },
  { id: 'issues', label: 'Issue', icon: ChatDotRound },
  { id: 'notifications', label: '通知', icon: Bell },
  { id: 'profile', label: '我的', icon: User },
]

// 抽屉菜单项
const drawerMenuItems = [
  { id: 'profile', label: '个人资料', icon: User },
  { id: 'repos', label: '我的仓库', icon: Folder },
  { id: 'stars', label: '星标仓库', icon: Star },
  { id: 'history', label: '浏览历史', icon: Clock },
  { id: 'settings', label: '设置', icon: Setting },
]

// 下拉刷新文字
const pullText = computed(() => {
  if (isRefreshing.value) return '刷新中...'
  if (pullDistance.value > 80) return '松开刷新'
  return '下拉刷新'
})

// 方法
function toggleDrawer() {
  isDrawerOpen.value = !isDrawerOpen.value
}

function closeDrawer() {
  isDrawerOpen.value = false
}

function toggleSearch() {
  emit('search', searchQuery.value)
}

function onSearch() {
  emit('search', searchQuery.value)
}

function onTabClick(tabId: string) {
  activeTab.value = tabId
  emit('tab-change', tabId)
}

function onNavClick(navId: string) {
  activeNav.value = navId
  emit('nav-change', navId)
  
  // 导航到对应页面
  const routes: Record<string, string> = {
    home: '/',
    repos: '/repos',
    issues: '/issues',
    notifications: '/notifications',
    profile: '/profile',
  }
  
  if (routes[navId]) {
    router.push(routes[navId])
  }
}

function onDrawerMenuClick(itemId: string) {
  closeDrawer()
  
  const routes: Record<string, string> = {
    profile: '/profile',
    repos: '/repos',
    stars: '/stars',
    history: '/history',
    settings: '/settings',
  }
  
  if (routes[itemId]) {
    router.push(routes[itemId])
  }
}

function toggleDarkMode() {
  isDarkMode.value = !isDarkMode.value
  document.documentElement.classList.toggle('dark', isDarkMode.value)
  localStorage.setItem('darkMode', isDarkMode.value.toString())
}

function logout() {
  // 退出登录逻辑
  router.push('/login')
}

function onFabClick() {
  emit('fab-click')
}

// 下拉刷新
function onTouchStart(e: TouchEvent) {
  if (contentWrapper.value?.scrollTop === 0) {
    startY.value = e.touches[0].clientY
    isPulling.value = true
  }
}

function onTouchMove(e: TouchEvent) {
  if (!isPulling.value || isRefreshing.value) return
  
  const currentY = e.touches[0].clientY
  pullDistance.value = Math.max(0, currentY - startY.value)
  
  if (pullDistance.value > 0) {
    e.preventDefault()
  }
}

function onTouchEnd() {
  if (pullDistance.value > 80 && !isRefreshing.value) {
    refresh()
  }
  
  isPulling.value = false
  pullDistance.value = 0
}

async function refresh() {
  isRefreshing.value = true
  
  try {
    emit('refresh')
    await new Promise(resolve => setTimeout(resolve, 1000))
  } finally {
    isRefreshing.value = false
  }
}

// 无限滚动
function onScroll() {
  if (!contentWrapper.value || isLoading.value || !props.hasMore) return
  
  const { scrollTop, scrollHeight, clientHeight } = contentWrapper.value
  
  if (scrollHeight - scrollTop - clientHeight < 100) {
    loadMore()
  }
}

async function loadMore() {
  isLoading.value = true
  
  try {
    emit('load-more')
    await new Promise(resolve => setTimeout(resolve, 500))
  } finally {
    isLoading.value = false
  }
}

// 生命周期
onMounted(() => {
  // 恢复暗色模式设置
  const savedDarkMode = localStorage.getItem('darkMode')
  if (savedDarkMode === 'true') {
    isDarkMode.value = true
    document.documentElement.classList.add('dark')
  }
  
  // 添加滚动监听
  contentWrapper.value?.addEventListener('scroll', onScroll, { passive: true })
})

onUnmounted(() => {
  contentWrapper.value?.removeEventListener('scroll', onScroll)
})
</script>

<style scoped lang="scss">
.mobile-layout {
  min-height: 100vh;
  background: var(--bg-color, #f6f8fa);
  display: flex;
  flex-direction: column;
}

/* 头部 */
.mobile-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 56px;
  background: var(--bg-color, #fff);
  border-bottom: 1px solid var(--border-color, #e1e4e8);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 8px;
  z-index: 1000;
}

.header-btn {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  border-radius: 50%;
  cursor: pointer;
  
  &:active {
    background: var(--bg-secondary, #f6f8fa);
  }
}

.header-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--text-color, #24292e);
}

/* 搜索栏 */
.mobile-search-bar {
  position: fixed;
  top: 56px;
  left: 0;
  right: 0;
  padding: 8px 16px;
  background: var(--bg-color, #fff);
  border-bottom: 1px solid var(--border-color, #e1e4e8);
  z-index: 999;
}

/* 主内容 */
.mobile-content {
  flex: 1;
  margin-top: 56px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  
  &.with-tabs {
    padding-bottom: 48px;
  }
  
  &.with-bottom-nav {
    padding-bottom: 56px;
  }
}

.content-wrapper {
  min-height: calc(100vh - 56px);
}

/* 下拉刷新 */
.pull-refresh-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  color: var(--text-secondary, #586069);
  font-size: 14px;
  
  .rotating {
    animation: rotate 1s linear infinite;
  }
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* 无限滚动 */
.infinite-scroll-loader,
.no-more-data {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  color: var(--text-secondary, #586069);
  font-size: 14px;
}

/* 标签栏 */
.mobile-tabs {
  position: fixed;
  bottom: 56px;
  left: 0;
  right: 0;
  height: 48px;
  background: var(--bg-color, #fff);
  border-top: 1px solid var(--border-color, #e1e4e8);
  display: flex;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  
  &::-webkit-scrollbar {
    display: none;
  }
}

.mobile-tab {
  flex: 1;
  min-width: 80px;
  padding: 12px 16px;
  border: none;
  background: transparent;
  font-size: 14px;
  color: var(--text-secondary, #586069);
  white-space: nowrap;
  position: relative;
  
  &.active {
    color: var(--primary-color, #0366d6);
    font-weight: 600;
    
    &::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 24px;
      height: 2px;
      background: var(--primary-color, #0366d6);
      border-radius: 1px;
    }
  }
}

.tab-badge {
  margin-left: 4px;
  padding: 2px 6px;
  background: var(--primary-color, #0366d6);
  color: white;
  font-size: 11px;
  border-radius: 10px;
}

/* 底部导航 */
.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 56px;
  background: var(--bg-color, #fff);
  border-top: 1px solid var(--border-color, #e1e4e8);
  display: flex;
  z-index: 1000;
}

.nav-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: none;
  background: transparent;
  color: var(--text-secondary, #586069);
  font-size: 12px;
  cursor: pointer;
  
  &.active {
    color: var(--primary-color, #0366d6);
  }
  
  .el-icon {
    font-size: 20px;
  }
}

/* 抽屉 */
.drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1999;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s;
  
  &.open {
    opacity: 1;
    pointer-events: auto;
  }
}

.mobile-drawer {
  position: fixed;
  top: 0;
  left: -280px;
  width: 280px;
  height: 100%;
  background: var(--bg-color, #fff);
  z-index: 2000;
  transition: transform 0.3s;
  display: flex;
  flex-direction: column;
  
  &.open {
    transform: translateX(280px);
  }
}

.drawer-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 16px;
  background: var(--bg-secondary, #f6f8fa);
}

.drawer-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
}

.drawer-username {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-color, #24292e);
}

.drawer-email {
  font-size: 13px;
  color: var(--text-secondary, #586069);
}

.drawer-menu {
  flex: 1;
  padding: 8px 0;
  overflow-y: auto;
}

.drawer-menu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px 16px;
  border: none;
  background: transparent;
  color: var(--text-color, #24292e);
  font-size: 15px;
  text-align: left;
  cursor: pointer;
  
  &:active {
    background: var(--bg-secondary, #f6f8fa);
  }
}

.drawer-footer {
  border-top: 1px solid var(--border-color, #e1e4e8);
  padding: 8px 0;
}

/* 快速操作按钮 */
.fab {
  position: fixed;
  right: 16px;
  bottom: 72px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--primary-color, #0366d6);
  color: white;
  border: none;
  box-shadow: 0 4px 12px rgba(3, 102, 214, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 999;
  
  .el-icon {
    font-size: 24px;
  }
  
  &:active {
    transform: scale(0.95);
  }
}

/* 暗色模式 */
.dark {
  --bg-color: #0d1117;
  --bg-secondary: #161b22;
  --text-color: #c9d1d9;
  --text-secondary: #8b949e;
  --border-color: #30363d;
  --primary-color: #58a6ff;
}

/* 安全区域适配 */
@supports (padding: env(safe-area-inset-bottom)) {
  .mobile-bottom-nav {
    padding-bottom: env(safe-area-inset-bottom);
    height: calc(56px + env(safe-area-inset-bottom));
  }
  
  .mobile-content.with-bottom-nav {
    padding-bottom: calc(56px + env(safe-area-inset-bottom));
  }
  
  .fab {
    bottom: calc(72px + env(safe-area-inset-bottom));
  }
}
</style>
