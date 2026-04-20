import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/api/types'
import { authApi } from '@/api/auth'

export const useUserStore = defineStore('user', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const isLoggedIn = computed(() => !!token.value)
  const username = computed(() => user.value?.username || '')

  async function login(email: string, password: string) {
    const res = await authApi.login(email, password)
    if (res.code === 200) {
      token.value = res.data.token
      user.value = res.data.user
      localStorage.setItem('token', res.data.token)
      return true
    }
    throw new Error(res.message)
  }

  async function register(email: string, username: string, password: string, code: string) {
    const res = await authApi.register(email, username, password, code)
    if (res.code === 200) {
      return true
    }
    throw new Error(res.message)
  }

  async function sendCode(email: string) {
    const res = await authApi.sendCode(email)
    if (res.code === 200) {
      return true
    }
    throw new Error(res.message)
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const res = await authApi.me()
      if (res.code === 200) {
        user.value = res.data
      }
    } catch (e) {
      logout()
    }
  }

  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('token')
  }

  return {
    user,
    token,
    isLoggedIn,
    username,
    login,
    register,
    sendCode,
    fetchUser,
    logout
  }
})
