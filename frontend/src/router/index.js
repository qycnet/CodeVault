import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/store/user'

const routes = [
  {
    path: '/',
    component: () => import('@/views/Layout.vue'),
    children: [
      {
        path: '',
        name: 'Home',
        component: () => import('@/views/Home.vue'),
        meta: { title: '首页' }
      },
      {
        path: 'repos',
        name: 'Repos',
        component: () => import('@/views/Repos.vue'),
        meta: { title: '仓库列表', requiresAuth: true }
      },
      {
        path: 'repo/:owner/:name',
        name: 'RepoDetail',
        component: () => import('@/views/RepoDetail.vue'),
        meta: { title: '仓库详情' }
      },
      {
        path: 'repo/:owner/:name/pulls',
        name: 'PullRequests',
        component: () => import('@/views/PullRequests.vue'),
        meta: { title: '合并请求' }
      },
      {
        path: 'repo/:owner/:name/issues',
        name: 'Issues',
        component: () => import('@/views/Issues.vue'),
        meta: { title: '问题列表' }
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/Profile.vue'),
        meta: { title: '个人中心', requiresAuth: true }
      }
    ]
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { title: '登录' }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/Register.vue'),
    meta: { title: '注册' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 路由守卫
router.beforeEach((to, from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - CodeVault` : 'CodeVault'
  
  const userStore = useUserStore()
  
  if (to.meta.requiresAuth && !userStore.isLoggedIn) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } else {
    next()
  }
})

export default router
