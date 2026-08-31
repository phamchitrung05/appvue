<script setup>
const router = useRouter()

// ==================== Bộ lọc & phân trang ====================
// selectedStatus: null | true | false — lọc theo cột is_active của DB.
const selectedStatus = ref(null)
const searchQuery = ref('')
const selectedRows = ref([])

// Debounce 500ms: useApi có refetch:true nên mỗi lần query đổi là một request —
// phải debounce ở nguồn để không gọi /v1/stores cho từng ký tự người dùng gõ.
const debouncedSearchQuery = refDebounced(searchQuery, 500)

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
    title: 'Đang hoạt động',
    value: true,
  },
  {
    title: 'Ngừng hoạt động',
    value: false,
  },
])

// Ghép query thủ công để BỎ QUA các filter đang rỗng — gửi is_active=null
// lên backend sẽ lọc sai
const storesQuery = computed(() => {
  const query = {
    page: page.value,
    per_page: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  }

  if (debouncedSearchQuery.value)
    query.q = debouncedSearchQuery.value

  if (selectedStatus.value !== null && selectedStatus.value !== undefined)
    query.is_active = selectedStatus.value

  return query
})

// Tìm kiếm/lọc mới quay về trang 1. Watch đặt TRƯỚC useApi để watcher
// refetch của useApi chạy sau, đọc giá trị page đã cập nhật — tránh gọi 2 lần.
watch([debouncedSearchQuery, selectedStatus], () => {
  page.value = 1
})

// Endpoint thật: GET /api/v1/stores — StoresController không override
// indexResponse nên trả shape phân trang gốc: { data, current_page, total, last_page }
const {
  data: storesData,
  isFetching,
  execute: fetchStores,
} = await useApi(createUrl('/v1/stores', {
  query: storesQuery,
}))

const stores = computed(() => storesData.value?.data ?? [])
const totalStores = computed(() => storesData.value?.total ?? 0)

// Headers dùng key TRÙNG tên cột DB (name, address, phone, email, is_active)
// — sortBy gửi lên áp thẳng vào ORDER BY; whitelist sort nằm ở
// StoreRepositoryEloquent::$fieldSortable
const headers = [
  {
    title: 'Cửa hàng',
    key: 'name',
  },
  {
    title: 'Địa chỉ',
    key: 'address',
  },
  {
    title: 'Điện thoại',
    key: 'phone',
  },
  {
    title: 'Email',
    key: 'email',
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

// Chip trạng thái dựa trên is_active (boolean thật của model)
const resolveStatus = isActive =>
  isActive
    ? { text: 'Đang hoạt động', color: 'success' }
    : { text: 'Ngừng hoạt động', color: 'error' }

// Xoá cửa hàng qua API thật rồi refetch lại danh sách
const deleteStore = async id => {
  await useApi(`/v1/stores/${id}`, { method: 'DELETE' }).json()

  // Xoá khỏi danh sách đang chọn
  const index = selectedRows.value.findIndex(row => row === id)

  if (index !== -1)
    selectedRows.value.splice(index, 1)

  fetchStores()
}
</script>

<template>
  <div>
    <!-- 👉 Bộ lọc -->
    <VCard
      title="Bộ lọc"
      class="mb-6"
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <!-- 👉 Lọc theo trạng thái is_active -->
            <AppSelect
              v-model="selectedStatus"
              placeholder="Trạng thái"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <div class="d-flex flex-wrap gap-4 ma-6">
        <div class="d-flex align-center">
          <!-- 👉 Search -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Tìm cửa hàng"
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

          <!-- 👉 Thêm cửa hàng -->
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="router.push('/apps/ecommerce/store/add')"
          >
            Thêm cửa hàng
          </VBtn>
        </div>
      </div>

      <VDivider class="mt-4" />

      <!-- 👉 Datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        :items="stores"
        :items-length="totalStores"
        :loading="isFetching"
        show-select
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Cửa hàng: avatar + tên -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              variant="tonal"
              rounded
              size="38"
            >
              <VIcon icon="tabler-building-store" />
            </VAvatar>
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
          </div>
        </template>

        <!-- Địa chỉ: truncate nếu quá dài -->
        <template #item.address="{ item }">
          <span
            class="text-body-2 text-medium-emphasis d-inline-block"
            style="max-inline-size: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
            :title="item.address"
          >
            {{ item.address || '—' }}
          </span>
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
            <VTooltip
              activator="parent"
              location="top"
            >
              Sửa cửa hàng
            </VTooltip>
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem
                  value="delete"
                  prepend-icon="tabler-trash"
                  @click="deleteStore(item.id)"
                >
                  Xóa
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
            :total-items="totalStores"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
