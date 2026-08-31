<script setup>
import { useToasts } from '@/composables/useToasts'

// Host duy nhất hiển thị toast cho cả app — gắn 1 lần trong layout.
// Vị trí góc TRÊN PHẢI: location="top end". Đổi kiểu hiển thị sửa ở đây.
const { toasts, dismissToast } = useToasts()
</script>

<template>
  <VSnackbar
    v-for="toast in toasts"
    :key="toast.id"
    model-value
    :color="toast.color"
    location="top end"
    multi-line
    variant="elevated"
    @update:model-value="dismissToast(toast.id)"
  >
    {{ toast.message }}

    <template #actions>
      <IconBtn
        aria-label="Đóng thông báo"
        @click="dismissToast(toast.id)"
      >
        <VIcon icon="tabler-x" />
      </IconBtn>
    </template>
  </VSnackbar>
</template>
