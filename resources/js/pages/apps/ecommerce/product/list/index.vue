<script setup>
const widgetData = ref([
  // ⚠️ Widget thống kê phía trên vẫn là dữ liệu tĩnh của template —
  // chưa có endpoint thống kê thật ở backend nên tạm giữ nguyên.
  {
    title: 'Bán tại quầy',
    value: '$5,345',
    icon: 'tabler-smart-home',
    desc: '5k đơn hàng',
    change: 5.7,
  },
  {
    title: 'Bán qua website',
    value: '$674,347',
    icon: 'tabler-device-laptop',
    desc: '21k đơn hàng',
    change: 12.4,
  },
  {
    title: 'Giảm giá',
    value: '$14,235',
    icon: 'tabler-gift',
    desc: '6k đơn hàng',
  },
  {
    title: 'Tiếp thị liên kết',
    value: '$8,345',
    icon: 'tabler-wallet',
    desc: '150 đơn hàng',
    change: -3.5,
  },
])

// Các cột bảng dùng đúng tên trường THẬT trả về từ API (name, product_group_id,
// price, is_active) — key TRÙNG tên cột DB để sortBy gửi lên backend áp thẳng
// vào ORDER BY mà không cần ánh xạ thêm. Cột actions chỉ chứa nút nên không sort;
// cột lạ gửi tay lên sẽ bị backend chặn qua $fieldSortable của ProductRepository.
const headers = [
  {
    title: 'Sản phẩm',
    key: 'name',
  },
  {
    title: 'Nhóm hàng',
    key: 'product_group_id',
  },
  {
    title: 'Giá',
    key: 'price',
  },
  {
    title: 'Trạng thái',
    key: 'is_active',
  },
  {
    title: 'Thao tác',
    key: 'actions',
    sortable: false,
  },
]

// ==================== Bộ lọc & phân trang ====================
// selectedStatus: null | true | false — lọc theo cột is_active của DB.
// selectedCategory: id của nhóm sản phẩm (product_group_id).
const selectedStatus = ref()
const selectedCategory = ref()
const searchQuery = ref('')

// Debounce 500ms: useApi có refetch:true nên mỗi lần query đổi là một request —
// phải debounce ở nguồn để không gọi /v1/products cho từng ký tự người dùng gõ.
const debouncedSearchQuery = refDebounced(searchQuery, 500)
const selectedRows = ref([])

// Tuỳ chọn phân trang của VDataTableServer
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// Bộ lọc trạng thái: is_active là boolean trong DB nên items dùng true/false
const status = ref([
  {
    title: 'Đang bán',
    value: true,
  },
  {
    title: 'Ngừng bán',
    value: false,
  },
])

// ==================== Danh sách nhóm sản phẩm ====================
// Gọi endpoint thật /v1/product-groups để lọc Category + hiển thị tên nhóm.
// Endpoint này trả shape phân trang mặc định của Laravel, sau khi useApi
// đã bóc envelope thì mảng bản ghi nằm ở thuộc tính `data`.
const {
  data: groupsData,
  execute: fetchGroups,
} = await useApi(createUrl('/v1/product-groups', {
  query: { per_page: 100 },
}))

// Biến danh sách nhóm thành options cho AppSelect: { title: tên, value: id }
const categories = computed(() =>
  (groupsData.value?.data ?? []).map(group => ({ title: group.name, value: group.id })),
)

// Nhóm mới vừa tạo qua dialog: tải lại options để nhóm xuất hiện trong bộ lọc
const onGroupCreated = async () => {
  await fetchGroups()
}

// Map product_group_id -> tên nhóm để hiển thị cột Category
const groupName = groupId =>
  groupsData.value?.data?.find(group => group.id === groupId)?.name ?? '—'

// Định dạng giá theo VND (backend trả số dạng string "25000.00")
const formatPrice = value =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

// Chip trạng thái dựa trên is_active (boolean thật của model)
const resolveStatus = isActive =>
  isActive
    ? { text: 'Đang bán', color: 'success' }
    : { text: 'Ngừng bán', color: 'error' }

// ==================== Gọi API danh sách sản phẩm ====================
// Ghép query thủ công để BỎ QUA các filter đang rỗng — nếu gửi is_active=null
// hay product_group_id=null lên backend sẽ lọc sai (giá trị rỗng được loại
// qua $request->filled nhưng gửi thừa vẫn là rác trên URL).
const productsQuery = computed(() => {
  const query = {
    page: page.value,
    per_page: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  }

  if (debouncedSearchQuery.value)
    query.q = debouncedSearchQuery.value

  if (selectedCategory.value !== null && selectedCategory.value !== undefined)
    query.product_group_id = selectedCategory.value

  if (selectedStatus.value !== null && selectedStatus.value !== undefined)
    query.is_active = selectedStatus.value

  return query
})

// Từ khoá mới luôn quay về trang 1. Watch phải đặt TRƯỚC useApi để watcher
// refetch của useApi chạy sau và đọc giá trị page đã cập nhật — nếu không sẽ
// gọi API hai lần (một lần với page cũ, một lần với page 1).
watch(debouncedSearchQuery, () => {
  page.value = 1
})

// Endpoint thật của Laravel: GET /api/v1/products
// Backend trả shape datatable: { products: [...], total, page, itemsPerPage, lastPage }
// (đã bóc envelope ở useApi). Tham số tìm kiếm/lọc/sắp xếp do DataTableCriteria
// bên backend dịch: q -> search, sortBy/orderBy -> orderBy/sortedBy.
const {
  data: productsData,
  execute: fetchProducts,
} = await useApi(createUrl('/v1/products', {
  query: productsQuery,
}))

