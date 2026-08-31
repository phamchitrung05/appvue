import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isSuccessStatus, parseApiError } from '@/utils/apiMutation'

/**
 * Quản lý state dùng chung của các cửa hàng (chi nhánh).
 *
 * Store tải và lưu toàn bộ store để các màn hình chọn cửa hàng (khi tạo
 * khu vực/bàn, form sản phẩm...) đọc cùng một nguồn dữ liệu reactive.
 */
export const useStoreStore = defineStore('store', () => {
  const stores = ref([])
  const isLoading = ref(false)

  // Đánh dấu store đã nhận response thành công, kể cả khi danh sách rỗng.
  const isLoaded = ref(false)
  const error = ref(null)

  /**
   * Tìm cửa hàng theo id trong danh sách state hiện tại.
   *
   * @returns {(storeId: number) => object|undefined} Hàm trả về cửa hàng tương ứng hoặc undefined.
   */
  const storeById = computed(() => storeId =>
    stores.value.find(store => store.id === storeId))

  /**
   * Tải toàn bộ cửa hàng từ API và lưu vào state của Pinia.
   *
   * @returns {Promise<Array>} Danh sách cửa hàng đã được lưu vào store.
   */
  const fetchStores = async () => {
    isLoading.value = true
    error.value = null

    try {
      const { data, error: requestError } = await useApi(createUrl('/v1/stores', {
        query: { per_page: -1 },
      }))

      if (requestError.value) {
        error.value = requestError.value

        return stores.value
      }

      stores.value = data.value?.data ?? []
      isLoaded.value = true

      return stores.value
    }
    finally {
      isLoading.value = false
    }
  }

  // Promise của lần tải đang chạy — các lời gọi ensureLoaded() trùng thời điểm
  // (nhiều trang mount gần nhau) chia sẻ chung một request thay vì gọi trùng.
  const loadPromise = ref(null)

  /**
   * Đảm bảo store đã có dữ liệu: lần gọi đầu tiên tải từ API, các lần sau
   * dùng ngay cache trong Pinia. Trang tiêu thụ không cần tự kiểm tra isLoaded.
   *
   * @returns {Promise<Array>} Danh sách cửa hàng hiện có trong store.
   */
  const ensureLoaded = async () => {
    if (isLoaded.value)
      return stores.value

    loadPromise.value ??= fetchStores().finally(() => {
      loadPromise.value = null
    })

    return loadPromise.value
  }

  /**
   * Bắt buộc tải lại từ API bất kể store đã có cache (dùng cho nút "Tải lại"
   * hoặc sau khi tạo/sửa/xoá muốn đồng bộ lại toàn bộ danh sách).
   *
   * @returns {Promise<Array>} Danh sách cửa hàng mới từ API.
   */
  const refresh = async () => fetchStores()

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

    stores.value = [record, ...stores.value]
  }

  /**
   * Vá một bản ghi trong danh sách theo id bằng dữ liệu server trả về sau
   * khi update — map ra mảng MỚI để watcher theo dõi ref này kích hoạt.
   *
   * @param {object} record Bản ghi đã cập nhật trả về từ response update.
   * @returns {void}
   */
  const applyRecord = record => {
    if (!record?.id)
      return

    stores.value = stores.value.map(item => item.id === record.id ? record : item)
  }

  /**
   * Xoá một bản ghi khỏi danh sách theo id (204 không có body nên chỉ cần
   * lọc tại chỗ) — không refetch toàn bộ.
   *
   * @param {number|string} id Id của bản ghi cần loại khỏi state.
   * @returns {void}
   */
  const removeRecord = id => {
    stores.value = stores.value.filter(item => item.id !== id)
  }

  /**
   * Gọi API tạo mới cửa hàng rồi chèn bản ghi trả về vào ĐẦU state.
   *
   * @param {object} payload Payload khớp createRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi mới (nếu thành công).
   */
  const createStore = async payload => {
    const { data, error, statusCode } = await useApi('/v1/stores', {
      method: 'POST',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    prependRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Gọi API cập nhật cửa hàng rồi vá đúng bản ghi trong state theo id.
   *
   * @param {number|string} id Id cửa hàng cần cập nhật.
   * @param {object} payload Payload khớp updateRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi đã cập nhật (nếu thành công).
   */
  const updateStore = async (id, payload) => {
    const { data, error, statusCode } = await useApi(`/v1/stores/${id}`, {
      method: 'PUT',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    applyRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Gọi API xoá cửa hàng rồi loại bản ghi khỏi state tại chỗ.
   *
   * @param {number|string} id Id cửa hàng cần xoá.
   * @returns {Promise<{ok: boolean, message: string}>} Kết quả xoá.
   */
  const removeStore = async id => {
    const { error, statusCode } = await useApi(`/v1/stores/${id}`, { method: 'DELETE' }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, message: parseApiError(error) }

    removeRecord(id)

    return { ok: true, message: '' }
  }

  /**
   * Thay thế danh sách cửa hàng hiện đang lưu trong store.
   *
   * @param {Array} storeList Danh sách cửa hàng mới.
   * @returns {void}
   */
  const setStores = storeList => {
    stores.value = storeList
  }

  /**
   * Xoá toàn bộ cửa hàng đang lưu trong store.
   *
   * @returns {void}
   */
  const clearStores = () => {
    stores.value = []
    isLoaded.value = false
  }

  return {
    stores,
    isLoading,
    isLoaded,
    error,
    storeById,
    fetchStores,
    ensureLoaded,
    refresh,
    prependRecord,
    applyRecord,
    removeRecord,
    createStore,
    updateStore,
    removeStore,
    setStores,
    clearStores,
  }
})
