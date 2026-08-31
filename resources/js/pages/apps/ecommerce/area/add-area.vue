<script setup>
import { validationMessages } from '@/utils/validationMessages'

const router = useRouter()

const isSubmitting = ref(false)
const formRef = ref()
const snackbar = ref({ show: false, message: '', color: 'error' })

const form = ref({
  name: '',
  store_id: null,
  is_active: true,
})

const nameRules = [
  value => !!value?.trim() || validationMessages.area.nameRequired,
  value => (value?.trim().length || 0) <= 255 || validationMessages.area.nameMax,
]

const storeRules = [
  value => value !== null && value !== undefined || validationMessages.area.storeRequired,
]

// Cửa hàng cho select: khu luôn thuộc một cửa hàng (table_zones.store_id)
const { data: storesData } = await useApi(createUrl('/v1/stores', {
  query: { per_page: 100, sortBy: 'name', orderBy: 'asc' },
}))

const storeOptions = computed(() =>
  (storesData.value?.data ?? []).map(store => ({ title: store.name, value: store.id })),
)

const showSnackbar = (message, color = 'error') => {
  snackbar.value = { show: true, message, color }
}

const submitArea = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // statusCode/error là REF (xem ghi chú ở trang thêm sản phẩm)
  const { error, statusCode } = await useApi('/v1/table-zones', {
    method: 'POST',
    body: {
      name: form.value.name.trim(),
      store_id: form.value.store_id,
      is_active: form.value.is_active,
    },
  }).json()

  isSubmitting.value = false

  const isOk = (statusCode.value ?? 0) >= 200 && statusCode.value < 300

  if (isOk) {
    showSnackbar(validationMessages.area.createSuccess, 'success')

    // Khu mới xong — sang sơ đồ bàn để thêm bàn vào khu vừa tạo
    setTimeout(() => router.push('/apps/ecommerce/area/add-table'), 900)

    return
  }

  let message = validationMessages.area.createFailed

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
  <div class="d-flex justify-center">
    <VCard
      title="Thêm khu vực"
      class="mt-6"
      style="inline-size: 560px;"
    >
      <VForm
        ref="formRef"
        @submit.prevent="submitArea"
      >
        <VCardText>
          <AppTextField
            v-model="form.name"
            label="Tên khu"
            placeholder="Khu sân thượng"
            :rules="nameRules"
          />

          <AppSelect
            v-model="form.store_id"
            label="Cửa hàng"
            placeholder="Chọn cửa hàng"
            :items="storeOptions"
            :rules="storeRules"
            class="mt-4"
          />

          <div class="d-flex align-center justify-space-between mt-4">
            <span>Đang hoạt động</span>
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
            @click="router.push('/apps/ecommerce/area/add-table')"
          >
            Quay lại sơ đồ bàn
          </VBtn>

          <VBtn
            type="submit"
            :loading="isSubmitting"
          >
            Tạo khu
          </VBtn>
        </VCardActions>
      </VForm>
    </VCard>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
    >
      {{ snackbar.message }}
    </VSnackbar>
  </div>
</template>
