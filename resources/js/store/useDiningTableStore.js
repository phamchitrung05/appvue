import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isSuccessStatus, parseApiError } from '@/utils/apiMutation'

/**
 * Quản lý state dùng chung của các bàn ăn.
 *
 * Store tải và lưu toàn bộ dining_table để các màn hình quản lý bàn có thể
 * đọc cùng một nguồn dữ liệu reactive mà không tạo state cục bộ trùng lặp.
 */
export const useDiningTableStore = defineStore('diningTable', () => {
  const diningTables = ref([])
  const isLoading = ref(false)

  // Đánh dấu store đã nhận response thành công, kể cả khi danh sách bàn trống.
  const isLoaded = ref(false)
  const error = ref(null)

  /**
   * Tìm bàn ăn theo id trong danh sách state hiện tại.
   *
   * @returns {(diningTableId: number) => object|undefined} Hàm trả về bàn ăn tương ứng hoặc undefined.
   */
  const diningTableById = computed(() => diningTableId =>
    diningTables.value.find(diningTable => diningTable.id === diningTableId))

  /**
   * Tải toàn bộ bàn ăn từ API và lưu vào state của Pinia.
   *
   * @returns {Promise<Array>} Danh sách bàn ăn đã được lưu vào store.
   */
  const fetchDiningTables = async () => {
    isLoading.value = true
    error.value = null

    try {
      const { data, error: requestError } = await useApi(createUrl('/v1/dining-tables', {
        query: { per_page: -1 },
      }))

      if (requestError.value) {
        error.value = requestError.value

        return diningTables.value
      }

      diningTables.value = data.value?.data ?? []
      isLoaded.value = true

      return diningTables.value
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

    diningTables.value = [record, ...diningTables.value]
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
    diningTables.value = diningTables.value.map(item => item.id === record.id ? record : item)
  }

  /**
   * Thay thế danh sách bàn ăn hiện đang lưu trong store.
   *
   * @param {Array} diningTableList Danh sách bàn ăn mới.
   * @returns {void}
   */
  const setDiningTables = diningTableList => {
    diningTables.value = diningTableList
  }

  // Promise của lần tải đang chạy — các lời gọi ensureLoaded() trùng thời điểm
  // (nhiều trang mount gần nhau) chia sẻ chung một request thay vì gọi trùng.
  const loadPromise = ref(null)

  /**
   * Đảm bảo store đã có dữ liệu: lần gọi đầu tiên tải từ API, các lần sau
   * dùng ngay cache trong Pinia. Trang tiêu thụ không cần tự kiểm tra isLoaded.
   *
   * @returns {Promise<Array>} Danh sách bàn ăn hiện có trong store.
   */
  const ensureLoaded = async () => {
    if (isLoaded.value)
      return diningTables.value

    loadPromise.value ??= fetchDiningTables().finally(() => {
      loadPromise.value = null
    })

    return loadPromise.value
  }

  /**
   * Bắt buộc tải lại từ API bất kể store đã có cache (dùng cho nút "Tải lại"
   * hoặc sau khi tạo/sửa/xoá muốn đồng bộ lại toàn bộ danh sách).
   *
   * @returns {Promise<Array>} Danh sách bàn ăn mới từ API.
   */
  const refresh = async () => fetchDiningTables()

  /**
   * Gọi API tạo mới bàn ăn rồi chèn bản ghi trả về vào ĐẦU state.
   *
   * @param {object} payload Payload khớp createRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi mới (nếu thành công).
   */
  const createDiningTable = async payload => {
    const { data, error, statusCode } = await useApi('/v1/dining-tables', {
      method: 'POST',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    prependRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Gọi API cập nhật bàn ăn rồi vá đúng bản ghi trong state theo id.
   *
   * @param {number|string} id Id bàn cần cập nhật.
   * @param {object} payload Payload khớp updateRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi đã cập nhật (nếu thành công).
   */
  const updateDiningTable = async (id, payload) => {
    const { data, error, statusCode } = await useApi(`/v1/dining-tables/${id}`, {
      method: 'PUT',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    applyRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Xoá toàn bộ bàn ăn đang lưu trong store.
   *
   * @returns {void}
   */
  const clearDiningTables = () => {
    diningTables.value = []
    isLoaded.value = false
  }

  return {
    diningTables,
    isLoading,
    isLoaded,
    error,
    diningTableById,
    fetchDiningTables,
    ensureLoaded,
    refresh,
    prependRecord,
    applyRecord,
    createDiningTable,
    updateDiningTable,
    setDiningTables,
    clearDiningTables,
  }
})
