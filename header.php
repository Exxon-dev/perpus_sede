<?php
// header.php - Template dengan Sidebar
if (!isset($page_title)) {
    $page_title = "Perpustakaan Digital";
}

// Tentukan base path berdasarkan lokasi file
$base_path = '';
$current_file = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Deteksi posisi file untuk menentukan base path
if ($current_dir == 'admin') {
    $base_path = '../';
} elseif ($current_dir == 'guru') {
    $base_path = '../';
} elseif ($current_dir == 'shared') {
    $base_path = '../';
} else {
    $base_path = '';
}

// Fungsi untuk mengecek apakah menu sedang aktif
function isActiveMenu($menu_name) {
    $current_file = basename($_SERVER['PHP_SELF']);
    switch($menu_name) {
        case 'dashboard':
            return $current_file == 'dashboard.php';
        case 'admin_buku':
            return $current_file == 'admin_buku.php';
        case 'admin_kategori':
            return $current_file == 'admin_kategori.php';
        case 'admin_user':
            return $current_file == 'admin_user.php';
        case 'admin_pengembalian':
            return $current_file == 'admin_pengembalian.php';
        case 'daftar_buku':
            return $current_file == 'daftar_buku.php';
        case 'guru_tambah_buku':
            return $current_file == 'guru_tambah_buku.php';
        case 'guru_pengembalian':
            return $current_file == 'guru_pengembalian.php';
        default:
            return false;
    }
}

// Fungsi untuk mengecek apakah submenu sedang aktif
function isSubmenuActive($submenus) {
    $current_file = basename($_SERVER['PHP_SELF']);
    return in_array($current_file, $submenus);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Perpustakaan</title>
    <!-- SweetAlert2 CSS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: #2c3e50;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-header h3 {
            font-size: 20px;
            font-weight: normal;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 12px;
            color: #bdc3c7;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
            gap: 10px;
        }

        .sidebar-menu li a:hover {
            background: #34495e;
            padding-left: 25px;
        }

        .sidebar-menu li.active > a {
            background: #3498db;
            color: white;
        }

        .sidebar-menu .menu-icon {
            font-size: 18px;
            width: 25px;
        }

        .sidebar-menu .menu-text {
            font-size: 14px;
        }

        .sidebar-menu .submenu {
            list-style: none;
            padding-left: 45px;
            background: #243342;
            display: none;
        }

        .sidebar-menu .submenu.show {
            display: block;
        }

        .sidebar-menu .has-submenu > a:after {
            content: "▼";
            float: right;
            font-size: 10px;
        }

        .sidebar-menu .has-submenu.active > a {
            background: #2c3e50;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            transition: all 0.3s;
            min-height: 100vh;
        }

        /* Top Header */
        .top-header {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .menu-toggle {
            display: none;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 20px;
        }

        .page-title {
            font-size: 20px;
            color: #333;
            font-weight: normal;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            color: #333;
        }

        .user-role {
            background: #2c3e50;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .logout-btn {
            background: #ff3333;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            border: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* Content Container */
        .content-container {
            padding: 25px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📚 Perpustakaan</h3>
            <p>Digital Library System</p>
        </div>
        
        <ul class="sidebar-menu">
            <?php 
            // Tentukan base URL untuk dashboard
            $dashboard_url = $base_path . 'dashboard.php';
            ?>
            <li class="<?= isActiveMenu('dashboard') ? 'active' : '' ?>">
                <a href="<?= $dashboard_url ?>">
                    <span class="menu-icon">🏠</span>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            
            <?php if(isAdmin()): ?>
                <?php 
                $isBukuActive = isActiveMenu('admin_buku') || isActiveMenu('admin_kategori');
                ?>
                <li class="has-submenu <?= $isBukuActive ? 'active' : '' ?>">
                    <a href="javascript:void(0)" onclick="toggleSubmenu(this)">
                        <span class="menu-icon">📚</span>
                        <span class="menu-text">Kelola Buku</span>
                    </a>
                    <ul class="submenu <?= $isBukuActive ? 'show' : '' ?>">
                        <li class="<?= isActiveMenu('admin_buku') ? 'active' : '' ?>">
                            <a href="<?= $base_path ?>admin/admin_buku.php">📖 Daftar Buku</a>
                        </li>
                        <li class="<?= isActiveMenu('admin_kategori') ? 'active' : '' ?>">
                            <a href="<?= $base_path ?>admin/admin_kategori.php">🏷️ Kategori Buku</a>
                        </li>
                    </ul>
                </li>
                <li class="<?= isActiveMenu('admin_user') ? 'active' : '' ?>">
                    <a href="<?= $base_path ?>admin/admin_user.php">
                        <span class="menu-icon">👥</span>
                        <span class="menu-text">Kelola User</span>
                    </a>
                </li>
                <li class="<?= isActiveMenu('admin_pengembalian') ? 'active' : '' ?>">
                    <a href="<?= $base_path ?>admin/admin_pengembalian.php">
                        <span class="menu-icon">🔄</span>
                        <span class="menu-text">Pengembalian Buku</span>
                    </a>
                </li>
                <li class="<?= isActiveMenu('daftar_buku') ? 'active' : '' ?>">
                    <a href="<?= $base_path ?>shared/daftar_buku.php">
                        <span class="menu-icon">📖</span>
                        <span class="menu-text">Daftar Buku</span>
                    </a>
                </li>
            <?php elseif(isSiswa()): ?>
                <li class="<?= isActiveMenu('daftar_buku') ? 'active' : '' ?>">
                    <a href="<?= $base_path ?>shared/daftar_buku.php">
                        <span class="menu-icon">📖</span>
                        <span class="menu-text">Daftar Buku</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <button class="menu-toggle" id="menuToggle">☰ Menu</button>
            <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
            <div class="user-info">
                <span class="user-name">Halo, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></span>
                <span class="user-role"><?= ucfirst($_SESSION['role'] ?? '') ?></span>
                <a href="javascript:void(0)" class="logout-btn" id="logoutBtn">Logout</a>
            </div>
        </div>
        <div class="content-container">

<script>
    // Toggle sidebar on mobile
    document.getElementById('menuToggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Toggle submenu
    function toggleSubmenu(element) {
        let submenu = element.parentElement.querySelector('.submenu');
        if (submenu) {
            submenu.classList.toggle('show');
        }
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // SweetAlert2 Konfirmasi Logout
    document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Logout',
            html: `<div style="text-align: left;">
                       <p>Apakah Anda yakin ingin keluar dari sistem?</p>
                       <p style="color: #666; font-size: 13px; margin-top: 10px;">
                           <strong>Perhatian:</strong><br>
                           Anda akan diarahkan ke halaman login.
                       </p>
                   </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ff3333',
            cancelButtonColor: '#6c757d',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Keluar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Tampilkan loading
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Sedang mengarahkan ke halaman login',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Redirect ke logout.php
                window.location.href = '<?= $base_path ?>logout.php';
            }
        });
    });
</script>