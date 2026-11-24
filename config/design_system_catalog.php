<?php

/**
 * Static, local-only catalog for the Proma Pay design system playground.
 * Preview data here is illustrative; it must never be connected to the finance database.
 */
$languages = [
    ['id' => 'html', 'label' => 'HTML', 'purpose' => 'ساختار معنایی و دسترس‌پذیر'],
    ['id' => 'css', 'label' => 'CSS', 'purpose' => 'ظاهر مبتنی بر Design Tokenها'],
    ['id' => 'javascript', 'label' => 'JavaScript', 'purpose' => 'تعامل مرورگر و مدیریت رویدادها'],
    ['id' => 'php', 'label' => 'PHP', 'purpose' => 'نمونه View در معماری فعلی Proma Pay'],
    ['id' => 'typescript', 'label' => 'TypeScript', 'purpose' => 'تعریف نوع و قرارداد Props'],
    ['id' => 'tsx', 'label' => 'React / TSX', 'purpose' => 'نمونه جایگزین برای Integration؛ هسته Proma Pay با React اجرا نمی‌شود'],
    ['id' => 'json', 'label' => 'JSON', 'purpose' => 'پیکربندی داده و نمونه API'],
];

$makeItems = static function (string $groupId, string $family, array $definitions, array $defaults = []): array {
    $items = [];
    foreach ($definitions as $definition) {
        [$slug, $name, $english, $variant] = array_pad($definition, 4, null);
        $items[] = [
            'id' => $slug,
            'group' => $groupId,
            'family' => $family,
            'variant' => $variant ?: $slug,
            'name' => $name,
            'english' => $english,
            'aliases' => array_values(array_unique([$name, $english, str_replace('-', ' ', $slug)])),
            'overview' => $defaults['overview'] ?? ($name . ' برای نمایش یک الگوی سازگار و قابل پیش‌بینی در رابط پروماپی استفاده می‌شود.'),
            'usage' => $defaults['usage'] ?? 'وقتی به این الگو نیاز دارید، از همین کامپوننت مشترک استفاده کنید تا رفتار صفحه‌های مختلف یکدست بماند.',
            'props' => $defaults['props'] ?? [
                ['name' => 'variant', 'type' => 'string', 'default' => $variant ?: $slug, 'description' => 'گونه بصری یا رفتاری کامپوننت.'],
                ['name' => 'disabled', 'type' => 'boolean', 'default' => 'false', 'description' => 'کنترل را از تعامل خارج می‌کند.'],
                ['name' => 'className', 'type' => 'string', 'default' => '—', 'description' => 'کلاس محدود به محدوده کامپوننت.'],
            ],
            'events' => $defaults['events'] ?? [
                ['name' => 'activate', 'description' => 'کاربر با کلیک یا صفحه‌کلید با کامپوننت تعامل می‌کند.'],
                ['name' => 'change', 'description' => 'مقدار یا حالت انتخاب‌شده تغییر می‌کند.'],
            ],
            'states' => $defaults['states'] ?? ['پیش‌فرض', 'تمرکز صفحه‌کلید', 'غیرفعال'],
            'accessibility' => $defaults['accessibility'] ?? 'نام قابل‌فهم، focus واضح و ترتیب منطقی صفحه‌کلید را حفظ کنید. وضعیت را فقط با رنگ منتقل نکنید.',
            'responsive' => $defaults['responsive'] ?? 'در عرض کم، محتوا wrap می‌شود و کنترل‌های لمسی حداقل ۴۴px ارتفاع دارند.',
            'do' => $defaults['do'] ?? 'از نام و وضعیت روشن استفاده کنید و مقدارهای مالی را از پاسخ معتبر سمت سرور بگیرید.',
            'dont' => $defaults['dont'] ?? 'منطق دسترسی یا محاسبه مالی را به preview جاوااسکریپت واگذار نکنید.',
            'related' => $defaults['related'] ?? [],
        ];
    }
    return $items;
};

