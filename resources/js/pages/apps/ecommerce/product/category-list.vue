<script setup>
import { animations, place } from '@formkit/drag-and-drop'
import { useDragAndDrop } from '@formkit/drag-and-drop/vue'
import { storeToRefs } from 'pinia'
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { useProductStore } from '@/store/useProductStore'
import { validationMessages } from '@/utils/validationMessages'

const selectedGroupId = ref(null) // null → đang xem "Tất cả sản phẩm"

// ==================== Store Pinia dùng chung ====================
// productGroupStore giữ toàn bộ danh mục sản phẩm; productStore giữ toàn bộ
// sản phẩm. Store tự quyết định có cần gọi API hay không qua ensureLoaded
// (chỉ tải lần đầu); lọc theo danh mục/tìm món là việc của trang, làm client-side.
const productGroupStore = useProductGroupStore()
const productStore = useProductStore()

const { productGroups } = storeToRefs(productGroupStore)
const { products: allProducts } = storeToRefs(productStore)

await Promise.all([
  productGroupStore.ensureLoaded(),
  productStore.ensureLoaded(),
])

// setProductGroups: cập nhật local sau kéo thả (optimistic, không gọi API).
const { setProductGroups } = productGroupStore

// Cờ tổng hợp dùng cho overlay "Đang tải" của trang.
const isFetching = computed(() => productGroupStore.isLoading || productStore.isLoading)

// groups là ref do useDragAndDrop quản lý. Plugin `place` giữ hành vi
// "kéo chỉ hiện vị trí sẽ thả" (highlight), thả hẳn ra mới cập nhật danh
// sách; plugin `animations` cho hiệu ứng trượt mượt khi di chuyển.
const [groupListEl, groups] = useDragAndDrop([], {
  plugins: [animations(), place()],
  draggingClass: 'group-dragging',
  dropZoneClass: 'group-drop-target',

  // Chỉ item nhóm hàng được kéo (loại "Tất cả sản phẩm" + node lạ của Vuetify)
  draggable: child => child.classList.contains('group-item'),
})

const sortGroupsForDisplay = list => {
  // sort_order 0/null = chưa xếp (mặc định của cột) → đưa xuống cuối
  const ordered = list
    .filter(group => group.sort_order)
    .sort((a, b) => a.sort_order - b.sort_order)

  const unordered = list.filter(group => !group.sort_order)

  return [...ordered, ...unordered]
}

let syncingGroups = false

watch(productGroups, value => {
  syncingGroups = true
  groups.value = sortGroupsForDisplay(value ?? [])
  nextTick(() => {
    syncingGroups = false
  })
}, { immediate: true })

const selectGroup = groupId => {
  selectedGroupId.value = groupId
}

// Nhóm mới tạo từ header "Danh mục": store đã tự chèn bản ghi vào ĐẦU state
// (createProductGroup chạy prependRecord), handler chỉ đưa nhóm xuống cuối
// danh sách hiển thị, gán sort_order rồi chọn luôn nhóm đó
const onGroupCreated = async group => {
  if (group?.id === undefined || group?.id === null)
    return

  // Đợi watcher đồng bộ danh sách kéo-thả từ store (store đã tự prepend
  // ngay sau create, watcher cần 1 tick để phản ánh vào groups.value).
  await nextTick()

  const index = groups.value.findIndex(item => item.id === group.id)

  if (index !== -1) {
    const [created] = groups.value.splice(index, 1)

    created.sort_order = groups.value.length + 1
    groups.value.push(created)

    // Cập nhật qua store — store tự vá bản ghi trong state theo id
    await productGroupStore.updateProductGroup(group.id, {
      sort_order: created.sort_order,
    })
  }

  selectedGroupId.value = group.id
}

// ==================== Sửa nhóm hàng ====================
const editingGroup = ref(null)

const openEdit = group => {
  editingGroup.value = group
}

// Store đã tự vá bản ghi theo id khi update (applyRecord trong
// updateProductGroup) — handler ở đây chỉ nhận sự kiện để trang cha
// làm tiếp nếu cần, không chạm state nữa.
const onGroupSaved = () => {}

// ==================== Kéo thả: lưu thứ tự vào DB ====================
const isSavingOrder = ref(false)

// useDragAndDrop tự cập nhật thứ tự groups.value khi kéo — sau khi thay đổi
// ổn định (debounce) thì gán sort_order 1..n và PUT các nhóm đổi chỗ qua
// store (mỗi request trả về bản ghi — store tự vá state theo id)
const persistOrder = async () => {
  if (syncingGroups)
    return

  const requests = []

  groups.value.forEach((group, index) => {
    const nextOrder = index + 1

    if (group.sort_order !== nextOrder) {
      group.sort_order = nextOrder
      requests.push(
        productGroupStore.updateProductGroup(group.id, { sort_order: nextOrder }),
      )
    }
  })

  if (requests.length) {
    isSavingOrder.value = true
    await Promise.all(requests)
    isSavingOrder.value = false
    setProductGroups([...groups.value])
    notify.success(validationMessages.productGroup.orderSaved)
  }
}

