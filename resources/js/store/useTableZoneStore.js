import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isSuccessStatus, parseApiError } from '@/utils/apiMutation'

/**
 * Quản lý state dùng chung của các khu vực bàn ăn.
 *
 * Store tải và lưu toàn bộ table_zone để các màn hình sơ đồ bàn, tạo bàn hoặc
 * quản lý khu vực có thể dùng chung một nguồn dữ liệu reactive.
 */
export const useTableZoneStore = defineStore('tableZone', () => {
  const tableZones = ref([])
  const isLoading = ref(false)

  // Đánh dấu store đã nhận response thành công, kể cả khi danh sách khu trống.
  const isLoaded = ref(false)
  const error = ref(null)

  /**
   * Tìm khu vực bàn theo id trong danh sách state hiện tại.
   *
   * @returns {(tableZoneId: number) => object|undefined} Hàm trả về khu vực tương ứng hoặc undefined.
   */
  const tableZoneById = computed(() => tableZoneId =>
    tableZones.value.find(tableZone => tableZone.id === tableZoneId))

  /**
   * Tải toàn bộ khu vực bàn từ API và lưu vào state của Pinia.
   *
   * @returns {Promise<Array>} Danh sách khu vực bàn đã được lưu vào store.
   */
  const fetchTableZones = async () => {
    isLoading.value = true
    error.value = null

    try {
      const { data, error: requestError } = await useApi(createUrl('/v1/table-zones', {
        query: { per_page: -1 },
      }))

      if (requestError.value) {
        error.value = requestError.value

        return tableZones.value
      }

      tableZones.value = data.value?.data ?? []
      isLoaded.value = true

      return tableZones.value
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

    tableZones.value = [record, ...tableZones.value]
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
    tableZones.value = tableZones.value.map(item => item.id === record.id ? record : item)
  }

  /**
   * Thay thế danh sách khu vực bàn hiện đang lưu trong store.
   *
   * @param {Array} tableZoneList Danh sách khu vực bàn mới.
   * @returns {void}
   */
  const setTableZones = tableZoneList => {
    tableZones.value = tableZoneList
  }

  // Promise của lần tải đang chạy — các lời gọi ensureLoaded() trùng thời điểm
  // (nhiều trang mount gần nhau) chia sẻ chung một request thay vì gọi trùng.
  const loadPromise = ref(null)

  /**
   * Đảm bảo store đã có dữ liệu: lần gọi đầu tiên tải từ API, các lần sau
   * dùng ngay cache trong Pinia. Trang tiêu thụ không cần tự kiểm tra isLoaded.
   *
   * @returns {Promise<Array>} Danh sách khu vực bàn hiện có trong store.
   */
  const ensureLoaded = async () => {
    if (isLoaded.value)
      return tableZones.value

    loadPromise.value ??= fetchTableZones().finally(() => {
      loadPromise.value = null
    })

    return loadPromise.value
  }

  /**
   * Bắt buộc tải lại từ API bất kể store đã có cache (dùng cho nút "Tải lại"
   * hoặc sau khi tạo/sửa/xoá muốn đồng bộ lại toàn bộ danh sách).
   *
   * @returns {Promise<Array>} Danh sách khu vực bàn mới từ API.
   */
  const refresh = async () => fetchTableZones()

  /**
   * Gọi API tạo mới khu vực rồi chèn bản ghi trả về vào ĐẦU state.
   *
   * @param {object} payload Payload khớp createRules() của backend.
   * @returns {Promise<{ok: boolean, record: object|null, message: string}>} Kết quả + bản ghi mới (nếu thành công).
   */
  const createTableZone = async payload => {
    const { data, error, statusCode } = await useApi('/v1/table-zones', {
      method: 'POST',
      body: payload,
    }).json()

    if (!isSuccessStatus(statusCode))
      return { ok: false, record: null, message: parseApiError(error) }

    prependRecord(data.value)

    return { ok: true, record: data.value, message: '' }
  }

  /**
   * Xoá toàn bộ khu vực đang lưu trong store.
   *
   * @returns {void}
   */
  const clearTableZones = () => {
    tableZones.value = []
    isLoaded.value = false
  }

  return {
    tableZones,
    isLoading,
    isLoaded,
    error,
    tableZoneById,
    fetchTableZones,
    ensureLoaded,
    refresh,
    prependRecord,
    applyRecord,
    createTableZone,
    setTableZones,
    clearTableZones,
  }
})
