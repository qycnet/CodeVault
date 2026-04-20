<template>
  <div class="api-docs">
    <el-container>
      <el-aside width="280px">
        <div class="sidebar">
          <h3>API 文档</h3>
          
          <el-menu :default-active="activeSection" @select="scrollToSection">
            <el-sub-menu index="auth">
              <template #title>认证 API</template>
              <el-menu-item index="auth-register">用户注册</el-menu-item>
              <el-menu-item index="auth-login">用户登录</el-menu-item>
              <el-menu-item index="auth-logout">用户登出</el-menu-item>
              <el-menu-item index="auth-me">获取当前用户</el-menu-item>
            </el-sub-menu>
            
            <el-sub-menu index="repos">
              <template #title>仓库 API</template>
              <el-menu-item index="repos-list">仓库列表</el-menu-item>
              <el-menu-item index="repos-create">创建仓库</el-menu-item>
              <el-menu-item index="repos-detail">仓库详情</el-menu-item>
              <el-menu-item index="repos-update">更新仓库</el-menu-item>
              <el-menu-item index="repos-delete">删除仓库</el-menu-item>
            </el-sub-menu>
            
            <el-sub-menu index="issues">
              <template #title>Issue API</template>
              <el-menu-item index="issues-list">Issue 列表</el-menu-item>
              <el-menu-item index="issues-create">创建 Issue</el-menu-item>
              <el-menu-item index="issues-update">更新 Issue</el-menu-item>
              <el-menu-item index="issues-close">关闭 Issue</el-menu-item>
            </el-sub-menu>
            
            <el-sub-menu index="pr">
              <template #title>Pull Request API</template>
              <el-menu-item index="pr-list">PR 列表</el-menu-item>
              <el-menu-item index="pr-create">创建 PR</el-menu-item>
              <el-menu-item index="pr-merge">合并 PR</el-menu-item>
              <el-menu-item index="pr-close">关闭 PR</el-menu-item>
            </el-sub-menu>
            
            <el-sub-menu index="git">
              <template #title>Git 操作 API</template>
              <el-menu-item index="git-clone">Clone 仓库</el-menu-item>
              <el-menu-item index="git-push">Push 代码</el-menu-item>
              <el-menu-item index="git-pull">Pull 代码</el-menu-item>
              <el-menu-item index="git-log">提交历史</el-menu-item>
            </el-sub-menu>
          </el-menu>
        </div>
      </el-aside>
      
      <el-main>
        <div class="content">
          <h1>CodeVault API 文档</h1>
          
          <el-alert
            title="Base URL"
            type="info"
            :closable="false"
            style="margin-bottom: 20px;"
          >
            <code>http://localhost:8000/api</code>
          </el-alert>
          
          <!-- 认证 API -->
          <section id="auth" class="api-section">
            <h2>认证 API</h2>
            
            <div id="auth-register" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="success">POST</el-tag>
                <code>/api/auth/register</code>
              </div>
              
              <h4>请求参数</h4>
              <el-table :data="authRegisterParams" border size="small">
                <el-table-column prop="name" label="参数名" width="150" />
                <el-table-column prop="type" label="类型" width="100" />
                <el-table-column prop="required" label="必填" width="80" />
                <el-table-column prop="description" label="说明" />
              </el-table>
              
              <h4>响应示例</h4>
              <pre><code>{
  "success": true,
  "message": "注册成功",
  "user": {
    "id": 1,
    "username": "test",
    "email": "test@example.com"
  }
}</code></pre>
            </div>
            
            <div id="auth-login" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="success">POST</el-tag>
                <code>/api/auth/login</code>
              </div>
              
              <h4>请求参数</h4>
              <el-table :data="authLoginParams" border size="small">
                <el-table-column prop="name" label="参数名" width="150" />
                <el-table-column prop="type" label="类型" width="100" />
                <el-table-column prop="required" label="必填" width="80" />
                <el-table-column prop="description" label="说明" />
              </el-table>
            </div>
          </section>
          
          <!-- 仓库 API -->
          <section id="repos" class="api-section">
            <h2>仓库 API</h2>
            
            <div id="repos-list" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="primary">GET</el-tag>
                <code>/api/repos</code>
              </div>
              
              <h4>请求参数</h4>
              <el-table :data="reposListParams" border size="small">
                <el-table-column prop="name" label="参数名" width="150" />
                <el-table-column prop="type" label="类型" width="100" />
                <el-table-column prop="required" label="必填" width="80" />
                <el-table-column prop="description" label="说明" />
              </el-table>
            </div>
            
            <div id="repos-create" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="success">POST</el-tag>
                <code>/api/repos</code>
              </div>
              
              <h4>请求参数</h4>
              <el-table :data="reposCreateParams" border size="small">
                <el-table-column prop="name" label="参数名" width="150" />
                <el-table-column prop="type" label="类型" width="100" />
                <el-table-column prop="required" label="必填" width="80" />
                <el-table-column prop="description" label="说明" />
              </el-table>
            </div>
          </section>
          
          <!-- Issue API -->
          <section id="issues" class="api-section">
            <h2>Issue API</h2>
            
            <div id="issues-list" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="primary">GET</el-tag>
                <code>/api/issues</code>
              </div>
              
              <h4>请求参数</h4>
              <el-table :data="issuesListParams" border size="small">
                <el-table-column prop="name" label="参数名" width="150" />
                <el-table-column prop="type" label="类型" width="100" />
                <el-table-column prop="required" label="必填" width="80" />
                <el-table-column prop="description" label="说明" />
              </el-table>
            </div>
          </section>
          
          <!-- PR API -->
          <section id="pr" class="api-section">
            <h2>Pull Request API</h2>
            
            <div id="pr-list" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="primary">GET</el-tag>
                <code>/api/pull-requests</code>
              </div>
            </div>
            
            <div id="pr-create" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="success">POST</el-tag>
                <code>/api/pull-requests</code>
              </div>
              
              <h4>请求参数</h4>
              <el-table :data="prCreateParams" border size="small">
                <el-table-column prop="name" label="参数名" width="150" />
                <el-table-column prop="type" label="类型" width="100" />
                <el-table-column prop="required" label="必填" width="80" />
                <el-table-column prop="description" label="说明" />
              </el-table>
            </div>
          </section>
          
          <!-- Git API -->
          <section id="git" class="api-section">
            <h2>Git 操作 API</h2>
            
            <div id="git-clone" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="success">POST</el-tag>
                <code>/api/git/clone</code>
              </div>
            </div>
            
            <div id="git-push" class="endpoint">
              <div class="endpoint-header">
                <el-tag type="success">POST</el-tag>
                <code>/api/git/push</code>
              </div>
            </div>
          </section>
        </div>
      </el-main>
    </el-container>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const activeSection = ref('auth-register')

