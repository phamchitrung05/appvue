<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2.12
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- vue (VUE) - v3
- eslint (ESLINT) - v8

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
</laravel-boost-guidelines>

=== project session notes (đồng bộ giữa các máy qua git) ===

# Quy tắc bắt buộc khi viết code

- **Mọi comment / PHPDoc trong code phải viết bằng TIẾNG VIỆT**, mô tả chi tiết chức năng của class, method, tham số và giá trị trả về — áp dụng cho cả file mới lẫn khi sửa code cũ. Message API trả về cho người dùng cũng dùng tiếng Việt (tập trung ở `config/messages.php`).
- Controller không hard-code chuỗi message; luôn trả response qua `BaseResponseService` (envelope `{success, status, code, message, data}`), message mặc định đọc từ `config/messages.php`.
- **Response lỗi phải kèm trường `code`** — mã lỗi ổn định viết HOA (vd: `VALIDATION_FAILED`, `PRODUCT_NOT_FOUND`, `UNAUTHENTICATED`) để frontend báo lại, backend tra tức thì. Mã mặc định theo HTTP status nằm ở `messages.codes.by_status`; mã theo tài nguyên ở `messages.codes.crud`. Khi thêm loại lỗi mới, khai báo code trong config trước rồi mới dùng.
- Frontend: lời nhắc validate / thông báo form KHÔNG hard-code trong trang — tập trung ở `resources/js/utils/validationMessages.js` (nhóm theo tài nguyên: `product`, `productGroup`...), tương ứng với `config/messages.php` của backend.

# Ghi chú phiên làm việc — cập nhật sau mỗi buổi code quan trọng

Ngôn ngữ làm việc với user: **tiếng Việt**.

## Tổng quan kiến trúc
- Laravel 12 + Vue 3 (Vuetify) + `prettus/l5-repository` (repository pattern), API JSON CRUD.
- Controllers: `app/Http/Controllers/Api/*Controller.php` kế thừa `ApiCrudController` — chỉ khai báo `repositoryClass()` + `createRules()/updateRules()`.
- Repositories: `app/Repositories/*RepositoryEloquent` — mỗi repo `pushCriteria(app(DataTableCriteria::class))` trong `boot()`; `$fieldSearchable` đóng vai trò whitelist cho tìm kiếm/lọc.
- `app/Criteria/DataTableCriteria.php`: dịch tham số Vuetify (`q`, `sortBy`, `orderBy`=chiều) sang cú pháp `RequestCriteria` (`search`, `orderBy`=tên cột, `sortedBy`=chiều), rồi áp tìm kiếm + sắp xếp qua `parent::apply()` + lọc theo cột qua `applyFieldFilters()`. `apply()` luôn refresh `$this->request = app(Request::class)` để không dính request cũ khi repository bị tái sử dụng trong cùng process (feature test/Octane).
- Sort: repository khai `public $fieldSortable` (whitelist cột sort, đọc bởi `DataTableCriteria::isSortableColumn`, fallback về `$fieldSearchable`); cột lạ gửi lên bị bỏ qua thay vì lỗi SQL. `ApiCrudController::index()` KHÔNG push RequestCriteria gốc nữa (trước đây áp 2 lần, đã bỏ — nếu cần khôi phục phải whitelist cả đường `?orderBy=`).
- Trang products (`resources/js/pages/apps/ecommerce/product/list/index.vue`): headers dùng key TRÙNG tên cột DB (`name`, `product_group_id`, `price`, `is_active`), sort bật đủ 4 cột dữ liệu; ô tìm kiếm có debounce 500ms (`refDebounced`) + tự reset về trang 1 khi tìm kiếm mới.
- Cột "Nhóm hàng" sort theo `product_group_id` (thứ tự ID nhóm) chứ chưa sort theo tên — muốn sort theo tên cần join hoặc sort kiểu relation `product_group|name` của l5-repository.
- Routes API: `routes/api.php` dùng `Route::apiResource` cho từng resource; route tuỳ biến (`dining-tables/floor`) phải đăng ký TRƯỚC apiResource để không bị `{id}` nuốt.
- Màn hình sơ đồ bàn (Order/List, `resources/js/pages/apps/ecommerce/order/list/index.vue`): gọi `GET /v1/dining-tables/floor`, mỗi bàn là record `dining_table`. Bảng `dining_table` có thêm cột `area` ('indoor'/'outdoor', null coi như indoor) + `reserved_at` (migration 2026_08_30_035553). Trạng thái bàn KHÔNG lưu cột mà suy ra ở `DiningTablesController::floor()`: phiên mở (status=open, end_time=null) có đơn → 'occupied', không đơn → 'ordering', có reserved_at → 'reserved', còn lại 'available'; tổng tiền = sum(total) các đơn status != cancelled của phiên hiện tại. Seeder tạo 14 bàn trong nhà (01..14) + 6 ngoài trời (T01..T06). LƯU Ý: 11 test cũ trong `ApiCrudTest` fail sẵn từ commit 5b21a34 (chưa rõ nguyên nhân, chưa sửa).

## Trạng thái máy đã làm việc (Windows, project tại `D:\Ai\appvue`)
- Đã clone `https://github.com/phamchitrung05/appvue`, đã chạy `composer install` + `npm install` thành công (Laravel 12.36.1, Vite 7.1.12).
- Đã dựng `.env` (sqlite), chạy migrate, có data test trong bảng products.
- **Ngôn ngữ UI mặc định: TIẾNG VIỆT** — locale `vi` khai ở `themeConfig.js` (defaultLocale + langConfig), bản dịch menu/Vuetify ở `resources/js/plugins/i18n/locales/vi.json`; các chuỗi UI cứng trên trang làm việc phải viết tiếng Việt. Cookie `vuexy-language` ghi đè mặc định (người dùng đã chọn ngôn ngữ thì ưu tiên cookie).
- Frontend chạy: SPA được Laravel serve tại `/admin/*` (blade `application.blade.php`); Vite dev có proxy `/api` → `127.0.0.1:8000`.

