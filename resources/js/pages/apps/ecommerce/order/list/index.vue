<script setup>
import { useApi } from '@/composables/useApi'

// ==================== Sơ đồ bàn ăn (Order/List) ====================
// Toàn bộ khu + bàn lấy từ API thật GET /v1/dining-tables/floor — khu lấy
// từ bảng table_zones, mỗi bàn là một record của dining_table, trạng thái
// (trống / có khách / đang order / đã đặt), thời gian và tổng tiền do
// backend suy ra từ phiên bàn + đơn hàng.
const {
  data: floorData,
  execute: fetchFloor,
  isFetching: floorLoading,
} = await useApi('/v1/dining-tables/floor')

const zones = computed(() => floorData.value?.zones ?? [])
const totalTables = computed(() => zones.value.reduce((count, zone) => count + zone.tables.length, 0))

// ==================== Bộ lọc khu vực & tìm bàn ====================
// selectedZoneId: id khu (table_zones.id) hoặc 'all' để xem mọi khu —
// thêm khu mới ở backend là tab tự xuất hiện, không cần sửa code.
const selectedZoneId = ref('all')
const searchQuery = ref('')

const debouncedSearchQuery = refDebounced(searchQuery, 300)

const areaTabs = computed(() => [
  { title: 'Tất cả', value: 'all', count: totalTables.value },
  ...zones.value.map(zone => ({
    title: zone.name,
    value: zone.id,
    count: zone.tables.length,
  })),
])

const filteredZones = computed(() => {
  const keyword = debouncedSearchQuery.value.trim().toLowerCase()

  return zones.value
    .map(zone => ({
      ...zone,
      tables: zone.tables.filter(table => {
        const matchZone = selectedZoneId.value === 'all' || selectedZoneId.value === zone.id
        const matchKeyword = !keyword || table.name.toLowerCase().includes(keyword)

        return matchZone && matchKeyword
      }),
    }))
    .filter(zone => zone.tables.length)
})

// ==================== Trạng thái bàn ====================
// Map status (key tiếng Anh trả về từ API) sang nhãn tiếng Việt + màu.
// Class màu viết dạng chuỗi literal ('text-success'...) để Vuetify quét
// được và sinh style tương ứng.
const STATUS_META = {
  available: { label: 'Trống', color: 'success' },
  occupied: { label: 'Có khách', color: 'error' },
  ordering: { label: 'Đang order', color: 'warning' },
  reserved: { label: 'Đã đặt', color: 'secondary' },
}

const statusMeta = table => STATUS_META[table.status] ?? STATUS_META.available

// Định dạng tiền theo kiểu rút gọn Việt Nam: 350000 → "350.000đ"
const formatMoney = value =>
  `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`

// Cập nhật mỗi 30s để đồng hồ đếm thời gian ngồi của các bàn không bị đứng.
const now = useNow({ interval: 30000 })

// Cột thời gian trên thẻ bàn:
// - Trống: '--:--'
// - Có khách / Đang order: thời gian đã ngồi tính từ start_time (HH:MM)
// - Đã đặt: giờ hẹn cụ thể (HH:MM)
const formatTableTime = table => {
  if (table.status === 'reserved' && table.reserved_at)
    return new Date(table.reserved_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false })

  if ((table.status === 'occupied' || table.status === 'ordering') && table.started_at) {
    const elapsedMinutes = Math.max(0, Math.floor((now.value - new Date(table.started_at)) / 60000))
    const hours = Math.floor(elapsedMinutes / 60)
    const minutes = elapsedMinutes % 60

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
  }

  return '--:--'
}

// Icon tiêu đề khu: đoán theo tên phổ biến, mặc định là icon nhà/khu.
const zoneIcon = zone => /ngoài trời|ngoai troi|sân|san/i.test(zone.name) ? 'tabler-umbrella' : 'tabler-home'

const legend = Object.values(STATUS_META).map(meta => ({ label: meta.label, color: meta.color }))
</script>

<template>
  <div>
    <!-- 👉 Thanh lọc khu vực + tìm bàn -->
    <div class="d-flex flex-wrap align-center gap-4 mb-6">
      <VBtn
        v-for="tab in areaTabs"
        :key="tab.value"
        rounded="pill"
        :variant="selectedZoneId === tab.value ? 'tonal' : 'outlined'"
        color="primary"
        @click="selectedZoneId = tab.value"
      >
        {{ tab.title }} ({{ tab.count }})
      </VBtn>

      <VSpacer />

      <AppTextField
        v-model="searchQuery"
        placeholder="Tìm bàn..."
        prepend-inner-icon="tabler-search"
        clearable
        clear-icon="tabler-x"
        style="inline-size: 220px;"
        hide-details
      />

      <!-- Tải lại sơ đồ bàn (trạng thái/thu tiền thay đổi liên tục trong ca) -->
      <IconBtn
        :loading="floorLoading"
        @click="fetchFloor"
      >
        <VIcon icon="tabler-refresh" />
      </IconBtn>
    </div>

    <!-- 👉 Sơ đồ các khu bàn -->
    <VCard :loading="floorLoading">
      <VCardText>
        <div
          v-for="(zone, zoneIndex) in filteredZones"
          :key="zone.id"
          :class="zoneIndex > 0 ? 'mt-6' : ''"
        >
          <!-- Tiêu đề khu -->
          <div class="d-flex align-center gap-x-3 mb-4">
            <VIcon
              :icon="zoneIcon(zone)"
              size="24"
              class="text-high-emphasis"
            />
            <h3 class="text-h6">
              {{ zone.name }}
            </h3>
          </div>

          <!-- Lưới thẻ bàn -->
          <VRow>
            <VCol
              v-for="table in zone.tables"
              :key="table.id"
              cols="6"
              sm="4"
              md="3"
              lg="2"
            >
              <VCard
                variant="tonal"
                :color="statusMeta(table).color"
                class="h-100"
              >
                <VCardText class="text-center py-4">
                  <div class="text-h4 font-weight-bold mb-2">
                    {{ table.name }}
                  </div>

                  <VChip
                    :color="statusMeta(table).color"
                    size="small"
                    label
                    class="mb-4"
                  >
                    {{ statusMeta(table).label }}
                  </VChip>

                  <VDivider class="mb-3" />

                  <div class="d-flex align-center justify-center gap-x-2 text-body-2 mb-2">
                    <VIcon
                      icon="tabler-clock"
                      size="18"
                    />
                    <span>{{ formatTableTime(table) }}</span>
                  </div>

                  <div class="d-flex align-center justify-center gap-x-2 text-body-2">
                    <VIcon
                      icon="tabler-cash"
                      size="18"
                    />
                    <span class="font-weight-medium">{{ formatMoney(table.total) }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- Không còn bàn nào sau khi lọc/tìm -->
        <div
          v-if="!filteredZones.length"
          class="text-center text-body-1 py-10 text-disabled"
        >
          Không tìm thấy bàn nào phù hợp.
        </div>
      </VCardText>

      <VDivider />

      <!-- 👉 Chú thích màu trạng thái -->
      <div class="d-flex flex-wrap justify-center align-center gap-x-6 gap-y-2 py-3">
        <div
          v-for="item in legend"
          :key="item.label"
          class="d-flex align-center gap-x-2 text-body-2"
        >
          <span
            :class="`bg-${item.color}`"
            style="inline-size: 10px; block-size: 10px; border-radius: 50%;"
          />
          <span>{{ item.label }}</span>
        </div>
      </div>
    </VCard>
  </div>
</template>