const persistOrderDebounced = useDebounceFn(() => persistOrder(), 400)

watch(groups, () => {
  persistOrderDebounced()
})

const selectedGroupName = computed(() => {
  if (selectedGroupId.value === null)
    return 'Tất cả sản phẩm'

  return groups.value.find(group => group.id === selectedGroupId.value)?.name ?? 'Nhóm hàng'
})

// ==================== Đếm sản phẩm theo nhóm từ Pinia ====================
// allProducts là state của useProductStore; mọi bộ đếm/danh sách phía dưới đều
// derive bằng computed để UI tự cập nhật khi store thay đổi.
const totalAllProducts = computed(() => allProducts.value.length)

const countByGroup = computed(() => {
  const counts = {}

  for (const product of allProducts.value)
    counts[product.product_group_id] = (counts[product.product_group_id] ?? 0) + 1

  return counts
})

// ==================== Cột phải: lưới sản phẩm của nhóm đang chọn ====================
const page = ref(1)
const perPage = 12
const searchQuery = ref('')

// Debounce ô search: tìm sản phẩm trong danh sách Pinia của nhóm đang chọn.
const debouncedSearchQuery = refDebounced(searchQuery, 400)

// Đổi từ khoá hoặc đổi nhóm → quay về trang 1 để người dùng luôn thấy kết quả
// đầu tiên của danh mục/bộ lọc mới.
watch([debouncedSearchQuery, selectedGroupId], () => {
  page.value = 1
})

const filteredProducts = computed(() => {
  const keyword = debouncedSearchQuery.value.trim().toLowerCase()

  return allProducts.value.filter(product => {
    const matchGroup = selectedGroupId.value === null || selectedGroupId.value === undefined || product.product_group_id === selectedGroupId.value
    const matchKeyword = !keyword || product.name.toLowerCase().includes(keyword)

    return matchGroup && matchKeyword
  })
})

const totalGridProducts = computed(() => filteredProducts.value.length)
const lastPage = computed(() => Math.max(1, Math.ceil(totalGridProducts.value / perPage)))

// products là danh sách đã phân trang từ state Pinia, giữ nguyên tên biến để
// template hiện tại không cần đổi cấu trúc hiển thị card sản phẩm.
const products = computed(() => {
  const startIndex = (page.value - 1) * perPage

  return filteredProducts.value.slice(startIndex, startIndex + perPage)
})

const headerCount = computed(() => {
  if (selectedGroupId.value === null)
    return totalAllProducts.value

  // computed ref phải đọc .value trong script (template mới tự unwrap)
  return countByGroup.value[selectedGroupId.value] ?? 0
})

const emptyMessage = computed(() => {
  if (debouncedSearchQuery.value)
    return 'Không tìm thấy sản phẩm nào phù hợp.'

  return 'Nhóm này chưa có sản phẩm. Bấm "Thêm mới" để tạo sản phẩm đầu tiên.'
})

// Định dạng giá theo VND
const formatPrice = value =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

// Sản phẩm mới tạo từ dialog "Thêm mới": store đã tự chèn vào ĐẦU state
// (createProduct chạy prependRecord) — lưới + bộ đếm theo nhóm tự cập nhật
// qua computed, không chạm state ở đây nữa.
const onProductCreated = () => {}
</script>

