import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '../stores/auth'

const request = axios.create({
  baseURL: '/api',
  timeout: 15000,
})

// 统一注入 Bearer Token
request.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

/**
 * 统一处理 {code, message, data}：
 * - code=0 直接返回 data，页面不用每次解包；
 * - code!=0 统一弹提示，并把错误继续抛给调用方（重试/状态展示需要）；
 * - 401 清理登录态并跳登录页。
 */
request.interceptors.response.use(
  (response) => {
    const body = response.data

    if (!body || typeof body.code === 'undefined') {
      return body
    }

    if (body.code === 0) {
      return body.data
    }

    if (body.code === 1002) {
      useAuthStore().logout()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }

    ElMessage.error(body.message || '请求失败')
    return Promise.reject(Object.assign(new Error(body.message || '请求失败'), { code: body.code }))
  },
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore().logout()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    const message = error.response?.data?.message || error.message || '网络异常'
    ElMessage.error(message)
    return Promise.reject(error)
  }
)

export default request
