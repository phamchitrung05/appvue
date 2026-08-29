import { createFetch } from '@vueuse/core'
import { destr } from 'destr'

export const useApi = createFetch({
  baseUrl: import.meta.env.VITE_API_BASE_URL || '/api',
  fetchOptions: {
    headers: {
      Accept: 'application/json',
    },
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      const accessToken = useCookie('accessToken').value
      if (accessToken) {
        options.headers = {
          ...options.headers,
          Authorization: `Bearer ${accessToken}`,
        }
      }

      // Tự serialize body object thành JSON + gắn Content-Type: body truyền
      // qua fetchOptions KHÔNG được @vueuse tự stringify (cơ chế đó chỉ áp
      // cho chuỗi .post(payload)), và Content-Type tự sinh cũng bị đè bởi
      // base headers — Laravel sẽ đọc không ra JSON và validate thiếu trường.
      if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        options.body = JSON.stringify(options.body)
        options.headers = {
          ...options.headers,
          'Content-Type': 'application/json',
        }
      }

      return { options }
    },
    afterFetch(ctx) {
      const { data, response } = ctx

      // Parse data if it's JSON
      let parsedData = null
      try {
        parsedData = destr(data)
      }
      catch (error) {
        console.error(error)
      }

      // Bóc envelope chuẩn của backend Laravel: { success, status, code, message, data }
      // → trả thẳng phần `data` để các trang đọc productsData.products thay vì
      // productsData.data.products. Chỉ unwrap khi response mang dấu hiệu envelope
      // (có trường `success`) để không ảnh hưởng tới API demo của template.
      if (parsedData && typeof parsedData === 'object' && !Array.isArray(parsedData) && 'success' in parsedData && 'data' in parsedData)
        parsedData = parsedData.data

      return { data: parsedData, response }
    },
  },
})
