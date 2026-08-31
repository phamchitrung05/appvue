/**
 * Tiện ích dùng chung cho các action mutation (create/update/delete) trong
 * store Pinia — chuẩn hoá cách đọc response của useApi.
 */

/**
 * Kiểm tra statusCode (REF trả về từ useApi) có thuộc dải 2xx hay không.
 * statusCode là REF nên đọc qua .value; thiếu/0 coi như thất bại.
 *
 * @param {import('vue').Ref<number|undefined>|number|undefined} statusCode Mã HTTP trả về từ useApi.
 * @returns {boolean} true nếu request thành công (2xx).
 */
export const isSuccessStatus = statusCode => {
  const status = statusCode?.value ?? statusCode ?? 0

  return status >= 200 && status < 300
}

/**
 * Parse thân lỗi từ useApi thành message hiển thị được. Backend trả lỗi
 * dạng chuỗi JSON (vd: {"message":"...","errors":{...}}); lỗi mạng không
 * phải JSON thì dùng message dự phòng.
 *
 * @param {import('vue').Ref<string|undefined>|string|undefined} error Thân lỗi trả về từ useApi.
 * @param {string} fallback Message dự phòng khi không parse được.
 * @returns {string} Message tiếng Việt hiển thị cho người dùng.
 */
export const parseApiError = (error, fallback = 'Đã xảy ra lỗi. Vui lòng thử lại.') => {
  try {
    const raw = error?.value ?? error
    const body = typeof raw === 'string' ? JSON.parse(raw) : raw

    if (body?.message)
      return body.message
  }
  catch {
    // error không phải JSON (lỗi mạng...) — dùng message mặc định
  }

  return fallback
}
