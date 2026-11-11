<template>
  <div class="title-row">
    <div>
      <h2 class="page-title">我的订单</h2>
      <p class="page-subtitle">待支付订单 15 分钟未支付会被定时任务关闭，并回滚商品/秒杀库存</p>
    </div>
    <el-button :loading="loading" @click="load">刷新</el-button>
  </div>

  <el-skeleton v-if="loading && !orders.length" :rows="5" animated />

  <div v-else-if="!orders.length" class="empty-tip">
    还没有订单
    <div style="margin-top: 14px">
      <el-button type="primary" @click="$router.push('/')">去下单</el-button>
    </div>
  </div>

  <div v-else class="order-list">
    <article v-for="order in orders" :key="order.id" class="surface order">
      <header class="order-head">
        <div>
          <span class="no">{{ order.order_no }}</span>
          <el-tag v-if="order.seckill_id" type="danger" size="small" effect="plain">秒杀</el-tag>
        </div>
        <el-tag :type="statusMeta(order.status).type" effect="light">{{ order.status_text }}</el-tag>
      </header>

      <div class="items">
        <div v-for="item in order.items" :key="`${order.id}-${item.goods_id}`" class="item">
          <el-image :src="item.cover" fit="cover" class="thumb">
            <template #error>
              <div class="thumb-fallback">{{ (item.title || '商').slice(0, 1) }}</div>
            </template>
          </el-image>
          <div class="item-main">
            <span class="name">{{ item.title }}</span>
            <span class="muted">¥{{ item.price }} × {{ item.num }}</span>
          </div>
        </div>
      </div>

      <footer class="order-foot">
        <div class="muted meta">
          下单时间 {{ order.created_at }}
          <template v-if="order.pay_time"> · 支付时间 {{ order.pay_time }}</template>
        </div>
        <div class="amount">
          <span class="muted">实付</span>
          <span class="price">¥{{ order.pay_amount }}</span>
        </div>
        <el-button
          v-if="order.status === 0"
          type="primary"
          :loading="payingNo === order.order_no"
          @click="handlePay(order)"
        >
          模拟支付
        </el-button>
      </footer>
    </article>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getOrders, payOrder } from '../api'

const orders = ref([])
const loading = ref(false)
const payingNo = ref('')

const statusMeta = (status) => {
  const map = {
    0: { type: 'warning' },
    1: { type: 'success' },
    2: { type: 'info' },
  }
  return map[status] || { type: 'info' }
}

const load = async () => {
  loading.value = true
  try {
    orders.value = await getOrders()
  } finally {
    loading.value = false
  }
}

const handlePay = async (order) => {
  payingNo.value = order.order_no
  try {
    await payOrder(order.order_no)
    ElMessage.success('支付成功（重复点击也会返回成功，不会重复扣减）')
    await load()
  } finally {
    payingNo.value = ''
  }
}

onMounted(load)
</script>

<style scoped>
.title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.order-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.order-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid #f2f3f7;
}

.no {
  font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
  font-size: 13px;
  margin-right: 8px;
}

.items {
  display: flex;
  flex-direction: column;
}

.item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px dashed #f0f1f5;
}

.item:last-child {
  border-bottom: none;
}

.thumb {
  width: 56px;
  height: 56px;
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

.item-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.name {
  font-size: 14px;
}

.order-foot {
  display: flex;
  align-items: center;
  gap: 20px;
  padding-top: 14px;
  border-top: 1px solid #f2f3f7;
}

.meta {
  font-size: 12px;
  flex: 1;
}

.amount {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 13px;
}

.amount .price {
  font-size: 22px;
}

@media (max-width: 760px) {
  .order-foot {
    flex-wrap: wrap;
  }

  .meta {
    width: 100%;
  }
}
</style>