<template>
  <VRow>
    <!-- 👉 Cột trái: danh mục nhóm hàng -->
    <VCol
      cols="12"
      md="4"
    >
      <VCard>
        <VCardText class="pa-0">
          <!-- 👉 Header "Danh mục": cả hàng là nút — bấm mở dialog thêm nhóm mới -->
          <ProductGroupCreateDialog @created="onGroupCreated">
            <template #activator="{ open }">
              <div
                class="d-flex align-center justify-space-between px-6 pt-6 pb-3 cursor-pointer"
                role="button"
                tabindex="0"
                @click="open"
                @keydown.enter.prevent="open"
              >
                <span class="text-caption text-uppercase text-medium-emphasis">Danh mục</span>
                <VBtn
                  icon="tabler-plus"
                  size="small"
                  variant="tonal"
                  aria-label="Thêm nhóm hàng"
                />
              </div>
            </template>
          </ProductGroupCreateDialog>

          <!-- parent element của useDragAndDrop: các item bên trong kéo thả được -->
          <VList
            ref="groupListEl"
            class="py-0"
          >
            <VListItem
              rounded="0"
              class="category-item"
              style="min-block-size: 52px;"
              :active="selectedGroupId === null"
              @click="selectGroup(null)"
            >
              <VListItemTitle
                class="font-weight-medium"
                style="font-size: 16px;"
              >
                Tất cả sản phẩm
              </VListItemTitle>
              <template #append>
                <VChip
                  size="small"
                  variant="tonal"
                >
                  {{ totalAllProducts }}
                </VChip>
              </template>
            </VListItem>

            <!--
              TransitionGroup (không tag → không thêm wrapper): khi thả,
              các item trượt mượt tới vị trí mới (FLIP) 
            -->
            <TransitionGroup name="flip">
              <VListItem
                v-for="group in groups"
                :key="group.id"
                rounded="0"
                class="group-item category-item"
                style="min-block-size: 52px;"
                :active="selectedGroupId === group.id"
                @click="selectGroup(group.id)"
              >
                <VListItemTitle
                  class="font-weight-medium"
                  style="font-size: 16px;"
                >
                  {{ group.name }}
                </VListItemTitle>
                <template #append>
                  <div class="d-flex align-center gap-x-2">
                    <VChip
                      size="small"
                      variant="tonal"
                    >
                      {{ countByGroup[group.id] ?? 0 }}
                    </VChip>
                    <IconBtn
                      size="small"
                      icon="tabler-pencil"
                      aria-label="Sửa nhóm hàng"
                      draggable="false"
                      @click.stop="openEdit(group)"
                    />
                  </div>
                </template>
              </VListItem>
            </TransitionGroup>
          </VList>

          <ProductGroupEditDialog
            :group="editingGroup"
            :model-value="editingGroup !== null"
            @update:model-value="editingGroup = null"
            @saved="onGroupSaved"
          />
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Cột phải: sản phẩm thuộc nhóm đang chọn -->
    <VCol
      cols="12"
      md="8"
    >
      <VCard>
        <VCardText>
          <div class="d-flex flex-wrap align-center justify-sm-space-between gap-4 mb-6">
            <h4 class="text-h4">
              {{ selectedGroupName }} ({{ headerCount }})
            </h4>

            <div class="d-flex align-center gap-x-4 flex-grow-1 flex-sm-grow-0">
              <AppTextField
                v-model="searchQuery"
                prepend-inner-icon="tabler-search"
                placeholder="Tìm món ăn, đồ uống..."
                clearable
                hide-details
                class="flex-grow-1"
                style="min-inline-size: 240px;"
              />

              <ProductCreateDialog
                :group-id="selectedGroupId"
                @created="onProductCreated"
              >
                <template #activator="{ open }">
                  <VBtn
                    prepend-icon="tabler-plus"
                    @click="open"
                  >
                    Thêm mới
                  </VBtn>
                </template>
              </ProductCreateDialog>
            </div>
          </div>

          <VProgressLinear
            v-if="isFetching"
            indeterminate
            rounded
            class="mb-4"
          />

          <VRow v-if="products.length">
            <VCol
              v-for="product in products"
              :key="product.id"
              cols="6"
              sm="4"
              lg="3"
            >
              <VCard class="text-center h-100">
                <VImg
                  v-if="product.image_url"
                  :src="product.image_url"
                  height="150"
                  cover
                />
                <div
                  v-else
                  class="d-flex align-center justify-center text-medium-emphasis"
                  style="block-size: 150px;"
                >
                  <VIcon
                    icon="tabler-photo"
                    size="48"
                  />
                </div>

                <VCardText>
                  <div class="text-body-1 font-weight-medium">
                    {{ product.name }}
                  </div>
                  <div class="text-body-2 text-medium-emphasis mt-1">
                    {{ formatPrice(product.price) }}
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <div
            v-else
            class="text-center py-12 text-medium-emphasis"
          >
            {{ emptyMessage }}
          </div>

          <VPagination
            v-if="lastPage > 1"
            v-model="page"
            :length="lastPage"
            class="mt-6"
          />

          <div
            v-if="products.length"
            class="text-center text-body-2 text-medium-emphasis mt-2"
          >
            {{ totalGridProducts }} sản phẩm
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
// Đường kẻ phân tách mảnh giữa các item danh mục (item cuối không có)
.category-item:not(:last-child) {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}

.group-dragging {
  opacity: 0.4;
}

.group-drop-target {
  outline: 2px dashed rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

// Hiệu ứng trượt FLIP khi item đổi vị trí sau khi thả
.flip-move,
.flip-enter-active,
.flip-leave-active {
  transition: transform 0.28s ease, opacity 0.28s ease;
}

.flip-enter-from,
.flip-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