const products = computed(() => productsData.value?.products ?? [])
const totalProduct = computed(() => productsData.value?.total ?? 0)

// Xoá sản phẩm qua API thật rồi refetch lại danh sách.
// Lưu ý: biến toàn cục $api của template KHÔNG tồn tại trong bản Vue+Vite
// này nên phải dùng useApi; 204 không có body nên .json() trả null là bình thường.
const deleteProduct = async id => {
  await useApi(`/v1/products/${id}`, { method: 'DELETE' }).json()

  // Xoá khỏi danh sách đang chọn
  const index = selectedRows.value.findIndex(row => row === id)
  if (index !== -1)
    selectedRows.value.splice(index, 1)

  // Tải lại danh sách sản phẩm
  fetchProducts()
}
</script>

<template>
  <div>
    <!-- 👉 widgets -->
    <VCard class="mb-6">
      <VCardText class="px-3">
        <VRow>
          <template
            v-for="(data, id) in widgetData"
            :key="id"
          >
            <VCol
              cols="12"
              sm="6"
              md="3"
              class="px-6"
            >
              <div
                class="d-flex justify-space-between"
                :class="$vuetify.display.xs
                  ? id !== widgetData.length - 1 ? 'border-b pb-4' : ''
                  : $vuetify.display.sm
                    ? id < (widgetData.length / 2) ? 'border-b pb-4' : ''
                    : ''"
              >
                <div class="d-flex flex-column gap-y-1">
                  <div class="text-body-1 text-capitalize">
                    {{ data.title }}
                  </div>

                  <h4 class="text-h4">
                    {{ data.value }}
                  </h4>

                  <div class="d-flex align-center gap-x-2">
                    <div class="text-no-wrap">
                      {{ data.desc }}
                    </div>

                    <VChip
                      v-if="data.change"
                      label
                      :color="data.change > 0 ? 'success' : 'error'"
                      size="small"
                    >
                      {{ prefixWithPlus(data.change) }}%
                    </VChip>
                  </div>
                </div>

                <VAvatar
                  variant="tonal"
                  rounded
                  size="44"
                >
                  <VIcon
                    :icon="data.icon"
                    size="28"
                    class="text-high-emphasis"
                  />
                </VAvatar>
              </div>
            </VCol>
            <VDivider
              v-if="$vuetify.display.mdAndUp ? id !== widgetData.length - 1
                : $vuetify.display.smAndUp ? id % 2 === 0
                  : false"
              vertical
              inset
              length="92"
            />
          </template>
        </VRow>
      </VCardText>
    </VCard>

    <!-- 👉 products -->
    <VCard
      title="Bộ lọc"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <!-- 👉 Lọc theo trạng thái is_active -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Status"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>

          <!-- 👉 Lọc theo nhóm sản phẩm (product_group_id) + nút thêm nhanh nhóm -->
          <VCol
            cols="12"
            sm="6"
          >
            <div class="d-flex align-center gap-x-4">
              <AppSelect
                v-model="selectedCategory"
                placeholder="Category"
                :items="categories"
                clearable
                clear-icon="tabler-x"
                class="flex-grow-1"
              />
              <ProductGroupCreateDialog @created="onGroupCreated" />
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <div class="d-flex flex-wrap gap-4 ma-6">
        <div class="d-flex align-center">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Tìm sản phẩm"
            style="inline-size: 200px;"
            class="me-3"
          />
        </div>

        <VSpacer />
        <div class="d-flex gap-4 flex-wrap align-center">
          <AppSelect
            v-model="itemsPerPage"
            :items="[5, 10, 20, 25, 50]"
          />
          <!-- 👉 Export button -->
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-upload"
          >
            Xuất file
          </VBtn>

          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="$router.push('/apps/ecommerce/product/add')"
          >
            Thêm sản phẩm
          </VBtn>
        </div>
      </div>

      <VDivider class="mt-4" />

      <!-- 👉 Datatable  -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        show-select
        :items="products"
        :items-length="totalProduct"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Sản phẩm: ảnh + tên từ các trường thật image_url / name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.image_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.image_url"
            />
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
          </div>
        </template>

        <!-- Nhóm sản phẩm: map product_group_id sang tên nhóm -->
        <template #item.product_group_id="{ item }">
          <span class="text-body-1 text-high-emphasis">{{ groupName(item.product_group_id) }}</span>
        </template>

        <!-- Giá: định dạng VND -->
        <template #item.price="{ item }">
          <span class="text-body-1">{{ formatPrice(item.price) }}</span>
        </template>

        <!-- Trạng thái: chip dựa trên is_active -->
        <template #item.is_active="{ item }">
          <VChip
            v-bind="resolveStatus(item.is_active)"
            density="default"
            label
            size="small"
          />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-edit" />
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem
                  value="download"
                  prepend-icon="tabler-download"
                >
                  Tải xuống
                </VListItem>

                <VListItem
                  value="delete"
                  prepend-icon="tabler-trash"
                  @click="deleteProduct(item.id)"
                >
                  Xóa
                </VListItem>

                <VListItem
                  value="duplicate"
                  prepend-icon="tabler-copy"
                >
                  Nhân bản
                </VListItem>
              </VList>
            </VMenu>
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalProduct"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
