-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 23 Apr 2026 pada 06.37
-- Versi server: 10.4.28-MariaDB
-- Versi PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `perpustakaan`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `buku`
--

CREATE TABLE `buku` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `penulis` varchar(100) NOT NULL,
  `penerbit` varchar(100) DEFAULT NULL,
  `tahun_terbit` int(11) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `stok` int(11) DEFAULT 1,
  `gambar` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `buku`
--

INSERT INTO `buku` (`id`, `judul`, `penulis`, `penerbit`, `tahun_terbit`, `kategori_id`, `stok`, `gambar`, `created_by`, `created_at`) VALUES
(2, 'buku jurusan', 'prodi', 'jdsf', 2026, 3, 20, NULL, NULL, '2026-04-20 03:15:36'),
(3, 'hsdgi', 'hbg', 'ibrg', 2025, 1, 12, NULL, NULL, '2026-04-20 03:22:43'),
(4, 're', 'er', 'ery', 2020, 2, 22, NULL, NULL, '2026-04-20 03:23:31'),
(7, 'sjdbf`', 'kbdf', 'jbsdk', 2304, 10, 234, NULL, NULL, '2026-04-21 04:06:09'),
(11, 'pelajaran', 'guru', 'guru sejarah', 2026, 3, 10, NULL, NULL, '2026-04-22 06:46:49'),
(12, 'Tumbuh Lewat Tantangan', 'Ridwan', 'PT.Ridwan', 2026, 2, 43, NULL, NULL, '2026-04-23 00:53:05'),
(13, 'asd', 'ad', 'sdf1234', 2004, 13, 22, '1776913650_69e98cf24da62.png', NULL, '2026-04-23 02:30:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `created_at`) VALUES
(1, 'Fiksii', '2026-04-20 01:10:15'),
(2, 'Non-Fiksi', '2026-04-20 01:10:15'),
(3, 'Pendidikan', '2026-04-20 01:10:15'),
(5, 'Agama', '2026-04-20 01:10:15'),
(9, 'wer', '2026-04-21 02:25:45'),
(10, 'ewr', '2026-04-21 02:25:50'),
(11, 'wefgbfsv', '2026-04-21 02:25:56'),
(12, 'wrgsd', '2026-04-21 02:25:59'),
(13, 'ghngh', '2026-04-21 02:26:02'),
(14, 'gerg', '2026-04-21 02:26:12'),
(15, 'ds', '2026-04-21 04:23:36'),
(17, 'kabangsaan merdeka', '2026-04-23 03:12:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `buku_id` int(11) NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `status` enum('dipinjam','dikembalikan') DEFAULT 'dipinjam',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `user_id`, `buku_id`, `tanggal_pinjam`, `tanggal_kembali`, `status`, `created_at`) VALUES
(2, 3, 2, '2026-04-20', '2026-04-21', 'dikembalikan', '2026-04-20 03:22:18'),
(3, 3, 3, '2026-04-20', '2026-04-20', 'dikembalikan', '2026-04-20 03:22:54'),
(5, 3, 4, '2026-04-20', '2026-04-21', 'dikembalikan', '2026-04-20 03:23:48'),
(6, 1, 4, '2026-04-20', '2026-04-20', 'dikembalikan', '2026-04-20 03:48:14'),
(7, 3, 4, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 03:03:16'),
(9, 3, 7, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 06:20:33'),
(11, 3, 7, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 06:32:20'),
(14, 1, 7, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 06:58:24'),
(17, 1, 2, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 07:09:28'),
(18, 1, 3, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 07:09:32'),
(19, 1, 2, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 07:18:06'),
(23, 1, 3, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 07:38:51'),
(26, 1, 3, '2026-04-21', '2026-04-21', 'dikembalikan', '2026-04-21 07:47:47'),
(27, 1, 4, '2026-04-21', '2026-04-22', 'dikembalikan', '2026-04-21 07:50:19'),
(37, 1, 2, '2026-04-22', '2026-04-22', 'dikembalikan', '2026-04-22 03:35:02'),
(39, 1, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:20:48'),
(40, 1, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:21:06'),
(41, 1, 2, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:23:33'),
(42, 1, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:32:30'),
(43, 1, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:34:55'),
(44, 3, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:44:03'),
(45, 3, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:44:12'),
(46, 1, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:52:52'),
(47, 1, 2, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 03:58:22'),
(48, 1, 13, '2026-04-23', '2026-04-23', 'dikembalikan', '2026-04-23 04:14:33'),
(49, 1, 13, '2026-04-23', NULL, 'dipinjam', '2026-04-23 04:28:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','siswa') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `created_at`) VALUES
(1, 'admin', '123', 'Administrator', 'admin', '2026-04-20 01:10:15'),
(3, 'siswa1', '123', 'Ani Wijaya', 'siswa', '2026-04-20 01:10:15'),
(16, 'siswa2', '222', 'siswa2', 'siswa', '2026-04-20 03:38:26'),
(17, 'admin1', '123', 'admin1', 'admin', '2026-04-20 06:50:14'),
(19, 'www', 'www', 'www', 'siswa', '2026-04-21 02:26:58'),
(22, 'ttt', 'tgtt', 'ttt', 'siswa', '2026-04-21 02:27:29'),
(23, 'wer', 'ewrt', 'ert', 'siswa', '2026-04-21 04:34:03'),
(24, 'qqq', 'qqq123', 'qqq12', 'siswa', '2026-04-21 08:41:12'),
(29, 'qwe', 'qwe', 'qwe', 'siswa', '2026-04-22 04:04:37'),
(30, 'vgsdhf', 'gvsdhf', 'shsdvf', 'siswa', '2026-04-22 04:17:47'),
(31, 'uvfwvuwf', 'uwevf', 'uvf', 'siswa', '2026-04-23 03:17:52');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `buku`
--
ALTER TABLE `buku`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `buku_id` (`buku_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `buku`
--
ALTER TABLE `buku`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `buku`
--
ALTER TABLE `buku`
  ADD CONSTRAINT `buku_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `buku_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`buku_id`) REFERENCES `buku` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
