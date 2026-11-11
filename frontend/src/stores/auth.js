import { defineStore } from 'pinia'

const TOKEN_KEY = 'demo_token'
const USER_KEY = 'demo_user'

/**
 * 登录态持久化：token 存 localStorage，刷新页面后仍然登录。
 * 生产环境建议用 httpOnly Cookie 存 refresh token，降低 XSS 风险。
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY) || '',
    user: JSON.parse(localStorage.getItem(USER_KEY) || 'null'),
  }),
  getters: {
    isLogin: (state) => Boolean(state.token),
    nickname: (state) => state.user?.nickname || '未登录',
  },
  actions: {
    setAuth({ token, user }) {
      this.token = token
      this.user = user
      localStorage.setItem(TOKEN_KEY, token)
      localStorage.setItem(USER_KEY, JSON.stringify(user))
    },
    logout() {
      this.token = ''
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(USER_KEY)
    },
  },
})
