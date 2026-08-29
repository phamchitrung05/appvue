<script setup>
import { validationMessages } from '@/utils/validationMessages'

// Component tự chứa: nút dấu + mở dialog thêm nhanh nhóm hàng.
// Sau khi tạo thành công sẽ emit `created` kèm bản ghi nhóm mới để trang
// cha tải lại danh sách option (và tự chọn nếu muốn).

const emit = defineEmits(['created'])

// Cho phép trang cha truyền class/style vào nút + (vd căn hàng khi select có label)
defineOptions({ inheritAttrs: false })

const isDialogOpen = ref(false)
const isSubmitting = ref(false)
const formRef = ref()
const snackbar = ref({ show: false, message: '', color: 'error' })

const form = ref({
  // name bắt buộc ≤255 (createRules của ProductGroupsController);
  // store_id/sort_order để null vì là trường nullable.
  name: '',
  is_active: true,
})

const nameRules = [
  value => !!value?.trim() || validationMessages.productGroup.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.productGroup.nameMax,
]

const showSnackbar = (message, color = 'error') => {
  snackbar.value = { show: true, message, color }
}

const openDialog = () => {
  form.value.name = ''
  form.value.is_active = true
  isDialogOpen.value = true
}

const submitGroup = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // statusCode/error là REF (xem ghi chú ở trang thêm sản phẩm)
  const { data, error, statusCode } = await useApi('/v1/product-groups', {
    method: 'POST',
    body: {
      name: form.value.name.trim(),
      is_active: form.value.is_active,
    },
  }).json()

  isSubmitting.value = false

  const isOk = (statusCode.value ?? 0) >= 200 && statusCode.value < 300

  if (isOk) {
    isDialogOpen.value = false
    showSnackbar(validationMessages.productGroup.createSuccess, 'success')
    emit('created', data.value)

    return
  }

  let message = validationMessages.productGroup.createFailed

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
  <!--
    Trigger mặc định: nút + — trang cha có thể thay bằng slot #activator
    (nhận `open` để tự mở dialog) 
  -->
  <slot
    name="activator"
    :open="openDialog"
  >
    <VBtn
      v-bind="$attrs"
      icon="tabler-plus"
      variant="tonal"
      rounded
      aria-label="Thêm nhóm hàng"
      @click="openDialog"
    />
  </slot>

  <VDialog
    v-model="isDialogOpen"
    max-width="480"
  >
    <VCard title="Thêm nhóm hàng">
      <VForm
        ref="formRef"
        @submit.prevent="submitGroup"
      >
        <VCardText>
          <AppTextField
            v-model="form.name"
            label="Tên nhóm hàng"
            placeholder="Tráng miệng"
            :rules="nameRules"
            autofocus
          />

          <div class="d-flex align-center justify-space-between mt-4">
            <span>Đang bán</span>
            <VSwitch
              v-model="form.is_active"
              density="compact"
            />
          </div>
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

  <VSnackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="top"
  >
    {{ snackbar.message }}
  </VSnackbar>
</template>
