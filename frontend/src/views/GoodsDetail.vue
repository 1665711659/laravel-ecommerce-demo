<template>
  <el-skeleton v-if="loading" :rows="8" animated />

  <div v-else-if="goods" class="detail">
    <div class="gallery">
      <el-image :src="goods.cover" fit="cover">
        <template #error>
          <div class="cover-fallback">{{ goods.title.slice(0, 2) }}</div>
        </template>
      </el-image>
    </div>

    <div class="info surface">
      <el-tag v-if="seckill" type="danger" effect="dark" size="small">限时秒杀</el-tag>
      <h1>{{ goods.title }}</h1>
      <p class="muted">{{ goods.desc }}</p>

      <div class="price-row">
        <span class="price"><small>¥</small>{{ seckill ? seckill.seckill_price : goods.price }}</span>
        <span v-if="seckill" class="origin">原价 ¥{{ goods.price }}</span>
        <span class="muted">库存 {{ goods.stock }}</span>
      </div>

      <div v-if="seckill" class="seckill-panel">
        <div class="seckill-head">
          <strong>秒杀进行中</strong>
          <span class="muted">剩余 {{ seckill.remaining }} 件</span>
        </div>
        <div class="seckill-time">
          <template v-if="windowState === 'not_started'">
            <span>距开始</span>
            <Countdown :target="seckill.start_time" prefix="" @finish="reload" />
          </template>
          <template v-else-if="windowState === 'running'">
            <span>距结束</span>
            <Countdown :target="seckill.end_time" prefix="" @finish="reload" />
          </template>
          <template v-else>
            <span>本场活动已结束</span>
          </template>
        </div>
        <el-button
          type="danger"
          size="large"
          :loading="buying"
          :disabled="windowState !== 'running' || seckill.remaining <= 0"
          @click="handleSeckill"
        >
          {{ seckill.remaining <= 0 ? '已售罄' : windowState === 'running' ? '立即抢购' : '活动未开始' }}
        </el-button>
      </div>

      <div class="buy-row">
        <el-input-number v-model="num" :min="1" :max="Math.max(goods.stock, 1)" />
        <el-button size="large" @click="handleAdd">加入购物车</el-button>
        <el-button size="large" type="primary" @click="handleBuyNow">立即购买</el-button>
      </div>

      <el-alert
        title="接口链路：GET /api/goods/{id} → 缓存穿透用空值缓存、击穿用 SETNX 互斥重建、雪崩用 TTL 随机偏移"
        type="info"
        :closable="false"
        show-icon
      />
    </div>
  </div>

  <el-empty v-else description="商品不存在或已下架" />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import Countdown from '../components/Countdown.vue'
import { addCart, buySeckill, getGoodsDetail, getSeckillDetail } from '../api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const goods = ref(null)
const seckill = ref(null)
const loading = ref(true)
const buying = ref(false)
const num = ref(1)
const now = ref(Date.now())
let timer = null

const windowState = computed(() => {
  if (!seckill.value) return 'none'
  const start = new Date(seckill.value.start_time.replace(/-/g, '/')).getTime()
  const end = new Date(seckill.value.end_time.replace(/-/g, '/')).getTime()
  if (now.value < start) return 'not_started'
  if (now.value > end) return 'ended'
  return 'running'
})

const needLogin = () => {
  if (auth.isLogin) return false
  router.push({ path: '/login', query: { redirect: route.fullPath } })
  return true
}

const load = async () => {
  loading.value = true
  try {
    const data = await getGoodsDetail(route.params.id)
    goods.value = data.goods
    seckill.value = data.seckill ? await getSeckillDetail(data.seckill.id) : null
  } finally {
    loading.value = false
  }
}

const reload = () => load()

const handleAdd = async () => {
  if (needLogin()) return
  await addCart({ goods_id: goods.value.id, num: num.value })
  ElMessage.success('已加入购物车')
}

const handleBuyNow = async () => {
  if (needLogin()) return
  const data = await addCart({ goods_id: goods.value.id, num: num.value })
  router.push({ path: '/checkout', query: { ids: data.id } })
}

const handleSeckill = async () => {
  if (needLogin()) return
  buying.value = true
  try {
    const result = await buySeckill(seckill.value.id)
    ElMessageBox.alert(
      `订单号：${result.order_no}，剩余库存 ${result.remaining} 件。
请到「我的订单」完成支付，15 分钟未支付会自动关闭并回滚库存。`,
      '抢购成功',
      { confirmButtonText: '去支付' }
    ).then(() => router.push('/orders'))
    await load()
  } catch (e) {
    // 售罄/重复购买/限流由拦截器统一提示，这里只需刷新剩余库存
    await load()
  } finally {
    buying.value = false
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
.detail {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
  gap: 24px;
}

.gallery {
  aspect-ratio: 1;
  border-radius: 18px;
  overflow: hidden;
  background: #f2f4f9;
}

.gallery :deep(.el-image) {
  width: 100%;
  height: 100%;
}

.cover-fallback {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  font-size: 48px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #ff8a5b, #ff5000);
}

.info h1 {
  margin: 12px 0 8px;
  font-size: 26px;
  letter-spacing: -0.4px;
}

.price-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin: 18px 0;
}

.price {
  font-size: 32px;
}

.origin {
  color: var(--muted);
  text-decoration: line-through;
  font-size: 13px;
}

.seckill-panel {
  margin: 18px 0;
  padding: 16px;
  border-radius: 12px;
  background: #fff7f2;
  border: 1px solid #ffddcc;
}

.seckill-head {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 8px;
}

.seckill-head strong {
  color: var(--brand);
}

.seckill-time {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 12px;
  font-size: 13px;
  color: var(--muted);
}

.seckill-time .countdown {
  color: var(--brand);
  font-size: 18px;
}

.buy-row {
  display: flex;
  gap: 10px;
  margin: 20px 0;
  flex-wrap: wrap;
}

@media (max-width: 860px) {
  .detail {
    grid-template-columns: 1fr;
  }
}
</style>
