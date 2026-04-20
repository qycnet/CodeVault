import api from './index'
import type { ApiResponse, User } from './types'

export const authApi = {
  // 发送验证码
  sendCode(email: string): Promise<ApiResponse> {
    return api.post('/auth/send-code', { email })
  },

  // 注册
  register(email: string, username: string, password: string, code: string): Promise<ApiResponse> {
    return api.post('/auth/register', { email, username, password, code })
  },

  // 登录
  login(login: string, password: string): Promise<ApiResponse<{ token: string; user: User }>> {
    return api.post('/auth/login', { login, password })
  },

  // 登出
  logout(): Promise<ApiResponse> {
    return api.post('/auth/logout')
  },

  // 获取当前用户
  me(): Promise<ApiResponse<User>> {
    return api.get('/auth/me')
  }
}
