<script setup>
import { animations, place } from '@formkit/drag-and-drop'
import { useDragAndDrop } from '@formkit/drag-and-drop/vue'
import { validationMessages } from '@/utils/validationMessages'

const selectedGroupId = ref(null) // null → đang xem "Tất cả sản phẩm"

const { data: groupsData, execute: fetchGroups } = await useApi(createUrl('/v1/product-groups', {
  query: { per_page: 100, sortBy: 'sort_order', orderBy: 'asc' },
}))

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

watch(groupsData, value => {
  syncingGroups = true
  groups.value = sortGroupsForDisplay(value?.data ?? [])
  nextTick(() => {
    syncingGroups = false
  })
}, { immediate: true })

const selectGroup = groupId => {
  selectedGroupId.value = groupId
}

// Nhóm mới tạo từ header "Danh mục": đưa xuống cuối danh sách, gán
// sort_order = tổng số nhóm rồi chọn luôn nhóm đó
const onGroupCreated = async group => {
  await fetchGroups()

  if (group?.id === undefined || group?.id === null)
    return

  const index = groups.value.findIndex(item => item.id === group.id)

  if (index !== -1) {
    const [created] = groups.value.splice(index, 1)

    created.sort_order = groups.value.length + 1
    groups.value.push(created)

    await useApi(`/v1/product-groups/${group.id}`, {
      method: 'PUT',
      body: { sort_order: created.sort_order },
    }).json()
  }

  selectedGroupId.value = group.id
}

// ==================== Sửa nhóm hàng ====================
const editingGroup = ref(null)

const openEdit = group => {
  editingGroup.value = group
}

// Phản ánh bản ghi vừa lưu vào danh sách đang hiển thị
const onGroupSaved = updated => {
  const local = groups.value.find(item => item.id === updated?.id)

  if (local) {
    local.name = updated.name
    local.is_active = updated.is_active
  }
}

// ==================== Kéo thả: lưu thứ tự vào DB ====================
const isSavingOrder = ref(false)
const snackbar = ref({ show: false, message: '', color: 'error' })

// useDragAndDrop tự cập nhật thứ tự groups.value khi kéo — sau khi thay đổi
// ổn định (debounce) thì gán sort_order 1..n và PUT các nhóm đổi chỗ
const persistOrder = async () => {
  if (syncingGroups)
    return

  const requests = []

  groups.value.forEach((group, index) => {
    const nextOrder = index + 1

    if (group.sort_order !== nextOrder) {
      group.sort_order = nextOrder
      requests.push(
        useApi(`/v1/product-groups/${group.id}`, {
          method: 'PUT',
          body: { sort_order: nextOrder },
        }).json(),
      )
    }
  })

  if (requests.length) {
    isSavingOrder.value = true
    await Promise.all(requests)
    isSavingOrder.value = false
    snackbar.value = { show: true, message: validationMessages.productGroup.orderSaved, color: 'success' }
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

// ==================== Đếm sản phẩm theo nhóm ====================
// Lấy TẤT CẢ sản phẩm (per_page=-1 — backend bù bằng tổng số bản ghi) rồi
// đếm client-side; khi catalog lớn nên thay bằng endpoint đếm riêng.
const { data: allProductsData, execute: fetchAllProducts } = await useApi(createUrl('/v1/products', {
  query: { per_page: -1 },
}))

const totalAllProducts = computed(() => allProductsData.value?.total ?? 0)

const countByGroup = computed(() => {
  const counts = {}

  for (const product of (allProductsData.value?.products ?? []))
    counts[product.product_group_id] = (counts[product.product_group_id] ?? 0) + 1

  return counts
})

// ==================== Cột phải: lưới sản phẩm của nhóm đang chọn ====================
const page = ref(1)
const perPage = 12
const searchQuery = ref('')

// Debounce ô search: search sản phẩm NẰM TRONG nhóm đang chọn (tham số q)
const debouncedSearchQuery = refDebounced(searchQuery, 400)

// Đổi từ khoá hoặc đổi nhóm → quay về trang 1 (watch đặt TRƯỚC useApi
// để watcher refetch đọc giá trị page đã cập nhật, tránh gọi API 2 lần)
watch([debouncedSearchQuery, selectedGroupId], () => {
  page.value = 1
})

const productsQuery = computed(() => {
  const query = {
    page: page.value,
    per_page: perPage,
  }

  if (debouncedSearchQuery.value)
    query.q = debouncedSearchQuery.value

  if (selectedGroupId.value !== null && selectedGroupId.value !== undefined)
    query.product_group_id = selectedGroupId.value

  return query
})

const {
  data: productsData,
  isFetching,
  execute: fetchGridProducts,
} = await useApi(createUrl('/v1/products', {
  query: productsQuery,
}))

const products = computed(() => productsData.value?.products ?? [])
const totalGridProducts = computed(() => productsData.value?.total ?? 0)
const lastPage = computed(() => productsData.value?.lastPage ?? 1)

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

// Sản phẩm mới tạo từ dialog "Thêm mới": tải lại lưới + bộ đếm theo nhóm
const onProductCreated = async () => {
  await Promise.all([fetchGridProducts(), fetchAllProducts()])
}
</script>

<template>
  <VRow>
    <!-- 👉 Cột trái: danh mục nhóm hàng -->
    <VCol
      cols="12"
      md="4"
    >
      <VCard>
        <VCardText>
          <!-- 👉 Header "Danh mục": cả hàng là nút — bấm mở dialog thêm nhóm mới -->
          <ProductGroupCreateDialog @created="onGroupCreated">
            <template #activator="{ open }">
              <div
                class="d-flex align-center justify-space-between mb-3 cursor-pointer"
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
              rounded="lg"
              class="mb-2 category-item"
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
                rounded="lg"
                class="mb-2 group-item category-item"
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

  <VSnackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="top"
  >
    {{ snackbar.message }}
  </VSnackbar>
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
