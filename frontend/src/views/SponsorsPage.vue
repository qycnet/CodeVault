<template>
  <div class="sponsors-page">
    <!-- 头部 -->
    <div class="page-header">
      <div class="header-left">
        <h1>
          <span class="emoji">❤️</span>
          Sponsors
        </h1>
        <p class="subtitle">赞助支持创作者</p>
      </div>
      <div class="header-right">
        <el-button v-if="isCreator" type="primary" @click="showCreateTierDialog = true">
          <el-icon><Plus /></el-icon>
          创建赞助等级
        </el-button>
        <el-button v-if="!isMyProfile" type="danger" @click="showSponsorDialog = true">
          <el-icon><Heart /></el-icon>
          赞助
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-cards" v-if="stats">
      <div class="stat-card">
        <div class="stat-value">{{ stats.active_sponsors || 0 }}</div>
        <div class="stat-label">活跃赞助者</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">¥{{ formatAmount(stats.monthly_earnings) }}</div>
        <div class="stat-label">月收入</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">¥{{ formatAmount(stats.total_earnings) }}</div>
        <div class="stat-label">总收入</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ stats.total_transactions || 0 }}</div>
        <div class="stat-label">交易次数</div>
      </div>
    </div>

    <!-- 赞助目标 -->
    <div class="goals-section" v-if="goals.length > 0">
      <h3>🎯 赞助目标</h3>
      <div class="goals-grid">
        <div v-for="goal in goals" :key="goal.id" class="goal-card">
          <div class="goal-header">
            <h4>{{ goal.title }}</h4>
            <el-tag v-if="goal.is_achieved" type="success" size="small">已达成</el-tag>
          </div>
          <p class="goal-desc">{{ goal.description }}</p>
          <el-progress 
            :percentage="Math.min(100, Math.round((goal.current_amount / goal.target_amount) * 100))"
            :status="goal.is_achieved ? 'success' : ''"
          />
          <div class="goal-amount">
            <span>¥{{ formatAmount(goal.current_amount) }}</span>
            <span>/ ¥{{ formatAmount(goal.target_amount) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 赞助等级 -->
    <div class="tiers-section">
      <h3>💎 赞助等级</h3>
      <div class="tiers-grid">
        <div
          v-for="tier in tiers"
          :key="tier.id"
          class="tier-card"
          :style="{ borderColor: tier.color }"
          @click="selectTier(tier)"
        >
          <div class="tier-icon">{{ getTierIcon(tier.icon) }}</div>
          <h4>{{ tier.name }}</h4>
          <div class="tier-amount">
            <span class="price">¥{{ tier.monthly_amount }}</span>
            <span class="period">/ 月</span>
          </div>
          <p class="tier-desc">{{ tier.description }}</p>
          <div class="tier-benefits">
            <div v-for="(benefit, index) in tier.benefits" :key="index" class="benefit-item">
              <el-icon><Check /></el-icon>
              {{ benefit }}
            </div>
          </div>
          <div class="tier-sponsors">
            <el-avatar 
              v-for="s in tier.sponsors?.slice(0, 5)" 
              :key="s.id" 
              :size="24" 
              :src="s.sponsor_avatar"
            >
              {{ s.sponsor_name?.charAt(0) }}
            </el-avatar>
            <span v-if="tier.sponsor_count > 5">+{{ tier.sponsor_count - 5 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 赞助者列表 -->
    <div class="sponsors-section" v-if="sponsors.length > 0">
      <h3>🏆 赞助者</h3>
      <div class="sponsors-list">
        <div v-for="sponsor in sponsors" :key="sponsor.id" class="sponsor-item">
          <el-avatar :size="48" :src="sponsor.sponsor_avatar">
            {{ sponsor.sponsor_name?.charAt(0) }}
          </el-avatar>
          <div class="sponsor-info">
            <div class="sponsor-name">
              {{ sponsor.sponsor_name }}
              <el-tag v-if="sponsor.tier_name" :color="sponsor.tier_color" size="small" effect="dark">
                {{ sponsor.tier_name }}
              </el-tag>
            </div>
            <div class="sponsor-meta">
              <span>¥{{ formatAmount(sponsor.amount) }}</span>
              <span>{{ sponsor.frequency === 'one_time' ? '一次性' : (sponsor.frequency === 'monthly' ? '每月' : '每年') }}</span>
              <span>{{ formatDate(sponsor.created_at) }}</span>
            </div>
            <p v-if="sponsor.message && !sponsor.is_anonymous" class="sponsor-message">
              "{{ sponsor.message }}"
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- 我的赞助 -->
    <div class="my-sponsorships-section" v-if="isMyProfile && mySponsorships.length > 0">
      <h3>💳 我的赞助</h3>
      <div class="sponsorships-list">
        <div v-for="sponsorship in mySponsorships" :key="sponsorship.id" class="sponsorship-item">
          <el-avatar :size="48" :src="sponsorship.creator_avatar">
            {{ sponsorship.creator_name?.charAt(0) }}
          </el-avatar>
          <div class="sponsorship-info">
            <div class="sponsorship-name">{{ sponsorship.creator_name }}</div>
            <div class="sponsorship-meta">
              <span>¥{{ formatAmount(sponsorship.amount) }} / {{ sponsorship.frequency === 'monthly' ? '月' : '年' }}</span>
              <el-tag :type="sponsorship.status === 'active' ? 'success' : 'info'" size="small">
                {{ sponsorship.status === 'active' ? '活跃' : '已取消' }}
              </el-tag>
            </div>
          </div>
          <el-button 
            v-if="sponsorship.status === 'active'" 
            type="danger" 
            size="small" 
            @click="cancelSponsorship(sponsorship.id)"
          >
            取消赞助
          </el-button>
        </div>
      </div>
    </div>

    <!-- 赞助对话框 -->
    <el-dialog
      v-model="showSponsorDialog"
      title="赞助支持"
      width="500px"
      :close-on-click-modal="false"
    >
      <div class="sponsor-form">
        <div class="amount-section">
          <h4>选择金额</h4>
          <div class="amount-options">
            <el-button
              v-for="amount in [10, 30, 50, 100, 300, 500]"
              :key="amount"
              :type="selectedAmount === amount ? 'primary' : 'default'"
              @click="selectedAmount = amount"
            >
              ¥{{ amount }}
            </el-button>
          </div>
          <el-input v-model="customAmount" placeholder="自定义金额" type="number" style="margin-top: 12px">
            <template #prepend>¥</template>
          </el-input>
        </div>
        
        <div class="frequency-section">
          <h4>赞助频率</h4>
          <el-radio-group v-model="sponsorFrequency">
            <el-radio label="one_time">一次性</el-radio>
            <el-radio label="monthly">每月</el-radio>
            <el-radio label="yearly">每年</el-radio>
          </el-radio-group>
        </div>
        
        <div class="tier-section" v-if="tiers.length > 0">
          <h4>选择等级</h4>
          <el-select v-model="selectedTierId" placeholder="选择赞助等级" clearable>
            <el-option
              v-for="tier in tiers"
              :key="tier.id"
              :label="tier.name"
              :value="tier.id"
            />
          </el-select>
        </div>
        
        <div class="message-section">
          <h4>留言（可选）</h4>
          <el-input v-model="sponsorMessage" type="textarea" :rows="2" placeholder="给创作者留言..." />
        </div>
        
        <div class="anonymous-section">
          <el-checkbox v-model="isAnonymous">匿名赞助</el-checkbox>
        </div>
      </div>
      
      <template #footer>
        <el-button @click="showSponsorDialog = false">取消</el-button>
        <el-button type="primary" @click="submitSponsor" :loading="sponsoring">
          确认赞助 ¥{{ sponsorAmount }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 创建等级对话框 -->
    <el-dialog
      v-model="showCreateTierDialog"
      title="创建赞助等级"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form :model="newTier" label-width="80px">
        <el-form-item label="名称" required>
          <el-input v-model="newTier.name" placeholder="等级名称" />
        </el-form-item>
        
        <el-form-item label="描述">
          <el-input v-model="newTier.description" type="textarea" :rows="2" placeholder="等级描述" />
        </el-form-item>
        
        <el-form-item label="月费" required>
          <el-input-number v-model="newTier.monthly_amount" :min="1" :step="10" />
        </el-form-item>
        
        <el-form-item label="年费">
          <el-input-number v-model="newTier.yearly_amount" :min="1" :step="100" />
        </el-form-item>
        
        <el-form-item label="颜色">
          <el-color-picker v-model="newTier.color" />
        </el-form-item>
        
        <el-form-item label="图标">
          <el-select v-model="newTier.icon">
            <el-option label="❤️ 心" value="heart" />
            <el-option label="⭐ 星" value="star" />
            <el-option label="💎 钻石" value="diamond" />
            <el-option label="🚀 火箭" value="rocket" />
            <el-option label="🎉 庆祝" value="party" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="权益">
          <div class="benefits-input">
            <div v-for="(benefit, index) in newTier.benefits" :key="index" class="benefit-row">
              <el-input v-model="newTier.benefits[index]" placeholder="权益描述" />
              <el-button type="danger" @click="newTier.benefits.splice(index, 1)" circle>
                <el-icon><Delete /></el-icon>
              </el-button>
            </div>
            <el-button type="primary" plain @click="newTier.benefits.push('')">
              <el-icon><Plus /></el-icon> 添加权益
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateTierDialog = false">取消</el-button>
        <el-button type="primary" @click="createTier" :loading="creating">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Heart, Check, Delete } from '@element-plus/icons-vue'
import api from '@/api'

const route = useRoute()

// 状态
const loading = ref(false)
const sponsoring = ref(false)
const creating = ref(false)
const showSponsorDialog = ref(false)
const showCreateTierDialog = ref(false)

const tiers = ref<any[]>([])
const sponsors = ref<any[]>([])
const mySponsorships = ref<any[]>([])
const goals = ref<any[]>([])
const stats = ref<any>(null)

const selectedAmount = ref(30)
const customAmount = ref('')
const sponsorFrequency = ref('monthly')
const selectedTierId = ref<number | null>(null)
const sponsorMessage = ref('')
const isAnonymous = ref(false)

const newTier = reactive({
  name: '',
  description: '',
  monthly_amount: 10,
  yearly_amount: null as number | null,
  color: '#0366d6',
  icon: 'heart',
  benefits: [''] as string[]
})

// 计算属性
const userId = computed(() => Number(route.params.id) || 1)
const currentUserId = computed(() => {
  // 从 localStorage 获取当前用户 ID
  const userStr = localStorage.getItem('user')
  if (userStr) {
    try {
      const user = JSON.parse(userStr)
      return user.id
    } catch (e) {
      return null
    }
  }
  return null
})
const isMyProfile = computed(() => currentUserId.value === userId.value)
const isCreator = computed(() => {
  // 检查是否是创作者（有赞助等级或已开启赞助）
  return tiers.value.length > 0 || stats.value?.is_creator === true
})
const sponsorAmount = computed(() => {
  return customAmount.value ? Number(customAmount.value) : selectedAmount.value
})

// 加载数据
async function loadData() {
  loading.value = true
  try {
    // 加载赞助等级
    const tiersRes = await api.get('/sponsors/tiers', { params: { user_id: userId.value } })
    tiers.value = tiersRes.data.data
    
    // 加载赞助者
    const sponsorsRes = await api.get('/sponsors/my-sponsors')
    sponsors.value = sponsorsRes.data.data
    
    // 加载统计
    const statsRes = await api.get(`/sponsors/stats/${userId.value}`)
    stats.value = statsRes.data.data
    
    // 加载目标
    const goalsRes = await api.get('/sponsors/goals', { params: { user_id: userId.value } })
    goals.value = goalsRes.data.data
    
    // 加载我的赞助
    const myRes = await api.get('/sponsors/my-sponsorships')
    mySponsorships.value = myRes.data.data
  } catch (error) {
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 创建赞助等级
async function createTier() {
  if (!newTier.name) {
    ElMessage.warning('请输入等级名称')
    return
  }
  
  creating.value = true
  try {
    await api.post('/sponsors/tiers', {
      ...newTier,
      benefits: newTier.benefits.filter(b => b)
    })
    
    ElMessage.success('创建成功')
    showCreateTierDialog.value = false
    
    // 重置表单
    Object.assign(newTier, {
      name: '',
      description: '',
      monthly_amount: 10,
      yearly_amount: null,
      color: '#0366d6',
      icon: 'heart',
      benefits: ['']
    })
    
    loadData()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '创建失败')
  } finally {
    creating.value = false
  }
}

// 提交赞助
async function submitSponsor() {
  if (sponsorAmount.value <= 0) {
    ElMessage.warning('请选择或输入赞助金额')
    return
  }
  
  sponsoring.value = true
  try {
    await api.post('/sponsors', {
      creator_user_id: userId.value,
      tier_id: selectedTierId.value,
      amount: sponsorAmount.value,
      currency: 'CNY',
      frequency: sponsorFrequency.value,
      message: sponsorMessage.value,
      is_anonymous: isAnonymous.value ? 1 : 0
    })
    
    ElMessage.success('赞助成功！感谢您的支持 ❤️')
    showSponsorDialog.value = false
    
    // 重置表单
    selectedAmount.value = 30
    customAmount.value = ''
    sponsorFrequency.value = 'monthly'
    selectedTierId.value = null
    sponsorMessage.value = ''
    isAnonymous.value = false
    
    loadData()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '赞助失败')
  } finally {
    sponsoring.value = false
  }
}

// 取消赞助
async function cancelSponsorship(sponsorId: number) {
  try {
    await ElMessageBox.confirm('确定取消赞助？', '确认', { type: 'warning' })
    
    await api.post(`/sponsors/${sponsorId}/cancel`)
    
    ElMessage.success('已取消赞助')
    loadData()
  } catch (error) {
    // 用户取消
  }
}

// 选择等级
function selectTier(tier: any) {
  selectedTierId.value = tier.id
  selectedAmount.value = tier.monthly_amount
  showSponsorDialog.value = true
}

// 获取等级图标
function getTierIcon(icon: string): string {
  const icons: Record<string, string> = {
    heart: '❤️',
    star: '⭐',
    diamond: '💎',
    rocket: '🚀',
    party: '🎉'
  }
  return icons[icon] || '❤️'
}

// 格式化金额
function formatAmount(amount: number): string {
  if (!amount) return '0'
  return amount.toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

// 格式化日期
function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('zh-CN')
}

// 初始化
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.sponsors-page {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.subtitle {
  color: #666;
  margin: 4px 0 0;
}

.header-right {
  display: flex;
  gap: 12px;
}

/* 统计卡片 */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.stat-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 20px;
  color: #fff;
  text-align: center;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  opacity: 0.9;
}

/* 目标 */
.goals-section {
  margin-bottom: 32px;
}

.goals-section h3 {
  margin-bottom: 16px;
}

.goals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 16px;
}

.goal-card {
  background: #fff;
  border: 1px solid #e1e4e8;
  border-radius: 8px;
  padding: 16px;
}

.goal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.goal-header h4 {
  margin: 0;
}

.goal-desc {
  color: #666;
  font-size: 14px;
  margin-bottom: 12px;
}

.goal-amount {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
  font-size: 14px;
  color: #666;
}

/* 等级 */
.tiers-section {
  margin-bottom: 32px;
}

.tiers-section h3 {
  margin-bottom: 16px;
}

.tiers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.tier-card {
  background: #fff;
  border: 2px solid #e1e4e8;
  border-radius: 12px;
  padding: 24px;
  cursor: pointer;
  transition: all 0.2s;
}

.tier-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.tier-icon {
  font-size: 48px;
  text-align: center;
  margin-bottom: 12px;
}

.tier-card h4 {
  margin: 0 0 12px;
  text-align: center;
}

.tier-amount {
  text-align: center;
  margin-bottom: 12px;
}

.tier-amount .price {
  font-size: 32px;
  font-weight: 700;
  color: #0366d6;
}

.tier-amount .period {
  color: #666;
}

.tier-desc {
  color: #666;
  font-size: 14px;
  text-align: center;
  margin-bottom: 16px;
}

.tier-benefits {
  margin-bottom: 16px;
}

.benefit-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  font-size: 14px;
  border-bottom: 1px solid #f0f0f0;
}

.benefit-item:last-child {
  border-bottom: none;
}

.benefit-item .el-icon {
  color: #10b981;
}

.tier-sponsors {
  display: flex;
  align-items: center;
  gap: 4px;
  padding-top: 12px;
  border-top: 1px solid #f0f0f0;
}

.tier-sponsors span {
  font-size: 12px;
  color: #666;
  margin-left: 8px;
}

/* 赞助者列表 */
.sponsors-section {
  margin-bottom: 32px;
}

.sponsors-section h3 {
  margin-bottom: 16px;
}

.sponsors-list {
  background: #fff;
  border: 1px solid #e1e4e8;
  border-radius: 8px;
}

.sponsor-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.sponsor-item:last-child {
  border-bottom: none;
}

.sponsor-info {
  flex: 1;
}

.sponsor-name {
  font-weight: 600;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.sponsor-meta {
  font-size: 13px;
  color: #666;
  display: flex;
  gap: 16px;
}

.sponsor-message {
  margin-top: 8px;
  font-size: 14px;
  color: #666;
  font-style: italic;
}

/* 我的赞助 */
.my-sponsorships-section h3 {
  margin-bottom: 16px;
}

.sponsorships-list {
  background: #fff;
  border: 1px solid #e1e4e8;
  border-radius: 8px;
}

.sponsorship-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.sponsorship-item:last-child {
  border-bottom: none;
}

.sponsorship-info {
  flex: 1;
}

.sponsorship-name {
  font-weight: 600;
  margin-bottom: 4px;
}

.sponsorship-meta {
  font-size: 13px;
  color: #666;
  display: flex;
  gap: 12px;
}

/* 赞助表单 */
.sponsor-form h4 {
  margin: 0 0 12px;
}

.amount-section {
  margin-bottom: 20px;
}

.amount-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.frequency-section,
.tier-section,
.message-section,
.anonymous-section {
  margin-bottom: 20px;
}

/* 权益输入 */
.benefits-input {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.benefit-row {
  display: flex;
  gap: 8px;
}

.benefit-row .el-input {
  flex: 1;
}

/* 暗色主题 */
:root.dark .stat-card {
  background: linear-gradient(135deg, #1e3a5f 0%, #2d1b4e 100%);
}

:root.dark .goal-card,
:root.dark .tier-card,
:root.dark .sponsors-list,
:root.dark .sponsorships-list {
  background: #1c1c1e;
  border-color: #3a3a3c;
}

:root.dark .benefit-item {
  border-color: #3a3a3c;
}
</style>
