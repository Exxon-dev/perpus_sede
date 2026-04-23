<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = "Kelola Kategori";

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Cek apakah kategori memiliki buku
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM buku WHERE kategori_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['swal_error'] = "Kategori tidak dapat dihapus karena masih memiliki $count buku!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM kategori WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['swal_success'] = "Kategori berhasil dihapus!";
    }
    redirect('admin_kategori.php');
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $nama_kategori = $_POST['nama_kategori'];
    
    // Cek duplikasi
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = ? AND id != ?");
    $stmt->execute([$nama_kategori, $id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['swal_error'] = "Nama kategori '$nama_kategori' sudah ada!";
    } else {
        $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ?");
        $stmt->execute([$nama_kategori, $id]);
        $_SESSION['swal_success'] = "Kategori berhasil diupdate!";
    }
    redirect('admin_kategori.php');
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $nama_kategori = $_POST['nama_kategori'];
    
    // Cek duplikasi
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = ?");
    $stmt->execute([$nama_kategori]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['swal_error'] = "Nama kategori '$nama_kategori' sudah ada!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $stmt->execute([$nama_kategori]);
        $_SESSION['swal_success'] = "Kategori berhasil ditambahkan!";
    }
    redirect('admin_kategori.php');
}

// Handle search
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build search query
$search_condition = "";
$search_params = [];

if (!empty($search_keyword)) {
    $search_condition = "WHERE nama_kategori LIKE :keyword";
    $search_params[':keyword'] = "%$search_keyword%";
}

// Get total records for pagination
$total_sql = "SELECT COUNT(*) as total FROM kategori $search_condition";
$total_stmt = $pdo->prepare($total_sql);
foreach ($search_params as $key => $value) {
    $total_stmt->bindValue($key, $value);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $limit);

// Get categories
$sql = "SELECT * FROM kategori $search_condition ORDER BY nama_kategori ASC LIMIT :limit OFFSET :offset";
$kategori_stmt = $pdo->prepare($sql);

foreach ($search_params as $key => $value) {
    $kategori_stmt->bindValue($key, $value);
}
$kategori_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$kategori_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$kategori_stmt->execute();
$kategori_list = $kategori_stmt->fetchAll();

$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : '';
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : '';
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

include '../header.php';
?>

