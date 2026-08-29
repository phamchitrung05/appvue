<script setup>
import { validationMessages } from '@/utils/validationMessages'

const form = ref({
  // Các field khớp 1-1 với createRules() của ProductsController:
  // name (bắt buộc, ≤255), price (bắt buộc, số, ≥0), description,
  // product_group_id, is_active, image_url (các trường tuỳ chọn).
  name: '',
  description: '',
  price: null,
  product_group_id: null,
  is_active: true,
  image_url: '',
})

const formRef = ref()
const isSubmitting = ref(false)
const snackbar = ref({ show: false, message: '', color: 'error' })

const router = useRouter()
const route = useRoute()

// Quy tắc validate phía client khớp createRules() của backend —
// server vẫn validate lại, đây chỉ là lớp chặn sớm cho UX.
const nameRules = [
  value => !!value?.trim() || validationMessages.product.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.product.nameMax,
]

const priceRules = [
  value => value !== null && value !== undefined && value !== '' || validationMessages.product.priceRequired,
  value => !Number.isNaN(Number(value)) || validationMessages.product.priceNumeric,
  value => Number(value) >= 0 || validationMessages.product.priceMin,
]

// ==================== Danh sách nhóm sản phẩm ====================
// Endpoint thật /v1/product-groups: sau khi useApi bóc envelope, mảng bản
// ghi nằm ở thuộc tính `data`. Biến thành options { title, value: id }.
const { data: groupsData, execute: refreshGroups } = await useApi(createUrl('/v1/product-groups', {
  query: { per_page: 100 },
}))

const productGroups = computed(() =>
  (groupsData.value?.data ?? []).map(group => ({ title: group.name, value: group.id })),
)

// Chọn nhóm ban đầu: ưu tiên nhóm truyền từ trang danh mục (?group=<id> —
// khi bấm "Thêm mới" trong một nhóm); không hợp lệ thì chọn nhóm đầu tiên.
const requestedGroupId = Number(route.query.group)
const hasRequestedGroup = productGroups.value.some(group => group.value === requestedGroupId)

form.value.product_group_id = hasRequestedGroup ? requestedGroupId : productGroups.value[0]?.value ?? null

// Nhóm mới vừa tạo qua dialog: tải lại options và tự chọn nhóm đó luôn
const onGroupCreated = async group => {
  await refreshGroups()
  form.value.product_group_id = group?.id ?? form.value.product_group_id
}

// ==================== Gửi form ====================
// Dựng payload khớp createRules(): trường rỗng gửi null thay vì chuỗi rỗng,
// price ép về số. Tạo thành công (2xx) quay về trang danh sách.
const buildPayload = () => ({
  name: form.value.name.trim(),
  description: form.value.description || null,
  price: form.value.price === null || form.value.price === '' ? null : Number(form.value.price),
  product_group_id: form.value.product_group_id ?? null,
  is_active: form.value.is_active,
  image_url: form.value.image_url?.trim() || null,
})

const showSnackbar = (message, color = 'error') => {
  snackbar.value = { show: true, message, color }
}

const submitProduct = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // useApi bóc envelope nên response thành công nằm ở `data`; lỗi (4xx/5xx)
  // nằm ở `error` dạng text — parse ra để lấy message tiếng Việt từ config.
  const { error, statusCode } = await useApi('/v1/products', {
    method: 'POST',
    body: buildPayload(),
  }).json()

  isSubmitting.value = false

  // useApi trả về các REF (giống productsData.value ở trang list) nên phải
  // đọc .value — so sánh trực tiếp statusCode sẽ luôn false.
  const isOk = (statusCode.value ?? 0) >= 200 && statusCode.value < 300

  if (isOk) {
    router.push('/apps/ecommerce/product/list')

    return
  }

  let message = validationMessages.product.createFailed

  try {
    const body = JSON.parse(error.value)

    if (body?.message)
      message = body.message
  }
  catch {
    // error không phải JSON (lỗi mạng...) — giữ message mặc định
  }

  showSnackbar(message)
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Thêm sản phẩm mới
        </h4>
        <div class="text-body-1">
          Nhập thông tin sản phẩm bán tại quầy và trên website
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="$router.push('/apps/ecommerce/product/list')"
        >
          Hủy bỏ
        </VBtn>
        <VBtn
          :loading="isSubmitting"
          @click="submitProduct"
        >
          Thêm sản phẩm
        </VBtn>
      </div>
    </div>

    <VForm
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
              </VRow>
            </VCardText>
          </VCard>

          <!--
            👉 Hình ảnh: model chỉ lưu đường dẫn (image_url) nên nhập URL,
            chưa có endpoint upload file 
          -->
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

          <!-- 👉 Nhóm Sản Phẩm -->
          <VCard title="Nhóm Sản Phẩm">
            <VCardText>
              <div class="d-flex flex-column gap-y-4">
                <div class="d-flex align-center gap-x-4">
                  <AppSelect
                    v-model="form.product_group_id"
                    placeholder="Chọn nhóm hàng"
                    :items="productGroups"
                    clearable
                    clear-icon="tabler-x"
                    class="flex-grow-1"
                  />
                  <ProductGroupCreateDialog @created="onGroupCreated" />
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
    >
      {{ snackbar.message }}
    </VSnackbar>
  </div>
</template>

<style lang="scss">
.ProseMirror {
  p {
    margin-block-end: 0;
  }

  padding: 0.5rem;
  outline: none;

  p.is-editor-empty:first-child::before {
    block-size: 0;
    color: #adb5bd;
    content: attr(data-placeholder);
    float: inline-start;
    pointer-events: none;
  }
}
</style>
