<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="header-inner">
        <router-link to="/" class="brand">
          <span class="brand-mark">S</span>
          <span>
            <strong>Seckill Mall</strong>
            <small>Laravel 10 · Vue 3 电商 Demo</small>
          </span>
        </router-link>

        <nav class="nav">
          <router-link to="/">首页</router-link>
          <router-link to="/seckill" class="seckill-link">秒杀专区</router-link>
          <router-link to="/cart">购物车</router-link>
          <router-link to="/orders">我的订单</router-link>
        </nav>

        <div class="account">
          <template v-if="auth.isLogin">
            <span class="nickname">{{ auth.nickname }}</span>
            <el-button size="small" text @click="handleLogout">退出</el-button>
          </template>
          <template v-else>
            <el-button type="primary" size="small" @click="$router.push('/login')">登录</el-button>
          </template>
        </div>
      </div>
    </header>

    <main class="page">
      <router-view />
    </main>

    <footer class="app-footer">
      面试 Demo：Controller → Service → Repository 分层 · Redis 缓存与秒杀防超卖 · 订单幂等与超时关闭
    </footer>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const router = useRouter()
const auth = useAuthStore()

const handleLogout = () => {
  auth.logout()
  router.push('/')
}
</script>
