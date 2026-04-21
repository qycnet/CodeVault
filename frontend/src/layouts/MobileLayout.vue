<template>
  <div class="mobile-layout">
    <!-- 移动端顶部导航 -->
    <header class="mobile-header">
      <button class="menu-btn" @click="toggleDrawer">
        <el-icon><Menu /></el-icon>
      </button>
      
      <div class="logo">
        <router-link to="/">CodeVault</router-link>
      </div>
      
      <div class="header-actions">
        <button class="search-btn" @click="showSearch = true">
          <el-icon><Search /></el-icon>
        </button>
        <button class="notif-btn" @click="showNotifications = true">
          <el-icon><Bell /></el-icon>
          <span v-if="unreadCount > 0" class="badge">{{ unreadCount }}</span>
        </button>
      </div>
    </header>
    
    <!-- 主内容区域 -->
    <main class="mobile-content" :class="{ 'with-bottom-nav': showBottomNav }">
      <router-view />
    </main>
    
    <!-- 底部导航 -->
    <nav v-if="showBottomNav" class="mobile-bottom-nav">
      <router-link to="/" class="nav-item" :class="{ active: $route.path === '/' }">
        <el-icon><HomeFilled /></el-icon>
        <span>{{ t('nav.home') }}</span>
      </router-link>
      
      <router-link to="/repositories" class="nav-item" :class="{ active: $route.path.startsWith('/repositories') }">
        <el-icon><FolderOpened /></el-icon>
        <span>{{ t('nav.repositories') }}</span>
      </router-link>
      
      <router-link to="/issues" class="nav-item" :class="{ active: $route.path.startsWith('/issues') }">
        <el-icon><CircleDot /></el-icon>
        <span>{{ t('nav.issues') }}</span>
      </router-link>
      
      <router-link to="/pulls" class="nav-item" :class="{ active: $route.path.startsWith('/pulls') }">
        <el-icon><Merge /></el-icon>
        <span>{{ t('nav.pull_requests') }}</span>
      </router-link>
      
      <router-link to="/profile" class="nav-item" :class="{ active: $route.path.startsWith('/profile') }">
        <el-icon><User /></el-icon>
        <span>{{ t('user.profile') }}</span>
      </router-link>
    </nav>
    
    <!-- 抽屉菜单 -->
    <div class="mobile-drawer-overlay" :class="{ open: drawerOpen }" @click="closeDrawer"></div>
    <aside class="mobile-drawer" :class="{ open: drawerOpen }">
      <div class="drawer-header">
        <div class="user-info" v-if="user">
          <el-avatar :size="48" :src="user.avatar_url">{{ user.username?.charAt(0) }}</el-avatar>
          <div class="user-details">
            <strong>{{ user.username }}</strong>
            <small>{{ user.email }}</small>
          </div>
        </div>
        <div v-else class="login-prompt">
          <el-button type="primary" @click="goToLogin">{{ t('user.login') }}</el-button>
        </div>
      </div>
      
      <div class="drawer-menu">
        <router-link to="/" class="menu-item" @click="closeDrawer">
          <el-icon><HomeFilled /></el-icon>
          <span>{{ t('nav.home') }}</span>
        </router-link>
        
        <router-link to="/repositories" class="menu-item" @click="closeDrawer">
          <el-icon><FolderOpened /></el-icon>
          <span>{{ t('nav.repositories') }}</span>
        </router-link>
        
        <router-link to="/issues" class="menu-item" @click="closeDrawer">
          <el-icon><CircleDot /></el-icon>
          <span>{{ t('nav.issues') }}</span>
        </router-link>
        
        <router-link to="/pulls" class="menu-item" @click="closeDrawer">
          <el-icon><Merge /></el-icon>
          <span>{{ t('nav.pull_requests') }}</span>
        </router-link>
        
        <router-link to="/actions" class="menu-item" @click="closeDrawer">
          <el-icon><VideoPlay /></el-icon>
          <span>{{ t('nav.actions') }}</span>
        </router-link>
        
        <div class="menu-divider"></div>
        
        <router-link to="/settings" class="menu-item" @click="closeDrawer">
          <el-icon><Setting /></el-icon>
          <span>{{ t('nav.settings') }}</span>
        </router-link>
        
        <div class="menu-item" @click="showLanguageDialog = true">
          <el-icon><Globe /></el-icon>
          <span>{{ t('settings.language') }}</span>
          <span class="menu-value">{{ currentLanguageName }}</span>
        </div>
        
        <div class="menu-divider"></div>
        
        <div v-if="user" class="menu-item danger" @click="logout">
          <el-icon><SwitchButton /></el-icon>
          <span>{{ t('user.logout') }}</span>
        </div>
      </div>
    </aside>
    
    <!-- 搜索对话框 -->
    <el-dialog v-model="showSearch" title="搜索" class="mobile-search-dialog">
      <el-input
        v-model="searchQuery"
        :placeholder="t('search.placeholder')"
        prefix-icon="Search"
        size="large"
        @keyup.enter="performSearch"
      />
      <div class="search-history" v-if="searchHistory.length > 0">
        <h4>{{ t('search.history') }}</h4>
        <div class="history-items">
          <el-tag
            v-for="item in searchHistory"
            :key="item"
            closable
            @close="removeHistory(item)"
            @click="searchQuery = item; performSearch()"
          >
            {{ item }}
          </el-tag>
        </div>
      </div>
    </el-dialog>
    
    <!-- 通知对话框 -->
    <el-dialog v-model="showNotifications" :title="t('notifications.title')" class="mobile-notif-dialog">
      <NotificationCenter mobile />
    </el-dialog>
    
    <!-- 语言选择对话框 -->
    <el-dialog v-model="showLanguageDialog" :title="t('settings.language')">
      <div class="language-list">
        <div
          v-for="(name, code) in supportedLanguages"
          :key="code"
          class="language-item"
          :class="{ active: currentLocale === code }"
          @click="changeLanguage(code)"
        >
          <span class="lang-name">{{ name }}</span>
          <el-icon v-if="currentLocale === code"><Check /></el-icon>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
  Menu, Search, Bell, HomeFilled, FolderOpened, CircleDot,
  Merge, User, Setting, Globe, SwitchButton, Check, VideoPlay
} from '@element-plus/icons-vue'
import NotificationCenter from '@/components/NotificationCenter.vue'

