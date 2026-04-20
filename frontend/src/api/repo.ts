import api from './index'
import type { ApiResponse, Repository, PaginatedResponse } from './types'

export const repoApi = {
  // 获取仓库列表
  list(params?: { page?: number; page_size?: number; search?: string }): Promise<ApiResponse<PaginatedResponse<Repository>>> {
    return api.get('/repos', { params })
  },

  // 获取仓库详情
  detail(owner: string, repo: string): Promise<ApiResponse<Repository>> {
    return api.get('/repos/detail', { params: { owner, repo } })
  },

  // 创建仓库
  create(data: { name: string; description?: string; is_private?: boolean }): Promise<ApiResponse<Repository>> {
    return api.post('/repos', data)
  },

  // 更新仓库
  update(id: number, data: { name?: string; description?: string; is_private?: boolean }): Promise<ApiResponse<Repository>> {
    return api.put('/repos', { id, ...data })
  },

  // 删除仓库
  delete(id: number): Promise<ApiResponse> {
    return api.delete('/repos', { data: { id } })
  },

  // 获取仓库状态
  status(owner: string, repo: string): Promise<ApiResponse> {
    return api.get('/repos/status', { params: { owner, repo } })
  },

  // 获取提交历史
  log(owner: string, repo: string, branch?: string): Promise<ApiResponse> {
    return api.get('/repos/log', { params: { owner, repo, branch } })
  },

  // 获取分支列表
  branches(owner: string, repo: string): Promise<ApiResponse<string[]>> {
    return api.get('/repos/branches', { params: { owner, repo } })
  }
}