const authRegisterParams = ref([
  { name: 'username', type: 'string', required: '是', description: '用户名（3-20字符）' },
  { name: 'email', type: 'string', required: '是', description: '邮箱地址' },
  { name: 'password', type: 'string', required: '是', description: '密码（至少8位）' },
  { name: 'code', type: 'string', required: '是', description: '邮箱验证码' },
])

const authLoginParams = ref([
  { name: 'email', type: 'string', required: '是', description: '邮箱地址' },
  { name: 'password', type: 'string', required: '是', description: '密码' },
])

const reposListParams = ref([
  { name: 'page', type: 'int', required: '否', description: '页码（默认1）' },
  { name: 'per_page', type: 'int', required: '否', description: '每页数量（默认20）' },
  { name: 'is_private', type: 'bool', required: '否', description: '是否私有' },
])

const reposCreateParams = ref([
  { name: 'name', type: 'string', required: '是', description: '仓库名称' },
  { name: 'description', type: 'string', required: '否', description: '仓库描述' },
  { name: 'is_private', type: 'bool', required: '否', description: '是否私有（默认false）' },
])

const issuesListParams = ref([
  { name: 'repo_id', type: 'int', required: '是', description: '仓库ID' },
  { name: 'status', type: 'string', required: '否', description: '状态（open/closed）' },
  { name: 'page', type: 'int', required: '否', description: '页码' },
])

const prCreateParams = ref([
  { name: 'repo_id', type: 'int', required: '是', description: '仓库ID' },
  { name: 'title', type: 'string', required: '是', description: 'PR 标题' },
  { name: 'description', type: 'string', required: '否', description: 'PR 描述' },
  { name: 'source_branch', type: 'string', required: '是', description: '源分支' },
  { name: 'target_branch', type: 'string', required: '是', description: '目标分支' },
])

function scrollToSection(index: string) {
  activeSection.value = index
  const element = document.getElementById(index)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth' })
  }
}
</script>

<style scoped>
.api-docs {
  padding: 20px;
}

.sidebar {
  background: #fff;
  padding: 20px;
  border-right: 1px solid #e4e7ed;
  height: calc(100vh - 40px);
  overflow-y: auto;
}

.sidebar h3 {
  margin-bottom: 20px;
  color: #303133;
}

.content {
  background: #fff;
  padding: 30px;
  border-radius: 8px;
}

.content h1 {
  margin-bottom: 20px;
  color: #303133;
}

.api-section {
  margin-top: 40px;
}

.api-section h2 {
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #409eff;
}

.endpoint {
  margin-bottom: 30px;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
}

.endpoint-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.endpoint-header code {
  font-size: 16px;
  font-weight: 500;
}

.endpoint h4 {
  margin: 16px 0 8px;
  color: #606266;
}

pre {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 16px;
  border-radius: 6px;
  overflow-x: auto;
}

code {
  font-family: 'Consolas', 'Monaco', monospace;
}
</style>
