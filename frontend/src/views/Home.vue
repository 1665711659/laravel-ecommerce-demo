<template>
  <section>
    <div class="hero">
      <div>
        <h1>把库存扣减做成一件严谨的事</h1>
        <p>Redis 缓存三件套 · Lua 原子预扣 · 令牌桶限流 · 一人一单 · 订单幂等</p>
      </div>
      <div class="hero-side">
        <span>今日秒杀</span>
        <strong>¥9.9 起</strong>
        <el-button size="small" @click="$router.push('/seckill')">去抢购</el-button>
      </div>
    </div>

    <div class="list-head">
      <h2 class="page-title">精选商品</h2>
      <span class="muted">共 {{ goods.length }} 件</span>
    </div>

    <el-skeleton v-if="loading" :rows="6" animated />

    <el-row v-else :gutter="18">
      <el-col v-for="item in goods" :key="item.id" :xs="24" :sm="12" :md="8" :lg="6">
        <article class="goods-card" @click="$router.push(`/goods/${item.id}`)">
          <div class="cover">
            <el-image :src="item.cover" fit="cover" lazy>
              <template #error>
                <div class="cover-fallback">{{ item.title.slice(0, 2) }}</div>
              </template>
            </el-image>
            <span v-if="item.seckill" class="seckill-badge">秒杀 ¥{{ item.seckill.seckill_price }}</span>
          </div>

          <div class="goods-body">
            <h3>{{ item.title }}</h3>
            <p class="desc">{{ item.desc }}</p>
            <div class="goods-foot">
              <span class="price"><small>¥</small>{{ item.seckill ? item.seckill.seckill_price : item.price }}</span>
              <span class="muted stock">库存 {{ item.stock }}</span>
            </div>

            <div class="actions" @click.stop>
              <el-button size="small" @click="handleAdd(item)">加入购物车</el-button>
              <el-button size="small" type="primary" @click="handleBuyNow(item)">立即购买</el-button>
            </div>
          </div>
        </article>
      </el-col>
    </el-row>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { addCart, getGoodsList } from '../api'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const goods = ref([])
const loading = ref(true)

const needLogin = () => {
  if (auth.isLogin) return false
  router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
  return true
}

const load = async () => {
  loading.value = true
  try {
    goods.value = await getGoodsList()
  } finally {
    loading.value = false
  }
}

const handleAdd = async (item) => {
  if (needLogin()) return
  await addCart({ goods_id: item.id, num: 1 })
  ElMessage.success('已加入购物车')
}

const handleBuyNow = async (item) => {
  if (needLogin()) return
  const data = await addCart({ goods_id: item.id, num: 1 })
  router.push({ path: '/checkout', query: { ids: data.id } })
}

onMounted(load)
</script>

<style scoped>
.hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 34px 34px;
  margin-bottom: 28px;
  border-radius: 20px;
  background: radial-gradient(circle at 15% 20%, #ff7a3d 0, #ff5000 38%, #c93a00 100%);
  color: #fff;
  box-shadow: 0 18px 40px -24px rgba(255, 80, 0, 0.8);
}

.hero h1 {
  margin: 0 0 10px;
  font-size: 28px;
  letter-spacing: -0.5px;
}

.hero p {
  margin: 0;
  opacity: 0.88;
  font-size: 13px;
}

.hero-side {
  flex-shrink: 0;
  padding: 14px 20px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.16);
  text-align: center;
}

.hero-side span {
  display: block;
  font-size: 12px;
  opacity: 0.85;
}

.hero-side strong {
  display: block;
  margin: 4px 0 10px;
  font-size: 22px;
}

.list-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 14px;
}

.goods-card {
  height: 100%;
  margin-bottom: 18px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.goods-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 30px -22px rgba(23, 32, 51, 0.55);
}

.cover {
  position: relative;
  aspect-ratio: 1 / 0.78;
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
  font-size: 30px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #ff8a5b, #ff5000);
}

.seckill-badge {
  position: absolute;
  left: 10px;
  top: 10px;
  padding: 3px 8px;
  border-radius: 999px;
  background: rgba(23, 32, 51, 0.82);
  color: #ffd8a8;
  font-size: 11px;
}

.goods-body {
  padding: 14px;
}

.goods-body h3 {
  margin: 0 0 6px;
  font-size: 15px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.desc {
  margin: 0 0 10px;
  height: 34px;
  overflow: hidden;
  font-size: 12px;
  color: var(--muted);
}

.goods-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.price {
  font-size: 20px;
}

.stock {
  font-size: 12px;
}

.actions {
  display: flex;
  gap: 8px;
}

.actions .el-button {
  flex: 1;
}

@media (max-width: 760px) {
  .hero {
    flex-direction: column;
    align-items: flex-start;
    padding: 26px 22px;
  }

  .hero h1 {
    font-size: 22px;
  }
}
</style>
