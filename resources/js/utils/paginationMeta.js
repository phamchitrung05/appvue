// Dựng dòng mô tả phân trang dùng chung cho mọi bảng:
// "Hiển thị X đến Y của Z mục" — được TablePagination gọi ở mọi trang danh sách.
export const paginationMeta = (options, total) => {
  const start = (options.page - 1) * options.itemsPerPage + 1
  const end = Math.min(options.page * options.itemsPerPage, total)

  return `Hiển thị ${total === 0 ? 0 : start} đến ${end} của ${total} mục`
}
