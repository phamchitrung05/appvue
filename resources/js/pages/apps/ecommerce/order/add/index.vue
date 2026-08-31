<script setup>
import { TABLE_STATUSES, tableStatusMeta } from '@/utils/tableStatuses'

const router = useRouter()
const route = useRoute()

// ==================== Bàn đang order ====================
// Đọc ?table=<id> từ sơ đồ bàn, tra tên + trạng thái qua API floor
const { data: floorData } = await useApi('/v1/dining-tables/floor')

const table = computed(() => {
  const tableId = Number(route.query.table)

  for (const zone of (floorData.value?.zones ?? [])) {
    const found = zone.tables.find(item => item.id === tableId)
    if (found)
      return found
  }

  return null
})

// Đồng hồ giờ hiện tại (HH:MM) trên tiêu đề
const now = useNow({ interval: 30000 })

const currentTime = computed(() =>
  now.value.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false }))

// ==================== Danh mục nhóm hàng ====================
const { data: groupsData } = await useApi(createUrl('/v1/product-groups', {
  query: { per_page: 100, sortBy: 'sort_order', orderBy: 'asc' },
}))

// Nhóm có sort_order (0/null = chưa xếp) hiển thị trước, chưa xếp xuống cuối
const groups = computed(() => {
  const list = groupsData.value?.data ?? []
  const ordered = list.filter(group => group.sort_order).sort((a, b) => a.sort_order - b.sort_order)
  const unordered = list.filter(group => !group.sort_order)

  return [...ordered, ...unordered]
})

// Đếm sản phẩm theo nhóm: lấy tất cả (per_page=-1 — backend bù tổng số
// bản ghi) rồi đếm client-side
const { data: allProductsData } = await useApi(createUrl('/v1/products', {
  query: { per_page: -1 },
}))

const countByGroup = computed(() => {
  const counts = {}

  for (const product of (allProductsData.value?.products ?? []))
    counts[product.product_group_id] = (counts[product.product_group_id] ?? 0) + 1

  return counts
})

// ==================== Lưới món theo nhóm đang chọn ====================
const selectedGroupId = ref(null)
const productSearch = ref('')

// Mặc định chọn nhóm ĐẦU TIÊN CÓ SẢN PHẨM (không mở vào nhóm rỗng)
watch([groups, countByGroup], () => {
  if (selectedGroupId.value === null && groups.value.length) {
    const firstWithProducts = groups.value.find(group => (countByGroup.value[group.id] ?? 0) > 0)

    selectedGroupId.value = firstWithProducts?.id ?? groups.value[0]?.id ?? null
  }
}, { immediate: true })

const selectedGroupName = computed(() =>
  groups.value.find(group => group.id === selectedGroupId.value)?.name ?? 'Món')

const productsQuery = computed(() => ({
  per_page: 100,
  product_group_id: selectedGroupId.value,
}))

const { data: groupProductsData } = await useApi(createUrl('/v1/products', {
  query: productsQuery,
}))

// Lọc client-side theo ô tìm kiếm (F3) trên nhóm đang chọn
const visibleProducts = computed(() => {
  const keyword = productSearch.value.trim().toLowerCase()

  return (groupProductsData.value?.products ?? []).filter(product =>
    !keyword || product.name.toLowerCase().includes(keyword))
})

// ==================== Giỏ hàng (đơn hàng bên phải) ====================
const cart = ref([])
const orderNote = ref('')

const addToCart = product => {
  const found = cart.value.find(item => item.id === product.id)

  if (found) {
    found.quantity++
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      price: Number(product.price),
      quantity: 1,
      image_url: product.image_url,
    })
  }
}

const increment = item => {
  item.quantity++
}

const decrement = item => {
  item.quantity--

  if (item.quantity < 1)
    removeFromCart(item)
}

const removeFromCart = item => {
  cart.value = cart.value.filter(cartItem => cartItem.id !== item.id)
}

const clearCart = () => {
  cart.value = []
}

const subtotal = computed(() => cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0))
const vat = computed(() => Math.round(subtotal.value * 0.08))
const grandTotal = computed(() => subtotal.value + vat.value)

// Định dạng tiền: 350000 → "350.000đ"
const formatMoney = value =>
  `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`

// ==================== Phím tắt & placeholder ====================
const searchFieldRef = ref(null)
const noteFieldRef = ref(null)

