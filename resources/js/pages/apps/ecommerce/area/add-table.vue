<script setup>
import { useApi } from '@/composables/useApi'
import { TABLE_STATUSES, tableStatusMeta } from '@/utils/tableStatuses'

const router = useRouter()

// ==================== Sơ đồ bàn ăn (Order/List) ====================
// Toàn bộ khu + bàn lấy từ API thật GET /v1/dining-tables/floor — khu lấy
// từ bảng table_zones, mỗi bàn là một record của dining_table, trạng thái
// chỉ có 2 giá trị (trống / có khách — xem utils/tableStatuses.js), thời
// gian và tổng tiền do backend suy ra từ phiên bàn + đơn hàng.
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

// Items cho select khu vực: gộp "Tất cả" + các khu, kèm số bàn
const zoneSelectItems = computed(() => areaTabs.value.map(tab => ({
  title: `${tab.title} (${tab.count})`,
  value: tab.value,
})))

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
// Nhãn + màu đọc từ file config utils/tableStatuses.js — chỉ 2 trạng thái:
// "Có khách" dùng màu PRIMARY của hệ thống (đổi primary trong Theme
// Customizer là màu thẻ bàn tự đổi theo).
const statusMeta = table => tableStatusMeta(table.status)

// Định dạng tiền theo kiểu rút gọn Việt Nam: 350000 → "350.000đ"
const formatMoney = value =>
  `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`

// Cập nhật mỗi 30s để đồng hồ đếm thời gian ngồi của các bàn không bị đứng.
const now = useNow({ interval: 30000 })

// Cột thời gian trên thẻ bàn:
// - Trống: '--:--'
// - Có khách: thời gian đã ngồi tính từ start_time của phiên (HH:MM)
const formatTableTime = table => {
  if (table.status === 'occupied' && table.started_at) {
    const elapsedMinutes = Math.max(0, Math.floor((now.value - new Date(table.started_at)) / 60000))
    const hours = Math.floor(elapsedMinutes / 60)
    const minutes = elapsedMinutes % 60

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
  }

  return '--:--'
}

// Icon tiêu đề khu: đoán theo tên phổ biến, mặc định là icon nhà/khu.
const zoneIcon = zone => /ngoài trời|ngoai troi|sân|san/i.test(zone.name) ? 'tabler-umbrella' : 'tabler-home'

const legend = Object.values(TABLE_STATUSES).map(meta => ({ label: meta.label, color: meta.color }))
</script>

<template>
  <div>
    <VCard :loading="floorLoading">
      <!-- 👉 Thanh lọc khu vực + tìm bàn (nằm chung 1 card với sơ đồ) -->
      <VCardText class="pb-4">
        <div class="d-flex flex-wrap align-center gap-4">
          <div class="d-flex align-center gap-x-2">
            <!-- 👉 Chọn khu: chọn khu nào hiện khu đó, "Tất cả" hiện mọi khu -->
            <AppSelect
              v-model="selectedZoneId"
              :items="zoneSelectItems"
              placeholder="Chọn khu vực"
              hide-details
              style="min-inline-size: 240px;"
            />

            <!-- 👉 Thêm bàn vào khu đang chọn -->
            <DiningTableCreateDialog
              :zones="zones"
              :zone-id="selectedZoneId"
              @created="fetchFloor"
            />

            <!-- 👉 Thêm khu vực mới: chuyển sang trang add-area -->
            <IconBtn
              aria-label="Thêm khu vực"
              @click="router.push('/apps/ecommerce/area/add-area')"
            >
              <VIcon icon="tabler-folder-plus" />
              <VTooltip
                activator="parent"
                location="top"
              >
                Thêm khu vực
              </VTooltip>
            </IconBtn>
          </div>

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
      </VCardText>

      <VDivider />

      <!-- 👉 Sơ đồ các khu bàn — vùng cuộn riêng khi có quá nhiều bàn -->
      <VCardText
        class="zones-scroll"
        style="max-block-size: 75vh;"
      >
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

          <!-- Lưới thẻ bàn — bấm vào số bàn để sang màn hình order -->
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
                class="h-100 table-card"
                @click="router.push(`/apps/ecommerce/order/add?table=${table.id}`)"
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

<style lang="scss" scoped>
// Vùng sơ đồ bàn cuộn riêng khi một khu có quá nhiều bàn
.zones-scroll {
  overflow-block: auto;
}

// Bấm vào số bàn để sang màn hình order
.table-card {
  cursor: pointer;
}
</style>