$foundationGroups = [
    [
        'id' => 'colors', 'label' => 'رنگ‌ها', 'english' => 'Color Tokens', 'icon' => 'droplet', 'family' => 'foundation-colors',
        'items' => $makeItems('colors', 'foundation-colors', [['color-tokens', 'پالت رنگ', 'Color Palette', 'palette']], [
            'overview' => 'پالت رنگ از CSS Variableهای موجود در tokens.css خوانده می‌شود تا مقدار نمایش‌داده‌شده با برنامه یکی باشد.',
            'usage' => 'برای رنگ برند، سطح، متن، حاشیه و وضعیت از token معنایی استفاده کنید.',
            'props' => [['name' => 'token', 'type' => 'CSS variable', 'default' => '--pp-primary-600', 'description' => 'نام token رنگ در CSS.']],
            'events' => [['name' => 'copy', 'description' => 'نام متغیر، HEX یا RGB در کلیپ‌بورد کپی می‌شود.']],
            'states' => ['روشن', 'تاریک', 'کپی‌شده'],
            'related' => ['typography', 'radius', 'shadows'],
        ]),
    ],
    [
        'id' => 'typography', 'label' => 'تایپوگرافی', 'english' => 'Typography', 'icon' => 'type', 'family' => 'foundation-type',
        'items' => $makeItems('typography', 'foundation-type', [['typography', 'مقیاس تایپوگرافی', 'Typography Scale', 'scale']], [
            'overview' => 'وزن‌ها و اندازه‌های نمونه با Yekan Bakh محلی پروژه نمایش داده می‌شوند.',
            'usage' => 'متن بدنه و label را خوانا نگه دارید؛ اعداد مالی باید tabular و قابل مقایسه باشند.',
            'props' => [['name' => 'fontSize', 'type' => 'number', 'default' => '16', 'description' => 'اندازه قابل تنظیم برحسب پیکسل.'], ['name' => 'fontWeight', 'type' => 'number', 'default' => '600', 'description' => 'وزن بین ۱۰۰ تا ۹۰۰.']],
            'events' => [['name' => 'input', 'description' => 'تغییر اندازه و وزن فوراً در نمونه دیده می‌شود.']],
            'states' => ['Display', 'Heading 1 تا Heading 6', 'Body', 'Caption', 'Label', 'Button', 'Financial number', 'Code'],
            'related' => ['color-tokens', 'spacing', 'grid-breakpoints'],
        ]),
    ],
    [
        'id' => 'spacing', 'label' => 'فاصله‌گذاری', 'english' => 'Spacing', 'icon' => 'move', 'family' => 'foundation-spacing',
        'items' => $makeItems('spacing', 'foundation-spacing', [['spacing-scale', 'مقیاس فاصله', 'Spacing Scale', 'scale']], ['related' => ['radius', 'grid-breakpoints']]),
    ],
    [
        'id' => 'radius', 'label' => 'گوشه‌ها', 'english' => 'Radius', 'icon' => 'square', 'family' => 'foundation-radius',
        'items' => $makeItems('radius', 'foundation-radius', [['radius-scale', 'مقیاس گردی', 'Border Radius', 'scale']], ['related' => ['spacing', 'shadows']]),
    ],
    [
        'id' => 'shadows', 'label' => 'سایه‌ها', 'english' => 'Shadows', 'icon' => 'layers', 'family' => 'foundation-shadows',
        'items' => $makeItems('shadows', 'foundation-shadows', [['shadow-scale', 'سطح و سایه', 'Elevation', 'scale']], ['related' => ['radius', 'color-tokens']]),
    ],
    [
        'id' => 'grid-breakpoints', 'label' => 'شبکه و Breakpoint', 'english' => 'Grid & Breakpoints', 'icon' => 'grid', 'family' => 'foundation-grid',
        'items' => $makeItems('grid-breakpoints', 'foundation-grid', [['grid-breakpoints', 'شبکه واکنش‌گرا', 'Responsive Grid', 'scale']], ['related' => ['spacing', 'navigation']]),
    ],
    [
        'id' => 'icons', 'label' => 'آیکون‌ها', 'english' => 'Icons', 'icon' => 'star', 'family' => 'icons',
        'items' => $makeItems('icons', 'icons', [['icon-library', 'کتابخانه آیکون', 'Icon Library', 'library']], [
            'overview' => 'گالری نام آیکون‌ها از فایل‌های محلی Feather، Font Awesome، Themify و helper سفارشی Proma Pay ساخته می‌شود.',
            'usage' => 'یک خانواده آیکون را در یک context ثابت نگه دارید و برای دکمه آیکون‌محور accessible name بدهید.',
            'props' => [['name' => 'name', 'type' => 'string', 'default' => 'search', 'description' => 'شناسه واقعی آیکون در کتابخانه انتخاب‌شده.'], ['name' => 'set', 'type' => 'string', 'default' => 'feather', 'description' => 'Feather، Font Awesome، Themify یا Proma Pay.']],
            'events' => [['name' => 'copy', 'description' => 'نام و کلاس استفاده از آیکون انتخاب‌شده کپی می‌شود.']],
            'states' => ['جست‌وجو', 'انتخاب خانواده', 'کپی کد'],
            'related' => ['buttons', 'navigation'],
        ]),
    ],
];