const router = useRouter()
const { t, locale } = useI18n()

// 状态
const drawerOpen = ref(false)
const showSearch = ref(false)
const showNotifications = ref(false)
const showLanguageDialog = ref(false)
const searchQuery = ref('')
const searchHistory = ref<string[]>([])
const unreadCount = ref(0)
const user = ref<any>(null)

// 支持的语言
const supportedLanguages = {
  'zh-CN': '简体中文',
  'zh-TW': '繁體中文',
  'en-US': 'English',
  'ja-JP': '日本語',
  'ko-KR': '한국어'
}

// 当前语言名称
const currentLanguageName = computed(() => supportedLanguages[locale.value] || '简体中文')
const currentLocale = computed(() => locale.value)

// 是否显示底部导航
const showBottomNav = computed(() => {
  const hiddenRoutes = ['/login', '/register', '/settings']
  return !hiddenRoutes.some(route => router.currentRoute.value.path.startsWith(route))
})

// 切换抽屉
function toggleDrawer() {
  drawerOpen.value = !drawerOpen.value
}

function closeDrawer() {
  drawerOpen.value = false
}

// 搜索
function performSearch() {
  if (!searchQuery.value.trim()) return
  
  // 保存搜索历史
  if (!searchHistory.value.includes(searchQuery.value)) {
    searchHistory.value.unshift(searchQuery.value)
    searchHistory.value = searchHistory.value.slice(0, 10)
    localStorage.setItem('searchHistory', JSON.stringify(searchHistory.value))
  }
  
  showSearch.value = false
  router.push(`/search?q=${encodeURIComponent(searchQuery.value)}`)
}

function removeHistory(item: string) {
  searchHistory.value = searchHistory.value.filter(h => h !== item)
  localStorage.setItem('searchHistory', JSON.stringify(searchHistory.value))
}

// 切换语言
function changeLanguage(code: string) {
  locale.value = code
  localStorage.setItem('locale', code)
  document.documentElement.lang = code
  showLanguageDialog.value = false
  ElMessage.success(t('settings.language_changed'))
}

// 登录
function goToLogin() {
  closeDrawer()
  router.push('/login')
}

