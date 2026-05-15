<template>
  <div class="loading-spinner-wrap">
    <div ref="container" class="lottie-container"></div>
    <Transition name="fade" mode="out-in">
      <p :key="currentText" class="loading-text">{{ currentText }}</p>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import lottie from 'lottie-web/build/player/lottie_light'
import animationData from '../assets/loading.json'

const messages = [
  'Loading…',
  'Fetching data…',
  'Almost there…',
  'Hang tight…',
  'Just a moment…',
]

const container = ref(null)
const currentText = ref(messages[0])
let anim = null
let msgIndex = 0
let interval = null

onMounted(() => {
  anim = lottie.loadAnimation({
    container: container.value,
    renderer: 'svg',
    loop: true,
    autoplay: true,
    animationData,
  })

  interval = setInterval(() => {
    msgIndex = (msgIndex + 1) % messages.length
    currentText.value = messages[msgIndex]
  }, 2000)
})

onBeforeUnmount(() => {
  anim?.destroy()
  clearInterval(interval)
})
</script>

<style scoped>
.loading-spinner-wrap {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 48px 0;
  gap: 4px;
}
.lottie-container {
  width: 100px;
  height: 100px;
}
.loading-text {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
