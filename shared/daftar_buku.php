<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../index.php');
}

$page_title = "Kelola Daftar Buku";

// Handle peminjaman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pinjam'])) {
    $buku_id = $_POST['buku_id'];
    $user_id = $_SESSION['user_id'];
    $tanggal_pinjam = date('Y-m-d');

    // Cek apakah sudah meminjam buku ini
    $stmt = $pdo->prepare("SELECT * FROM peminjaman WHERE user_id = ? AND buku_id = ? AND status = 'dipinjam'");
    $stmt->execute([$user_id, $buku_id]);
    $sudahPinjam = $stmt->fetch();

    if ($sudahPinjam) {
        $_SESSION['swal_error'] = "Anda sudah meminjam buku ini dan belum mengembalikannya!";
        redirect('daftar_buku.php');
    }

    // Cek stok buku
    $stmt = $pdo->prepare("SELECT stok, judul FROM buku WHERE id = ?");
    $stmt->execute([$buku_id]);
    $buku = $stmt->fetch();

    if (!$buku) {
        $_SESSION['swal_error'] = "Buku tidak ditemukan!";
        redirect('daftar_buku.php');
    }

    if ($buku['stok'] > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE buku SET stok = stok - 1 WHERE id = ? AND stok > 0");
            $stmt->execute([$buku_id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO peminjaman (user_id, buku_id, tanggal_pinjam, status) VALUES (?, ?, ?, 'dipinjam')");
                $stmt->execute([$user_id, $buku_id, $tanggal_pinjam]);
                $pdo->commit();
                // Simpan dalam bentuk JSON untuk menghindari masalah karakter
                $_SESSION['swal_success'] = json_encode([
                    'message' => "Buku '{$buku['judul']}' berhasil dipinjam!",
                    'judul' => $buku['judul']
                ]);
            } else {
                $pdo->rollBack();
                $_SESSION['swal_error'] = "Stok buku habis!";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else {
        $_SESSION['swal_error'] = "Stok buku habis!";
    }

    redirect('daftar_buku.php');
}

// Ambil dan decode session messages
$swal_success_raw = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : '';
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : '';

// Coba decode jika berupa JSON
$swal_success = '';
if ($swal_success_raw) {
    $decoded = json_decode($swal_success_raw, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
        $swal_success = $decoded['message'];
    } else {
        $swal_success = $swal_success_raw;
    }
}

unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Pagination
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total data
$count_sql = "SELECT COUNT(*) as total 
        FROM buku b 
        LEFT JOIN kategori k ON b.kategori_id = k.id 
        WHERE b.stok > 0";
$count_params = [];

if (!empty($search)) {
    $count_sql .= " AND (b.judul LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $count_sql .= " AND b.kategori_id = ?";
    $count_params[] = $kategori_filter;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $limit);

// Query utama dengan pagination dan urutan abjad
$sql = "SELECT b.*, k.nama_kategori 
        FROM buku b 
        LEFT JOIN kategori k ON b.kategori_id = k.id 
        WHERE b.stok > 0";

$params = [];

if (!empty($search)) {
    $sql .= " AND (b.judul LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $sql .= " AND b.kategori_id = ?";
    $params[] = $kategori_filter;
}

// Urutkan berdasarkan judul secara abjad A-Z
$sql .= " ORDER BY b.judul ASC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind parameter pencarian
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$buku = $stmt->fetchAll();

$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, b.judul, b.penulis, b.penerbit 
    FROM peminjaman p 
    JOIN buku b ON p.buku_id = b.id 
    WHERE p.user_id = ? AND p.status = 'dipinjam'
");
$stmt->execute([$_SESSION['user_id']]);
$pinjaman_aktif = $stmt->fetchAll();

$borrowed_ids = [];
foreach ($pinjaman_aktif as $p) {
    $borrowed_ids[] = $p['buku_id'];
}

include '../header.php';
?>

<!-- Include SweetAlert2 CSS and JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .btn {
        display: inline-block;
        padding: 8px 16px;
        background: #333;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn:hover {
        opacity: 0.85;
        transform: translateY(-1px);
    }

    .btn-pinjam {
        background: #4d88ff;
    }

    .btn-success {
        background: #28a745;
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-disabled {
        background: #6c757d;
        cursor: not-allowed;
    }

    .btn-sm {
        padding: 4px 12px;
        font-size: 12px;
    }

    .search-container {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
    }

    .search-group {
        flex: 1;
        min-width: 200px;
    }

    .search-group label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-size: 13px;
    }

    .search-group input,
    .search-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .btn-search {
        background: #333;
    }

    .btn-reset {
        background: #6c757d;
    }

    .search-info {
        margin-top: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 13px;
    }

    table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    th {
        background: #f8f8f8;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }

    .badge-dipinjam {
        background: #ffeaa7;
        color: #d35400;
    }

    .stats {
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 8px;
        color: #666;
    }

    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 5px;
    }

    .pagination a,
    .pagination span {
        padding: 8px 16px;
        text-decoration: none;
        border: 1px solid #ddd;
        color: #333;
        border-radius: 4px;
        transition: background-color 0.3s;
    }

    .pagination a:hover {
        background-color: #E1E8ED;
    }

    .pagination .active {
        background-color: #4d88ff;
        color: white;
        border: 1px solid #4d88ff;
    }

    .pagination .disabled {
        color: #999;
        cursor: not-allowed;
    }

    .info-pagination {
        text-align: center;
        margin-top: 10px;
        color: #666;
        font-size: 14px;
    }

    @media (max-width: 768px) {

        table,
        thead,
        tbody,
        th,
        td,
        tr {
            display: block;
        }

        thead {
            display: none;
        }

        tr {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        td {
            display: flex;
            justify-content: space-between;
            padding: 10px;
        }

        td:before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 10px;
        }

        .search-form {
            flex-direction: column;
        }

        .search-group {
            width: 100%;
        }

        .stats {
            flex-direction: column;
            text-align: center;
        }

        .pagination {
            flex-wrap: wrap;
        }
    }
</style>

<?php if (!empty($pinjaman_aktif)): ?>
    <div class="stats">
        <p>Anda sedang meminjam <strong><?= count($pinjaman_aktif) ?></strong> buku</p>
        <p>Jangan lupa mengembalikan tepat waktu!</p>
    </div>
    <h3>Buku yang Sedang Anda Pinjam</h3>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="text-align: center;">No</th>
                    <th style="text-align: center;">Judul</th>
                    <th style="text-align: center;">Penulis</th>
                    <th style="text-align: center;">Tanggal Pinjam</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no_pinjam = 1; ?>
                <?php foreach ($pinjaman_aktif as $p): ?>
                    <tr id="pinjam-row-<?= $p['id'] ?>">
                        <td data-label="No" style="text-align: center;"><?= $no_pinjam++ ?></td>
                        <td data-label="Judul"><?= htmlspecialchars($p['judul']) ?></td>
                        <td data-label="Penulis"><?= htmlspecialchars($p['penulis']) ?></td>
                        <td data-label="Tanggal Pinjam" style="text-align: center;"><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                        <td data-label="Status" style="text-align: center;"><span class="badge badge-dipinjam">Dipinjam</span></td>
                        <td data-label="Aksi" style="text-align: center;">
                            <button class="btn btn-success btn-sm return-book"
                                data-id="<?= $p['id'] ?>"
                                data-judul="<?= htmlspecialchars($p['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                Kembalikan
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="stats">
        <p>Anda sedang tidak meminjam buku apapun</p>
        <p>Silakan pinjam buku di bawah</p>
    </div>
<?php endif; ?>

<h3>Daftar Buku Tersedia</h3>
<div class="search-container">
    <form method="GET" class="search-form" id="searchForm">
        <div class="search-group">
            <label>Filter Kategori</label>
            <select name="kategori">
                <option value="">Semua Kategori</option>
                <?php foreach ($kategori_list as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kategori_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="search-group">
            <label>Cari Buku</label>
            <input type="text" name="search" placeholder="Judul, penulis, atau penerbit..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="search-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-search">Cari</button>
        </div>
        <div class="search-group">
            <label>&nbsp;</label>
            <a href="daftar_buku.php" class="btn btn-reset">Reset</a>
        </div>
    </form>
    <?php if (!empty($search) || !empty($kategori_filter)): ?>
        <div class="search-info">Menampilkan <strong><?= count($buku) ?></strong> hasil <a href="daftar_buku.php" style="float:right;">Hapus filter</a></div>
    <?php endif; ?>
</div>

<?php if (empty($buku)): ?>
    <div class="empty-state">
        <p>Tidak ada buku ditemukan.</p>
        <a href="daftar_buku.php" class="btn">Lihat Semua Buku</a>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="text-align: center;">No</th>
                    <th style="text-align: center;">Judul</th>
                    <th style="text-align: center;">Penulis</th>
                    <th style="text-align: center;">Penerbit</th>
                    <th style="text-align: center;">Kategori</th>
                    <th style="text-align: center;">Stok</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $start_number = $offset + 1;
                foreach ($buku as $index => $b):
                ?>
                    <tr id="buku-row-<?= $b['id'] ?>">
                        <td data-label="No" style="text-align: center;"><?= $start_number + $index ?></td>
                        <td data-label="Judul"><?= htmlspecialchars($b['judul']) ?></td>
                        <td data-label="Penulis"><?= htmlspecialchars($b['penulis']) ?></td>
                        <td data-label="Penerbit"><?= htmlspecialchars($b['penerbit'] ?? '-') ?></td>
                        <td data-label="Kategori"><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
                        <td data-label="Stok" style="text-align: center;"><?= $b['stok'] ?></td>
                        <td data-label="Aksi" style="text-align: center;">
                            <?php if (in_array($b['id'], $borrowed_ids)): ?>
                                <span class="btn btn-disabled">Sudah Dipinjam</span>
                            <?php else: ?>
                                <button class="btn btn-pinjam borrow-book"
                                    data-id="<?= $b['id'] ?>"
                                    data-judul="<?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                    Pinjam
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="info-pagination">
            Menampilkan <?= count($buku) ?> dari <?= $total_rows ?> buku
        </div>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori_filter) ?>">&laquo; Sebelumnya</a>
            <?php else: ?>
                <span class="disabled">&laquo; Sebelumnya</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori_filter) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori_filter) ?>">Selanjutnya &raquo;</a>
            <?php else: ?>
                <span class="disabled">Selanjutnya &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Form untuk peminjaman (hidden) -->
