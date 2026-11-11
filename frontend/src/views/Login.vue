<template>
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-head">
        <span class="brand-mark">S</span>
        <div>
          <h1>登录 Seckill Mall</h1>
          <p>手机号 + 密码，登录后获取 Sanctum Token</p>
        </div>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="form.phone" maxlength="11" placeholder="请输入手机号" />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input v-model="form.password" type="password" show-password placeholder="请输入密码" @keyup.enter="submit" />
        </el-form-item>
        <el-button type="primary" size="large" class="submit" :loading="loading" @click="submit">
          登录
        </el-button>
      </el-form>

      <div class="hint">
        测试账号：<code>13800138000</code> / <code>123456</code>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { login } from '../api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const formRef = ref()
const loading = ref(false)
const form = reactive({ phone: '13800138000', password: '123456' })

const rules = {
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' },
  ],
  password: [{ required: true, min: 6, message: '密码至少 6 位', trigger: 'blur' }],
}

const submit = async () => {
  await formRef.value.validate()
  loading.value = true
  try {
    const data = await login({ phone: form.phone, password: form.password })
    auth.setAuth(data)
    ElMessage.success('登录成功')
    router.replace(String(route.query.redirect || '/'))
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-wrap {
  display: flex;
  justify-content: center;
  padding: 40px 0 20px;
}

.login-card {
  width: min(420px, 100%);
  padding: 30px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 18px;
  box-shadow: 0 24px 60px -46px rgba(23, 32, 51, 0.7);
}

.login-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 22px;
}

.brand-mark {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--brand), #ff9a00);
  color: #fff;
  font-weight: 800;
  flex-shrink: 0;
}

.login-head h1 {
  margin: 0 0 4px;
  font-size: 19px;
}

.login-head p {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.submit {
  width: 100%;
  margin-top: 4px;
}

.hint {
  margin-top: 16px;
  text-align: center;
  font-size: 12px;
  color: var(--muted);
}

.hint code {
  padding: 1px 5px;
  border-radius: 4px;
  background: #f3f4f8;
}
</style>
