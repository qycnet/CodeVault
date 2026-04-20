import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json'
  },
  withCredentials: true // 启用 Cookie 支持
})

// 请求拦截器 - 不再使用 localStorage 存储 token
api.interceptors.request.use(
  (config) => {
    // Token 通过 httpOnly cookie 自动发送，无需手动添加
    return config
  },
  (error) => Promise.reject(error)
)

// 响应拦截器
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      // 清除本地状态
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
