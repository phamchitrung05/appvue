/**
 * Tập trung toàn bộ lời nhắc validate / thông báo form của frontend.
 *
 * Mục tiêu: khi cần sửa câu chữ chỉ sửa ở đây, không phải lần theo từng
 * trang — giống cách backend tập trung message trong config/messages.php.
 *
 * Cấu trúc: nhóm theo tài nguyên (product, ...); khi thêm form cho tài
 * nguyên mới thì khai báo thêm nhóm tương ứng.
 */
export const validationMessages = {
  product: {
    // Form thêm sản phẩm (resources/js/pages/apps/ecommerce/product/add)
    nameRequired: 'Vui lòng nhập tên sản phẩm',
    nameMax: 'Tên sản phẩm tối đa 255 ký tự',
    priceRequired: 'Vui lòng nhập giá bán',
    priceNumeric: 'Giá bán phải là số',
    priceMin: 'Giá bán không được âm',
    createFailed: 'Không thể tạo sản phẩm. Vui lòng thử lại.',
    createSuccess: 'Đã thêm sản phẩm ":name".',
  },

  productGroup: {
    // Dialog thêm nhanh nhóm hàng (ProductGroupCreateDialog)
    nameRequired: 'Vui lòng nhập tên nhóm hàng',
    nameMax: 'Tên nhóm hàng tối đa 255 ký tự',
    createFailed: 'Không thể tạo nhóm hàng. Vui lòng thử lại.',
    createSuccess: 'Đã thêm nhóm hàng mới.',
    updateSuccess: 'Đã cập nhật nhóm hàng.',
    updateFailed: 'Không thể cập nhật nhóm hàng. Vui lòng thử lại.',
    orderSaved: 'Đã cập nhật thứ tự nhóm hàng.',
  },
}
