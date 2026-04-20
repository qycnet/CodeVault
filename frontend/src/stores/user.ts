import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/api/types'
import api from '@/api/index'

export const useUserStore = defineStore('user', () => {
  const user = ref<User | null>(null)
  const isLoggedIn = computed(() => !!user.value)
  const username = computed(() => user.value?.username || '')

  async function login(login: string, password: string) {
    const res: any = await api.post('/auth/login', { login, password })
    if (res.code === 200) {
      user.value = res.data.user
      return true
    }
    throw new Error(res.message)
  }

  async function register(email: string, username: string, password: string, code: string) {
    const res: any = await api.post('/auth/register', { email, username, password, code })
    if (res.code === 200) {
      return true
    }
    throw new Error(res.message)
  }

  async function sendCode(email: string) {
    const res: any = await api.post('/auth/send-code', { email })
    if (res.code === 200) {
      return true
    }
    throw new Error(res.message)
  }

  async function fetchUser() {
    try {
      const res: any = await api.get('/auth/me')
      if (res.code === 200) {
        user.value = res.data
      }
    } catch (e) {
      logout()
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (e) {
      // ignore
    }
    user.value = null
  }

  return {
    user,
    isLoggedIn,
    username,
    login,
    register,
    sendCode,
    fetchUser,
    logout
  }
})
