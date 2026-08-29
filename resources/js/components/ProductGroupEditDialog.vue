<script setup>
import { validationMessages } from '@/utils/validationMessages'

// Dialog sửa nhóm hàng: nhận `group` (bản ghi hiện tại) + v-model điều
// khiển mở/đóng. Lưu thành công emit `saved` kèm bản ghi đã cập nhật để
// trang cha phản ánh ngay vào danh sách.
const props = defineProps({
  group: {
    type: Object,
    default: null,
  },
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const isSubmitting = ref(false)
const formRef = ref()
const snackbar = ref({ show: false, message: '', color: 'error' })

const form = ref({
  name: '',
  is_active: true,
})

const nameRules = [
  value => !!value?.trim() || validationMessages.productGroup.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.productGroup.nameMax,
]

// Mở dialog → nạp dữ liệu nhóm hiện tại vào form
watch(() => props.modelValue, isOpen => {
  if (isOpen && props.group)
    form.value = {
      name: props.group.name,
      is_active: !!props.group.is_active,
    }
})

const close = () => emit('update:modelValue', false)

const showSnackbar = (message, color = 'error') => {
  snackbar.value = { show: true, message, color }
}

const submitGroup = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // statusCode/error là REF (xem ghi chú ở trang thêm sản phẩm)
  const { data, error, statusCode } = await useApi(`/v1/product-groups/${props.group.id}`, {
    method: 'PUT',
    body: {
      name: form.value.name.trim(),
      is_active: form.value.is_active,
    },
  }).json()

  isSubmitting.value = false

  const isOk = (statusCode.value ?? 0) >= 200 && statusCode.value < 300

  if (isOk) {
    close()
    showSnackbar(validationMessages.productGroup.updateSuccess, 'success')
    emit('saved', data.value)

    return
  }

  let message = validationMessages.productGroup.updateFailed

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
  <VDialog
    :model-value="modelValue"
    max-width="480"
    @update:model-value="close"
  >
    <VCard title="Sửa nhóm hàng">
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
            @click="close"
          >
            Hủy bỏ
          </VBtn>

          <VBtn
            type="submit"
            :loading="isSubmitting"
          >
            Lưu
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
