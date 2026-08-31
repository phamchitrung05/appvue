<script setup>
import { useStoreStore } from '@/store/useStoreStore'
import { useTableZoneStore } from '@/store/useTableZoneStore'
import { validationMessages } from '@/utils/validationMessages'

const tableZoneStore = useTableZoneStore()
const storeStore = useStoreStore()

const router = useRouter()

const isSubmitting = ref(false)
const formRef = ref()

// Toast toàn cục: hiện ở góc trên phải qua <AppToasts /> trong layout.
const showSnackbar = message => notify.success(message)
const showErrorSnackbar = message => notify.error(message)

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

// Cửa hàng cho select: khu luôn thuộc một cửa hàng — đọc từ Pinia store
// (tự nạp lần đầu qua ensureLoaded), vào lại trang dùng ngay cache.
await storeStore.ensureLoaded()

const storeOptions = computed(() =>
  storeStore.stores.map(store => ({ title: store.name, value: store.id })),
)

const submitArea = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // Ghi data đi qua store — store tự gọi API và chèn khu mới vào đầu state
  const result = await tableZoneStore.createTableZone({
    name: form.value.name.trim(),
    store_id: form.value.store_id,
    is_active: form.value.is_active,
  })

  isSubmitting.value = false

  if (result.ok) {
    showSnackbar(validationMessages.area.createSuccess)

    // Khu mới xong — sang sơ đồ bàn để thêm bàn vào khu vừa tạo
    setTimeout(() => router.push('/apps/ecommerce/cashier'), 900)

    return
  }

  showErrorSnackbar(result.message || validationMessages.area.createFailed)
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
            @click="router.push('/apps/ecommerce/cashier')"
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
  </div>
</template>
