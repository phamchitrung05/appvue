import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isSuccessStatus, parseApiError } from '@/utils/apiMutation'

export const useProductGroupStore = defineStore('productGroup', () => {
  const productGroups = ref([])
  const isLoading = ref(false)

  // Đánh dấu store đã nhận response thành công, kể cả khi chưa có danh mục.
  const isLoaded = ref(false)
  const error = ref(null)

  const productGroupById = computed(() => productGroupId =>
    productGroups.value.find(productGroup => productGroup.id === productGroupId))

  /**
   * Tải toàn bộ nhóm sản phẩm từ API và lưu vào state của Pinia.
   *
   * @returns {Promise<Array>} Danh sách nhóm sản phẩm đã được lưu vào store.
   */
  const fetchProductGroups = async () => {
    isLoading.value = true
    error.value = null

    try {
      const { data, error: requestError } = await useApi(createUrl('/v1/product-groups', {
        query: { per_page: -1, sortBy: 'sort_order', orderBy: 'asc' },
      }))

      if (requestError.value) {
        error.value = requestError.value

        return productGroups.value
      }

      productGroups.value = data.value?.data ?? []
      isLoaded.value = true

      return productGroups.value
    }
    finally {
      isLoading.value = false
    }
  }

  /**
   * Thêm một bản ghi vừa tạo vào ĐẦU danh sách. Controller trả về đúng
   * record sau khi create nên không cần refetch toàn bộ danh sách.
   *
   * @param {object} record Bản ghi trả về từ response create.
   * @returns {void}
   */
  const prependRecord = record => {
    if (!record?.id)
      return

    productGroups.value = [record, ...productGroups.value]
  }

  /**
   * Vá một bản ghi trong danh sách theo id bằng dữ liệu server trả về sau
   * khi update — bản ghi khác trong state giữ nguyên, không refetch.
   *
   * @param {object} record Bản ghi đã cập nhật trả về từ response update.
   * @returns {void}
   */
  const applyRecord = record => {
    if (!record?.id)
      return

    // map ra mảng MỚI (thay vì gán theo index) để watcher theo dõi ref này
    // kích hoạt và UI đồng bộ tức thì.
    productGroups.value = productGroups.value.map(item => item.id === record.id ? record : item)
  }

  /**
   * Thay thế danh sách nhóm sản phẩm hiện đang lưu trong store.
   *
   * @param {Array} productGroupList Danh sách nhóm sản phẩm mới.
   * @returns {void}
   */
  const setProductGroups = productGroupList => {
    productGroups.value = productGroupList
  }

  // Promise của lần tải đang chạy — các lời gọi ensureLoaded() trùng thời điểm
  // (nhiều trang mount gần nhau) chia sẻ chung một request thay vì gọi trùng.
  const loadPromise = ref(null)

  /**
   * Đảm bảo store đã có dữ liệu: lần gọi đầu tiên tải từ API, các lần sau
   * dùng ngay cache trong Pinia. Trang tiêu thụ không cần tự kiểm tra isLoaded.
   *
   * @returns {Promise<Array>} Danh sách nhóm sản phẩm hiện có trong store.
   */
  const ensureLoaded = async () => {
    if (isLoaded.value)
      return productGroups.value

    loadPromise.value ??= fetchProductGroups().finally(() => {
      loadPromise.value = null
    })

    return loadPromise.value
  }

  /**
   * Bắt buộc tải lại từ API bất kể store đã có cache (dùng cho nút "Tải lại"
   * hoặc sau khi tạo/sửa/xoá muốn đồng bộ lại toàn bộ danh sách).
   *
   * @returns {Promise<Array>} Danh sách nhóm sản phẩm mới từ API.
   */
  const refresh = async () => fetchProductGroups()

  /**
   * Xoá một bản ghi khỏi danh sách theo id (204 không có body nên chỉ cần
   * lọc tại chỗ) — không refetch toàn bộ.
   *
   * @param {number|string} id Id của bản ghi cần loại khỏi state.
   * @returns {void}
   */
  const removeRecord = id => {
    productGroups.value = productGroups.value.filter(item => item.id !== id)
  }

  /**
   * Gọi API tạo mới nhóm sản phẩm rồi chèn bản ghi trả về vào ĐẦU state.
   *
   * @param {object} payload Payload khớp createRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi mới (nếu thành công).
   */
  const createProductGroup = async payload => {
    const { data, error, statusCode } = await useApi('/v1/product-groups', {
      method: 'POST',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    prependRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Gọi API cập nhật nhóm sản phẩm rồi vá đúng bản ghi trong state theo id.
   *
   * @param {number|string} id Id nhóm cần cập nhật.
   * @param {object} payload Payload khớp updateRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi đã cập nhật (nếu thành công).
   */
  const updateProductGroup = async (id, payload) => {
    const { data, error, statusCode } = await useApi(`/v1/product-groups/${id}`, {
      method: 'PUT',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    applyRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Xoá toàn bộ nhóm sản phẩm đang lưu trong store.
   *
   * @returns {void}
   */
  const clearProductGroups = () => {
    productGroups.value = []
    isLoaded.value = false
  }

  return {
    productGroups,
    isLoading,
    isLoaded,
    error,
    productGroupById,
    fetchProductGroups,
    ensureLoaded,
    refresh,
    prependRecord,
    applyRecord,
    removeRecord,
    createProductGroup,
    updateProductGroup,
    setProductGroups,
    clearProductGroups,
  }
})
