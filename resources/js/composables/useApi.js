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