<!-- Include SweetAlert2 CSS and JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .btn {
        display: inline-block;
        padding: 8px 16px;
        background: #E1E8ED;
        color: black;
        text-decoration: none;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 14px;
    }

    .btn:hover {
        background: #66757F;
        color: white;
    }

    .btn-small {
        padding: 4px 8px;
        font-size: 12px;
        color: white;
    }

    .btn-edit {
        background: #33CC33;
    }
    
    .btn-edit:hover {
        background: #009933;
    }

    .btn-delete {
        background: #dc3545;
    }

    .btn-delete:hover {
        background: #b91d47;
    }

    .btn-update {
        background: #0066FF;
        color: white;
    }
    .btn-update:hover {
        background: #0099FF;
    }

    .btn-batal {
        background: #5b9aa0;
        color: white;
    }
    
    .btn-cari {
        background: #333;
        color: white;
        padding: 8px 16px;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .btn-cari:hover {
        background: #66757F;
    }
    
    .btn-reset {
        background: #6c757d;
        color: white;
        padding: 8px 16px;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .btn-reset:hover {
        background: #333;
    }

    .form-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-weight: 500;
    }

    input,
    select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    /* Container untuk header Daftar Kategori dan search */
    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .header-section h3 {
        margin: 0;
    }
    
    .search-container {
        margin: 0;
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-input-group {
        width: 300px;
    }
    
    .search-input-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .search-buttons {
        display: flex;
        gap: 10px;
    }

    table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    th {
        background: #f8f8f8;
        font-weight: 600;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        width: 400px;
        max-height: 90vh;
        overflow-y: auto;
    }

    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination span {
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
        background-color: #0066FF;
        color: white;
        border: 1px solid #0066FF;
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
    
    .search-info {
        margin-bottom: 15px;
        padding: 10px;
        background: #e7f3ff;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .search-info span {
        color: #0066FF;
        font-weight: bold;
    }
    
    /* Badge untuk jumlah buku */
    .badge {
        display: inline-block;
        padding: 3px 8px;
        background: #E1E8ED;
        color: #333;
        border-radius: 20px;
        font-size: 12px;
        font-weight: normal;
    }
    
    /* Responsif untuk mobile */
    @media (max-width: 768px) {
        .header-section {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .search-input-group {
            width: 100%;
        }
        
        .search-form {
            width: 100%;
            flex-direction: column;
        }
        
        .search-buttons {
            width: 100%;
        }
        
        .search-buttons .btn {
            flex: 1;
            text-align: center;
        }
        
        table {
            display: block;
            overflow-x: auto;
        }
    }
    
    .text-center {
        text-align: center;
    }
</style>

<div class="form-card">
    <h3>Tambah Kategori Baru</h3>
    <form method="POST" id="addCategoryForm">
        <div class="form-group">
            <label>Nama Kategori *</label>
            <input type="text" name="nama_kategori" required placeholder="Contoh: Fiksi, Non-Fiksi, Teknologi, dll">
        </div>
        <button type="submit" name="add" class="btn">Simpan Kategori</button>
    </form>
</div>

<!-- Header dengan Daftar Kategori dan Search di samping kanan -->
<div class="header-section">
    <h3>Daftar Kategori</h3>
    
    <!-- Search Form -->
    <div class="search-container">
        <form method="GET" class="search-form" id="searchForm">
            <div class="search-input-group">
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Cari kategori...">
            </div>
            <div class="search-buttons">
                <button type="submit" class="btn btn-cari">Cari</button>
                <?php if (!empty($search_keyword)): ?>
                    <button type="button" class="btn btn-reset" onclick="resetSearch()">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($search_keyword)): ?>
<div class="search-info">
    <div>Menampilkan hasil pencarian untuk: "<span><?= htmlspecialchars($search_keyword) ?></span>"</div>
    <div>Ditemukan: <?= $total_rows ?> kategori</div>
</div>
<?php endif; ?>

<div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th style="text-align: center;">No</th>
                <th style="text-align: center;">Nama Kategori</th>
                <th style="text-align: center;">Jumlah Buku</th>
                <th style="text-align: center;">Dibuat Pada</th>
                <th style="text-align: center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $start_number = $offset + 1;
            foreach ($kategori_list as $index => $k): 
                // Hitung jumlah buku dalam kategori ini
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM buku WHERE kategori_id = ?");
                $stmt->execute([$k['id']]);
                $jumlah_buku = $stmt->fetchColumn();
            ?>
            <tr>
                <td class="text-center"><?= $start_number + $index ?></td>
                <td><?= htmlspecialchars($k['nama_kategori']) ?></td>
                <td class="text-center">
                    <span class="badge"><?= $jumlah_buku ?> buku</span>
                </td>
                <td class="text-center"><?= date('d/m/Y ', strtotime($k['created_at'])) ?></td>
                <td class="text-center">
                    <button class="btn btn-small btn-edit" onclick="editKategori(<?= htmlspecialchars(json_encode($k)) ?>)">Edit</button>
                    <button class="btn btn-small btn-delete" onclick="confirmDelete(<?= $k['id'] ?>, '<?= htmlspecialchars($k['nama_kategori']) ?>', <?= $jumlah_buku ?>)">Hapus</button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($kategori_list)): ?>
            <tr>
                <td colspan="5" class="text-center">Tidak ada data kategori</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="info-pagination">
        Menampilkan <?= count($kategori_list) ?> dari <?= $total_rows ?> data
    </div>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>">&laquo; Sebelumnya</a>
        <?php else: ?>
            <span class="disabled">&laquo; Sebelumnya</span>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>">Selanjutnya &raquo;</a>
        <?php else: ?>
            <span class="disabled">Selanjutnya &raquo;</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Modal Edit Kategori -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Kategori</h3>
        <form method="POST" id="editCategoryForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Nama Kategori *</label>
                <input type="text" name="nama_kategori" id="edit_nama_kategori" required>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit" class="btn btn-update">Update</button>
                <button type="button" class="btn btn-batal" onclick="closeModal()">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert2 Script -->
<?php if ($swal_success): ?>
<script>
    Swal.fire({
        position: "top-end",
        icon: "success",
        title: "<?= htmlspecialchars($swal_success) ?>",
        showConfirmButton: false,
        timer: 1500,
        toast: true
    });
</script>
<?php endif; ?>

<?php if ($swal_error): ?>
<script>
    Swal.fire({
        icon: "error",
        title: "Gagal!",
        text: "<?= htmlspecialchars($swal_error) ?>",
        confirmButtonColor: "#dc3545"
    });
</script>
<?php endif; ?>

<script>
    function editKategori(kategori) {
        document.getElementById('edit_id').value = kategori.id;
        document.getElementById('edit_nama_kategori').value = kategori.nama_kategori;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function confirmDelete(id, namaKategori, jumlahBuku) {
        let warningText = "Data kategori akan dihapus secara permanen!";
        
        if (jumlahBuku > 0) {
            warningText = "Kategori ini memiliki " + jumlahBuku + " buku. Kategori tidak dapat dihapus jika masih memiliki buku!";
            
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Dapat Dihapus!',
                text: warningText,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Kategori '" + namaKategori + "' akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + id;
            }
        });
    }
    
    function resetSearch() {
        window.location.href = window.location.pathname.split('/').pop();
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Allow Enter key to submit search
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchForm').submit();
        }
    });
    
    // Form validation for add category
    document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
        var namaKategori = document.querySelector('#addCategoryForm input[name="nama_kategori"]').value.trim();
        if (namaKategori === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Nama kategori tidak boleh kosong!',
                confirmButtonColor: '#dc3545'
            });
        }
    });
    
    // Form validation for edit category
    document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
        var namaKategori = document.getElementById('edit_nama_kategori').value.trim();
        if (namaKategori === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Nama kategori tidak boleh kosong!',
                confirmButtonColor: '#dc3545'
            });
        }
    });
</script>

<?php include '../footer.php'; ?>