import request from './request'

export const authApi = {
  // 用户注册
  register(data) {
    return request({
      url: '/auth/register',
      method: 'post',
      data
    })
  },

  // 用户登录
  login(data) {
    return request({
      url: '/auth/login',
      method: 'post',
      data
    })
  },

  // 获取当前用户信息
  me() {
    return request({
      url: '/auth/me',
      method: 'get'
    })
  },

  // 退出登录
  logout() {
    return request({
      url: '/auth/logout',
      method: 'post'
    })
  }
}
