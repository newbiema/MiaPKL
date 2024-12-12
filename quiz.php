<?php
session_start();
include 'db.php';

// Fungsi untuk memuat soal dari file teks ke database (dijalankan hanya sekali)
function loadSoal($conn, $filename = 'soal.txt') {
    if (!file_exists($filename)) return;

    $content = file_get_contents($filename);
    $questions = preg_split('/\n\s*\n/', trim($content));

    foreach ($questions as $q) {
        $lines = explode("\n", $q);
        $question = '';
        $options = [];
        $answer = '';
        $gambar = '';

        foreach ($lines as $line) {
            if (strpos($line, 'Pertanyaan:') === 0) {
                $question = trim(substr($line, strlen('Pertanyaan:')));
            } elseif (strpos($line, 'A:') === 0) {
                $options['A'] = trim(substr($line, 2));
            } elseif (strpos($line, 'B:') === 0) {
                $options['B'] = trim(substr($line, 2));
            } elseif (strpos($line, 'C:') === 0) {
                $options['C'] = trim(substr($line, 2));
            } elseif (strpos($line, 'D:') === 0) {
                $options['D'] = trim(substr($line, 2));
            } elseif (strpos($line, 'Jawaban:') === 0) {
                $answer = trim(substr($line, strlen('Jawaban:')));
            } elseif (strpos($line, 'Gambar:') === 0) {
                $gambar = trim(substr($line, strlen('Gambar:')));
            }
        }

        if ($question && $answer && count($options) === 4) {
            $stmtCheck = $conn->prepare("SELECT id FROM soal WHERE pertanyaan = ?");
            $stmtCheck->bind_param("s", $question);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows === 0) {
                $answerUpper = strtoupper($answer);
                $stmtInsert = $conn->prepare("INSERT INTO soal (pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->bind_param(
                    "sssssss",
                    $question,
                    $options['A'],
                    $options['B'],
                    $options['C'],
                    $options['D'],
                    $answerUpper,
                    $gambar
                );
                $stmtInsert->execute();
            }
        }
    }
}

// Jalankan loadSoal sekali
loadSoal($conn);

// Randomisasi soal jika sesi baru dimulai
if (!isset($_SESSION['soal_ids'])) {
    $result = $conn->query("SELECT id FROM soal");
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    shuffle($ids);
    $_SESSION['soal_ids'] = $ids;
    $_SESSION['current_soal'] = 0;
    $_SESSION['jawaban_user'] = [];
    $_SESSION['ragu_ragu'] = [];
}

// Navigasi soal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['jawaban'])) {
        $_SESSION['jawaban_user'][$_SESSION['current_soal']] = $_POST['jawaban'];
    }

    if (isset($_POST['ragu'])) {
        if (isset($_POST['ragu'])) {
            if (isset($_SESSION['ragu_ragu'][$_SESSION['current_soal']])) {
                // Jika sudah ragu-ragu, hapus status ragu-ragu
                unset($_SESSION['ragu_ragu'][$_SESSION['current_soal']]);
            } else {
                // Jika belum ragu-ragu, tandai sebagai ragu-ragu
                $_SESSION['ragu_ragu'][$_SESSION['current_soal']] = true;
            }
        }
        
    } elseif (isset($_POST['next'])) {
        $_SESSION['current_soal'] = min($_SESSION['current_soal'] + 1, count($_SESSION['soal_ids']) - 1);
    } elseif (isset($_POST['prev'])) {
        $_SESSION['current_soal'] = max($_SESSION['current_soal'] - 1, 0);
    } elseif (isset($_POST['finish'])) {
        header('Location: result.php');
        exit;
    } elseif (isset($_POST['reset'])) {
        session_destroy();
        header('Location: quiz.php');
        exit;
    }
}

// Cek soal yang akan ditampilkan
$currentIndex = $_SESSION['current_soal'];
$currentId = $_SESSION['soal_ids'][$currentIndex] ?? null;

if ($currentId) {
    $query = $conn->query("SELECT * FROM soal WHERE id = $currentId");
    $soal = $query->fetch_assoc();
}

