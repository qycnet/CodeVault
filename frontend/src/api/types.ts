// 用户相关类型
export interface User {
  id: number
  email: string
  username: string
  created_at: string
}

// SSH Key
export interface SSHKey {
  id: number
  name: string
  fingerprint: string
  public_key: string
  created_at: string
}

// 仓库
export interface Repository {
  id: number
  name: string
  description: string
  owner_id: number
  owner_name: string
  is_private: boolean
  default_branch: string
  created_at: string
  updated_at: string
}

// Issue
export interface Issue {
  id: number
  repo_id: number
  author_id: number
  author_name: string
  title: string
  content: string
  status: 'open' | 'closed'
  created_at: string
  updated_at: string
}

// Pull Request
export interface PullRequest {
  id: number
  repo_id: number
  author_id: number
  author_name: string
  title: string
  description: string
  source_branch: string
  target_branch: string
  status: 'open' | 'merged' | 'closed'
  created_at: string
  updated_at: string
}

// 评论
export interface Comment {
  id: number
  parent_type: 'issue' | 'pull_request'
  parent_id: number
  author_id: number
  author_name: string
  content: string
  line_number?: number
  file_path?: string
  created_at: string
}

// API 响应
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
  timestamp: number
}

// 分页
export interface PaginatedResponse<T> {
  items: T[]
  total: number
  page: number
  page_size: number
}