$groupDefinitions = [
    [
        'id' => 'buttons', 'label' => 'دکمه‌ها', 'english' => 'Buttons', 'icon' => 'check', 'family' => 'buttons',
        'items' => [
            ['button-primary', 'اصلی', 'Primary', 'primary'], ['button-secondary', 'ثانویه', 'Secondary', 'secondary'],
            ['button-outline', 'خطی', 'Outline', 'outline'], ['button-ghost', 'بدون زمینه', 'Ghost', 'ghost'],
            ['button-danger', 'خطر', 'Danger', 'danger'], ['button-success', 'موفقیت', 'Success', 'success'],
            ['button-warning', 'هشدار', 'Warning', 'warning'], ['button-link', 'پیوند', 'Link', 'link'],
            ['button-icon', 'دکمه آیکون', 'Icon Button', 'icon'], ['button-split', 'دو‌بخشی', 'Split Button', 'split'],
            ['button-loading', 'در حال بارگذاری', 'Loading Button', 'loading'], ['button-disabled', 'غیرفعال', 'Disabled Button', 'disabled'],
            ['button-group', 'گروه دکمه', 'Button Group', 'group'],
        ],
        'defaults' => [
            'overview' => 'دکمه برای اقدام مشخص استفاده می‌شود و گونه‌های آن اهمیت و وضعیت عمل را نشان می‌دهند.',
            'usage' => 'در هر بخش یک اقدام اصلی نگه دارید. برای اقدام غیرقابل بازگشت، متن دقیق و تأیید مناسب ارائه کنید.',
            'props' => [['name' => 'variant', 'type' => 'enum', 'default' => 'primary', 'description' => 'primary، secondary، outline، ghost یا معنایی.'], ['name' => 'size', 'type' => 'enum', 'default' => 'medium', 'description' => 'small، medium یا large.'], ['name' => 'loading', 'type' => 'boolean', 'default' => 'false', 'description' => 'نمایش progress و جلوگیری از تکرار عمل.'], ['name' => 'disabled', 'type' => 'boolean', 'default' => 'false', 'description' => 'حالت غیرقابل تعامل با توضیح علت در صورت نیاز.'], ['name' => 'fullWidth', 'type' => 'boolean', 'default' => 'false', 'description' => 'گسترش کنترل تا عرض container.']],
            'events' => [['name' => 'click', 'description' => 'پس از فعال‌سازی با ماوس یا صفحه‌کلید اجرا می‌شود.'], ['name' => 'submit', 'description' => 'برای submit فرم، نوع button باید آگاهانه انتخاب شود.']],
            'states' => ['default', 'hover', 'focus-visible', 'loading', 'disabled'],
            'related' => ['forms', 'feedback'],
        ],
    ],
    [
        'id' => 'forms', 'label' => 'فرم‌ها', 'english' => 'Forms', 'icon' => 'edit-3', 'family' => 'forms',
        'items' => [
            ['form-text', 'ورودی متن', 'Text Input', 'text'], ['form-password', 'گذرواژه', 'Password', 'password'],
            ['form-email', 'ایمیل', 'Email', 'email'], ['form-phone', 'شماره تلفن', 'Phone', 'tel'],
            ['form-national-id', 'کد ملی', 'National ID', 'national-id'], ['form-search', 'جست‌وجو', 'Search', 'search'],
            ['form-number', 'عدد', 'Number', 'number'], ['form-money', 'مبلغ', 'Money', 'money'],
            ['form-percentage', 'درصد', 'Percentage', 'percentage'], ['form-textarea', 'متن چندخطی', 'Textarea', 'textarea'],
            ['form-select', 'انتخاب تکی', 'Select', 'select'], ['form-multi-select', 'انتخاب چندتایی', 'Multi Select', 'multi-select'],
            ['form-searchable-select', 'انتخاب جست‌وجوپذیر', 'Searchable Select', 'searchable-select'], ['form-checkbox', 'چک‌باکس', 'Checkbox', 'checkbox'],
            ['form-radio', 'دکمه رادیویی', 'Radio', 'radio'], ['form-switch', 'کلید روشن/خاموش', 'Switch', 'switch'],
            ['form-range', 'لغزنده', 'Range Slider', 'range'], ['form-otp', 'کد یک‌بارمصرف', 'OTP', 'otp'],
            ['form-file', 'بارگذاری فایل', 'File Upload', 'file'], ['form-image', 'بارگذاری تصویر', 'Image Upload', 'image'],
            ['form-rich-text', 'ویرایشگر متن', 'Rich Text Editor', 'rich-text'],
        ],
        'defaults' => [
            'overview' => 'کنترل فرم باید label پایدار، راهنمای مرتبط و وضعیت اعتبارسنجی قابل‌فهم داشته باشد.',
            'usage' => 'خطا را کنار فیلد بیان کنید، مقدار واردشده را حفظ کنید و در کنار رنگ از متن و aria-invalid استفاده کنید.',
            'props' => [['name' => 'name', 'type' => 'string', 'default' => 'fieldName', 'description' => 'نام پایدار برای ارسال فرم.'], ['name' => 'label', 'type' => 'string', 'default' => 'عنوان فیلد', 'description' => 'برچسب قابل مشاهده و متصل به کنترل.'], ['name' => 'required', 'type' => 'boolean', 'default' => 'false', 'description' => 'اجباری بودن همراه با نشان واضح.'], ['name' => 'disabled', 'type' => 'boolean', 'default' => 'false', 'description' => 'غیرفعال‌سازی با حفظ وضوح متن.'], ['name' => 'error', 'type' => 'string', 'default' => '—', 'description' => 'پیام خطای متنی.']],
            'events' => [['name' => 'input', 'description' => 'کاربر مقدار را تغییر می‌دهد.'], ['name' => 'change', 'description' => 'مقدار نهایی یا انتخاب تغییر می‌کند.'], ['name' => 'invalid', 'description' => 'اعتبارسنجی سمت کاربر شکست می‌خورد؛ سرور همچنان مرجع نهایی است.']],
            'states' => ['default', 'hover', 'focus', 'error', 'success', 'disabled', 'readonly'],
            'related' => ['date-time', 'feedback', 'multi-step-form'],
        ],
    ],
    [
        'id' => 'date-time', 'label' => 'تاریخ و زمان', 'english' => 'Date & Time', 'icon' => 'calendar', 'family' => 'date-time',
        'items' => [
            ['date-persian-picker', 'انتخاب‌گر تاریخ شمسی', 'Persian Date Picker', 'jalali'], ['date-range', 'بازه تاریخ', 'Date Range', 'range'],
            ['time-picker', 'انتخاب‌گر زمان', 'Time Picker', 'time'], ['datetime-picker', 'تاریخ و ساعت', 'DateTime Picker', 'datetime'],
            ['calendar', 'تقویم', 'Calendar', 'calendar'], ['jalali-calendar', 'تقویم جلالی', 'Jalali Calendar', 'jalali'],
            ['relative-date', 'تاریخ نسبی', 'Relative Date', 'relative'], ['deadline-indicator', 'نشانگر مهلت', 'Deadline Indicator', 'deadline'],
        ],
        'defaults' => [
            'overview' => 'ورودی زمان از قالب فارسی مرورگر استفاده می‌کند و مقدار قابل ذخیره را در قالب استاندارد نگه می‌دارد.',
            'usage' => 'منطقه زمانی و نوع تقویم را آشکار کنید؛ تاریخ سررسید را همراه متن وضعیت بیان کنید.',
            'props' => [['name' => 'value', 'type' => 'date|string', 'default' => 'today', 'description' => 'تاریخ انتخاب‌شده.'], ['name' => 'calendar', 'type' => 'enum', 'default' => 'persian', 'description' => 'تقویم نمایشی جلالی.'], ['name' => 'min/max', 'type' => 'date', 'default' => '—', 'description' => 'مرزهای انتخاب معتبر.']],
            'events' => [['name' => 'select', 'description' => 'یک روز از تقویم انتخاب می‌شود.'], ['name' => 'navigate', 'description' => 'ماه قبل یا بعد نمایش داده می‌شود.']],
            'states' => ['ماه جاری', 'روز انتخاب‌شده', 'امروز', 'غیرفعال', 'سررسید گذشته'],
            'related' => ['forms', 'installments'],
        ],
    ],
    [
        'id' => 'overlays', 'label' => 'پنجره و لایه‌ها', 'english' => 'Modals & Overlays', 'icon' => 'sidebar', 'family' => 'overlays',
        'items' => [
            ['overlay-modal', 'پنجره استاندارد', 'Standard Modal', 'modal'], ['overlay-confirmation', 'تأیید', 'Confirmation Modal', 'confirm'],
            ['overlay-danger', 'تأیید خطر', 'Danger Confirmation', 'danger'], ['overlay-form', 'فرم در پنجره', 'Form Modal', 'form'],
            ['overlay-fullscreen', 'تمام‌صفحه', 'Fullscreen Modal', 'fullscreen'], ['overlay-drawer', 'کشویی کناری', 'Drawer', 'drawer'],
            ['overlay-bottom-sheet', 'برگه پایین صفحه', 'Bottom Sheet', 'sheet'], ['overlay-popover', 'پاپ‌اور', 'Popover', 'popover'],
            ['overlay-tooltip', 'راهنمای شناور', 'Tooltip', 'tooltip'], ['overlay-dropdown', 'منوی بازشونده', 'Dropdown Menu', 'dropdown'],
            ['overlay-context-menu', 'منوی زمینه‌ای', 'Context Menu', 'context'],
        ],
        'defaults' => [
            'overview' => 'لایه روی محتوای صفحه قرار می‌گیرد و برای کار موقت focus را به شکل قابل پیش‌بینی مدیریت می‌کند.',
            'usage' => 'عنوان و دکمه بستن فراهم کنید؛ Escape، کلیک پس‌زمینه، focus trap و قفل پیمایش را فعال نگه دارید.',
            'props' => [['name' => 'open', 'type' => 'boolean', 'default' => 'false', 'description' => 'باز یا بسته بودن لایه.'], ['name' => 'dismissible', 'type' => 'boolean', 'default' => 'true', 'description' => 'اجازه خروج با Escape یا backdrop.'], ['name' => 'size', 'type' => 'enum', 'default' => 'medium', 'description' => 'عرض یا حالت تمام‌صفحه.']],
            'events' => [['name' => 'open', 'description' => 'لایه باز می‌شود و focus به آن منتقل می‌شود.'], ['name' => 'close', 'description' => 'لایه بسته و focus به عنصر آغازگر برمی‌گردد.']],
            'states' => ['closed', 'open', 'keyboard focus', 'loading'],
            'accessibility' => 'از dialog و aria-modal استفاده کنید؛ عنوان را با aria-labelledby پیوند دهید، focus را در پنجره نگه دارید و پس از بستن به عنصر آغازگر برگردانید.',
            'related' => ['buttons', 'feedback', 'navigation'],
        ],
    ],
    [
        'id' => 'navigation', 'label' => 'ناوبری', 'english' => 'Navigation', 'icon' => 'compass', 'family' => 'navigation',
        'items' => [
            ['navigation-sidebar', 'نوار کناری اصلی', 'Main Sidebar', 'sidebar'], ['navigation-collapsed', 'نوار کناری جمع‌شده', 'Collapsed Sidebar', 'collapsed'],
            ['navigation-mobile', 'نوار کناری موبایل', 'Mobile Sidebar', 'mobile'], ['navigation-header', 'سربرگ', 'Header', 'header'],
            ['navigation-breadcrumb', 'مسیر راهنما', 'Breadcrumb', 'breadcrumb'], ['navigation-tabs', 'زبانه‌ها', 'Tabs', 'tabs'],
            ['navigation-vertical-tabs', 'زبانه عمودی', 'Vertical Tabs', 'vertical-tabs'], ['navigation-pagination', 'صفحه‌بندی', 'Pagination', 'pagination'],
            ['navigation-stepper', 'گام‌ها', 'Stepper', 'stepper'], ['navigation-menu', 'منوی ناوبری', 'Navigation Menu', 'menu'],
            ['navigation-bottom', 'ناوبری پایین', 'Bottom Navigation', 'bottom'],
        ],
        'defaults' => [
            'overview' => 'ناوبری مسیر و موقعیت فعلی را نشان می‌دهد و باید با کلیدها و صفحه‌خوان قابل فهم باشد.',
            'usage' => 'بخش فعال را از مسیر واقعی بگیرید و روی موبایل از drawer یا bottom navigation استفاده کنید.',
            'props' => [['name' => 'items', 'type' => 'array', 'default' => '—', 'description' => 'پیوندهای دارای عنوان و مقصد.'], ['name' => 'active', 'type' => 'string', 'default' => 'current', 'description' => 'آیتم جاری با aria-current مشخص می‌شود.'], ['name' => 'collapsible', 'type' => 'boolean', 'default' => 'true', 'description' => 'قابلیت جمع/باز شدن در دسکتاپ.']],
            'events' => [['name' => 'navigate', 'description' => 'انتخاب آیتم به مقصد آن می‌رود.'], ['name' => 'toggle', 'description' => 'ناوبری باز یا بسته می‌شود.']],
            'states' => ['active', 'expanded', 'collapsed', 'mobile drawer'],
            'related' => ['overlays', 'icons'],
        ],
    ],
    [
        'id' => 'data-display', 'label' => 'نمایش داده', 'english' => 'Data Display', 'icon' => 'bar-chart-2', 'family' => 'data-display',
        'items' => [
            ['display-card', 'کارت', 'Card', 'card'], ['display-metric', 'کارت شاخص', 'Metric Card', 'metric'],
            ['display-avatar', 'آواتار', 'Avatar', 'avatar'], ['display-avatar-group', 'گروه آواتار', 'Avatar Group', 'avatars'],
            ['display-badge', 'نشان', 'Badge', 'badge'], ['display-status', 'وضعیت', 'Status Indicator', 'status'],
            ['display-divider', 'جداکننده', 'Divider', 'divider'], ['display-accordion', 'آکاردئون', 'Accordion', 'accordion'],
            ['display-table', 'جدول', 'Table', 'table'], ['display-grid', 'جدول داده', 'Data Grid', 'grid'],
            ['display-list', 'فهرست', 'List', 'list'], ['display-timeline', 'خط زمانی', 'Timeline', 'timeline'],
            ['display-empty', 'حالت خالی', 'Empty State', 'empty'], ['display-skeleton', 'اسکلت بارگذاری', 'Skeleton', 'skeleton'],
            ['display-progress', 'نوار پیشرفت', 'Progress Bar', 'progress'], ['display-circular-progress', 'پیشرفت دایره‌ای', 'Circular Progress', 'circular'],
            ['display-financial-numbers', 'اعداد مالی', 'Financial Numbers', 'financial'],
        ],
        'defaults' => [
            'overview' => 'الگوی نمایش داده، اطلاعات را با تراکم متناسب نشان می‌دهد و روی موبایل به ساختار قابل اسکن تبدیل می‌شود.',
            'usage' => 'مقادیر را برچسب‌گذاری کنید و برای جدول، empty/loading/error state و راهکار موبایل داشته باشید.',
            'props' => [['name' => 'label', 'type' => 'string', 'default' => 'عنوان', 'description' => 'نام قابل فهم داده.'], ['name' => 'value', 'type' => 'string|number', 'default' => '—', 'description' => 'مقدار نمایشی از منبع معتبر.'], ['name' => 'status', 'type' => 'enum', 'default' => 'neutral', 'description' => 'neutral، success، warning یا danger همراه متن.']],
            'events' => [['name' => 'sort', 'description' => 'مرتب‌سازی جدول تغییر می‌کند.'], ['name' => 'expand', 'description' => 'جزئیات یک ردیف یا بخش باز می‌شود.']],
            'states' => ['default', 'loading', 'empty', 'error', 'selected'],
            'related' => ['feedback', 'payments', 'accounting'],
        ],
    ],
    [
        'id' => 'feedback', 'label' => 'بازخورد', 'english' => 'Feedback', 'icon' => 'bell', 'family' => 'feedback',
        'items' => [
            ['feedback-toast', 'پیام موقت', 'Toast', 'toast'], ['feedback-alert', 'هشدار', 'Alert', 'alert'],
            ['feedback-inline', 'پیام درون‌صفحه', 'Inline Message', 'inline'], ['feedback-success', 'وضعیت موفق', 'Success State', 'success'],
            ['feedback-error', 'وضعیت خطا', 'Error State', 'error'], ['feedback-warning', 'وضعیت هشدار', 'Warning State', 'warning'],
            ['feedback-loading', 'وضعیت بارگذاری', 'Loading State', 'loading'], ['feedback-offline', 'وضعیت آفلاین', 'Offline State', 'offline'],
            ['feedback-permission', 'عدم دسترسی', 'Permission Denied', 'permission'], ['feedback-empty-results', 'نتیجه خالی', 'Empty Results', 'no-results'],
        ],
        'defaults' => [
            'overview' => 'بازخورد نتیجه کنش را با متن کوتاه و معنای روشن به کاربر اعلام می‌کند.',
            'usage' => 'موفقیت و خطا را در context مرتبط نشان دهید و پیام مهم را صرفاً با toast گذرا منتقل نکنید.',
            'props' => [['name' => 'tone', 'type' => 'enum', 'default' => 'info', 'description' => 'success، warning، danger یا info همراه آیکون و متن.'], ['name' => 'message', 'type' => 'string', 'default' => 'تغییر ذخیره شد', 'description' => 'پیام مستقیم و قابل درک.'], ['name' => 'duration', 'type' => 'number', 'default' => '5000', 'description' => 'مدت نمایش پیام موقت برحسب ms.']],
            'events' => [['name' => 'dismiss', 'description' => 'کاربر پیام را می‌بندد.'], ['name' => 'retry', 'description' => 'کاربر درخواست ناموفق را دوباره انجام می‌دهد.']],
            'states' => ['success', 'warning', 'error', 'loading', 'offline', 'empty'],
            'related' => ['overlays', 'forms', 'data-display'],
        ],
    ],
    [
        'id' => 'advanced', 'label' => 'کامپوننت پیشرفته', 'english' => 'Advanced Components', 'icon' => 'command', 'family' => 'advanced',
        'items' => [
            ['advanced-kanban', 'برد کانبان', 'Kanban Board', 'kanban'], ['advanced-drag-drop', 'کشیدن و رها کردن', 'Drag & Drop', 'drag-drop'],
            ['advanced-file-manager', 'مدیر فایل', 'File Manager', 'file-manager'], ['advanced-rich-text', 'ویرایشگر غنی', 'Rich Text Editor', 'rich-editor'],
            ['advanced-command', 'پالت فرمان', 'Command Palette', 'command'], ['advanced-search-overlay', 'پوشش جست‌وجو', 'Search Overlay', 'search-overlay'],
            ['advanced-notifications', 'مرکز اعلان', 'Notification Center', 'notifications'], ['advanced-activity', 'رویدادهای اخیر', 'Activity Feed', 'activity'],
            ['advanced-global-search', 'جست‌وجوی سراسری', 'Global Search', 'global-search'], ['advanced-multi-step', 'فرم چندمرحله‌ای', 'Multi-Step Form', 'multi-step'],
            ['advanced-filter-builder', 'سازنده فیلتر', 'Filter Builder', 'filter'], ['advanced-bulk-actions', 'اقدام گروهی', 'Bulk Actions', 'bulk'],
            ['advanced-export', 'منوی خروجی', 'Export Menu', 'export'],
        ],
        'defaults' => [
            'overview' => 'این الگو برای workflowهای چندمرحله‌ای و داده‌های تعاملی است؛ عملیات نمونه فقط روی state همین صفحه اثر دارد.',
            'usage' => 'قابلیت‌ها را به وظیفه مشخص محدود کنید، نتیجه کنش را قابل بازگشت نگه دارید و برای صفحه‌کلید جایگزین فراهم کنید.',
            'props' => [['name' => 'items', 'type' => 'array', 'default' => 'sample data', 'description' => 'داده‌های محلی preview.'], ['name' => 'onChange', 'type' => 'event', 'default' => '—', 'description' => 'تغییر فقط در state نمایشی.'], ['name' => 'keyboard', 'type' => 'boolean', 'default' => 'true', 'description' => 'پشتیبانی از صفحه‌کلید.']],
            'events' => [['name' => 'change', 'description' => 'ترتیب یا فیلتر داده نمایشی تغییر می‌کند.'], ['name' => 'search', 'description' => 'جست‌وجوی لحظه‌ای در catalog محلی انجام می‌شود.']],
            'states' => ['default', 'active', 'dragging', 'empty', 'loading'],
            'related' => ['navigation', 'data-display', 'forms'],
        ],
    ],
    [
        'id' => 'financial-display', 'label' => 'نمایش مالی', 'english' => 'Financial Display', 'icon' => 'dollar-sign', 'family' => 'financial',
        'items' => [
            ['finance-money', 'نمایش مبلغ', 'Money Display', 'money'], ['finance-currency-input', 'ورودی ارز', 'Currency Input', 'currency-input'],
            ['finance-balance-card', 'کارت موجودی', 'Balance Card', 'balance'], ['finance-credit-card', 'کارت اعتباری', 'Credit Card', 'credit-card'],
            ['finance-debt-summary', 'خلاصه بدهی', 'Debt Summary', 'debt'], ['finance-summary', 'خلاصه مالی', 'Financial Summary', 'summary'],
            ['finance-transaction-amount', 'مبلغ تراکنش', 'Transaction Amount', 'transaction'], ['finance-profit', 'سود', 'Profit Display', 'profit'],
            ['finance-loss', 'زیان', 'Loss Display', 'loss'],
        ],
        'defaults' => [
            'overview' => 'کامپوننت مالی اعداد آزمایشی فارسی را با واحد، جهت و وضعیت قابل فهم نمایش می‌دهد.',
            'usage' => 'نمونه‌های این صفحه ثابت و نمایشی‌اند. مبلغ معتبر را از منطق PHP سمت سرور دریافت و فرمت کنید.',
            'props' => [['name' => 'amount', 'type' => 'integer', 'default' => '۱۲٬۵۰۰٬۰۰۰', 'description' => 'مقدار آزمایشی به ریال/تومان مشخص.'], ['name' => 'currency', 'type' => 'enum', 'default' => 'تومان', 'description' => 'واحد کنار مقدار نمایش داده می‌شود.'], ['name' => 'tone', 'type' => 'enum', 'default' => 'neutral', 'description' => 'مفهوم سود/زیان همراه متن و علامت.']],
            'events' => [['name' => 'copy', 'description' => 'مبلغ قالب‌بندی‌شده کپی می‌شود.'], ['name' => 'input', 'description' => 'مقدار ورودی محلی عوض می‌شود؛ هیچ تراکنشی ثبت نمی‌شود.']],
            'states' => ['مثبت', 'منفی', 'صفر', 'مانده در انتظار'],
            'accessibility' => 'واحد پول را صریح بنویسید و برای رقم‌های طولانی از tabular numbers و متن توضیحی استفاده کنید.',
            'related' => ['installments', 'payments', 'accounting'],
        ],
    ],
    [
        'id' => 'installments', 'label' => 'اقساط', 'english' => 'Installments', 'icon' => 'calendar', 'family' => 'installments',
        'items' => [
            ['installment-card', 'کارت قسط', 'Installment Card', 'card'], ['installment-table', 'جدول اقساط', 'Installment Table', 'table'],
            ['installment-timeline', 'خط زمانی اقساط', 'Installment Timeline', 'timeline'], ['installment-schedule', 'برنامه پرداخت', 'Payment Schedule', 'schedule'],
            ['installment-due', 'تاریخ سررسید', 'Due Date Indicator', 'due'], ['installment-overdue', 'قسط معوق', 'Overdue Indicator', 'overdue'],
            ['installment-partial', 'پرداخت بخشی', 'Partial Payment', 'partial'], ['installment-status', 'وضعیت قسط', 'Installment Status', 'status'],
            ['installment-progress', 'پیشرفت اقساط', 'Installment Progress', 'progress'],
        ],
        'defaults' => [
            'overview' => 'الگوی اقساط، مبلغ قسط، سررسید و وضعیت را در کنار هم نشان می‌دهد.',
            'usage' => 'سررسید و وضعیت را متنی نمایش دهید. هر مبلغ نمونه روی داده محلی است و اقدامی در حساب مشتری انجام نمی‌دهد.',
            'props' => [['name' => 'installmentNumber', 'type' => 'integer', 'default' => '۶', 'description' => 'شماره قسط نمایشی.'], ['name' => 'dueDate', 'type' => 'date', 'default' => '1405/09/15', 'description' => 'سررسید نمونه جلالی.'], ['name' => 'status', 'type' => 'enum', 'default' => 'upcoming', 'description' => 'scheduled، due، partial، paid یا overdue.']],
            'events' => [['name' => 'open', 'description' => 'جزئیات قسط نمونه باز می‌شود.'], ['name' => 'filter', 'description' => 'فهرست نمایشی بر اساس وضعیت فیلتر می‌شود.']],
            'states' => ['paid', 'upcoming', 'due', 'partial', 'overdue'],
            'accessibility' => 'تاریخ، شماره و مبلغ را با label متنی مرتبط کنید و وضعیت را فقط با رنگ نشان ندهید.',
            'related' => ['financial-display', 'payments', 'date-time'],
        ],
    ],
    [
        'id' => 'contracts', 'label' => 'قراردادها', 'english' => 'Contracts', 'icon' => 'file-text', 'family' => 'contracts',
        'items' => [
            ['contract-card', 'کارت قرارداد', 'Contract Card', 'card'], ['contract-summary', 'خلاصه قرارداد', 'Contract Summary', 'summary'],
            ['contract-timeline', 'خط زمانی قرارداد', 'Contract Timeline', 'timeline'], ['contract-status', 'وضعیت قرارداد', 'Contract Status', 'status'],
            ['contract-preview', 'پیش‌نمایش قرارداد', 'Contract Preview', 'preview'], ['contract-document', 'سند قرارداد', 'Contract Document', 'document'],
            ['contract-signature', 'امضای قرارداد', 'Contract Signature', 'signature'], ['contract-variables', 'متغیرهای قرارداد', 'Contract Variables', 'variables'],
        ],
        'defaults' => [
            'overview' => 'کارت قرارداد، شناسه و وضعیت آزمایشی را در ساختاری قابل اسکن نشان می‌دهد.',
            'usage' => 'جزئیات سند را از View سمت سرور بگیرید و از پیش‌نمایش خوانا برای چاپ و بررسی استفاده کنید.',
            'props' => [['name' => 'contractNumber', 'type' => 'string', 'default' => 'PR-1405-001204', 'description' => 'شناسه نمونه.'], ['name' => 'status', 'type' => 'enum', 'default' => 'active', 'description' => 'وضعیت قرارداد همراه متن.'], ['name' => 'customer', 'type' => 'string', 'default' => 'نگار احمدی', 'description' => 'نام نمایشی داده آزمایشی.']],
            'events' => [['name' => 'open', 'description' => 'نمایش جزئیات نمونه.'], ['name' => 'download', 'description' => 'در نمونه واقعی سند مجاز دریافت می‌شود.']],
            'states' => ['draft', 'active', 'completed', 'cancelled', 'expired'],
            'related' => ['installments', 'payments', 'accounting'],
        ],
    ],
    [
        'id' => 'payments', 'label' => 'پرداخت‌ها', 'english' => 'Payments', 'icon' => 'credit-card', 'family' => 'payments',
        'items' => [
            ['payment-form', 'فرم پرداخت', 'Payment Form', 'form'], ['payment-receipt', 'رسید پرداخت', 'Payment Receipt', 'receipt'],
            ['payment-status', 'وضعیت پرداخت', 'Payment Status', 'status'], ['payment-history', 'تاریخچه پرداخت', 'Payment History', 'history'],
            ['payment-transaction', 'جزئیات تراکنش', 'Transaction Details', 'transaction'], ['payment-allocation', 'تخصیص پرداخت', 'Payment Allocation', 'allocation'],
            ['payment-settlement', 'پیش‌نمایش تسویه', 'Settlement Preview', 'settlement'],
        ],
        'defaults' => [
            'overview' => 'الگوی پرداخت اطلاعات نمونه را ارائه می‌دهد و به درگاه یا endpoint پرداخت متصل نیست.',
            'usage' => 'رسید را پس از تأیید سرور بسازید و reference، زمان و مبلغ را واضح نشان دهید.',
            'props' => [['name' => 'reference', 'type' => 'string', 'default' => 'PAY-1405-009812', 'description' => 'شناسه نمایشی تراکنش.'], ['name' => 'amount', 'type' => 'integer', 'default' => '۱۲٬۵۰۰٬۰۰۰', 'description' => 'مبلغ آزمایشی.'], ['name' => 'status', 'type' => 'enum', 'default' => 'success', 'description' => 'موفق، در انتظار یا ناموفق.']],
            'events' => [['name' => 'viewReceipt', 'description' => 'رسید نمونه باز می‌شود.'], ['name' => 'copyReference', 'description' => 'شناسه نمونه کپی می‌شود.']],
            'states' => ['pending', 'success', 'failed', 'refunded'],
            'related' => ['financial-display', 'installments', 'accounting'],
        ],
    ],
    [
        'id' => 'accounting', 'label' => 'حسابداری', 'english' => 'Accounting', 'icon' => 'book-open', 'family' => 'accounting',
        'items' => [
            ['accounting-ledger', 'دفتر کل', 'Ledger Table', 'ledger'], ['accounting-debit-credit', 'بدهکار و بستانکار', 'Debit / Credit', 'debit-credit'],
            ['accounting-balance', 'مانده حساب', 'Account Balance', 'balance'], ['accounting-transaction', 'تراکنش مالی', 'Financial Transaction', 'transaction'],
            ['accounting-journal', 'سند حسابداری', 'Journal Entry', 'journal'], ['accounting-report', 'گزارش مالی', 'Financial Report', 'report'],
            ['accounting-selector', 'انتخاب حساب', 'Account Selector', 'selector'],
        ],
        'defaults' => [
            'overview' => 'الگوهای حسابداری، طرف بدهکار/بستانکار، تاریخ و مرجع را برای داده آزمایشی نمایش می‌دهند.',
            'usage' => 'موازنه و ثبت حسابداری در سرویس سمت سرور انجام می‌شود؛ این نمونه فقط layout را نشان می‌دهد.',
            'props' => [['name' => 'account', 'type' => 'string', 'default' => 'صندوق شعبه مرکزی', 'description' => 'نام حساب نمونه.'], ['name' => 'debit', 'type' => 'integer', 'default' => '۵٬۰۰۰٬۰۰۰', 'description' => 'بدهکار نمایشی.'], ['name' => 'credit', 'type' => 'integer', 'default' => '۰', 'description' => 'بستانکار نمایشی.']],
            'events' => [['name' => 'sort', 'description' => 'ستون دفتر کل مرتب می‌شود.'], ['name' => 'selectAccount', 'description' => 'حساب نمونه انتخاب می‌شود.']],
            'states' => ['debit', 'credit', 'balanced', 'unbalanced'],
            'accessibility' => 'در جدول مالی headerها را به سلول‌ها متصل کنید و ارقام را با نام ستون و واحد پول ارائه دهید.',
            'related' => ['financial-display', 'payments', 'contracts'],
        ],
    ],
    [
        'id' => 'collection-legal', 'label' => 'وصول و حقوقی', 'english' => 'Collection & Legal', 'icon' => 'briefcase', 'family' => 'collection-legal',
        'items' => [
            ['collection-follow-up', 'کارت پیگیری', 'Follow-up Card', 'follow-up'], ['collection-timeline', 'خط زمانی وصول', 'Collection Timeline', 'timeline'],
            ['collection-overdue', 'صف معوق', 'Overdue Queue', 'queue'], ['legal-case', 'کارت پرونده حقوقی', 'Legal Case Card', 'case'],
            ['legal-document', 'سند حقوقی', 'Legal Document', 'document'], ['legal-activity', 'فعالیت پرونده', 'Case Activity', 'activity'],
            ['collection-reminder', 'یادآوری', 'Reminder Component', 'reminder'],
        ],
        'defaults' => [
            'overview' => 'الگوهای پیگیری وضعیت نمونه، موعد و اقدام بعدی را در یک نگاه ارائه می‌کنند.',
            'usage' => 'اقدام حقوقی و پیگیری را با دسترسی مجاز و event سمت سرور اجرا کنید؛ catalog داده واقعی بارگذاری نمی‌کند.',
            'props' => [['name' => 'status', 'type' => 'enum', 'default' => 'scheduled', 'description' => 'وضعیت پیگیری/پرونده.'], ['name' => 'dueDate', 'type' => 'date', 'default' => '1405/09/15', 'description' => 'موعد نمونه.'], ['name' => 'owner', 'type' => 'string', 'default' => 'کارشناس نمونه', 'description' => 'مسئول نمایشی.']],
            'events' => [['name' => 'openCase', 'description' => 'جزئیات محلی نمونه نمایش داده می‌شود.'], ['name' => 'dismissReminder', 'description' => 'یادآور نمونه بسته می‌شود.']],
            'states' => ['scheduled', 'contacted', 'overdue', 'legal-review', 'closed'],
            'related' => ['installments', 'contracts', 'feedback'],
        ],
    ],
];

