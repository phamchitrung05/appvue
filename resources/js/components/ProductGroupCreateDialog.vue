<script setup>
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { validationMessages } from '@/utils/validationMessages'

const emit = defineEmits(['created'])

// Component tự chứa: nút dấu + mở dialog thêm nhanh nhóm hàng.
// Sau khi tạo thành công sẽ emit `created` kèm bản ghi nhóm mới để trang
// cha làm tiếp (chọn nhóm, gán sort_order...). Ghi data đi qua store —
// store tự gọi API và chèn bản ghi vào đầu state.

const productGroupStore = useProductGroupStore()

// Cho phép trang cha truyền class/style vào nút + (vd căn hàng khi select có label)
defineOptions({ inheritAttrs: false })

const isDialogOpen = ref(false)
const isSubmitting = ref(false)
const formRef = ref()

// Toast toàn cục: hiện ở góc trên phải qua <AppToasts /> trong layout.
const showSnackbar = message => notify.success(message)
const showErrorSnackbar = message => notify.error(message)

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

  const result = await productGroupStore.createProductGroup({
    name: form.value.name.trim(),
    is_active: form.value.is_active,
  })

  isSubmitting.value = false

  if (result.ok) {
    isDialogOpen.value = false
    showSnackbar(validationMessages.productGroup.createSuccess)
    emit('created', result.record)

    return
  }

  showErrorSnackbar(result.message || validationMessages.productGroup.createFailed)
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
</template>
