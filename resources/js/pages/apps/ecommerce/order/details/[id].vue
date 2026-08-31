<script setup>
import { storeToRefs } from 'pinia'
import { useDiningTableStore } from '@/store/useDiningTableStore'
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { useProductStore } from '@/store/useProductStore'
import { tableStatusMeta } from '@/utils/tableStatuses'

const router = useRouter()
const route = useRoute()

// ==================== Thông tin bàn ====================
// Thông tin bàn lấy từ Pinia diningTableStore, không dùng endpoint floor.
// Store tự nạp lần đầu qua ensureLoaded; quay lại trang thì dùng ngay cache.
const diningTableStore = useDiningTableStore()

const { diningTables } = storeToRefs(diningTableStore)

await diningTableStore.ensureLoaded()

const table = computed(() => {
  const tableId = Number(route.params.id)

  return diningTables.value.find(item => item.id === tableId) ?? null
})

const tableTitle = computed(() => {
  const name = table.value?.name

  if (!name)
    return 'Chưa chọn bàn'

  return /^bàn\s/i.test(name) ? name : `Bàn ${name}`
})

const tableStatus = computed(() => {
  const meta = tableStatusMeta(table.value?.status)

  // API CRUD dining_table chưa trả trạng thái phiên bàn, nên trạng thái mặc
  // định là "Bàn trống" cho tới khi backend bổ sung trường status phù hợp.
  if (table.value?.status === 'occupied')
    return { label: 'Đang phục vụ', color: meta.color }

  return { label: 'Bàn trống', color: meta.color }
})

const now = useNow({ interval: 30000 })

const currentTime = computed(() =>
  now.value.toLocaleTimeString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }))

// ==================== Danh mục và món từ Pinia ====================
// Store tự quyết định có cần gọi API hay không qua ensureLoaded (chỉ tải lần
// đầu, sau đó dùng cache Pinia). Trang chỉ bóc dữ liệu cần dùng ra làm việc.
const productGroupStore = useProductGroupStore()
const productStore = useProductStore()

const { productGroups } = storeToRefs(productGroupStore)
const { products: allProducts } = storeToRefs(productStore)

await Promise.all([
  productGroupStore.ensureLoaded(),
  productStore.ensureLoaded(),
])

// Cờ tổng hợp dùng cho dòng "Đang tải..." phía trên lưới món.
const productsLoading = computed(() => productGroupStore.isLoading || productStore.isLoading)

const groups = computed(() => {
  const list = productGroups.value
  const ordered = list.filter(group => group.sort_order).sort((a, b) => a.sort_order - b.sort_order)
  const unordered = list.filter(group => !group.sort_order)

  return [...ordered, ...unordered]
})

// Đếm sản phẩm theo product_group_id từ state product dùng chung để hiện badge.
const countByGroup = computed(() => {
  const counts = {}

  for (const product of allProducts.value)
    counts[product.product_group_id] = (counts[product.product_group_id] ?? 0) + 1

  return counts
})

const selectedGroupId = ref(null)
const productSearch = ref('')

watch([groups, countByGroup], () => {
  if (selectedGroupId.value === null && groups.value.length) {
    const firstGroupWithProducts = groups.value.find(group => (countByGroup.value[group.id] ?? 0) > 0)

    selectedGroupId.value = firstGroupWithProducts?.id ?? groups.value[0].id
  }
}, { immediate: true })

const selectedGroupName = computed(() =>
  groups.value.find(group => group.id === selectedGroupId.value)?.name ?? 'Món')

// Lưới món chỉ đọc danh sách product trong Pinia và lọc theo id danh mục đang
// chọn cùng từ khóa tìm kiếm; computed giữ dữ liệu luôn đồng bộ với store.
const visibleProducts = computed(() => {
  const keyword = productSearch.value.trim().toLowerCase()

  return allProducts.value.filter(product => {
    const matchGroup = product.product_group_id === selectedGroupId.value
    const matchKeyword = !keyword || product.name.toLowerCase().includes(keyword)

    return matchGroup && matchKeyword
  })
})