$groups = [];
$components = [];
foreach ($groupDefinitions as $definition) {
    $items = $makeItems($definition['id'], $definition['family'], $definition['items'], $definition['defaults'] ?? []);
    $groups[] = [
        'id' => $definition['id'], 'label' => $definition['label'], 'english' => $definition['english'],
        'icon' => $definition['icon'], 'family' => $definition['family'], 'items' => $items,
    ];
    array_push($components, ...$items);
}

$foundationItems = [];
foreach ($foundationGroups as $group) {
    array_push($foundationItems, ...$group['items']);
}

$templateDefinitions = [
    ['template-admin-dashboard', 'داشبورد مدیر', 'Admin Dashboard', 'dashboard'],
    ['template-customer-dashboard', 'داشبورد مشتری', 'Customer Dashboard', 'customer'],
    ['template-customer-list', 'فهرست مشتریان', 'Customer List', 'customers'],
    ['template-customer-detail', 'جزئیات مشتری', 'Customer Detail', 'customer-detail'],
    ['template-contract-list', 'فهرست قراردادها', 'Contract List', 'contracts'],
    ['template-contract-detail', 'جزئیات قرارداد', 'Contract Detail', 'contract-detail'],
    ['template-create-contract', 'ساخت قرارداد', 'Create Contract', 'create-contract'],
    ['template-installments', 'مدیریت اقساط', 'Installment Management', 'installments'],
    ['template-payments', 'مدیریت پرداخت', 'Payment Management', 'payments'],
    ['template-financial-dashboard', 'داشبورد مالی', 'Financial Dashboard', 'finance'],
    ['template-ledger', 'دفتر کل', 'Ledger', 'ledger'],
    ['template-overdue', 'مدیریت معوقات', 'Overdue Management', 'overdue'],
    ['template-legal-cases', 'مدیریت پرونده حقوقی', 'Legal Case Management', 'legal'],
    ['template-users', 'مدیریت کاربران', 'User Management', 'users'],
    ['template-settings', 'تنظیمات', 'Settings', 'settings'],
    ['template-reports', 'گزارش‌ها', 'Reports', 'reports'],
];
$templates = $makeItems('templates', 'templates', $templateDefinitions, [
    'overview' => 'الگوی صفحه نمایشی با داده‌های فارسی ساختگی است و هیچ مسیر عملیاتی یا دیتابیسی اجرا نمی‌کند.',
    'usage' => 'صفحه را از کامپوننت‌های همین catalog بسازید و منطق کسب‌وکار را در Controller/Service مجاز نگه دارید.',
    'props' => [['name' => 'layout', 'type' => 'string', 'default' => 'responsive', 'description' => 'چیدمان سازگار با عرض صفحه.'], ['name' => 'components', 'type' => 'array', 'default' => 'catalog items', 'description' => 'بخش‌های استفاده‌شده در preview.']],
    'events' => [['name' => 'navigate', 'description' => 'پیوندهای preview بین نماهای نمونه جابه‌جا می‌شوند.']],
    'states' => ['loading', 'filled', 'empty', 'responsive'],
    'related' => ['navigation', 'data-display', 'forms'],
]);

$allDocumented = array_merge($foundationItems, $components, $templates);
$categories = [
    ['id' => 'foundations', 'label' => 'بنیادهای طراحی', 'icon' => 'sliders', 'items' => $foundationGroups],
    ['id' => 'components', 'label' => 'کتابخانه کامپوننت‌ها', 'icon' => 'package', 'items' => $groups],
    ['id' => 'templates', 'label' => 'الگوهای صفحه', 'icon' => 'layout', 'items' => $templates],
];

return [
    'languages' => $languages,
    'categories' => $categories,
    'foundations' => $foundationItems,
    'groups' => $groups,
    'components' => $components,
    'templates' => $templates,
    'all' => $allDocumented,
    'counts' => [
        'components' => count($components),
        'categories' => count($foundationGroups) + count($groups) + 1,
        'patterns' => count($templates),
        'codeExamples' => count($allDocumented) * count($languages),
        'icons' => 0,
    ],
];
