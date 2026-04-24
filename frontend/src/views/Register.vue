<template>
  <div class="register-page">
    <div class="register-container">
      <div class="register-header">
        <router-link to="/" class="logo">
          <el-icon :size="32"><Box /></el-icon>
          <span>CodeVault</span>
        </router-link>
        <h1>注册</h1>
        <p>创建您的 CodeVault 账户</p>
      </div>
      
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        class="register-form"
        @submit.prevent="handleRegister"
      >
        <el-form-item prop="username">
          <el-input
            v-model="form.username"
            placeholder="用户名"
            :prefix-icon="User"
            size="large"
          />
        </el-form-item>
        
        <el-form-item prop="email">
          <el-input
            v-model="form.email"
            placeholder="邮箱地址"
            :prefix-icon="Message"
            size="large"
          />
        </el-form-item>
        
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码"
            :prefix-icon="Lock"
            size="large"
            show-password
          />
        </el-form-item>
        
        <el-form-item prop="confirmPassword">
          <el-input
            v-model="form.confirmPassword"
            type="password"
            placeholder="确认密码"
            :prefix-icon="Lock"
            size="large"
            show-password
          />
        </el-form-item>
        
        <el-form-item prop="verificationCode">
          <div class="verification-row">
            <el-input
              v-model="form.verificationCode"
              placeholder="验证码"
              :prefix-icon="Key"
              size="large"
              maxlength="6"
            />
            <el-button
              type="primary"
              size="large"
              :disabled="countdown > 0"
              :loading="sendingCode"
              @click="sendVerificationCode"
            >
              {{ countdown > 0 ? `${countdown}s` : '发送验证码' }}
            </el-button>
          </div>
        </el-form-item>
        
        <el-form-item>
          <el-checkbox v-model="agreeTerms">
            我已阅读并同意
            <el-link type="primary">服务条款</el-link>
            和
            <el-link type="primary">隐私政策</el-link>
          </el-checkbox>
        </el-form-item>
        
        <el-form-item>
          <el-button
            type="primary"
            size="large"
            :loading="loading"
            :disabled="!agreeTerms"
            class="register-button"
            @click="handleRegister"
          >
            注册
          </el-button>
        </el-form-item>
      </el-form>
      
      <div class="register-footer">
        <p>
          已有账户？
          <router-link to="/login">立即登录</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/store/user'
import { User, Message, Lock, Key, Box } from '@element-plus/icons-vue'

const router = useRouter()
const userStore = useUserStore()

const formRef = ref(null)
const loading = ref(false)
const sendingCode = ref(false)
const countdown = ref(0)
const agreeTerms = ref(false)

const form = reactive({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  verificationCode: ''
})

const validateConfirmPassword = (rule, value, callback) => {
  if (value !== form.password) {
    callback(new Error('两次输入的密码不一致'))
  } else {
    callback()
  }
}

const rules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 20, message: '用户名长度为3-20个字符', trigger: 'blur' },
    { pattern: /^[a-zA-Z0-9_-]+$/, message: '用户名只能包含字母、数字、下划线和连字符', trigger: 'blur' }
  ],
  email: [
    { required: true, message: '请输入邮箱地址', trigger: 'blur' },
    { type: 'email', message: '请输入有效的邮箱地址', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 8, max: 40, message: '密码长度为8-40个字符', trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: '请确认密码', trigger: 'blur' },
    { validator: validateConfirmPassword, trigger: 'blur' }
  ],
  verificationCode: [
    { required: true, message: '请输入验证码', trigger: 'blur' },
    { len: 6, message: '验证码为6位数字', trigger: 'blur' }
  ]
}

async function sendVerificationCode() {
  // 验证邮箱
  try {
    await formRef.value.validateField('email')
  } catch {
    return
  }
  
  sendingCode.value = true
  
  try {
    // 调用发送验证码 API
    const res = await fetch('/api/auth/send-code', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: form.email,
        type: 'register'
      })
    })
    
    const data = await res.json()
    
    if (data.code === 200 || res.ok) {
      ElMessage.success('验证码已发送到您的邮箱')
      
      // 开始倒计时
      countdown.value = 60
      const timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
          clearInterval(timer)
        }
      }, 1000)
    } else {
      throw new Error(data.message || '发送失败')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '发送验证码失败')
  } finally {
    sendingCode.value = false
  }
}

async function handleRegister() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  
  if (!agreeTerms.value) {
    ElMessage.warning('请先同意服务条款和隐私政策')
    return
  }
  
  loading.value = true
  
  try {
    await userStore.register({
      username: form.username,
      email: form.email,
      password: form.password,
      verificationCode: form.verificationCode
    })
    
    ElMessage.success('注册成功，请登录')
    router.push('/login')
  } catch (error) {
    // 错误已在拦截器中处理
  } finally {
    loading.value = false
  }
}
</script>

<style lang="scss" scoped>
.register-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 24px;
}

.register-container {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 16px;
  padding: 48px 40px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.register-header {
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

.register-form {
  .verification-row {
    display: flex;
    gap: 12px;
    
    .el-input {
      flex: 1;
    }
  }
  
  .register-button {
    width: 100%;
  }
}

.register-footer {
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
}
</style>
