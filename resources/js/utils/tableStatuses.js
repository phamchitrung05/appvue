/**
 * Cấu hình trạng thái bàn ăn cho sơ đồ bàn (màn hình Order/List).
 *
 * table_sessions chỉ có 2 trạng thái hiển thị:
 * - occupied: bàn đang có khách (có phiên bàn đang mở, bất kể đã gọi món hay chưa)
 * - available: bàn trống (chưa có phiên mở)
 *
 * Màu dùng tên màu Vuetify — "primary" bám theo màu chủ đạo của hệ thống,
 * đổi primary trong Theme Customizer là màu bàn đổi theo.
 */
export const TABLE_STATUSES = {
  available: {
    label: 'Trống',
    color: 'success',
  },
  occupied: {
    label: 'Có khách',
    color: 'primary',
  },
}

/**
 * Lấy meta (nhãn + màu) của một trạng thái; trạng thái lạ rơi về "Trống".
 *
 * @param {string} status - Trạng thái trả về từ API (occupied | available).
 * @returns {{ label: string, color: string }} Nhãn tiếng Việt + màu Vuetify.
 */
export const tableStatusMeta = status => TABLE_STATUSES[status] ?? TABLE_STATUSES.available