## Checklist khi sang máy mới
1. Clone/pull repo `https://github.com/phamchitrung05/appvue`
2. `composer install && npm install`
3. `cp .env.example .env && php artisan key:generate` (+ `php artisan migrate` nếu có DB)
4. Chạy app: `npm run dev` + `php artisan serve`, hoặc gộp bằng `composer run dev`

## Tra cứu phiên cũ (khi cần ngữ cảnh chi tiết)
- Phiên 30/08/2026 (trang danh mục + dialogs + drag&drop): `sess_a26d9f68-d712-46f7-b78e-3f03ceac4009`
- Phiên ZCode 29/08/2026 giải thích `DataTableCriteria`: `sess_618156d7-fc11-496b-a7cc-37a34ace13e2` — thử `ReadSessionContext` với id này nếu lịch sử session đồng bộ theo tài khoản; không được thì dựa vào phần ghi chú ở trên.

## Phiên 30/08/2026 (tiếp máy nhà) — trang Danh mục + dialogs
- Trang Danh mục (`resources/js/pages/apps/ecommerce/product/category-list.vue`) viết lại hoàn toàn theo mockup user cung cấp: cột trái = danh sách product_group + badge số lượng, cột phải = lưới card sản phẩm của nhóm đang chọn (search trong nhóm debounce 400ms + VPagination). KHÔNG có nút quay lại/tiêu đề "Thêm sản phẩm".
- **Dependency mới `@formkit/drag-and-drop`** — pull xong phải chạy `npm install`. Kéo thả sắp xếp nhóm: plugins `[animations(), place()]` — `place()` = đang kéo CHỈ highlight vị trí sẽ thả, thả hẳn mới cập nhật danh sách (yêu cầu rõ của user); `animations()` = trượt FLIP mượt (bổ sung bằng `<TransitionGroup name="flip">` bọc v-for, không có tag để giữ item là con trực tiếp của VList — FormKit cần thế). Lưu sort_order debounce 400ms (`useDebounceFn`), chỉ PUT nhóm đổi chỗ, chuẩn hóa 1..n.
- `useDragAndDrop` quản lý luôn ref `groups`; sync từ API qua watcher `groupsData` với cờ `syncingGroups` (chống persist nhầm khi sync). Sort_order 0/null = chưa xếp → xuống cuối; nhóm mới từ header "Danh mục" được đẩy xuống cuối + gán sort_order = n+1 (PUT ngay).
- Components mới (auto-registered từ `resources/js/components/`): `ProductGroupCreateDialog` (thêm nhóm, hỗ trợ slot `#activator` nhận `open`), `ProductGroupEditDialog` (sửa nhóm, `:group` + v-model mở/đóng), `ProductCreateDialog` (thêm nhanh sản phẩm — 6 field như product/add, prop `group-id` chọn sẵn nhóm đang xem). Cả 3 emit `created`/`saved` + snackbar riêng.
- **Lời nhắc/thông báo form tập trung ở `resources/js/utils/validationMessages.js`** (nhóm theo tài nguyên: `product`, `productGroup`) — KHÔNG hard-code chuỗi trong trang. Đây là quy ước mới áp dụng cho các form sau này.
- Bug đã sửa trong phiên: (1) `useApi` không serialize object body khi POST/PATCH + mất Content-Type → Laravel đọc không ra JSON → 422 VALIDATION_FAILED; đã tự stringify trong `beforeFetch` (áp dụng toàn app); (2) giá trị trả về từ `useApi(...).json()` là REF — phải đọc `.value` (statusCode/error/productsData...); (3) Vite dev thỉnh thoảng serve module cũ sau khi sửa code → `touch <file>` rồi reload.
- Trạng thái data test (sqlite): nhóm "Gỏi Thái chua ngọt" đang Ngừng bán (tác dụng phụ khi test dialog sửa); sort_order các nhóm chuẩn hóa 1..n trừ nhóm tạo mới chưa kéo. Đếm sản phẩm theo nhóm đang đếm client-side (request `itemsPerPage=-1`) — catalog lớn thì cần endpoint aggregate.

## Phiên 29/08/2026 (máy nhà)
- Thêm debounce 500ms cho ô tìm kiếm trang products + whitelist sort (`$fieldSortable`) + bật sort 4 cột dữ liệu, key headers đổi trùng tên cột DB.
- Sửa 2 bug nền tảng: (1) `DataTableCriteria` đọc request cũ khi repository tái sử dụng; (2) bỏ push `RequestCriteria` thừa trong `index()`.
- ESLint: `.eslintrc.cjs` đổi `camelcase` thành `['error', { properties: 'never' }]` — property giữ snake_case vì là tham số API. LƯU Ý: editor VS Code của user format kiểu `{x}`/`/>` mâu thuẫn ESLint project — chạy `npx eslint --fix <file>` nếu bị phá lại format; cân nhắc tắt format-on-save.
- **11 test trong ApiCrudTest vẫn FAIL tồn đọng** (assert shape phẳng cũ `data.0.name`, `current_page`... từ trước khi refactor envelope `{success, data}` ở máy cty) — KHÔNG phải do lỗi mới; cần cập nhật assertions sang đường dẫn `data.*` khi có dịp.
