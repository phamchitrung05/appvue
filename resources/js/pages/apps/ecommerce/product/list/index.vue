<script setup>
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { useProductStore } from '@/store/useProductStore'

const selectedStatus = ref(null) // lọc is_active: null | true | false
const selectedCategory = ref() // lọc theo product_group_id
const searchQuery = ref('')
const selectedRows = ref([])

// Debounce 500ms: useApi có refetch:true nên mỗi lần query đổi là một request —
// phải debounce ở nguồn để không gọi /v1/products cho từng ký tự người dùng gõ.
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

// ==================== Danh sách nhóm sản phẩm ====================
// Danh sách nhóm đọc từ Pinia store (store tự nạp lần đầu qua ensureLoaded) —
// dùng cho lọc Category + hiển thị tên nhóm, không gọi API riêng nữa.
const productGroupStore = useProductGroupStore()
const productStore = useProductStore()

await productGroupStore.ensureLoaded()

const groupList = computed(() => productGroupStore.productGroups)

// Biến danh sách nhóm thành options cho AppSelect: { title: tên, value: id }
const categories = computed(() =>
  groupList.value.map(group => ({ title: group.name, value: group.id })),
)

// Map product_group_id -> tên nhóm để hiển thị cột Nhóm hàng
const groupName = groupId =>
  groupList.value.find(group => group.id === groupId)?.name ?? '—'

// Định dạng giá theo VND (backend trả số dạng string "25000.00")
const formatPrice = value =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

// Chip trạng thái dựa trên is_active (boolean thật của model)
const resolveStatus = isActive =>
  isActive
    ? { text: 'Đang hoạt động', color: 'success' }
    : { text: 'Ngừng hoạt động', color: 'error' }

// ==================== Gọi API danh sách sản phẩm ====================
// Ghép query thủ công để BỎ QUA các filter đang rỗng. Tìm kiếm (q), lọc
// (product_group_id / is_active), sắp xếp (sortBy/orderBy) và phân trang
// đều xử lý PHÍA SERVER qua DataTableCriteria (giống kiểu cũ).
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

// Từ khoá / filter mới luôn quay về trang 1. Watch đặt TRƯỚC useApi để
// watcher refetch của useApi đọc giá trị page đã cập nhật.
watch([debouncedSearchQuery, selectedCategory, selectedStatus], () => {
  page.value = 1
})

// Endpoint thật của Laravel: GET /api/v1/products — shape datatable
// { products: [...], total, page, itemsPerPage, lastPage }
const {
  data: productsData,
  isFetching,
  execute: fetchProducts,
} = await useApi(createUrl('/v1/products', {
  query: productsQuery,
}))

const products = computed(() => productsData.value?.products ?? [])
const totalProduct = computed(() => productsData.value?.total ?? 0)

// Headers dùng key TRÙNG tên cột DB — sortBy gửi lên áp thẳng vào
// ORDER BY (whitelist $fieldSortable của ProductRepositoryEloquent)
const headers = [
  {
    title: 'Sản phẩm',
    key: 'name',
  },
  {
    title: 'Nhóm hàng',
    key: 'product_group_id',
    sortable: false,
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

// Xoá sản phẩm: ghi data đi qua store (store tự gọi API và loại bản ghi
// khỏi state tại chỗ) — bảng server-side vẫn tải lại trang hiện tại vì
// dữ liệu trang này đọc trực tiếp từ API chứ không qua Pinia.
const deleteProduct = async id => {
  const result = await productStore.removeProduct(id)

  if (!result.ok)
    return

  // Xoá khỏi danh sách đang chọn
  const index = selectedRows.value.findIndex(row => row === id)
  if (index !== -1)
    selectedRows.value.splice(index, 1)

  // Tải lại danh sách sản phẩm của trang hiện tại (server-side pagination)
  fetchProducts()
}
</script>

<template>
  <div>
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
              placeholder="Trạng thái"
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
                placeholder="Nhóm hàng"
                :items="categories"
                clearable
                clear-icon="tabler-x"
                class="flex-grow-1"
              />
              <!-- Store tự prepend nhóm mới sau create (không cần handler) -->
              <ProductGroupCreateDialog />
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <div class="d-flex flex-wrap gap-4 ma-6">
        <div class="d-flex align-center">
          <!-- 👉 Search qua criteria (q) phía server, debounce 500ms -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Tìm theo tên, mô tả, giá..."
            style="inline-size: 240px;"
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

      <!-- 👉 Datatable server-side (criteria: search/lọc/sort/phân trang) -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :headers="headers"
        :items="products"
        :items-length="totalProduct"
        :loading="isFetching"
        show-select
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
          <IconBtn @click.stop="$router.push(`/apps/ecommerce/product/edit/${item.id}`)">
            <VIcon icon="tabler-edit" />
            <VTooltip
              activator="parent"
              location="top"
            >
              Sửa sản phẩm
            </VTooltip>
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem
                  value="delete"
                  prepend-icon="tabler-trash"
                  @click="deleteProduct(item.id)"
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
            :total-items="totalProduct"
          />
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
