<template>
<<<<<<< HEAD
  <div class="login-container">
    <el-card class="login-card">
      <template #header>
        <div class="card-header">
          <h2>CodeVault</h2>
          <p>代码仓库管理系统</p>
        </div>
      </template>
      
      <el-form ref="formRef" :model="form" :rules="rules" label-width="0">
        <el-form-item prop="login">
          <el-input
            v-model="form.login"
            placeholder="邮箱或用户名"
            prefix-icon="User"
=======
  <div class="login-page">
    <div class="login-container">
      <div class="login-header">
        <router-link to="/" class="logo">
          <el-icon :size="32"><Box /></el-icon>
          <span>CodeVault</span>
        </router-link>
        <h1>登录</h1>
        <p>欢迎回来，请登录您的账户</p>
      </div>
      
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        class="login-form"
        @submit.prevent="handleLogin"
      >
        <el-form-item prop="username">
          <el-input
            v-model="form.username"
            placeholder="用户名或邮箱"
            :prefix-icon="User"
>>>>>>> origin/main
            size="large"
          />
        </el-form-item>
        
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码"
<<<<<<< HEAD
            prefix-icon="Lock"
            size="large"
            show-password
            @keyup.enter="handleLogin"
=======
            :prefix-icon="Lock"
            size="large"
            show-password
>>>>>>> origin/main
          />
        </el-form-item>
        
        <el-form-item>
<<<<<<< HEAD
=======
          <div class="form-options">
            <el-checkbox v-model="rememberMe">记住我</el-checkbox>
            <el-link type="primary">忘记密码？</el-link>
          </div>
        </el-form-item>
        
        <el-form-item>
>>>>>>> origin/main
          <el-button
            type="primary"
            size="large"
            :loading="loading"
<<<<<<< HEAD
            @click="handleLogin"
            style="width: 100%"
=======
            class="login-button"
            @click="handleLogin"
>>>>>>> origin/main
          >
            登录
          </el-button>
        </el-form-item>
      </el-form>
      
<<<<<<< HEAD
      <div class="footer-links">
        <span>还没有账号？</span>
        <router-link to="/register">立即注册</router-link>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

const formRef = ref()
const loading = ref(false)

const form = reactive({
  login: '',
=======
      <div class="login-footer">
        <p>
          还没有账户？
          <router-link to="/register">立即注册</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/store/user'
import { User, Lock, Box } from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const formRef = ref(null)
const loading = ref(false)
const rememberMe = ref(false)

const form = reactive({
  username: '',
>>>>>>> origin/main
  password: ''
})

const rules = {
<<<<<<< HEAD
  login: [{ required: true, message: '请输入邮箱或用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
=======
  username: [
    { required: true, message: '请输入用户名或邮箱', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
  ]
>>>>>>> origin/main
}

async function handleLogin() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
  loading.value = true
<<<<<<< HEAD
  try {
    await userStore.login(form.login, form.password)
    ElMessage.success('登录成功')
    router.push('/')
  } catch (e: any) {
    ElMessage.error(e.message || '登录失败')
=======
  
  try {
    await userStore.login(form)
    ElMessage.success('登录成功')
    
    // 跳转到之前的页面或首页
    const redirect = route.query.redirect || '/'
    router.push(redirect)
  } catch (error) {
    // 错误已在拦截器中处理
>>>>>>> origin/main
  } finally {
    loading.value = false
  }
}
</script>

<<<<<<< HEAD
<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
  width: 400px;
}

.card-header {
  text-align: center;
}

.card-header h2 {
  margin: 0;
  color: #409eff;
}

.card-header p {
  margin: 8px 0 0;
  color: #909399;
  font-size: 14px;
}

.footer-links {
  text-align: center;
  margin-top: 16px;
  font-size: 14px;
  color: #909399;
}

.footer-links a {
  color: #409eff;
  text-decoration: none;
  margin-left: 4px;
}

.footer-links a:hover {
  text-decoration: underline;
=======
<style lang="scss" scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 24px;
}

.login-container {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 16px;
  padding: 48px 40px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.login-header {
  text-align: center;
  margin-bottom: 32px;
  
  .logo {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #24292f;
    text-decoration: none;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 24px;
  }
  
  h1 {
    font-size: 28px;
    font-weight: 600;
    color: #24292f;
    margin: 0 0 8px;
  }
  
  p {
    font-size: 14px;
    color: #57606a;
    margin: 0;
  }
}

.login-form {
  .form-options {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .login-button {
    width: 100%;
  }
}

.login-footer {
  text-align: center;
  margin-top: 24px;
  
  p {
    font-size: 14px;
    color: #57606a;
    margin: 0;
    
    a {
      color: #409eff;
      text-decoration: none;
      font-weight: 500;
      
      &:hover {
        text-decoration: underline;
      }
    }
  }
>>>>>>> origin/main
}
</style>
