<?php

/**
 * Kho tập trung mọi message API trả về cho frontend — toàn bộ tiếng Việt.
 *
 * Cách sử dụng:
 * - Controller không hard-code chuỗi message; luôn gọi qua BaseResponseService,
 *   service này mặc định nội suy từ file config.
 * - Placeholder `:resource` trong nhóm `crud` được thay bằng nhãn tiếng Việt
 *   tra từ bảng `resources`, vd: `crud.created` + 'Product' => "Đã tạo sản phẩm thành công."
 * - Muốn sửa câu chữ, chỉ cần sửa file này, không đụng code.
 */

return [

    // Message chung, không gắn với tài nguyên cụ thể.
    'common' => [
        'success' => 'Thành công.',
        'created' => 'Đã tạo thành công.',
        'updated' => 'Cập nhật thành công.',
        'deleted' => 'Đã xoá thành công.',
        'error' => 'Đã xảy ra lỗi. Vui lòng thử lại.',
        'validation_failed' => 'Dữ liệu gửi lên không hợp lệ.',
        'unauthenticated' => 'Chưa đăng nhập hoặc phiên đã hết hạn.',
        'forbidden' => 'Bạn không có quyền thực hiện thao tác này.',
        'not_found' => 'Không tìm thấy tài nguyên.',
        'method_not_allowed' => 'Phương thức không được hỗ trợ.',
        'too_many_requests' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
        'server_error' => 'Lỗi hệ thống. Vui lòng liên hệ bộ phận hỗ trợ.',
    ],

    // CRUD theo tài nguyên; `:resource` được thay bằng nhãn tiếng Việt từ bảng `resources`.
    'crud' => [
        'fetched_all' => 'Đã tải danh sách :resource.',
        'fetched' => 'Đã tải thông tin :resource.',
        'created' => 'Đã tạo :resource thành công.',
        'updated' => 'Đã cập nhật :resource thành công.',
        'deleted' => 'Đã xoá :resource thành công.',
        'not_found' => 'Không tìm thấy :resource.',
    ],

    // Bảng dịch tên model (tiếng Anh) sang nhãn hiển thị tiếng Việt.
    'resources' => [
        'Product' => 'sản phẩm',
        'ProductGroup' => 'nhóm sản phẩm',
        'Store' => 'cửa hàng',
        'DiningTable' => 'bàn ăn',
        'TableZone' => 'khu vực bàn',
        'Order' => 'đơn hàng',
        'OrderItem' => 'món trong đơn',
        'Payment' => 'thanh toán',
        'Printer' => 'máy in',
        'TableSession' => 'phiên bàn',
    ],

    /**
     * Mã lỗi ổn định (không đổi theo ngôn ngữ/message) để frontend báo lại.
     * Backend đọc code là biết ngay lỗi gì, không phụ thuộc câu chữ message.
     */
    'codes' => [
        // Mã mặc định theo HTTP status — tự áp khi caller không chỉ định code.
        'by_status' => [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_FAILED',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_ERROR',
        ],

        // Mã lỗi CRUD có gắn tên tài nguyên; :RESOURCE được thay bằng
        // tên model viết HOA (vd: Product => PRODUCT_NOT_FOUND).
        'crud' => [
            'not_found' => ':RESOURCE_NOT_FOUND',
        ],
    ],

    // Xác thực & phiên làm việc.
    'auth' => [
        'login_success' => 'Đăng nhập thành công.',
        'login_failed' => 'Tài khoản hoặc mật khẩu không đúng.',
        'logged_out' => 'Đã đăng xuất thành công.',
        'token_expired' => 'Phiên đã hết hạn. Vui lòng đăng nhập lại.',
    ],
];
