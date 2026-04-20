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
    path: '/repos/:owner/:repo/tree/:branch/:path(.*)?',
    name: 'FileBrowser',
    component: () => import('@/views/FileBrowser.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/blob/:branch/:path(.*)?',
    name: 'FileViewer',
    component: () => import('@/views/FileBrowser.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/commits/:branch?',
    name: 'CommitHistory',
    component: () => import('@/views/CommitHistory.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/branches',
    name: 'BranchManager',
    component: () => import('@/views/BranchManager.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/ci',
    name: 'CICD',
    component: () => import('@/views/CICD.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/projects',
    name: 'Projects',
    component: () => import('@/views/Projects.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin',
    name: 'Admin',
    component: () => import('@/views/Admin.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/api-docs',
    name: 'ApiDocs',
    component: () => import('@/views/ApiDocs.vue'),
    meta: { requiresAuth: false }
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
    path: '/repos/:owner/:repo/pulls/:id',
    name: 'PullDetail',
    component: () => import('@/views/PullDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/pulls/new',
    name: 'CreatePullRequest',
    component: () => import('@/views/CreatePullRequest.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings/ssh-keys',
    name: 'SSHKeys',
    component: () => import('@/views/SSHKeys.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/search',
    name: 'Search',
    component: () => import('@/views/Search.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/notifications',
    name: 'Notifications',
    component: () => import('@/views/Notifications.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('@/views/Settings.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/orgs',
    name: 'Organizations',
    component: () => import('@/views/Organizations.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/orgs/:org',
    name: 'OrganizationDetail',
    component: () => import('@/views/OrganizationDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/releases',
    name: 'Releases',
    component: () => import('@/views/Releases.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/wiki',
    name: 'Wiki',
    component: () => import('@/views/Wiki.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/settings/webhooks',
    name: 'Webhooks',
    component: () => import('@/views/Webhooks.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/repos/:owner/:repo/labels',
    name: 'LabelsMilestones',
    component: () => import('@/views/LabelsMilestones.vue'),
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
