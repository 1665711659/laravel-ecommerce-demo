<template>
  <div class="seckill-hero">
    <div>
      <span class="tag">FLASH SALE</span>
      <h1>限时秒杀</h1>
      <p>令牌桶限流 → Redis Lua 原子预扣 → MySQL 条件更新兜底 → 15 分钟未支付自动关单</p>
    </div>
  </div>

  <el-skeleton v-if="loading" :rows="5" animated />

  <div v-else-if="!activities.length" class="empty-tip">
    当前没有进行中的秒杀活动
    <div style="margin-top: 14px">
      <el-button type="primary" @click="$router.push('/')">返回首页</el-button>
    </div>
  </div>

  <div v-else class="cards">
    <article v-for="activity in activities" :key="activity.id" class="card">
      <div class="cover">
        <el-image :src="activity.goods?.cover" fit="cover">
          <template #error>
            <div class="cover-fallback">{{ (activity.goods?.title || '秒杀').slice(0, 2) }}</div>
          </template>
        </el-image>
        <span class="badge">秒杀价 ¥{{ activity.seckill_price }}</span>
      </div>

      <div class="body">
        <h3>{{ activity.goods?.title }}</h3>
        <div class="prices">
          <span class="price"><small>¥</small>{{ activity.seckill_price }}</span>
          <span class="origin">¥{{ activity.goods?.price }}</span>
        </div>

        <div class="meta">
          <span>剩余 <b>{{ activity.remaining }}</b> 件</span>
          <span class="muted">原价库存 {{ activity.goods?.stock }}</span>
        </div>

        <div class="time">
          <template v-if="stateOf(activity) === 'not_started'">
            距开始 <Countdown :target="activity.start_time" @finish="load" />
          </template>
          <template v-else-if="stateOf(activity) === 'running'">
            距结束 <Countdown :target="activity.end_time" @finish="load" />
          </template>
          <template v-else>本场已结束</template>
        </div>

        <el-button
          type="danger"
          size="large"
          class="buy"
          :loading="buyingId === activity.id"
          :disabled="stateOf(activity) !== 'running' || activity.remaining <= 0"
          @click="handleBuy(activity)"
        >
          {{ activity.remaining <= 0 ? '已售罄' : stateOf(activity) === 'running' ? '立即抢购' : '未开始' }}
        </el-button>

        <p class="hint">同一用户限购 1 件 · 抢购成功请 15 分钟内支付</p>
      </div>
    </article>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import Countdown from '../components/Countdown.vue'
import { buySeckill, getSeckillList } from '../api'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const activities = ref([])
const loading = ref(true)
const buyingId = ref(null)
const now = ref(Date.now())
let timer = null

const parseTime = (value) => new Date(String(value).replace(/-/g, '/')).getTime()

const stateOf = (activity) => {
  if (now.value < parseTime(activity.start_time)) return 'not_started'
  if (now.value > parseTime(activity.end_time)) return 'ended'
  return 'running'
}

const load = async () => {
  loading.value = true
  try {
    activities.value = await getSeckillList()
  } finally {
    loading.value = false
  }
}

const handleBuy = async (activity) => {
  if (!auth.isLogin) {
    router.push({ path: '/login', query: { redirect: '/seckill' } })
    return
  }

  buyingId.value = activity.id
  try {
    const result = await buySeckill(activity.id)
    ElMessageBox.alert(
      `订单号：${result.order_no}\n订单金额：¥${result.pay_amount}\n剩余库存：${result.remaining} 件`,
      '抢购成功',
      { confirmButtonText: '去支付' }
    ).then(() => router.push('/orders'))
  } catch (e) {
    // 售罄 / 重复购买 / 限流：拦截器已提示，这里只需刷新剩余库存
  } finally {
    buyingId.value = null
    await load()
  }
}

onMounted(() => {
  load()
  timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.seckill-hero {
  padding: 30px 34px;
  margin-bottom: 24px;
  border-radius: 20px;
  color: #fff;
  background: linear-gradient(120deg, #181f31 0%, #31223a 55%, #7a1f11 100%);
}

.tag {
  font-size: 11px;
  letter-spacing: 2px;
  color: #ffb08a;
}

.seckill-hero h1 {
  margin: 6px 0 10px;
  font-size: 30px;
}

.seckill-hero p {
  margin: 0;
  font-size: 13px;
  opacity: 0.72;
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
  gap: 20px;
}

.card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.cover {
  position: relative;
  aspect-ratio: 16 / 10;
  background: #f2f4f9;
}

.cover :deep(.el-image) {
  width: 100%;
  height: 100%;
}

.cover-fallback {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  font-size: 34px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #ff8a5b, #ff5000);
}

.badge {
  position: absolute;
  left: 12px;
  top: 12px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(23, 32, 51, 0.82);
  color: #ffcf9e;
  font-size: 12px;
}

.body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.body h3 {
  margin: 0;
  font-size: 16px;
}

.prices {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.prices .price {
  font-size: 28px;
}

.origin {
  color: var(--muted);
  text-decoration: line-through;
  font-size: 13px;
}

.meta {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.meta b {
  color: var(--brand);
}

.time {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted);
}

.time .countdown {
  color: var(--brand);
  font-size: 18px;
}

.buy {
  width: 100%;
}

.hint {
  margin: 0;
  text-align: center;
  font-size: 11px;
  color: var(--muted);
}
</style>
