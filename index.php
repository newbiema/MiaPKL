<?php
session_start();

// Validasi jika sudah ada session identitas
if (isset($_SESSION['nama']) && isset($_SESSION['nim'])) {
    header('Location: quiz.php'); // Arahkan ke halaman kuis jika sudah ada data
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan data identitas dalam session
    $_SESSION['nama'] = $_POST['nama'];
    $_SESSION['nim'] = $_POST['nim'];
    header('Location: quiz.php'); // Arahkan ke halaman kuis
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuis Interaktif</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-tr from-purple-100 via-blue-200 to-teal-100 min-h-screen flex items-center justify-center">
    <div class="relative bg-white shadow-2xl rounded-3xl p-8 w-full max-w-md text-center">
        <!-- Decorative Elements -->
        <div class="absolute -top-8 -right-8 bg-purple-200 w-32 h-32 rounded-full blur-2xl opacity-50"></div>
        <div class="absolute -bottom-8 -left-8 bg-teal-200 w-32 h-32 rounded-full blur-2xl opacity-50"></div>

        <!-- Hero Section -->
        <h1 class="text-4xl font-bold text-purple-700 mb-3">Selamat Datang!</h1>
        <p class="text-gray-600 text-sm mb-6">
            Masukkan nama dan NIM Anda untuk memulai kuis seru ini.
        </p>

        <!-- Form Identitas -->
        <form method="POST" class="space-y-6">
            <div>
                <label for="nama" class="block text-sm font-medium text-gray-700">Nama</label>
                <input type="text" id="nama" name="nama" required
                       class="mt-2 block w-full px-4 py-3 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 transition-all">
            </div>
            <div>
                <label for="nim" class="block text-sm font-medium text-gray-700">NIM</label>
                <input type="text" id="nim" name="nim" required
                       class="mt-2 block w-full px-4 py-3 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 transition-all">
            </div>
            <button type="submit"
                    class="w-full px-6 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white text-sm font-semibold rounded-lg shadow-lg hover:from-purple-600 hover:to-blue-600 transform hover:scale-105 transition-transform">
                Mulai Kuis
            </button>
        </form>

        <!-- Footer Note -->
        <p class="text-xs text-gray-400 mt-6">
            Informasi Anda hanya digunakan untuk keperluan kuis ini. <br> Semoga sukses!
        </p>
    </div>
</body>
</html>


