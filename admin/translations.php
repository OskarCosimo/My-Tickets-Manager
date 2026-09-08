<?php
// admin/translations.php
// Admin Language JSON generator using LibreTranslate
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

$message = '';
$error = '';

// Handle translation generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_lang'])) {
    $targetLang = preg_replace('/[^a-z0-9_-]/i', '', strtolower($_POST['target_lang']));
    $sourceFilePath = __DIR__ . '/../translations/lang-en.json';
    $targetFilePath = __DIR__ . '/../translations/lang-' . $targetLang . '.json';
    $apiUrl = get_setting($pdo, 'libretranslate_url', 'https://libretranslate.com');

    if (!file_exists($sourceFilePath)) {
        $error = 'Base source file (lang-en.json) is missing.';
    } else {
        $sourceData = json_decode(file_get_contents($sourceFilePath), true);
        $translatedData = [];

        foreach ($sourceData as $key => $value) {
            $postFields = [
                'q' => $value,
                'source' => 'en',
                'target' => $targetLang,
                'format' => 'text'
            ];

            $ch = curl_init($apiUrl . '/translate');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $res = json_decode($response, true);
                $translatedData[$key] = $res['translatedText'] ?? $value;
            } else {
                $translatedData[$key] = $value; // Fallback to English on error
            }
        }

        if (file_put_contents($targetFilePath, json_encode($translatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $message = "Translation file (lang-{$targetLang}.json) generated successfully!";
        } else {
            $error = "Failed to write translation file to disk.";
        }
    }
}

$availableLangs = get_available_languages();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container-fluid" style="max-width: 800px;">
        <h2>Language & i18n Generator</h2>
        <hr>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <!-- Active Languages List -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-dark text-white">Active Language Files</div>
            <div class="card-body">
                <ul class="list-group">
                    <?php foreach ($availableLangs as $langCode): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>lang-<?php echo $langCode; ?>.json</strong></span>
                            <span class="badge bg-primary rounded-pill"><?php echo strtoupper($langCode); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Generate New Language Form -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">Auto-Generate Language File via LibreTranslate</div>
            <div class="card-body">
                <form method="POST" action="translations.php">
                    <div class="mb-3">
                        <label class="form-label">Target Language Code (e.g. it, es, fr, de)</label>
                        <input type="text" name="target_lang" class="form-control" placeholder="it" required>
                        <div class="form-text">This will read lang-en.json, translate all values via LibreTranslate, and output lang-[code].json.</div>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate Translation JSON</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>