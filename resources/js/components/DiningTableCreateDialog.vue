<script setup>
import { useDiningTableStore } from '@/store/useDiningTableStore'
import { validationMessages } from '@/utils/validationMessages'

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

// Dialog thêm nhanh bàn vào khu trên sơ đồ bàn (Order/List).
// Trang cha truyền `zones` (danh sách khu từ Pinia store, có store_id) và
// `zone-id` (khu đang chọn — "Tất cả" thì mặc định khu đầu tiên).
// Ghi data đi qua store — store tự gọi API và chèn bàn vào đầu state.
const diningTableStore = useDiningTableStore()

defineOptions({ inheritAttrs: false })

const isDialogOpen = ref(false)
const isSubmitting = ref(false)
const formRef = ref()

// Toast toàn cục: hiện ở góc trên phải qua <AppToasts /> trong layout.
// notify.success / notify.error giữ nguyên tên kind khi gọi.
const showSnackbar = message => notify.success(message)
const showErrorSnackbar = message => notify.error(message)

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

const submitTable = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  // store_id lấy theo khu được chọn để dữ liệu nhất quán với cửa hàng
  const zone = props.zones.find(item => item.id === form.value.zone_id)

  const result = await diningTableStore.createDiningTable({
    name: form.value.name.trim(),
    zone_id: form.value.zone_id,
    store_id: zone?.store_id ?? null,
  })

  isSubmitting.value = false

  if (result.ok) {
    isDialogOpen.value = false
    showSnackbar(validationMessages.diningTable.createSuccess)
    emit('created', result.record)

    return
  }

  showErrorSnackbar(result.message || validationMessages.diningTable.createFailed)
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
</template>
