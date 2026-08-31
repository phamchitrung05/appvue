<script setup>
definePage({
  redirect: () => ({ path: '/apps/ecommerce/cashier' }),
})

const router = useRouter()

// Phương án dự phòng: nếu route-level redirect chưa có tác dụng thì
// chuyển hướng ngay khi component mount
onMounted(() => {
  router.replace('/apps/ecommerce/cashier')
})

// Trang sơ đồ bàn đã chuyển sang apps/ecommerce/cashier —
// giữ lại route cũ (chỉ chuyển hướng) để link "Order > List" trên sidebar
// vẫn hoạt động, không lỗi 404.
</script>

<template>
  <div />
</template>
