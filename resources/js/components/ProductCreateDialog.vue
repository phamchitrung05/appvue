<script setup>
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { useProductStore } from '@/store/useProductStore'
import { validationMessages } from '@/utils/validationMessages'

// Dialog thêm nhanh sản phẩm — field khớp trang product/add:
// name, price, product_group_id, is_active, image_url, description.
// Trang cha truyền `group-id` để chọn sẵn nhóm đang xem; sau khi tạo
// thành công emit `created` kèm bản ghi sản phẩm mới.
const props = defineProps({
  groupId: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['created'])

defineOptions({ inheritAttrs: false })

const isDialogOpen = ref(false)
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

// Quy tắc validate khớp createRules() của ProductsController
const nameRules = [
  value => !!value?.trim() || validationMessages.product.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.product.nameMax,
]

const priceRules = [
  value => value !== null && value !== undefined && value !== '' || validationMessages.product.priceRequired,
  value => !Number.isNaN(Number(value)) || validationMessages.product.priceNumeric,
  value => Number(value) >= 0 || validationMessages.product.priceMin,
]

// Danh sách nhóm cho select đọc từ Pinia store — store tự nạp lần đầu qua
// ensureLoaded, dialog KHÔNG tự gọi API product-groups (trước đây mỗi lần
// mount lại phát sinh thêm một request trùng với store của trang cha).
const productGroupStore = useProductGroupStore()
const productStore = useProductStore()

const productGroups = computed(() =>
  productGroupStore.productGroups.map(group => ({ title: group.name, value: group.id })),
)

await productGroupStore.ensureLoaded()

// Toast toàn cục: hiện ở góc trên phải qua <AppToasts /> trong layout.
const showSnackbar = message => notify.success(message)
const showErrorSnackbar = message => notify.error(message)

const openDialog = () => {
  form.value = {
    name: '',
    description: '',
    price: null,
    product_group_id: props.groupId ?? productGroups.value[0]?.value ?? null,
    is_active: true,
    image_url: '',
  }
  isDialogOpen.value = true
}

// Payload khớp createRules(): trường rỗng gửi null, price ép về số
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

  // Ghi data đi qua store — store tự gọi API và vá state (prependRecord).
  const result = await productStore.createProduct(buildPayload())

  isSubmitting.value = false

  if (result.ok) {
    isDialogOpen.value = false
    showSnackbar(validationMessages.product.createSuccess.replace(':name', result.record?.name ?? ''))
    emit('created', result.record)

    return
  }

  showErrorSnackbar(result.message || validationMessages.product.createFailed)
}
</script>

<template>
  <!-- Trigger mặc định: nút + — trang cha có thể thay bằng slot #activator -->
  <slot
    name="activator"
    :open="openDialog"
  >
    <VBtn
      v-bind="$attrs"
      prepend-icon="tabler-plus"
      aria-label="Thêm sản phẩm mới"
      @click="openDialog"
    />
  </slot>

  <VDialog
    v-model="isDialogOpen"
    max-width="640"
  >
    <VCard title="Thêm sản phẩm mới">
      <VForm
        ref="formRef"
        @submit.prevent="submitProduct"
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

            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.price"
                label="Giá bán (VNĐ)"
                placeholder="25000"
                type="number"
                min="0"
                :rules="priceRules"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
              class="d-flex align-center justify-space-between"
            >
              <span>Đang bán</span>
              <VSwitch
                v-model="form.is_active"
                density="compact"
              />
            </VCol>

            <VCol cols="12">
              <AppSelect
                v-model="form.product_group_id"
                label="Nhóm hàng"
                placeholder="Chọn nhóm hàng"
                :items="productGroups"
                clear-icon="tabler-x"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="form.image_url"
                label="Đường dẫn hình ảnh (URL)"
                placeholder="https://example.com/images/cafe-sua.jpg"
                clearable
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Mô tả (tuỳ chọn)"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />

          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isSubmitting"
            @click="isDialogOpen = false"
          >
            Hủy bỏ
          </VBtn>

          <VBtn
            type="submit"
            :loading="isSubmitting"
          >
            Thêm
          </VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>
