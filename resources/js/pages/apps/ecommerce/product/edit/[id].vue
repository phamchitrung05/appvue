<script setup>
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { useProductStore } from '@/store/useProductStore'
import { validationMessages } from '@/utils/validationMessages'

const route = useRoute()
const router = useRouter()

// ==================== Dữ liệu form (6 field của Product) ====================
const isSubmitting = ref(false)
const formRef = ref()

const form = ref({
  name: '',
  description: '',
  price: null,
  product_group_id: null,
  is_active: true,
  image_url: '',
})

// Quy tắc validate khớp updateRules() của ProductsController
const nameRules = [
  value => !!value?.trim() || validationMessages.product.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.product.nameMax,
]

const priceRules = [
  value => value !== null && value !== undefined && value !== '' || validationMessages.product.priceRequired,
  value => !Number.isNaN(Number(value)) || validationMessages.product.priceNumeric,
  value => Number(value) >= 0 || validationMessages.product.priceMin,
]

// ==================== Nhóm hàng cho select ====================
// Đọc từ Pinia store (tự nạp lần đầu qua ensureLoaded) — không gọi API riêng.
const productGroupStore = useProductGroupStore()
const productStore = useProductStore()

await productGroupStore.ensureLoaded()

const productGroups = computed(() =>
  productGroupStore.productGroups.map(group => ({ title: group.name, value: group.id })),
)

// ==================== Tải sản phẩm theo :id ====================
const productId = Number(route.params.id)

const {
  data: productData,
  error: productError,
} = await useApi(`/v1/products/${productId}`)

// Không tìm thấy sản phẩm (id sai/đã xóa)
const notFound = computed(() => !!productError.value)

// Nạp dữ liệu API vào form (price về số cho input number)
watch(productData, value => {
  if (!value)
    return

  form.value = {
    name: value.name ?? '',
    description: value.description ?? '',
    price: value.price !== null && value.price !== undefined ? Number(value.price) : null,
    product_group_id: value.product_group_id ?? null,
    is_active: !!value.is_active,
    image_url: value.image_url ?? '',
  }
}, { immediate: true })

// Toast toàn cục: hiện ở góc trên phải qua <AppToasts /> trong layout.
const showSnackbar = message => notify.success(message)
const showErrorSnackbar = message => notify.error(message)

// Payload khớp updateRules(): trường rỗng gửi null, price ép về số
const buildPayload = () => ({
  name: form.value.name.trim(),
  description: form.value.description || null,
  price: form.value.price === null || form.value.price === '' ? null : Number(form.value.price),
  product_group_id: form.value.product_group_id ?? null,
  is_active: form.value.is_active,
  image_url: form.value.image_url?.trim() || null,
})

const submitProduct = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // Ghi data đi qua store — store tự gọi API và vá đúng bản ghi trong state.
  const result = await productStore.updateProduct(productId, buildPayload())

  isSubmitting.value = false

  if (result.ok) {
    // Lưu xong quay về danh sách ngay (bản ghi trong Pinia đã được vá theo
    // id nên danh sách hiển thị giá trị mới, không cần refetch)
    router.push('/apps/ecommerce/product/list')

    return
  }

  showErrorSnackbar(result.message || validationMessages.product.updateFailed)
}
</script>

<template>
  <div>
    <!-- 👉 Không tìm thấy sản phẩm (id sai / đã xóa) -->
    <VAlert
      v-if="notFound"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      Không tìm thấy sản phẩm (id: {{ productId }}).

      <template #append>
        <VBtn
          variant="text"
          @click="router.push('/apps/ecommerce/product/list')"
        >
          Về danh sách
        </VBtn>
      </template>
    </VAlert>

    <div
      v-else
      class="d-flex flex-wrap align-center gap-4 mb-6"
    >
      <IconBtn @click="router.push('/apps/ecommerce/product/list')">
        <VIcon icon="tabler-arrow-left" />
      </IconBtn>

      <h4 class="text-h4 font-weight-medium">
        Sửa sản phẩm
      </h4>
    </div>

    <VForm
      v-if="!notFound"
      ref="formRef"
      @submit.prevent="submitProduct"
    >
      <VRow>
        <VCol
          md="8"
          cols="12"
        >
          <!-- 👉 Thông tin sản phẩm -->
          <VCard
            class="mb-6"
            title="Thông tin sản phẩm"
          >
            <VCardText>
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.name"
                    label="Tên sản phẩm"
                    placeholder="Cà phê sữa đá"
                    :rules="nameRules"
                  />
                </VCol>
                <VCol cols="12">
                  <span class="mb-1 d-inline-block">Mô tả (tuỳ chọn)</span>
                  <ProductDescriptionEditor
                    v-model="form.description"
                    placeholder="Mô tả sản phẩm"
                    class="border rounded"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- 👉 Hình ảnh: model chỉ lưu đường dẫn (image_url) -->
          <VCard
            title="Hình ảnh"
            class="mb-6"
          >
            <VCardText>
              <AppTextField
                v-model="form.image_url"
                label="Đường dẫn hình ảnh (URL)"
                placeholder="https://example.com/images/cafe-sua.jpg"
                clearable
              />
              <div
                v-if="form.image_url"
                class="d-flex justify-center mt-4"
              >
                <VImg
                  :src="form.image_url"
                  max-width="320"
                  max-height="220"
                  class="rounded border"
                  cover
                />
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          md="4"
          cols="12"
        >
          <!-- 👉 Giá -->
          <VCard
            title="Giá bán"
            class="mb-6"
          >
            <VCardText>
              <AppTextField
                v-model="form.price"
                label="Giá bán (VNĐ)"
                placeholder="25000"
                type="number"
                min="0"
                step="1000"
                :rules="priceRules"
                class="mb-6"
              />

              <VDivider class="my-2" />

              <div class="d-flex align-center justify-space-between">
                <span>Đang bán</span>
                <VSwitch
                  v-model="form.is_active"
                  density="compact"
                />
              </div>
            </VCardText>
          </VCard>

          <!-- 👉 Tổ chức -->
          <VCard title="Tổ chức">
            <VCardText>
              <div class="d-flex flex-column gap-y-4">
                <AppSelect
                  v-model="form.product_group_id"
                  placeholder="Chọn nhóm hàng"
                  :items="productGroups"
                  clearable
                  clear-icon="tabler-x"
                />
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- 👉 Hàng nút cố định dưới cùng -->
      <div class="d-flex justify-end gap-4 mb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="router.push('/apps/ecommerce/product/list')"
        >
          Hủy bỏ
        </VBtn>

        <VBtn
          type="submit"
          :loading="isSubmitting"
        >
          Lưu thay đổi
        </VBtn>
      </div>
    </VForm>
  </div>
</template>
