import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isSuccessStatus, parseApiError } from '@/utils/apiMutation'

export const useProductStore = defineStore('product', () => {
  const products = ref([])
  const isLoading = ref(false)

  // Đánh dấu store đã nhận response thành công, kể cả khi chưa có sản phẩm.
  const isLoaded = ref(false)
  const error = ref(null)

  const productById = computed(() => productId =>
    products.value.find(product => product.id === productId))

  /**
   * Tải toàn bộ sản phẩm từ API và lưu vào state của Pinia.
   *
   * @returns {Promise<Array>} Danh sách sản phẩm đã được lưu vào store.
   */
  const fetchProducts = async () => {
    isLoading.value = true
    error.value = null

    try {
      const { data, error: requestError } = await useApi(createUrl('/v1/products', {
        query: { per_page: -1 },
      }))

      if (requestError.value) {
        error.value = requestError.value

        return products.value
      }

      products.value = data.value?.products ?? []
      isLoaded.value = true

      return products.value
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

    products.value = [record, ...products.value]
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
    products.value = products.value.map(item => item.id === record.id ? record : item)
  }

  /**
   * Thay thế danh sách sản phẩm hiện đang lưu trong store.
   *
   * @param {Array} productList Danh sách sản phẩm mới.
   * @returns {void}
   */
  const setProducts = productList => {
    products.value = productList
  }

  // Promise của lần tải đang chạy — các lời gọi ensureLoaded() trùng thời điểm
  // (nhiều trang mount gần nhau) chia sẻ chung một request thay vì gọi trùng.
  const loadPromise = ref(null)

  /**
   * Đảm bảo store đã có dữ liệu: lần gọi đầu tiên tải từ API, các lần sau
   * dùng ngay cache trong Pinia. Trang tiêu thụ không cần tự kiểm tra isLoaded.
   *
   * @returns {Promise<Array>} Danh sách sản phẩm hiện có trong store.
   */
  const ensureLoaded = async () => {
    if (isLoaded.value)
      return products.value

    loadPromise.value ??= fetchProducts().finally(() => {
      loadPromise.value = null
    })

    return loadPromise.value
  }

  /**
   * Bắt buộc tải lại từ API bất kể store đã có cache (dùng cho nút "Tải lại"
   * hoặc sau khi tạo/sửa/xoá muốn đồng bộ lại toàn bộ danh sách).
   *
   * @returns {Promise<Array>} Danh sách sản phẩm mới từ API.
   */
  const refresh = async () => fetchProducts()

  /**
   * Xoá một bản ghi khỏi danh sách theo id (204 không có body nên chỉ cần
   * lọc tại chỗ) — không refetch toàn bộ.
   *
   * @param {number|string} id Id của bản ghi cần loại khỏi state.
   * @returns {void}
   */
  const removeRecord = id => {
    products.value = products.value.filter(item => item.id !== id)
  }

  /**
   * Gọi API tạo mới sản phẩm rồi chèn bản ghi trả về vào ĐẦU state.
   * Component chỉ cần dựng payload (đã validate ở form) — mọi việc ghi data
   * và cập nhật state gom về store để không nơi nào quên vá UI.
   *
   * @param {object} payload Payload khớp createRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi mới (nếu thành công).
   */
  const createProduct = async payload => {
    const { data, error, statusCode } = await useApi('/v1/products', {
      method: 'POST',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    prependRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Gọi API cập nhật sản phẩm rồi vá đúng bản ghi trong state theo id.
   *
   * @param {number|string} id Id sản phẩm cần cập nhật.
   * @param {object} payload Payload khớp updateRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi đã cập nhật (nếu thành công).
   */
  const updateProduct = async (id, payload) => {
    const { data, error, statusCode } = await useApi(`/v1/products/${id}`, {
      method: 'PUT',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    applyRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Gọi API xoá sản phẩm rồi loại bản ghi khỏi state tại chỗ.
   *
   * @param {number|string} id Id sản phẩm cần xoá.
   * @returns {Promise<{ok: boolean, message: string}>} Kết quả xoá.
   */
  const removeProduct = async id => {
    const { error, statusCode } = await useApi(`/v1/products/${id}`, { method: 'DELETE' }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, message: parseApiError(error) }

    removeRecord(id)

    return { ok: true, message: '' }
  }

  /**
   * Xoá toàn bộ sản phẩm đang lưu trong store.
   *
   * @returns {void}
   */
  const clearProducts = () => {
    products.value = []
    isLoaded.value = false
  }

  return {
    products,
    isLoading,
    isLoaded,
    error,
    productById,
    fetchProducts,
    ensureLoaded,
    refresh,
    prependRecord,
    applyRecord,
    removeRecord,
    createProduct,
    updateProduct,
    removeProduct,
    setProducts,
    clearProducts,
  }
})