<form id="borrowForm" method="POST" style="display: none;">
    <input type="hidden" name="buku_id" id="borrow_buku_id">
    <input type="hidden" name="pinjam" value="1">
</form>

<!-- SweetAlert2 Scripts untuk notifikasi dengan posisi top -->
<?php if ($swal_success): ?>
    <script>
        // Gunakan json_encode untuk escape karakter khusus
        const successMessage = <?= json_encode($swal_success) ?>;
        Swal.fire({
            position: 'top', // Notifikasi sukses di tengah atas
            icon: "success",
            title: "Berhasil!",
            text: successMessage,
            showConfirmButton: false,
            timer: 1500,
            toast: false
        });
    </script>
<?php endif; ?>

<?php if ($swal_error): ?>
    <script>
        const errorMessage = <?= json_encode($swal_error) ?>;
        Swal.fire({
            position: 'top', // Notifikasi error di tengah atas
            icon: "error",
            title: "Gagal!",
            text: errorMessage,
            showConfirmButton: true,
            confirmButtonColor: "#dc3545",
            confirmButtonText: "OK"
        });
    </script>
<?php endif; ?>

<script>
    // Fungsi untuk escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Konfirmasi peminjaman buku dengan SweetAlert2 (DI TENGAH)
    document.querySelectorAll('.borrow-book').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const bukuId = this.dataset.id;
            const judul = this.dataset.judul;

            Swal.fire({
                title: 'Konfirmasi Peminjaman',
                html: `Apakah Anda yakin ingin meminjam buku <strong>"${escapeHtml(judul)}"</strong>?<br><br>
                       <small style="color:#666;">Lama pinjam maksimal 7 hari<br>
                       </small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4d88ff',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Ya, Pinjam!',
                cancelButtonText: 'Batal',
                position: 'center' // Konfirmasi DI TENGAH LAYAR
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tampilkan loading DI TENGAH
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Sedang memproses peminjaman buku',
                        allowOutsideClick: false,
                        position: 'center',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit form
                    document.getElementById('borrow_buku_id').value = bukuId;
                    document.getElementById('borrowForm').submit();
                }
            });
        });
    });

    // Konfirmasi pengembalian buku dengan SweetAlert2 menggunakan AJAX (DI TENGAH)
    document.querySelectorAll('.return-book').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const peminjamanId = this.dataset.id;
            const judul = this.dataset.judul;
            const buttonElement = this;
            const rowElement = this.closest('tr');

            Swal.fire({
                title: 'Konfirmasi Pengembalian',
                html: `Apakah Anda yakin ingin mengembalikan buku <strong>"${escapeHtml(judul)}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Ya, Kembalikan!',
                cancelButtonText: 'Batal',
                position: 'center' // Konfirmasi DI TENGAH LAYAR
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tampilkan loading DI TENGAH
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Sedang memproses pengembalian buku',
                        allowOutsideClick: false,
                        position: 'center',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Kirim request AJAX ke kembalikan_buku.php
                    fetch('../shared/kembalikan_buku.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: `peminjaman_id=${peminjamanId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Tutup loading
                                Swal.close();
                                // Tampilkan notifikasi sukses DI TENGAH ATAS
                                Swal.fire({
                                    position: 'top', // Notifikasi sukses di tengah atas
                                    icon: "success",
                                    title: "Berhasil!",
                                    text: data.message,
                                    showConfirmButton: false,
                                    timer: 2000
                                }).then(() => {
                                    // Reload halaman untuk memperbarui data
                                    location.reload();
                                });
                            } else {
                                // Tutup loading
                                Swal.close();
                                // Tampilkan notifikasi error DI TENGAH ATAS
                                Swal.fire({
                                    position: 'top', // Notifikasi error di tengah atas
                                    icon: "error",
                                    title: "Gagal!",
                                    text: data.message,
                                    confirmButtonColor: "#dc3545",
                                    confirmButtonText: "OK"
                                });
                            }
                        })
                        .catch(error => {
                            Swal.close();
                            Swal.fire({
                                position: 'top', // Notifikasi error di tengah atas
                                icon: "error",
                                title: "Error!",
                                text: "Terjadi kesalahan saat menghubungi server.",
                                confirmButtonColor: "#dc3545",
                                confirmButtonText: "OK"
                            });
                            console.error('Error:', error);
                        });
                }
            });
        });
    });
</script>

<?php include '../footer.php'; ?>