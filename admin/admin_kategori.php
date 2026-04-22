<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = "Kelola Kategori";

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Cek apakah kategori masih digunakan di buku
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM buku WHERE kategori_id = ?");
    $stmt->execute([$id]);
    $buku_count = $stmt->fetch()['total'];
    
    if ($buku_count > 0) {
        $_SESSION['swal_error'] = "Kategori tidak bisa dihapus karena masih digunakan oleh $buku_count buku!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM kategori WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['swal_success'] = "Kategori berhasil dihapus!";
    }
    redirect('admin_kategori.php');
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $nama = trim($_POST['nama_kategori']);
    
    if (empty($nama)) {
        $_SESSION['swal_error'] = "Nama kategori tidak boleh kosong!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $stmt->execute([$nama]);
        $_SESSION['swal_success'] = "Kategori '$nama' berhasil ditambahkan!";
    }
    redirect('admin_kategori.php');
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $nama = trim($_POST['nama_kategori']);
    
    if (empty($nama)) {
        $_SESSION['swal_error'] = "Nama kategori tidak boleh kosong!";
    } else {
        $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ?");
        $stmt->execute([$nama, $id]);
        $_SESSION['swal_success'] = "Kategori berhasil diupdate!";
    }
    redirect('admin_kategori.php');
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total records for pagination
$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM kategori");
$total_rows = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $limit);