const groupIcon = name => {
  if (/trà sữa|trà trái|trà/i.test(name))
    return 'tabler-glass'
  if (/cà phê|coffee/i.test(name))
    return 'tabler-coffee'
  if (/đá|sinh tố/i.test(name))
    return 'tabler-ice-cream'
  if (/nước|juice/i.test(name))
    return 'tabler-glass-full'
  if (/bánh|tráng miệng|dessert/i.test(name))
    return 'tabler-cake'
  if (/topping/i.test(name))
    return 'tabler-bubble'

  return 'tabler-tools-kitchen-2'
}

// ==================== Giỏ hàng ====================
const cart = ref([])
const customerName = ref('')

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
  if (item.quantity === 1) {
    removeFromCart(item)

    return
  }

  item.quantity--
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
const isSummaryExpanded = ref(false)

const formatMoney = value =>
  `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`

// ==================== Tương tác màn hình ====================
const searchFieldRef = ref(null)
const noteFieldRef = ref(null)

const focusSearch = () => {
  searchFieldRef.value?.$el?.querySelector('input')?.focus()
}

const focusNote = () => {
  noteFieldRef.value?.$el?.querySelector('textarea, input')?.focus()
}

// Toast toàn cục: thông báo chung của trang dùng kind info (góc trên phải)
const showMessage = message => notify.info(message)

const notifyNotConnected = action => {
  showMessage(`${action} — chức năng sẽ được kết nối API đơn hàng sau.`)
}

