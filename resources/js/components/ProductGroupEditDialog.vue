<script setup>
import { useProductGroupStore } from '@/store/useProductGroupStore'
import { validationMessages } from '@/utils/validationMessages'

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

// Dialog sửa nhóm hàng: nhận `group` (bản ghi hiện tại) + v-model điều
// khiển mở/đóng. Lưu thành công emit `saved` kèm bản ghi đã cập nhật —
// ghi data đi qua store, store tự vá state theo id.

const productGroupStore = useProductGroupStore()

const isSubmitting = ref(false)
const formRef = ref()

// Toast toàn cục: hiện ở góc trên phải qua <AppToasts /> trong layout.
const showSnackbar = message => notify.success(message)
const showErrorSnackbar = message => notify.error(message)

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

const submitGroup = async () => {
  const { valid } = await formRef.value.validate()

  if (!valid)
    return

  isSubmitting.value = true

  const result = await productGroupStore.updateProductGroup(props.group.id, {
    name: form.value.name.trim(),
    is_active: form.value.is_active,
  })

  isSubmitting.value = false

  if (result.ok) {
    close()
    showSnackbar(validationMessages.productGroup.updateSuccess)
    emit('saved', result.record)

    return
  }

  showErrorSnackbar(result.message || validationMessages.productGroup.updateFailed)
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
</template>
