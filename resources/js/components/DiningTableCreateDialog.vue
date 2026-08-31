<script setup>
import { validationMessages } from '@/utils/validationMessages'

// Dialog thêm nhanh bàn vào khu trên sơ đồ bàn (Order/List).
// Trang cha truyền `zones` (danh sách khu từ API floor, có store_id) và
// `zone-id` (khu đang chọn — "Tất cả" thì mặc định khu đầu tiên).
// Tạo thành công emit `created` để trang cha tải lại sơ đồ.
const props = defineProps({
  zones: {
    type: Array,
    default: () => [],
  },
  zoneId: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['created'])

defineOptions({ inheritAttrs: false })

const isDialogOpen = ref(false)
const isSubmitting = ref(false)
const formRef = ref()
const snackbar = ref({ show: false, message: '', color: 'error' })

const form = ref({
  name: '',
  zone_id: null,
})

const nameRules = [
  value => !!value?.trim() || validationMessages.diningTable.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.diningTable.nameMax,
]

const zoneRules = [
  value => value !== null && value !== undefined || validationMessages.diningTable.zoneRequired,
]

// Khu mặc định: khu đang chọn trên trang; đang ở "Tất cả" thì lấy khu đầu tiên
const openDialog = () => {
  form.value = {
    name: '',
    zone_id: typeof props.zoneId === 'number' ? props.zoneId : props.zones[0]?.id ?? null,
  }
  isDialogOpen.value = true
}

const showSnackbar = (message, color = 'error') => {
  snackbar.value = { show: true, message, color }
}

const submitTable = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // store_id lấy theo khu được chọn để dữ liệu nhất quán với cửa hàng
  const zone = props.zones.find(item => item.id === form.value.zone_id)

  // statusCode/error là REF (xem ghi chú ở trang thêm sản phẩm)
  const { data, error, statusCode } = await useApi('/v1/dining-tables', {
    method: 'POST',
    body: {
      name: form.value.name.trim(),
      zone_id: form.value.zone_id,
      store_id: zone?.store_id ?? null,
    },
  }).json()

  isSubmitting.value = false

  const isOk = (statusCode.value ?? 0) >= 200 && statusCode.value < 300

  if (isOk) {
    isDialogOpen.value = false
    showSnackbar(validationMessages.diningTable.createSuccess, 'success')
    emit('created', data.value)

    return
  }

  let message = validationMessages.diningTable.createFailed

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
  <!-- Trigger mặc định: nút + — trang cha có thể thay bằng slot #activator -->
  <slot
    name="activator"
    :open="openDialog"
  >
    <VBtn
      v-bind="$attrs"
      icon="tabler-plus"
      variant="tonal"
      rounded
      aria-label="Thêm bàn"
      @click="openDialog"
    />
  </slot>

  <VDialog
    v-model="isDialogOpen"
    max-width="480"
  >
    <VCard title="Thêm bàn vào khu">
      <VForm
        ref="formRef"
        @submit.prevent="submitTable"
      >
        <VCardText>
          <AppTextField
            v-model="form.name"
            label="Tên bàn"
            placeholder="Bàn 05"
            :rules="nameRules"
          />

          <AppSelect
            v-model="form.zone_id"
            label="Khu vực"
            placeholder="Chọn khu vực"
            :items="zones.map(zone => ({ title: zone.name, value: zone.id }))"
            :rules="zoneRules"
            class="mt-4"
          />
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
