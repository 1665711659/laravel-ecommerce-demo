import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', name: 'home', component: () => import('../views/Home.vue') },
  { path: '/goods/:id', name: 'goods-detail', component: () => import('../views/GoodsDetail.vue') },
  { path: '/seckill', name: 'seckill', component: () => import('../views/Seckill.vue') },
  { path: '/login', name: 'login', component: () => import('../views/Login.vue') },
  {
    path: '/cart',
    name: 'cart',
    component: () => import('../views/Cart.vue'),
    meta: { auth: true },
  },
  {
    path: '/checkout',
    name: 'checkout',
    component: () => import('../views/Checkout.vue'),
    meta: { auth: true },
  },
  {
    path: '/orders',
    name: 'orders',
    component: () => import('../views/Orders.vue'),
    meta: { auth: true },
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.auth && !auth.isLogin) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (to.path === '/login' && auth.isLogin) {
    return '/'
  }
})

export default router
