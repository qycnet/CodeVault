import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/Register.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos',
    name: 'RepoList',
    component: () => import('@/views/RepoList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo',
    name: 'RepoDetail',
    component: () => import('@/views/RepoDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/issues',
    name: 'IssueList',
    component: () => import('@/views/IssueList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/pulls',
    name: 'PullList',
    component: () => import('@/views/PullList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/ssh-keys',
    name: 'SSHKeys',
    component: () => import('@/views/SSHKeys.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 路由守卫
router.beforeEach(async (to, _from, next) => {
  const userStore = (await import('@/stores/user')).useUserStore()
  
  if (to.meta.requiresAuth && !userStore.isLoggedIn) {
    // 尝试获取用户信息
    await userStore.fetchUser()
    if (!userStore.isLoggedIn) {
      next('/login')
      return
    }
  }
  
  if (!to.meta.requiresAuth && userStore.isLoggedIn && (to.path === '/login' || to.path === '/register')) {
    next('/')
    return
  }
  
  next()
})

export default router