const openCustomerPicker = () => {
  showMessage(customerName.value ? `Khách hàng: ${customerName.value}` : 'Chưa chọn khách hàng.')
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
  <div class="order-screen">
    <header class="order-header">
      <div class="d-flex align-center gap-x-4">
        <IconBtn
          aria-label="Quay lại sơ đồ bàn"
          @click="router.back()"
        >
          <VIcon icon="tabler-arrow-left" />
        </IconBtn>

        <h1 class="order-title">
          Order - {{ tableTitle }}
        </h1>

        <VChip
          :color="tableStatus.color"
          label
          size="small"
        >
          {{ tableStatus.label }}
        </VChip>

        <span class="order-time">{{ currentTime }}</span>
      </div>

      <div class="order-header-actions">
        <AppTextField
          ref="searchFieldRef"
          v-model="productSearch"
          placeholder="Tìm món (F3)"
          prepend-inner-icon="tabler-search"
          clearable
          class="order-search"
        />

        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-pencil"
          @click="focusNote"
        >
          Ghi chú
        </VBtn>

        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-user"
          @click="openCustomerPicker"
        >
          Chọn khách
        </VBtn>

        <IconBtn aria-label="Thao tác khác">
          <VIcon icon="tabler-dots-vertical" />
        </IconBtn>
      </div>
    </header>

    <main class="order-content">
      <aside class="category-panel">
        <div class="panel-label">
          Danh mục
        </div>

        <div class="category-list">
          <button
            v-for="group in groups"
            :key="group.id"
            type="button"
            class="category-item"
            :class="{ 'category-item--active': selectedGroupId === group.id }"
            @click="selectedGroupId = group.id"
          >
            <span class="category-item__icon">
              <VIcon
                :icon="groupIcon(group.name)"
                size="22"
              />
            </span>
            <span class="category-item__name">{{ group.name }}</span>
            <span class="category-item__count">{{ countByGroup[group.id] ?? 0 }}</span>
          </button>
        </div>
      </aside>

      <section class="product-panel">
        <div class="product-panel__heading">
          <h2>{{ selectedGroupName }}</h2>
          <span
            v-if="productsLoading"
            class="text-medium-emphasis"
          >Đang tải...</span>
        </div>

        <div
          v-if="visibleProducts.length"
          class="product-grid"
        >
          <button
            v-for="product in visibleProducts"
            :key="product.id"
            type="button"
            class="product-card"
            @click="addToCart(product)"
          >
            <VImg
              v-if="product.image_url"
              :src="product.image_url"
              height="158"
              cover
              class="product-card__image"
            />
            <div
              v-else
              class="product-card__image product-card__image--placeholder"
            >
              <VIcon
                icon="tabler-bowl-chopsticks"
                size="52"
              />
            </div>

            <span class="product-card__body">
              <span class="product-card__name">{{ product.name }}</span>
              <span class="product-card__price">{{ formatMoney(product.price) }}</span>
            </span>
          </button>
        </div>

        <div
          v-else
          class="empty-state"
        >
          <VIcon
            icon="tabler-mood-empty"
            size="42"
          />
          <span>Không tìm thấy món phù hợp.</span>
        </div>
      </section>

      <aside class="order-panel">
        <div class="order-panel__heading">
          <h2>Đơn hàng</h2>
          <VBtn
            variant="tonal"
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
            class="cart-item"
          >
            <VAvatar
              rounded
              size="62"
              variant="tonal"
              class="cart-item__image"
            >
              <VImg
                v-if="item.image_url"
                :src="item.image_url"
                cover
              />
              <VIcon
                v-else
                icon="tabler-bowl-chopsticks"
              />
            </VAvatar>

            <div class="cart-item__info">
              <div class="cart-item__name">
                {{ item.name }}
              </div>
              <div class="cart-item__details">
                <span class="cart-item__price">
                  {{ formatMoney(item.price) }} x {{ item.quantity }}
                </span>
                <strong class="cart-item__amount">
                  {{ formatMoney(item.price * item.quantity) }}
                </strong>
              </div>
            </div>

            <div class="quantity-control">
              <VBtn
                icon="tabler-minus"
                size="small"
                variant="outlined"
                aria-label="Giảm số lượng"
                @click="decrement(item)"
              />
              <span>{{ item.quantity }}</span>
              <VBtn
                icon="tabler-plus"
                size="small"
                variant="outlined"
                aria-label="Tăng số lượng"
                @click="increment(item)"
              />
            </div>

            <IconBtn
              color="error"
              size="small"
              aria-label="Xóa món"
              @click="removeFromCart(item)"
            >
              <VIcon
                icon="tabler-trash"
                size="21"
              />
            </IconBtn>
          </div>

          <div
            v-if="!cart.length"
            class="empty-cart"
          >
            <VIcon
              icon="tabler-shopping-cart-off"
              size="34"
            />
            <span>Chưa có món nào trong đơn.</span>
            <small>Chọn món ở danh sách bên trái để bắt đầu.</small>
          </div>
        </div>

        <div class="order-summary">

          <VExpandTransition>
            <div
              v-show="isSummaryExpanded"
              id="order-summary-details"
            >
              <div class="summary-row">
                <span>Tạm tính</span>
                <span>{{ formatMoney(subtotal) }}</span>
              </div>
              <div class="summary-row">
                <span>Thuế VAT (8%)</span>
                <span>{{ formatMoney(vat) }}</span>
              </div>
              <VDivider class="my-4" />
            </div>
          </VExpandTransition>

          <button
            type="button"
            class="summary-row summary-row--total"
            :aria-expanded="isSummaryExpanded"
            aria-controls="order-summary-details"
            @click="isSummaryExpanded = !isSummaryExpanded"
          >
            <span>Tổng cộng</span>
            <span class="d-flex align-center gap-x-2">
              <strong>{{ formatMoney(grandTotal) }}</strong>
              <VIcon :icon="isSummaryExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
            </span>
          </button>
        </div>

        <div class="order-actions">
          <IconBtn
            size="large"
            variant="outlined"
            aria-label="Mở thêm thao tác"
          >
            <VIcon icon="tabler-menu-2" />
          </IconBtn>
          <VBtn
            variant="tonal"
            color="warning"
            class="flex-grow-1"
            :disabled="!cart.length"
            @click="notifyNotConnected('Tạm tính')"
          >
            Tạm tính (F4)
          </VBtn>
          <VBtn
            color="error"
            class="flex-grow-1"
            :disabled="!cart.length"
            @click="notifyNotConnected('Thanh toán')"
          >
            Thanh toán (F5)
          </VBtn>
        </div>
      </aside>
    </main>
  </div>
</template>

<style lang="scss" scoped>
.order-screen {
  block-size: calc(100vh - 175px);
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
}

.order-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  min-block-size: 88px;
  padding: 16px 22px;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.order-title {
  margin: 0;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 22px;
  font-weight: 600;
  white-space: nowrap;
}

.order-time {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  white-space: nowrap;
}

.order-header-actions {
  display: flex;
  align-items: center;
  gap: 14px;
}

.order-search {
  inline-size: 245px;
}

.order-content {
  display: grid;
  grid-template-columns: minmax(190px, 20%) minmax(420px, 1fr) minmax(370px, 32%);
  min-block-size: calc(100vh - 255px);
}

.category-panel,
.product-panel,
.order-panel {
  min-inline-size: 0;
  padding: 26px 20px;
}

.category-panel,
.product-panel {
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.panel-label {
  margin: 0 8px 18px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 14px;
  font-weight: 500;
  text-transform: uppercase;
}

// Danh mục có vùng cuộn riêng để danh sách dài không làm thay đổi chiều cao POS.
.category-panel {
  display: flex;
  flex-direction: column;
  max-block-size: calc(100vh - 176px);
  overflow: hidden;
}

.category-list {
  display: flex;
  flex-direction: column;
  gap: 5px;
  overflow-block: auto;
  overscroll-behavior: contain;
  padding-inline-end: 4px;
}

.category-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  min-block-size: 61px;
  padding: 8px 10px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  cursor: pointer;
  font: inherit;
  text-align: start;
  transition: background-color 160ms ease, color 160ms ease;
}

.category-item:not(:last-child) {
  &::after {
    position: absolute;
    inset-inline: 0;
    inset-block-end: 0;
    block-size: 1px;
    background: rgba(var(--v-theme-on-surface), 0.1);
    content: '';
  }
}

.category-item:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.category-item--active {
  background: rgba(var(--v-theme-error), 0.08);
  color: rgb(var(--v-theme-error));
}

.category-item__icon {
  display: grid;
  flex: 0 0 34px;
  place-items: center;
}

.category-item__name {
  flex: 1;
  overflow: hidden;
  font-size: 16.5px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-item__count {
  display: grid;
  min-inline-size: 32px;
  block-size: 32px;
  place-items: center;
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.06);
  font-size: 13px;
}

.category-item--active .category-item__count {
  background: rgba(var(--v-theme-error), 0.12);
}

.product-panel {
  overflow-block: auto;
}

.product-panel__heading,
.order-panel__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-block-end: 24px;
}