// 退出
function logout() {
  localStorage.removeItem('token')
  user.value = null
  closeDrawer()
  router.push('/')
  ElMessage.success(t('user.logout_success'))
}

// 初始化
onMounted(() => {
  // 加载搜索历史
  const history = localStorage.getItem('searchHistory')
  if (history) {
    searchHistory.value = JSON.parse(history)
  }
  
  // 加载用户信息
  const token = localStorage.getItem('token')
  if (token) {
    // TODO: 获取用户信息
  }
  
  // 设置语言
  const savedLocale = localStorage.getItem('locale')
  if (savedLocale) {
    locale.value = savedLocale
  }
})
</script>

<style scoped>
.mobile-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* 顶部导航 */
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
  padding: 0 12px;
  z-index: 100;
}

.menu-btn, .search-btn, .notif-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: transparent;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-color, #333);
}

.menu-btn:active, .search-btn:active, .notif-btn:active {
  background: var(--hover-bg, #f6f8fa);
}

.logo a {
  font-size: 18px;
  font-weight: 700;
  color: var(--primary-color, #0366d6);
  text-decoration: none;
}

.header-actions {
  display: flex;
  gap: 4px;
}

.notif-btn {
  position: relative;
}

.notif-btn .badge {
  position: absolute;
  top: 4px;
  right: 4px;
  min-width: 18px;
  height: 18px;
  background: #f56c6c;
  color: #fff;
  font-size: 11px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* 主内容 */
.mobile-content {
  flex: 1;
  margin-top: 56px;
  padding-bottom: 16px;
}

.mobile-content.with-bottom-nav {
  padding-bottom: 76px;
}

/* 底部导航 */
.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: var(--bg-color, #fff);
  border-top: 1px solid var(--border-color, #e1e4e8);
  display: flex;
  justify-content: space-around;
  align-items: center;
  padding-bottom: env(safe-area-inset-bottom);
  z-index: 100;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 12px;
  color: var(--text-secondary, #666);
  text-decoration: none;
  font-size: 11px;
}

.nav-item .el-icon {
  font-size: 20px;
}

.nav-item.active {
  color: var(--primary-color, #0366d6);
}

/* 抽屉 */
.mobile-drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 199;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s;
}

.mobile-drawer-overlay.open {
  opacity: 1;
  visibility: visible;
}

.mobile-drawer {
  position: fixed;
  top: 0;
  left: -280px;
  width: 280px;
  height: 100vh;
  background: var(--bg-color, #fff);
  z-index: 200;
  transition: left 0.3s;
  display: flex;
  flex-direction: column;
}

.mobile-drawer.open {
  left: 0;
}

.drawer-header {
  padding: 20px;
  border-bottom: 1px solid var(--border-color, #e1e4e8);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-details strong {
  display: block;
  font-size: 16px;
}

.user-details small {
  color: var(--text-secondary, #666);
}

.drawer-menu {
  flex: 1;
  overflow-y: auto;
  padding: 8px 0;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  color: var(--text-color, #333);
  text-decoration: none;
  cursor: pointer;
}

.menu-item:active {
  background: var(--hover-bg, #f6f8fa);
}

.menu-item.danger {
  color: #f56c6c;
}

.menu-value {
  margin-left: auto;
  color: var(--text-secondary, #666);
  font-size: 13px;
}

.menu-divider {
  height: 1px;
  background: var(--border-color, #e1e4e8);
  margin: 8px 0;
}

/* 搜索历史 */
.search-history {
  margin-top: 16px;
}

.search-history h4 {
  margin-bottom: 12px;
  color: var(--text-secondary, #666);
}

.history-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* 语言列表 */
.language-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.language-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: 8px;
  cursor: pointer;
}

.language-item:active {
  background: var(--hover-bg, #f6f8fa);
}

.language-item.active {
  background: var(--primary-bg, #e6f4ff);
  color: var(--primary-color, #0366d6);
}

/* 暗色主题 */
:root.dark .mobile-header,
:root.dark .mobile-bottom-nav,
:root.dark .mobile-drawer {
  background: #1c1c1e;
  border-color: #3a3a3c;
}

:root.dark .menu-btn:active,
:root.dark .search-btn:active,
:root.dark .notif-btn:active,
:root.dark .menu-item:active,
:root.dark .language-item:active {
  background: #2c2c2e;
}
</style>
