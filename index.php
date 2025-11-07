<?php
// Panggil config (gunakan __DIR__)
require_once __DIR__ . '/config.php';

// --- DEFINISIKAN META TAG SPESIFIK UNTUK HALAMAN INI ---
$page_title = 'Selamat Datang di RangkumanMateri.com';
$page_description = 'Temukan rangkuman materi pelajaran lengkap untuk semua jenjang pendidikan.';
$page_keywords = 'rangkuman materi, belajar, sekolah, materi pelajaran';
// --- AKHIR DEFINISI META ---

// --- Ambil Kategori Hierarkis (untuk Sidebar) ---
$categoryTree = buildCategoryTree($pdo);

// --- LOGIKA PENCARIAN & PAGINATION ---
$limit = 6;
$search_term = $_GET['search'] ?? '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

// Siapkan query
$base_sql = "FROM pages p LEFT JOIN categories c ON p.category_id = c.id";
$where_sql = "";
$params = [];

if (!empty($search_term)) {
    $where_sql = " WHERE p.title LIKE ?";
    $params[] = '%' . $search_term . '%';
}

// Query TOTAL POSTINGAN
$count_query = "SELECT COUNT(p.id) " . $base_sql . $where_sql;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_posts = (int)$stmt_count->fetchColumn();
$total_pages = ceil($total_posts / $limit);

// Query DATA POSTINGAN
$data_query = "SELECT p.title, p.slug, p.icon_path, c.name AS category_name "
            . $base_sql . $where_sql
            . " ORDER BY p.updated_at DESC LIMIT ? OFFSET ?";

$stmt_data = $pdo->prepare($data_query);
$data_params = $params;
$data_params[] = $limit;
$data_params[] = $offset;

$stmt_data->execute($data_params);
$posts = $stmt_data->fetchAll();


// Siapkan array desain
$design_elements = [
    ['color' => 'blue', 'icon' => 'file-text'],
    ['color' => 'purple', 'icon' => 'book-open'],
    ['color' => 'teal', 'icon' => 'clipboard-check'],
    ['color' => 'rose', 'icon' => 'award'],
    ['color' => 'amber', 'icon' => 'lightbulb'],
    ['color' => 'sky', 'icon' => 'pen-tool'],
    ['color' => 'indigo', 'icon' => 'target'],
    ['color' => 'lime', 'icon' => 'feather'],
    ['color' => 'orange', 'icon' => 'compass']
];

// Siapkan parameter URL untuk pagination
$query_params = [];
if (!empty($search_term)) $query_params['search'] = $search_term;


// Muat header
include __DIR__ . '/partials/header.php';
?>

<main>
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row gap-12">

                <aside class="w-full md:w-1/4 order-2 md:order-1" data-aos="fade-up">
                    <div class="sticky top-28 space-y-8">

                        <div>
                            <h3 class="text-xl font-bold text-slate-800 mb-4">Kategori</h3>
                            <div class="rounded-lg max-h-[70vh] overflow-y-auto border border-slate-200 shadow-sm bg-white p-2">
                                <ul class="space-y-1">
                                    <li>
                                        <a href="/" class="block w-full px-4 py-3 rounded-lg text-sm font-medium transition text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                                            Semua Kategori
                                        </a>
                                    </li>
                                </ul>
                                <?php
                                // Panggil fungsi render sidebar dari config.php
                                echo renderCategorySidebar($categoryTree);
                                ?>
                            </div>
                        </div>

                    </div>
                </aside>


                <div class="w-full md:w-3/4 order-1 md:order-2">

                    <div class="flex items-center space-x-3 mb-10" data-aos="fade-up">
                        <div class="flex-shrink-0 bg-blue-100 p-2 rounded-full">
                             <i data-lucide="home" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <div>
                             <h2 class="text-3xl font-extrabold text-slate-800">Semua Materi</h2>
                             <p class="text-slate-500">Telusuri semua materi yang tersedia.</p>
                        </div>
                    </div>

                    <form action="" method="GET" class="relative mb-8">
                        <input type="search" name="search"
                               class="w-full pl-4 pr-10 py-2.5 rounded-lg shadow-sm border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ketik judul materi..."
                               value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="absolute right-0 top-0 h-full px-2.5 text-slate-400 hover:text-blue-600 transition-colors">
                            <i data-lucide="search" class="w-5 h-5"></i>
                        </button>
                    </form>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">

                        <?php if (empty($posts)): ?>
                             <div class="col-span-full text-center py-10">
                                <h3 class="text-2xl font-bold text-slate-700">Tidak Ditemukan</h3>
                                <p class="text-slate-500 mt-2">
                                    <?php if (!empty($search_term)): ?>
                                        Kami tidak menemukan materi dengan judul "<?php echo htmlspecialchars($search_term); ?>".
                                    <?php else: ?>
                                        Belum ada materi untuk ditampilkan.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $index => $post): ?>
                                <?php
                                $design = $design_elements[$index % count($design_elements)];
                                $color = $design['color'];
                                $default_icon = $design['icon'];
                                ?>

                                <a href="/halaman/<?php echo $post['slug']; ?>"
                                   class="group block bg-white rounded-2xl p-4 shadow-lg border border-slate-200 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-<?php echo $color; ?>-500/20"
                                   data-aos="fade-up"
                                   data-aos-delay="<?php echo ($index % 2) * 100; ?>">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0 w-16 h-16 rounded-xl flex items-center justify-center overflow-hidden <?php echo !empty($post['icon_path']) ? 'bg-slate-100' : 'bg-' . $color . '-100'; ?>">
                                            <?php if (!empty($post['icon_path'])): ?>
                                                <img src="/uploads/<?php echo htmlspecialchars($post['icon_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i data-lucide="<?php echo $default_icon; ?>" class="w-8 h-8 text-<?php echo $color; ?>-600"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-800 mb-1 group-hover:text-<?php echo $color; ?>-700 transition-colors"><?php echo htmlspecialchars($post['title']); ?></h3>
                                            <?php if (!empty($post['category_name'])): ?>
                                                <span class="inline-block bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>

                    <nav class="mt-16 flex items-center justify-center space-x-2" data-aos="fade-up">
                        <?php if ($total_pages > 1): ?>
                            <?php if ($current_page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page - 1])); ?>" class="flex items-center justify-center w-10 h-10 rounded-full bg-white text-slate-700 shadow-md border border-slate-200 hover:bg-slate-100 transition">
                                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => $i])); ?>" class="flex items-center justify-center w-10 h-10 rounded-full shadow-md border border-slate-200 transition <?php echo ($i == $current_page) ? 'bg-blue-600 text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page + 1])); ?>" class="flex items-center justify-center w-10 h-10 rounded-full bg-white text-slate-700 shadow-md border border-slate-200 hover:bg-slate-100 transition">
                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </nav>

                </div>
            </div>
        </div>
    </section>
</main>

<?php
// Muat footer
include __DIR__ . '/partials/footer.php';
?>
