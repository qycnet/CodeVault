<template>
  <div class="home-page">
    <!-- Hero 区域 -->
    <section class="hero-section">
      <div class="hero-content">
        <h1 class="hero-title">
          让团队协作更简单
        </h1>
        <p class="hero-subtitle">
          CodeVault 是一个现代化的代码仓库管理平台，支持 Git 仓库托管、协作开发、代码审查等功能。
        </p>
        
        <div class="hero-actions">
          <el-button type="primary" size="large" @click="handleStart">
            开始使用
            <el-icon class="el-icon--right"><ArrowRight /></el-icon>
          </el-button>
          <el-button size="large" @click="handleLearnMore">
            了解更多
          </el-button>
        </div>
      </div>
      
      <div class="hero-image">
        <div class="code-preview">
          <div class="code-header">
            <span class="dot red"></span>
            <span class="dot yellow"></span>
            <span class="dot green"></span>
            <span class="file-name">README.md</span>
          </div>
          <pre class="code-content"><code># CodeVault

## 快速开始

```bash
# 克隆仓库
git clone https://codevault.dev/user/repo.git

# 创建分支
git checkout -b feature/new-feature

# 提交更改
git commit -m "添加新功能"
git push origin feature/new-feature
```

## 功能特性

- ✅ Git 仓库托管
- ✅ 合并请求 (Pull Request)
- ✅ 问题追踪 (Issues)
- ✅ SSH 密钥管理
</code></pre>
        </div>
      </div>
    </section>
    
    <!-- 功能特性 -->
    <section class="features-section">
      <h2 class="section-title">核心功能</h2>
      
      <div class="features-grid">
        <el-card v-for="feature in features" :key="feature.title" class="feature-card" shadow="hover">
          <div class="feature-icon">
            <el-icon :size="40"><component :is="feature.icon" /></el-icon>
          </div>
          <h3 class="feature-title">{{ feature.title }}</h3>
          <p class="feature-desc">{{ feature.desc }}</p>
        </el-card>
      </div>
    </section>
    
    <!-- 统计数据 -->
    <section class="stats-section">
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value">{{ stats.repos }}+</div>
          <div class="stat-label">仓库数量</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">{{ stats.users }}+</div>
          <div class="stat-label">活跃用户</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">{{ stats.commits }}+</div>
          <div class="stat-label">提交次数</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">{{ stats.prs }}+</div>
          <div class="stat-label">合并请求</div>
        </div>
      </div>
    </section>
    
    <!-- CTA 区域 -->
    <section class="cta-section">
      <div class="cta-content">
        <h2>准备好开始了吗？</h2>
        <p>加入 CodeVault，体验更高效的团队协作</p>
        <el-button type="primary" size="large" @click="handleRegister">
          免费注册
        </el-button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/store/user'
import { ArrowRight, Folder, GitBranch, ChatDotRound, Lock } from '@element-plus/icons-vue'

const router = useRouter()
const userStore = useUserStore()

const features = ref([
  {
    icon: 'Folder',
    title: '仓库管理',
    desc: '创建、克隆和管理 Git 仓库，支持公开和私有仓库'
  },
  {
    icon: 'GitBranch',
    title: '分支管理',
    desc: '创建分支、合并请求，进行代码审查和协作开发'
  },
  {
    icon: 'ChatDotRound',
    title: '问题追踪',
    desc: '创建和管理问题，跟踪 Bug 和功能请求'
  },
  {
    icon: 'Lock',
    title: '安全可靠',
    desc: 'SSH 密钥认证，确保代码安全和访问控制'
  }
])

const stats = ref({
  repos: '10,000',
  users: '5,000',
  commits: '100,000',
  prs: '20,000'
})

function handleStart() {
  if (userStore.isLoggedIn) {
    router.push('/repos')
  } else {
    router.push('/register')
  }
}

function handleLearnMore() {
  // 滚动到功能特性区域
  document.querySelector('.features-section').scrollIntoView({ behavior: 'smooth' })
}

function handleRegister() {
  router.push('/register')
}
</script>

<style lang="scss" scoped>
.home-page {
  max-width: 1200px;
  margin: 0 auto;
}

.hero-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 48px;
  align-items: center;
  padding: 48px 0;
}

.hero-content {
  .hero-title {
    font-size: 48px;
    font-weight: 700;
    color: #24292f;
    margin: 0 0 16px;
    line-height: 1.2;
  }
  
  .hero-subtitle {
    font-size: 18px;
    color: #57606a;
    line-height: 1.6;
    margin: 0 0 32px;
  }
}

.hero-actions {
  display: flex;
  gap: 16px;
}

.code-preview {
  background: #24292f;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  
  .code-header {
    background: #30363d;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    
    .dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      
      &.red { background: #ff5f56; }
      &.yellow { background: #ffbd2e; }
      &.green { background: #27c93f; }
    }
    
    .file-name {
      margin-left: 12px;
      color: rgba(255, 255, 255, 0.7);
      font-size: 13px;
    }
  }
  
  .code-content {
    margin: 0;
    padding: 20px;
    color: #c9d1d9;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 13px;
    line-height: 1.6;
    overflow-x: auto;
    
    code {
      color: inherit;
    }
  }
}

.features-section {
  padding: 64px 0;
  
  .section-title {
    text-align: center;
    font-size: 32px;
    font-weight: 600;
    color: #24292f;
    margin: 0 0 48px;
  }
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
}

.feature-card {
  text-align: center;
  padding: 32px 24px;
  
  .feature-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #409eff 0%, #67c23a 100%);
    border-radius: 16px;
    color: #fff;
  }
  
  .feature-title {
    font-size: 18px;
    font-weight: 600;
    color: #24292f;
    margin: 0 0 12px;
  }
  
  .feature-desc {
    font-size: 14px;
    color: #57606a;
    line-height: 1.6;
    margin: 0;
  }
}

.stats-section {
  background: linear-gradient(135deg, #409eff 0%, #67c23a 100%);
  border-radius: 16px;
  padding: 48px;
  margin: 48px 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 32px;
}

.stat-item {
  text-align: center;
  color: #fff;
  
  .stat-value {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 8px;
  }
  
  .stat-label {
    font-size: 16px;
    opacity: 0.9;
  }
}

.cta-section {
  text-align: center;
  padding: 64px 0;
  
  .cta-content {
    h2 {
      font-size: 32px;
      font-weight: 600;
      color: #24292f;
      margin: 0 0 16px;
    }
    
    p {
      font-size: 18px;
      color: #57606a;
      margin: 0 0 32px;
    }
  }
}

@media (max-width: 1024px) {
  .hero-section {
    grid-template-columns: 1fr;
    text-align: center;
  }
  
  .hero-image {
    display: none;
  }
  
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .features-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
