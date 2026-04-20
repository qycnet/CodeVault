import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/auth'

export const useUserStore = defineStore('user', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || '')

  const isLoggedIn = computed(() => !!token.value && !!user.value)
  const username = computed(() => user.value?.username || '')

  async function login(credentials) {
    const res = await authApi.login(credentials)
    token.value = res.data.token
    user.value = res.data.user
    localStorage.setItem('token', res.data.token)
    return res
  }

  async function register(userData) {
    const res = await authApi.register(userData)
    return res
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const res = await authApi.me()
      user.value = res.data.user
    } catch (error) {
      logout()
    }
  }

  function logout() {
    user.value = null
    token.value = ''
    localStorage.removeItem('token')
  }

  // 初始化时获取用户信息
  if (token.value) {
    fetchUser()
  }

  return {
    user,
    token,
    isLoggedIn,
    username,
    login,
    register,
    logout,
    fetchUser
  }
})