const focusSearch = () => {
  const input = searchFieldRef.value?.$el?.querySelector('input')

  input?.focus()
}

// Nút "Ghi chú" trên header: đưa con trỏ tới ô ghi chú đơn hàng
const focusNote = () => {
  const input = noteFieldRef.value?.$el?.querySelector('textarea, input')

  input?.focus()
}

const snackbar = ref({ show: false, message: '', color: 'warning' })

const notifyNotConnected = action => {
  snackbar.value = { show: true, message: `${action} — sẽ kết nối API đơn hàng sau.`, color: 'warning' }
}

const handleShortcut = event => {
  if (event.key === 'F3') {
    event.preventDefault()
    focusSearch()
  } else if (event.key === 'F4') {
    event.preventDefault()
    notifyNotConnected('Tạm tính')
  } else if (event.key === 'F5') {
    event.preventDefault()
    notifyNotConnected('Thanh toán')
  }
}

onMounted(() => window.addEventListener('keydown', handleShortcut))
onUnmounted(() => window.removeEventListener('keydown', handleShortcut))
</script>

<template>
  <div>
    <!-- 👉 Header: quay lại sơ đồ + thông tin bàn + tìm món + hành động -->
    <div class="d-flex flex-wrap align-center gap-4 mb-4">
      <IconBtn @click="router.back()">
        <VIcon icon="tabler-arrow-left" />
      </IconBtn>

      <h4 class="text-h4 font-weight-medium">
        Order - {{ table?.name ?? 'Chưa chọn bàn' }}
      </h4>

      <VChip
        v-if="table"
        :color="tableStatusMeta(table.status).color"
        label
        size="small"
      >
        {{ tableStatusMeta(table.status).label }}
      </VChip>

      <span class="text-body-1 text-medium-emphasis">{{ currentTime }}</span>

      <VSpacer />

      <AppTextField
        ref="searchFieldRef"
        v-model="productSearch"
        placeholder="Tìm món (F3)"
        prepend-inner-icon="tabler-search"
        clearable
        hide-details
        style="inline-size: 240px;"
      />

      <VBtn
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-pencil"
        @click="focusNote"
      >
        Ghi chú
      </VBtn>

      <VBtn
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-user"
      >
        Chọn khách
      </VBtn>

      <IconBtn>
        <VIcon icon="tabler-dots-vertical" />
      </IconBtn>
    </div>

    <VRow class="order-body">
      <!-- 👉 Cột trái: danh mục nhóm hàng -->
      <VCol
        cols="12"
        md="2"
        class="order-col"
      >
        <div class="text-caption text-uppercase text-medium-emphasis mb-3">
          Danh mục
        </div>

        <VList class="py-0">
          <VListItem
            v-for="group in groups"
            :key="group.id"
            rounded="lg"
            class="mb-1 category-item"
            style="min-block-size: 44px;"
            :active="selectedGroupId === group.id"
            @click="selectedGroupId = group.id"
          >
            <VListItemTitle class="font-weight-medium">
              {{ group.name }}
            </VListItemTitle>
            <template #append>
              <VChip
                size="small"
                variant="tonal"
              >
                {{ countByGroup[group.id] ?? 0 }}
              </VChip>
            </template>
          </VListItem>
        </VList>
      </VCol>

      <!-- 👉 Cột giữa: lưới món của nhóm đang chọn -->
      <VCol
        cols="12"
        md="6"
        lg="7"
        class="order-col"
      >
        <h5 class="text-h5 mb-4 text-uppercase">
          {{ selectedGroupName }}
        </h5>

        <VRow v-if="visibleProducts.length">
          <VCol
            v-for="product in visibleProducts"
            :key="product.id"
            cols="6"
            sm="4"
            lg="3"
          >
            <VCard
              class="text-center product-card"
              hover
              @click="addToCart(product)"
            >
              <VImg
                v-if="product.image_url"
                :src="product.image_url"
                height="110"
                cover
              />
              <div
                v-else
                class="d-flex align-center justify-center text-medium-emphasis"
                style="block-size: 110px;"
              >
                <VIcon
                  icon="tabler-photo"
                  size="36"
                />
              </div>

              <VCardText class="pt-2">
                <div class="text-body-2 font-weight-medium">
                  {{ product.name }}
                </div>
                <div class="text-body-2 text-primary mt-1">
                  {{ formatMoney(product.price) }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <div
          v-else
          class="text-center text-body-1 py-10 text-disabled"
        >
          Nhóm này chưa có món.
        </div>
      </VCol>

      <!-- 👉 Cột phải: đơn hàng -->
      <VCol
        cols="12"
        md="4"
        lg="3"
        class="order-col"
      >
        <div class="d-flex align-center justify-space-between mb-4">
          <h5 class="text-h5 text-uppercase">
            Đơn hàng
          </h5>

          <VBtn
            variant="text"
            color="error"
            size="small"
            prepend-icon="tabler-trash"
            :disabled="!cart.length"
            @click="clearCart"
          >
            Xóa tất cả
          </VBtn>
        </div>

        <div class="cart-list">
          <div
            v-for="item in cart"
            :key="item.id"
            class="d-flex align-center gap-x-3 py-2 cart-item"
          >
            <VAvatar
              rounded
              size="44"
              variant="tonal"
            >
              <VImg
                v-if="item.image_url"
                :src="item.image_url"
                cover
              />
              <VIcon
                v-else
                icon="tabler-photo"
              />
            </VAvatar>

            <div class="flex-grow-1">
              <div class="text-body-2 font-weight-medium">
                {{ item.name }}
              </div>
              <div class="text-body-2 text-primary">
                {{ formatMoney(item.price) }}
              </div>
            </div>

            <div class="d-flex align-center gap-x-1">
              <VBtn
                icon="tabler-minus"
                size="x-small"
                variant="tonal"
                @click="decrement(item)"
              />
              <span class="text-body-2 font-weight-medium">{{ item.quantity }}</span>
              <VBtn
                icon="tabler-plus"
                size="x-small"
                variant="tonal"
                @click="increment(item)"
              />
            </div>

            <IconBtn
              size="small"
              color="error"
              @click="removeFromCart(item)"
            >
              <VIcon
                icon="tabler-trash"
                size="18"
              />
            </IconBtn>
          </div>

          <div
            v-if="!cart.length"
            class="text-center text-body-2 text-medium-emphasis py-6"
          >
            Chưa có món nào — bấm vào món để thêm vào đơn.
          </div>
        </div>

        <AppTextarea
          ref="noteFieldRef"
          v-model="orderNote"
          label="Ghi chú đơn hàng..."
          placeholder="Ghi chú đơn hàng..."
          rows="2"
          class="mt-4"
        />

        <VDivider class="my-4" />

        <div class="d-flex justify-space-between text-body-1 mb-2">
          <span>Tạm tính</span>
          <span>{{ formatMoney(subtotal) }}</span>
        </div>

        <div class="d-flex justify-space-between text-body-1 mb-2">
          <span>Thuế VAT (8%)</span>
          <span>{{ formatMoney(vat) }}</span>
        </div>

        <VDivider class="my-3" />

        <div class="d-flex justify-space-between align-center">
          <span class="text-h6">Tổng cộng</span>
          <span class="text-h5 font-weight-bold text-error">{{ formatMoney(grandTotal) }}</span>
        </div>

        <div class="d-flex gap-3 mt-6">
          <IconBtn
            size="large"
            variant="outlined"
          >
            <VIcon icon="tabler-menu-2" />
          </IconBtn>

          <VBtn
            variant="tonal"
            color="warning"
            class="flex-1"
            :disabled="!cart.length"
            @click="notifyNotConnected('Tạm tính')"
          >
            Tạm tính (F4)
          </VBtn>

          <VBtn
            color="error"
            class="flex-1"
            :disabled="!cart.length"
            @click="notifyNotConnected('Thanh toán')"
          >
            Thanh toán (F5)
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
    >
      {{ snackbar.message }}
    </VSnackbar>
  </div>
</template>

<style lang="scss" scoped>
// Ba cột cao cố định theo viewport, mỗi cột cuộn riêng
.order-body {
  --order-header: 150px;
}

.order-col {
  max-block-size: calc(100vh - var(--order-header));
  overflow-block: auto;
}

// Món: gợi ý bấm vào để thêm vào đơn
.product-card {
  cursor: pointer;
}

// Đường kẻ phân tách giữa các món trong đơn
.cart-item:not(:last-child) {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.category-item:not(:last-child) {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
</style>
