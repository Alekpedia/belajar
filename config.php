<?php
// Mendefinisikan konstanta keamanan
define('APP_RUNNING', true);

// / --- PENGATURAN DATABASE ---
// $db_host = 'localhost';     // Sesuaikan dengan host DB kamu
// $db_name = 'u596827699_study'; // Sesuaikan dengan nama DB kamu
// $db_user = 'u596827699_study';          // Sesuaikan dengan username DB kamu
// $db_pass = '9VJ5Zo|pnRt!';              // Sesuaikan dengan password DB kamu
// /--------------------------

// --- PENGATURAN DATABASE ---
$db_host = 'localhost';     // Sesuaikan dengan host DB kamu
$db_name = 'study'; // Sesuaikan dengan nama DB kamu
$db_user = 'root';          // Sesuaikan dengan username DB kamu
$db_pass = 'root';              // Sesuaikan dengan password DB kamu
// --------------------------

// Memulai sesi di setiap halaman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Membuat Token CSRF untuk keamanan form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Koneksi Database (PDO)
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    // Variabel $pdo akan digunakan di file lain
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Di mode produksi, jangan tampilkan error detail
    // Cukup log error dan tampilkan pesan umum
    die("Koneksi database gagal: " . $e->getMessage()); 
}

// Set pengaturan error (sesuai dari langkah sebelumnya)
ini_set('display_errors', 0); // Matikan di produksi
ini_set('log_errors', 1);
// ini_set('error_log', '/path/ke/php-error.log'); // Tentukan path log error
error_reporting(E_ALL);

// ================================================
// FUNGSI BARU UNTUK HIERARKI KATEGORI
// ================================================

/**
 * Mengambil semua kategori dan mengaturnya dalam struktur hierarki (pohon).
 * Digunakan untuk menampilkan sidebar/dropdown.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @param int $parentId ID dari induk kategori (default 0 untuk root).
 * @param int $level Tingkat kedalaman (untuk indentasi).
 * @return array Array kategori hierarkis.
 */
function getHierarchicalCategories(PDO $pdo, int $parentId = 0, int $level = 0): array {
    $categories = [];
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE parent_id = ? ORDER BY name ASC");
    $stmt->execute([$parentId]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['level'] = $level;
        $categories[] = $row;
        $children = getHierarchicalCategories($pdo, $row['id'], $level + 1);
        $categories = array_merge($categories, $children);
    }
    return $categories;
}

/**
 * Mengambil ID kategori induk beserta semua ID keturunannya (anak, cucu, dst.).
 * Digunakan untuk query artikel saat kategori induk dipilih.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @param int $categoryId ID kategori induk.
 * @return array Array berisi ID induk dan semua keturunannya.
 */
function getCategoryDescendants(PDO $pdo, int $categoryId): array {
    $ids = [$categoryId]; // Mulai dengan ID induk itu sendiri
    
    // Ambil anak langsung
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmt->execute([$categoryId]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Ambil keturunan dari anak ini (rekursif) dan gabungkan
        $descendantIds = getCategoryDescendants($pdo, $row['id']);
        $ids = array_merge($ids, $descendantIds);
    }
    
    return array_unique($ids); // Pastikan tidak ada ID duplikat
}

// ... (fungsi getHierarchicalCategories dan getCategoryDescendants) ...

/**
 * Membangun struktur pohon (nested array) dari kategori.
 * Setiap elemen array akan memiliki 'id', 'name', dan 'children' (array anak).
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @param int $parentId ID dari induk kategori (default 0 untuk root).
 * @return array Struktur pohon kategori.
 */
function buildCategoryTree(PDO $pdo, int $parentId = 0, string $searchTerm = ''): array {
    $tree = [];
    $sql = "SELECT id, name FROM categories WHERE parent_id = ?";
    $params = [$parentId];

    if (!empty($searchTerm)) {
        $sql .= " AND name LIKE ?";
        $params[] = '%' . $searchTerm . '%';
    }

    $sql .= " ORDER BY name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Ambil anak-anak dari kategori ini (rekursif)
        $children = buildCategoryTree($pdo, $row['id'], $searchTerm);
        if (!empty($children)) {
            $row['children'] = $children; // Tambahkan array 'children' jika ada
        } else {
             $row['children'] = []; // Tambahkan array kosong jika tidak punya anak
        }
        $tree[] = $row; // Tambahkan node ke pohon
    }

    return $tree;
}

// ... (fungsi buildCategoryTree) ...

/**
 * Mencetak (render) HTML untuk sidebar kategori accordion.
 *
 * @param array $categories Pohon kategori dari buildCategoryTree().
 * @param int $activeCategoryId ID kategori yang sedang aktif.
 * @param array $ancestorIds Array ID leluhur dari kategori aktif.
 * @param int $level Tingkat kedalaman saat ini.
 * @return string HTML untuk sidebar.
 */
function renderCategorySidebar(array $categories, int $activeCategoryId = 0, array $ancestorIds = [], int $level = 0, int $parentId = 0): string { // <-- DITAMBAHKAN $parentId = 0
    // PERBAIKAN: Hanya tambahkan data-parent-id jika level > 0
    $ulAttributes = ($level > 0) ? ' data-parent-id="' . $parentId . '"' : '';
    $html = '<ul class="space-y-1 ' . ($level > 0 ? 'ml-4 pl-4 border-l border-slate-200 mt-1 hidden' : '') . '"' . $ulAttributes . '>'; // <-- DIPERBARUI

    foreach ($categories as $cat) {
        $isActive = ($cat['id'] == $activeCategoryId);
        $isAncestor = in_array($cat['id'], $ancestorIds);
        $hasChildren = !empty($cat['children']);

        // Tentukan kelas CSS
        $linkClasses = 'block w-full px-4 py-2.5 rounded-lg text-sm transition flex items-center justify-between ';
        if ($isActive) {
            $linkClasses .= 'bg-blue-100 text-blue-700 font-semibold';
        } elseif ($isAncestor) {
             $linkClasses .= 'text-slate-700 font-medium hover:bg-slate-100';
        } else {
            $linkClasses .= 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
        }

        $html .= '<li>';
        // PERBAIKAN: Gunakan /kategori/ bukan kategori/
        $html .= '<a href="/kategori/' . $cat['id'] . '" class="' . $linkClasses . '" data-category-id="' . $cat['id'] . '" ' . ($hasChildren ? 'data-has-children="true"' : '') . '>';
        $html .= '<span>' . htmlspecialchars($cat['name']) . '</span>';
        if ($hasChildren) {
             $html .= '<i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform duration-200 flex-shrink-0"></i>'; // Tambah flex-shrink-0
        }
        $html .= '</a>';

        // Jika punya anak, panggil fungsi ini lagi (rekursif)
        if ($hasChildren) {
            // Kita teruskan ID kategori saat ini ($cat['id']) sebagai $parentId untuk level berikutnya
            $html .= renderCategorySidebar($cat['children'], $activeCategoryId, $ancestorIds, $level + 1, $cat['id']); // <-- Pastikan $cat['id'] dikirim
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}


?> 