.product-panel__heading h2,
.order-panel__heading h2 {
  margin: 0;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
}

.product-grid {
  display: grid;
  // Màn hình POS desktop luôn có ba món trên một hàng để card đủ lớn, dễ bấm.
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 24px 20px;
}

.product-card {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  aspect-ratio: 1;
  padding: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 16px;
  background: rgb(var(--v-theme-surface));
  color: inherit;
  cursor: pointer;
  font: inherit;
  text-align: center;
  transition: box-shadow 160ms ease, transform 160ms ease;
}

.product-card:hover {
  box-shadow: 0 8px 20px rgba(var(--v-theme-on-surface), 0.1);
  transform: translateY(-2px);
}

.product-card__image {
  flex: 1;
  min-block-size: 0;
  block-size: auto !important;
  background: #f3ede5;
}

.product-card__image--placeholder {
  display: grid;
  place-items: center;
  color: #a9896b;
}

.product-card__body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-block-size: 76px;
  padding: 12px 10px;
  justify-content: center;
}

.product-card__name {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-card__price,
.cart-item__price {
  color: rgb(var(--v-theme-error));
  font-size: 14px;
}

.empty-state,
.empty-cart {
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.empty-state {
  flex-direction: column;
  gap: 10px;
  min-block-size: 300px;
}

.order-panel {
  display: flex;
  flex-direction: column;
  min-block-size: 0;
}

.cart-list {
  // Chỉ danh sách món trong đơn được cuộn, phần ghi chú/tổng tiền/nút luôn thấy.
  overflow-block: auto;
  max-block-size: clamp(240px, 38vh, 440px);
  overscroll-behavior: contain;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.cart-item {
  display: flex;
  align-items: center;
  gap: 12px;
  min-block-size: 88px;
  padding: 12px;
}

.cart-item:not(:last-child) {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.cart-item__image {
  flex: 0 0 auto;
  background: #f3ede5;
  color: #a9896b;
}

.cart-item__info {
  min-inline-size: 0;
  flex: 1;
}

.cart-item__name {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cart-item__details {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-block-start: 4px;
}

.cart-item__amount {
  color: rgb(var(--v-theme-error));
  font-size: 14px;
  white-space: nowrap;
}

.quantity-control {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.quantity-control span {
  min-inline-size: 14px;
  text-align: center;
}

.empty-cart {
  flex-direction: column;
  gap: 6px;
  min-block-size: 180px;
  padding: 20px;
  text-align: center;
}

.empty-cart small {
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
}

.order-note {
  margin-block-end: 22px;
}

.order-summary {
  // Giữ ghi chú và phần tạm tính ở đáy cột đơn hàng, ngay trên các nút thao tác.
  margin-block-start: auto;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-block-end: 18px;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 14px;
}

.summary-row--total {
  inline-size: 100%;
  margin-block-end: 0;
  padding: 14px 16px;
  border: 0;
  border-radius: 12px;
  background: rgba(var(--v-theme-error), 0.1);
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  cursor: pointer;
  font: inherit;
  font-size: 16px;
  text-align: start;
}

.summary-row--total strong {
  color: rgb(var(--v-theme-error));
  font-size: 20px;
}

.order-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-block-start: 26px;
  padding-block-start: 26px;
}

@media (max-width: 1280px) {
  .order-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .order-header-actions {
    inline-size: 100%;
  }

  .order-search {
    flex: 1;
  }

  .order-content {
    grid-template-columns: 190px minmax(400px, 1fr) 360px;
  }

}

@media (max-width: 960px) {
  .order-content {
    grid-template-columns: 180px minmax(0, 1fr);
  }

  .order-panel {
    grid-column: 1 / -1;
    border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .category-panel,
  .product-panel {
    border-inline-end: 0;
  }

  .category-panel {
    border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .category-panel {
    max-block-size: none;
  }

  .cart-list {
    max-block-size: unset;
  }
}

@media (max-width: 600px) {
  .order-header {
    padding: 14px;
  }

  .order-header > div:first-child {
    flex-wrap: wrap;
    gap: 10px;
  }

  .order-title {
    font-size: 18px;
  }

  .order-header-actions {
    flex-wrap: wrap;
  }

  .order-search {
    flex-basis: 100%;
  }

  .order-content {
    display: block;
    min-block-size: auto;
  }

  .category-panel,
  .product-panel,
  .order-panel {
    padding: 20px 14px;
  }

  .category-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow-block: visible;
  }

  .category-item {
    min-block-size: 50px;
  }

  .category-panel,
  .product-panel {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .product-card__image,
  .product-card__image--placeholder {
    min-block-size: 0;
  }

  .order-panel {
    border-block-start: 0;
  }
}
</style>