// Navigasi soal berdasarkan GET
if (isset($_GET['soal'])) {
    $newSoal = (int) $_GET['soal'];
    if ($newSoal >= 0 && $newSoal < count($_SESSION['soal_ids'])) {
        $_SESSION['current_soal'] = $newSoal;
        header('Location: quiz.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuis Pilihan Ganda</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-green-50 min-h-screen flex flex-col">

<header class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 text-white shadow-2xl">
    <div class="container mx-auto flex flex-col md:flex-row justify-between items-center py-6 px-8">
        <!-- Logo dan Judul -->
        <div class="flex items-center space-x-4">
            <div class="bg-white text-indigo-500 rounded-full w-12 h-12 flex justify-center items-center font-bold text-lg shadow-lg">
                Q
            </div>
            <h1 class="text-4xl font-black tracking-wider">Kuis Pilihan Ganda</h1>
        </div>
        <!-- Info Soal -->
        <div class="mt-4 md:mt-0 text-center md:text-right">
            <p class="text-base md:text-lg font-semibold">
                Soal <span class="text-yellow-300"><?= $currentIndex + 1 ?></span> dari 
                <span class="text-yellow-300"><?= count($_SESSION['soal_ids']) ?></span>
            </p>
        </div>
    </div>
</header>

<div class="container mx-auto mt-8 px-6 flex flex-col lg:flex-row-reverse gap-12">
    <!-- Sidebar Navigasi Soal -->
    <aside class="bg-gradient-to-r from-gray-100 to-gray-200 shadow-lg rounded-2xl p-6 lg:w-1/3">
        <h2 class="text-xl font-bold text-center text-gray-700 mb-6">Soal</h2>
        <div class="grid grid-cols-5 gap-3">
            <?php for ($i = 0; $i < count($_SESSION['soal_ids']); $i++): ?>
                <a href="quiz.php?soal=<?= $i ?>" 
                   class="text-center py-3 rounded-full font-bold transition 
                   <?= isset($_SESSION['jawaban_user'][$i]) 
                    ? (isset($_SESSION['ragu_ragu'][$i]) && $_SESSION['ragu_ragu'][$i] 
                        ? 'bg-yellow-400 text-white hover:bg-yellow-500' 
                        : 'bg-green-500 text-white hover:bg-green-600') 
                    : (isset($_SESSION['ragu_ragu'][$i]) && $_SESSION['ragu_ragu'][$i] 
                        ? 'bg-yellow-400 text-white hover:bg-yellow-500' 
                        : 'bg-blue-400 text-white hover:bg-blue-500') ?>">
                    <?= $i + 1 ?>
                </a>
            <?php endfor; ?>
        </div>
        <form method="POST" class="mt-6">
        <button type="button" 
                id="resetButton" 
                class="w-full py-3 bg-red-500 text-white rounded-lg font-bold hover:bg-red-600 transition">
                Kosongkan Jawaban
        </button>

        </form>
    </aside>

    <!-- Main Section Soal -->
    <main class="bg-white shadow-2xl rounded-2xl p-8 lg:w-2/3">
        <?php if (isset($soal)): ?>
            <?php if (!empty($soal['gambar'])): ?>
                <div class="flex justify-center mb-6">
                    <img src="<?= htmlspecialchars($soal['gambar']) ?>" 
                         alt="Gambar Soal" 
                         class="rounded-lg shadow-md w-72 h-72 object-cover">
                </div>
            <?php endif; ?>
            <p class="text-2xl font-bold text-gray-800 mb-8"><?= $soal['pertanyaan'] ?></p>
            <form method="POST" class="space-y-6">
                <?php foreach (['a', 'b', 'c', 'd'] as $opsi): ?>
                    <label class="flex items-center space-x-4">
                        <input type="radio" name="jawaban" value="<?= strtoupper($opsi) ?>" 
                            <?= (isset($_SESSION['jawaban_user'][$currentIndex]) && $_SESSION['jawaban_user'][$currentIndex] === strtoupper($opsi)) ? 'checked' : '' ?> 
                            class="w-5 h-5 text-indigo-500 border-gray-300 focus:ring-indigo-400">
                        <span class="text-lg text-gray-700"><?= $soal["opsi_{$opsi}"] ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="flex justify-between items-center mt-8 ">
                    <button type="submit" name="prev" 
                            class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg font-bold hover:bg-gray-400 transition">
                        Prev
                    </button>
                    <div class="flex gap-4">
                        <button type="submit" name="ragu" 
                                class="px-6 py-3 bg-yellow-400 text-white rounded-lg font-bold hover:bg-yellow-500 transition">
                            Ragu-Ragu
                        </button>
                        <?php if ($currentIndex < count($_SESSION['soal_ids']) - 1): ?>
                            <button type="submit" name="next" 
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition">
                                Next
                            </button>
                        <?php else: ?>
                            <button type="submit" name="finish" 
                                    class="px-6 py-3 bg-green-600 text-white rounded-lg font-bold hover:bg-green-700 transition">
                                Finish
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p class="text-lg text-gray-600">Soal tidak ditemukan.</p>
        <?php endif; ?>
    </main>
</div>

<footer class="bg-gradient-to-r from-gray-800 via-gray-900 to-black text-white py-6 mt-12">
    <div class="container mx-auto flex flex-col md:flex-row justify-center items-center px-6">
        <!-- Info Kiri -->
        <div class="text-center md:text-left">
            <h3 class="text-xl font-bold">Kuis Pilihan Ganda</h3>
            <p class="text-gray-400 text-sm mt-2">
                <a href="https://ayobelajar.free.nf/">Kunjungi Website AyoBelajar.id</a>   
            </p>
        </div>

    </div>
</footer>

<script>
    document.getElementById('resetButton').addEventListener('click', function () {
        Swal.fire({
            title: 'Yakin ingin reset kuis?',
            text: "Semua jawaban akan dihapus!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, reset',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Form submit menggunakan JavaScript
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="reset" value="true">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
</script>



</body>


</html>