// Get categories with pagination and sort by nama_kategori alphabetically
$kategori = $pdo->prepare("SELECT k.*, 
    (SELECT COUNT(*) FROM buku WHERE kategori_id = k.id) as jumlah_buku 
    FROM kategori k 
    ORDER BY k.nama_kategori ASC 
    LIMIT :limit OFFSET :offset");
$kategori->bindValue(':limit', $limit, PDO::PARAM_INT);
$kategori->bindValue(':offset', $offset, PDO::PARAM_INT);
$kategori->execute();
$kategori_list = $kategori->fetchAll();

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
        background: #333;
        color: black;
        text-decoration: none;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    .btn:hover {
        opacity: 0.8;
    }
    .btn-small {
        padding: 4px 12px;
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
    .btn-add {
        background: #E1E8ED;
    }
    .btn-add:hover {
        background: #66757F;
    }
    .form-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .form-card h3 {
        margin-bottom: 20px;
        color: #333;
        font-weight: normal;
        font-size: 18px;
        border-left: 4px solid #2c3e50;
        padding-left: 15px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }
    input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    input:focus {
        outline: none;
        border-color: #2c3e50;
    }
    table {
        width: 100%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    th, td {
        padding: 14px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    th {
        background: #f8f8f8;
        font-weight: 600;
        color: #555;
    }
    tr:hover {
        background: #f9f9f9;
    }
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        width: 450px;
        max-width: 90%;
    }
    .modal-content h3 {
        margin-bottom: 20px;
        color: #333;
    }
    .modal-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    .modal-buttons button {
        flex: 1;
    }
    .stats-badge {
        background: #e8f4f8;
        color: #2c3e50;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: normal;
    }
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
        color: #666;
    }
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .search-box {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        width: 250px;
        font-size: 14px;
    }
    
    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 5px;
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
    
    @media (max-width: 768px) {
        .table-header {
            flex-direction: column;
            align-items: stretch;
        }
        .search-box {
            width: 100%;
        }
        table, thead, tbody, th, td, tr {
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
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        td:before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 10px;
        }
        td:last-child {
            border-bottom: none;
        }
    }
</style>

<div class="form-card">
    <h3>Tambah Kategori Baru</h3>
    <form method="POST" id="addCategoryForm">
        <div class="form-group">
            <label>Nama Kategori</label>
            <input type="text" name="nama_kategori" placeholder="Contoh: Fiksi, Non-Fiksi, Teknologi, dll." required autocomplete="off">
        </div>
        <button type="submit" name="add" class="btn btn-add">Simpan Kategori</button>
    </form>
</div>

<div class="table-header">
    <h3>Daftar Kategori</h3>
    <input type="text" id="searchInput" class="search-box" placeholder="Cari kategori...">
</div>

<?php if (empty($kategori_list)): ?>
    <div class="empty-state">
        <p>Belum ada kategori.</p>
        <p>Silakan tambahkan kategori baru menggunakan form di atas.</p>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table id="kategoriTable">
            <thead>
                <tr>
                    <th style="text-align: center;">No</th>
                    <th style="text-align: center;">Nama Kategori</th>
                    <th style="text-align: center;">Jumlah Buku</th>
                    <th style="text-align: center;">Dibuat</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $start_number = $offset + 1;
                foreach($kategori_list as $index => $k): 
                ?>
                <tr>
                    <td data-label="No" style="text-align: center;"><?= $start_number + $index ?></td>
                    <td data-label="Nama Kategori"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                    <td data-label="Jumlah Buku">
                        <span class="stats-badge"><?= $k['jumlah_buku'] ?> buku</span>
                    </td>
                    <td data-label="Dibuat" style="text-align: center;"><?= date('d/m/Y', strtotime($k['created_at'])) ?></td>
                    <td data-label="Aksi" style="text-align: center;">
                        <button class="btn btn-small btn-edit" onclick="editKategori(<?= $k['id'] ?>, '<?= htmlspecialchars($k['nama_kategori']) ?>')">Edit</button>
                        <?php if($k['jumlah_buku'] == 0): ?>
                            <button class="btn btn-small btn-delete" onclick="confirmDelete(<?= $k['id'] ?>, '<?= htmlspecialchars($k['nama_kategori']) ?>')">Hapus</button>
                        <?php else: ?>
                            <span class="btn btn-small btn-delete" style="background:#6c757d; cursor:not-allowed;" title="Kategori masih digunakan oleh <?= $k['jumlah_buku'] ?> buku">Tidak Bisa Hapus</span>
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
            Menampilkan <?= count($kategori_list) ?> dari <?= $total_rows ?> kategori
        </div>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Sebelumnya</a>
            <?php else: ?>
                <span class="disabled">&laquo; Sebelumnya</span>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>">Selanjutnya &raquo;</a>
            <?php else: ?>
                <span class="disabled">Selanjutnya &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal Edit Kategori -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Kategori</h3>
        <form method="POST" id="editCategoryForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori" id="edit_nama" required autocomplete="off">
            </div>
            <div class="modal-buttons">
                <button type="submit" name="edit" class="btn btn-update">Update</button>
                <button type="button" class="btn btn-batal" onclick="closeModal()">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert2 Scripts -->
<?php if ($swal_success): ?>
<script>
    Swal.fire({
        position: "top",
        icon: "success",
        title: "<?= htmlspecialchars($swal_success) ?>",
        showConfirmButton: false,
        timer: 1500
    });
</script>
<?php endif; ?>

<?php if ($swal_error): ?>
<script>
    Swal.fire({
        position: "top",
        icon: "error",
        title: "Gagal!",
        text: "<?= htmlspecialchars($swal_error) ?>",
        showConfirmButton: true,
        confirmButtonColor: "#dc3545"
    });
</script>
<?php endif; ?>

<script>
    function editKategori(id, nama) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    function confirmDelete(id, nama) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Kategori '" + nama + "' akan dihapus secara permanen!",
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
    
    // Search functionality (client-side filtering)
    document.getElementById('searchInput')?.addEventListener('keyup', function() {
        let searchText = this.value.toLowerCase();
        let rows = document.querySelectorAll('#kategoriTable tbody tr');
        
        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            if (text.indexOf(searchText) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        let modal = document.getElementById('editModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Optional: Tampilkan animasi loading saat submit form
    document.getElementById('addCategoryForm')?.addEventListener('submit', function() {
        Swal.fire({
            title: 'Menyimpan...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });

    document.getElementById('editCategoryForm')?.addEventListener('submit', function() {
        Swal.fire({
            title: 'Mengupdate...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
</script>

<?php include '../footer.php'; ?>