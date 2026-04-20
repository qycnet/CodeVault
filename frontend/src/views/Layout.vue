<template>
  <el-container class="layout-container">
    <!-- 顶部导航栏 -->
    <el-header class="layout-header">
      <div class="header-left">
        <router-link to="/" class="logo">
          <el-icon :size="28"><Box /></el-icon>
          <span class="logo-text">CodeVault</span>
        </router-link>
        
        <el-menu
          :default-active="activeMenu"
          mode="horizontal"
          :ellipsis="false"
          class="nav-menu"
          router
        >
          <el-menu-item index="/">首页</el-menu-item>
          <el-menu-item index="/repos" v-if="userStore.isLoggedIn">仓库</el-menu-item>
        </el-menu>
      </div>
      
      <div class="header-right">
        <template v-if="userStore.isLoggedIn">
          <el-input
            v-model="searchQuery"
            placeholder="搜索仓库..."
            :prefix-icon="Search"
            class="search-input"
            @keyup.enter="handleSearch"
          />
          
          <el-dropdown @command="handleCommand">
            <span class="user-info">
              <el-avatar :size="32" class="user-avatar">
                {{ userStore.username.charAt(0).toUpperCase() }}
              </el-avatar>
              <span class="username">{{ userStore.username }}</span>
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">
                  <el-icon><User /></el-icon>个人中心
                </el-dropdown-item>
                <el-dropdown-item command="logout" divided>
                  <el-icon><SwitchButton /></el-icon>退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
        
        <template v-else>
          <el-button type="primary" @click="$router.push('/login')">登录</el-button>
          <el-button @click="$router.push('/register')">注册</el-button>
        </template>
      </div>
    </el-header>
    
    <!-- 主内容区 -->
    <el-main class="layout-main">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </el-main>
    
    <!-- 页脚 -->
    <el-footer class="layout-footer">
      <div class="footer-content">
        <span>© 2026 CodeVault. 保留所有权利。</span>
        <div class="footer-links">
          <a href="#">关于我们</a>
          <a href="#">使用条款</a>
          <a href="#">隐私政策</a>
        </div>
      </div>
    </el-footer>
  </el-container>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/store/user'
import { Search, ArrowDown, User, SwitchButton, Box } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const searchQuery = ref('')

const activeMenu = computed(() => route.path)

function handleSearch() {
  if (searchQuery.value.trim()) {
    router.push({ path: '/repos', query: { q: searchQuery.value } })
  }
}

function handleCommand(command) {
  switch (command) {
    case 'profile':
      router.push('/profile')
      break
    case 'logout':
      userStore.logout()
      router.push('/')
      break
  }
}
</script>

<style lang="scss" scoped>
.layout-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.layout-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #24292f;
  color: #fff;
  padding: 0 24px;
  height: 64px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 24px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #fff;
  text-decoration: none;
  font-size: 20px;
  font-weight: 600;
  
  .logo-text {
    color: #fff;
  }
}

.nav-menu {
  background: transparent;
  border: none;
  
  :deep(.el-menu-item) {
    color: rgba(255, 255, 255, 0.85);
    border-bottom: 2px solid transparent;
    
    &:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    &.is-active {
      color: #fff;
      border-bottom-color: #409eff;
    }
  }
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.search-input {
  width: 280px;
  
  :deep(.el-input__wrapper) {
    background: rgba(255, 255, 255, 0.1);
    box-shadow: none;
    
    .el-input__inner {
      color: #fff;
      
      &::placeholder {
        color: rgba(255, 255, 255, 0.5);
      }
    }
  }
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: #fff;
  
  .user-avatar {
    background: #409eff;
  }
  
  .username {
    font-weight: 500;
  }
}

.layout-main {
  flex: 1;
  background: #f6f8fa;
  padding: 24px;
}

.layout-footer {
  background: #24292f;
  color: rgba(255, 255, 255, 0.7);
  height: auto;
  padding: 24px;
}

.footer-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
}

.footer-links {
  display: flex;
  gap: 24px;
  
  a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    
    &:hover {
      color: #fff;
    }
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
