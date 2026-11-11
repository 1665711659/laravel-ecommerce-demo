<template>
  <h2 class="page-title">购物车</h2>
  <p class="page-subtitle">勾选、改数量、删除都会实时同步到后端；结算时只提交选中的 cart_id</p>

  <el-skeleton v-if="loading" :rows="5" animated />

  <div v-else-if="!items.length" class="empty-tip">
    购物车还是空的
    <div style="margin-top: 14px">
      <el-button type="primary" @click="$router.push('/')">去逛逛</el-button>
    </div>
  </div>

  <template v-else>
    <div class="surface cart">
      <div class="cart-head">
        <span></span>
        <span>商品</span>
        <span>单价</span>
        <span>数量</span>
        <span>小计</span>
        <span>操作</span>
      </div>

      <div v-for="item in items" :key="item.id" class="cart-row">
        <el-checkbox
          :model-value="item.is_checked === 1"
          :disabled="item.status !== 1"
          @change="(val) => toggleChecked(item, val)"
        />

        <div class="goods">
          <el-image :src="item.cover" fit="cover" class="thumb">
            <template #error>
              <div class="thumb-fallback">{{ (item.title || '商品').slice(0, 1) }}</div>
            </template>
          </el-image>
          <div>
            <router-link :to="`/goods/${item.goods_id}`" class="name">{{ item.title }}</router-link>
            <span class="muted stock">库存 {{ item.stock }}</span>
            <el-tag v-if="item.status !== 1" type="info" size="small">已下架</el-tag>
          </div>
        </div>

        <span>¥{{ item.price }}</span>

        <el-input-number
          v-model="item.num"
          :min="1"
          :max="Math.max(item.stock, 1)"
          size="small"
          @change="changeNum(item)"
        />

        <span class="price">¥{{ item.subtotal }}</span>

        <el-button text type="danger" @click="remove(item)">删除</el-button>
      </div>
    </div>

    <div class="cart-foot">
      <span class="muted">已选 {{ checkedItems.length }} 件</span>
      <span>
        合计 <b class="price total">¥{{ total }}</b>
      </span>
      <el-button type="primary" size="large" :disabled="!checkedItems.length" @click="checkout">
        去结算
      </el-button>
    </div>
  </template>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { deleteCart, getCart, updateCart } from '../api'

const router = useRouter()
const items = ref([])
const loading = ref(true)

const checkedItems = computed(() => items.value.filter((item) => item.is_checked === 1 && item.status === 1))
const total = computed(() =>
  checkedItems.value.reduce((sum, item) => sum + Number(item.subtotal || 0), 0).toFixed(2)
)

const load = async () => {
  loading.value = true
  try {
    const data = await getCart()
    items.value = data.items
  } finally {
    loading.value = false
  }
}

const toggleChecked = async (item, checked) => {
  await updateCart(item.id, { is_checked: checked })
  await load()
}

const changeNum = async (item) => {
  try {
    await updateCart(item.id, { num: item.num })
    await load()
  } catch (e) {
    await load()
  }
}

const remove = async (item) => {
  await ElMessageBox.confirm(`确定删除「${item.title}」吗？`, '提示', { type: 'warning' })
  await deleteCart(item.id)
  ElMessage.success('已删除')
  await load()
}

const checkout = () => {
  const ids = checkedItems.value.map((item) => item.id)
  router.push({ path: '/checkout', query: { ids: ids.join(',') } })
}

onMounted(load)
</script>

<style scoped>
.cart-head,
.cart-row {
  display: grid;
  grid-template-columns: 34px minmax(0, 2.6fr) 0.8fr 1.1fr 0.8fr 0.7fr;
  align-items: center;
  gap: 12px;
}

.cart-head {
  padding-bottom: 12px;
  border-bottom: 1px solid var(--line);
  color: var(--muted);
  font-size: 12px;
}

.cart-row {
  padding: 16px 0;
  border-bottom: 1px solid #f2f3f7;
}

.cart-row:last-child {
  border-bottom: none;
}

.goods {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.thumb {
  width: 64px;
  height: 64px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
  background: #f2f4f9;
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

.name {
  display: block;
  font-size: 14px;
  margin-bottom: 4px;
}

.name:hover {
  color: var(--brand);
}

.stock {
  display: block;
  font-size: 12px;
}

.cart-foot {
  position: sticky;
  bottom: 16px;
  margin-top: 16px;
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 22px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: 0 12px 28px -26px rgba(23, 32, 51, 0.6);
}

.total {
  font-size: 24px;
}

@media (max-width: 860px) {
  .cart-head {
    display: none;
  }

  .cart-row {
    grid-template-columns: 34px minmax(0, 1fr);
    row-gap: 10px;
  }
}
</style>
