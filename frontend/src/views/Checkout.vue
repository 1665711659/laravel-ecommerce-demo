<template>
  <h2 class="page-title">确认订单</h2>
  <p class="page-subtitle">提交时携带 X-Request-Id：网络重试/连点都会命中同一个幂等 token，只生成一笔订单</p>

  <el-skeleton v-if="loading" :rows="5" animated />

  <div v-else-if="preview" class="checkout">
    <div class="surface">
      <h3 class="block-title">商品清单</h3>
      <div v-for="item in preview.items" :key="item.cart_id" class="line">
        <el-image :src="item.cover" fit="cover" class="thumb">
          <template #error>
            <div class="thumb-fallback">{{ item.title.slice(0, 1) }}</div>
          </template>
        </el-image>
        <div class="line-main">
          <span class="name">{{ item.title }}</span>
          <span class="muted">¥{{ item.price }} × {{ item.num }}</span>
        </div>
        <span class="price">¥{{ item.subtotal }}</span>
      </div>
    </div>

    <aside class="surface summary">
      <h3 class="block-title">订单摘要</h3>
      <div class="summary-row">
        <span class="muted">商品件数</span>
        <span>{{ totalNum }} 件</span>
      </div>
      <div class="summary-row">
        <span class="muted">应付金额</span>
        <span class="price total">¥{{ preview.total_amount }}</span>
      </div>
      <div class="token">
        <span class="muted">幂等 Token</span>
        <code>{{ requestId }}</code>
      </div>
      <el-button type="primary" size="large" class="submit" :loading="submitting" @click="submit">
        提交订单
      </el-button>
      <el-button size="large" class="back" @click="$router.push('/cart')">返回购物车</el-button>
    </aside>
  </div>

  <el-empty v-else description="没有待结算的商品" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { createOrder, previewCart } from '../api'

const route = useRoute()
const router = useRouter()

const preview = ref(null)
const loading = ref(true)
const submitting = ref(false)

const randomToken = () => {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID()
  return `rid-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

// 同一个结算页实例内保持 token 不变：失败重试、用户连点都复用同一个幂等键
const requestId = ref(randomToken())

const cartIds = computed(() =>
  String(route.query.ids || '')
    .split(',')
    .map((id) => Number(id))
    .filter((id) => Number.isInteger(id) && id > 0)
)

const totalNum = computed(() => preview.value?.items.reduce((sum, item) => sum + item.num, 0) || 0)

const load = async () => {
  loading.value = true
  try {
    preview.value = await previewCart(cartIds.value)
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  submitting.value = true
  try {
    const order = await createOrder(preview.value.cart_ids, requestId.value)
    await ElMessageBox.alert(
      `订单号：${order.order_no}\n订单金额：¥${order.pay_amount}\n状态：${order.status_text}`,
      '下单成功',
      { confirmButtonText: '去支付' }
    )
    router.replace('/orders')
  } catch (e) {
    // 失败时保留同一个 requestId，用户重试不会重复下单
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.checkout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 20px;
  align-items: start;
}

.block-title {
  margin: 0 0 14px;
  font-size: 16px;
}

.line {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f2f3f7;
}

.line:last-child {
  border-bottom: none;
}

.thumb {
  width: 58px;
  height: 58px;
  border-radius: 10px;
  overflow: hidden;
  background: #f2f4f9;
  flex-shrink: 0;
}

.thumb-fallback {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  color: #fff;
  background: linear-gradient(135deg, #ff8a5b, #ff5000);
  font-weight: 700;
}

.line-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.name {
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.summary {
  position: sticky;
  top: 86px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 12px;
}

.total {
  font-size: 26px;
}

.token {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  margin: 16px 0;
  background: #f7f8fc;
  border-radius: 10px;
  font-size: 12px;
}

.token code {
  word-break: break-all;
  color: #59617a;
}

.submit,
.back {
  width: 100%;
  margin: 0 0 10px;
}

@media (max-width: 860px) {
  .checkout {
    grid-template-columns: 1fr;
  }
}
</style>
