<template>
  <span class="countdown">{{ display }}</span>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  target: { type: String, required: true },
  prefix: { type: String, default: '' },
})

const emit = defineEmits(['finish'])
const now = ref(Date.now())
let timer = null

// 后端返回 "YYYY-MM-DD HH:mm:ss"（东八区），替换分隔符兼容 Safari
const targetTime = () => new Date(String(props.target).replace(/-/g, '/')).getTime()

const remainSeconds = computed(() => Math.max(0, Math.floor((targetTime() - now.value) / 1000)))

const display = computed(() => {
  const seconds = remainSeconds.value
  const h = String(Math.floor(seconds / 3600)).padStart(2, '0')
  const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0')
  const s = String(seconds % 60).padStart(2, '0')

  return `${props.prefix}${h}:${m}:${s}`
})

watch(remainSeconds, (value) => {
  if (value === 0) {
    emit('finish')
  }
})

onMounted(() => {
  timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>
