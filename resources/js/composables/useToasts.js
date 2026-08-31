// ==================== Toast thông báo toàn cục ====================
// Gom toàn bộ snackbar của app về một nơi duy nhất: mọi component gọi
// `notify(...)` thay vì tự dựng VSnackbar riêng. Component hiển thị duy nhất
// là <AppToasts /> đã gắn trong layout — đổi vị trí/màu/thời gian chỉ cần
// sửa ở đây.
import { readonly, ref } from 'vue'

/** Danh sách toast đang hiển thị, mỗi toast có id riêng để đóng được. */
const toasts = ref([])
let nextId = 1

// Thời gian hiển thị mặc định (ms) — chỉnh một chỗ áp dụng cho mọi toast.
const DEFAULT_DURATION = 4000

/**
 * Đóng một toast theo id (dùng bởi nút close và timeout tự huỷ).
 *
 * @param {number} id Id của toast cần đóng.
 * @returns {void}
 */
export const dismissToast = id => {
  toasts.value = toasts.value.filter(toast => toast.id !== id)
}

/**
 * Hiện một toast thông báo ở góc TRÊN PHẢI màn hình.
 *
 * @param {string} message Nội dung thông báo.
 * @param {string} [kind='success'] Loại toast: success | error | warning | info — quyết định màu.
 * @param {number} [duration] Thời gian hiển thị (ms), bỏ qua dùng mặc định.
 * @returns {void}
 */
export const notify = (message, kind = 'success', duration = DEFAULT_DURATION) => {
  const id = nextId++

  toasts.value.push({ id, message, color: kind })

  setTimeout(() => {
    dismissToast(id)
  }, duration)
}

// Shorthand theo loại để gọi gọn: notify.success('...'), notify.error('...')...
notify.success = (message, duration) => notify(message, 'success', duration)
notify.error = (message, duration) => notify(message, 'error', duration)
notify.warning = (message, duration) => notify(message, 'warning', duration)
notify.info = (message, duration) => notify(message, 'info', duration)

/**
 * Composable cho component hiển thị (<AppToasts />) — đọc danh sách và đóng.
 *
 * @returns {{ toasts: import('vue').Readonly<import('vue').Ref<Array>>, dismissToast: (id: number) => void }}
 */
export const useToasts = () => ({
  toasts: readonly(toasts),
  dismissToast,
})
