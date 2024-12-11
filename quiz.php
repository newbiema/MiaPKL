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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-green-50 min-h-screen flex flex-col">
    <header class="bg-gradient-to-r from-blue-600 to-teal-600 text-white shadow-lg">
        <div class="container mx-auto flex justify-between items-center py-4 px-6">
            <h1 class="text-2xl font-bold">Kuis Pilihan Ganda</h1>
            <p class="text-sm">Soal <?= $currentIndex + 1 ?> dari <?= count($_SESSION['soal_ids']) ?></p>
        </div>
    </header>

    <div class="container mx-auto mt-6 px-4 flex flex-col lg:flex-row-reverse gap-8">
        <aside class="bg-white shadow-md rounded-lg p-4 lg:w-1/4">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Navigasi Soal</h2>
            <div class="grid grid-cols-5 gap-2">
                <?php for ($i = 0; $i < count($_SESSION['soal_ids']); $i++): ?>
                    <a href="quiz.php?soal=<?= $i ?>" 
                       class="block text-center p-2 rounded-lg font-semibold 
                       <?= isset($_SESSION['jawaban_user'][$i]) 
                        ? (isset($_SESSION['ragu_ragu'][$i]) && $_SESSION['ragu_ragu'][$i] 
                            ? 'bg-yellow-500 text-white' 
                            : 'bg-green-500 text-white') 
                        : (isset($_SESSION['ragu_ragu'][$i]) && $_SESSION['ragu_ragu'][$i] 
                            ? 'bg-yellow-500 text-white' 
                            : 'bg-blue-400 text-white') ?>


                       hover:bg-green-600 transition">
                        <?= $i + 1 ?>
                    </a>
                <?php endfor; ?>
            </div>
            <form method="POST" class="mt-4">
                <button type="submit" name="reset" onclick="return confirm('Yakin ingin reset kuis?');" 
                        class="px-4 py-2 bg-red-600 text-white w-full rounded-md hover:bg-red-700">
                    Kosongkan Jawaban
                </button>
            </form>
        </aside>

        <main class="bg-white shadow-md rounded-lg p-6 lg:w-3/4">
            <?php if (isset($soal)): ?>
                <?php if (!empty($soal['gambar'])): ?>
                    <img src="<?= htmlspecialchars($soal['gambar']) ?>" 
                         alt="Gambar Soal" 
                         class="rounded-md shadow-md w-64 h-64 object-cover mb-4">
                <?php endif; ?>
                <p class="text-xl font-semibold text-gray-700 mb-6"><?= $soal['pertanyaan'] ?></p>
                <form method="POST" class="space-y-4">
                    <?php foreach (['a', 'b', 'c', 'd'] as $opsi): ?>
                        <label class="flex items-center space-x-3">
                            <input type="radio" name="jawaban" value="<?= strtoupper($opsi) ?>" 
                                <?= (isset($_SESSION['jawaban_user'][$currentIndex]) && $_SESSION['jawaban_user'][$currentIndex] === strtoupper($opsi)) ? 'checked' : '' ?> 
                                class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <span class="text-gray-700"><?= $soal["opsi_{$opsi}"] ?></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="flex justify-between items-center mt-6">
                        <button type="submit" name="prev" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Sebelumnya</button>
                        <div class="flex gap-2">
                            <button type="submit" name="ragu" class="px-4 py-2 bg-yellow-400 text-white rounded-md hover:bg-yellow-500">Ragu-Ragu</button>
                            <?php if ($currentIndex < count($_SESSION['soal_ids']) - 1): ?>
                                <button type="submit" name="next" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Berikutnya</button>
                            <?php else: ?>
                                <button type="submit" name="finish" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Selesai</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-lg text-gray-700">Soal tidak ditemukan.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
