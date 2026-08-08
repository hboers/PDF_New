<?php
function findPdfFiles(string $baseDir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $entry) {
        if ($entry->isFile() && strtolower($entry->getExtension()) === 'pdf') {
            $relativePath = substr($entry->getPathname(), strlen($baseDir) + 1);
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        }
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return $files;
}

function hasBookingNumber(string $filename): bool
{
    return preg_match('/^\d{10}-/', $filename) === 1;
}

$baseDir = realpath(__DIR__);
$files = findPdfFiles($baseDir);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'remove_pdf') {
    $relativePath = is_string($_POST['file'] ?? null) ? $_POST['file'] : '';
    $sourcePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $sourceRealPath = realpath($sourcePath);
    $isInsideBaseDir = $sourceRealPath !== false
        && strncmp($sourceRealPath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) === 0;
    $isPdf = $sourceRealPath !== false
        && strtolower(pathinfo($sourceRealPath, PATHINFO_EXTENSION)) === 'pdf';

    $removed = $relativePath !== ''
        && $isInsideBaseDir
        && $isPdf
        && is_file($sourceRealPath)
        && !is_link($sourcePath)
        && !file_exists($sourceRealPath . '.deleted')
        && @rename($sourceRealPath, $sourceRealPath . '.deleted');

    header('Location: ./?remove_result=' . ($removed ? 'success' : 'failed'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'archive_pdf') {
    $relativePath = is_string($_POST['file'] ?? null) ? $_POST['file'] : '';
    $sourcePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $sourceRealPath = realpath($sourcePath);
    $isInsideBaseDir = $sourceRealPath !== false
        && strncmp($sourceRealPath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) === 0;
    $isPdf = $sourceRealPath !== false
        && strtolower(pathinfo($sourceRealPath, PATHINFO_EXTENSION)) === 'pdf';
    $archived = false;

    if ($relativePath !== ''
        && $isInsideBaseDir
        && $isPdf
        && is_file($sourceRealPath)
        && !is_link($sourcePath)
        && hasBookingNumber(basename($sourceRealPath))) {
        $filename = basename($sourceRealPath);
        $archiveDir = dirname($baseDir) . DIRECTORY_SEPARATOR . 'PDF_Archive'
            . DIRECTORY_SEPARATOR . substr($filename, 0, 4);
        $targetPath = $archiveDir . DIRECTORY_SEPARATOR . $filename;
        $archiveDirReady = is_dir($archiveDir) || @mkdir($archiveDir, 0777, true);

        $archived = $archiveDirReady
            && !file_exists($targetPath)
            && @rename($sourceRealPath, $targetPath);
    }

    header('Location: ./?archive_result=' . ($archived ? 'success' : 'failed'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'assign_booking_numbers') {
    $bookingDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Ymd');
    $usedNumbers = [];

    foreach ($files as $file) {
        if (preg_match('/^' . preg_quote($bookingDate, '/') . '(\d{2})-/', basename($file), $matches)) {
            $number = (int) $matches[1];
            if ($number >= 1 && $number <= 99) {
                $usedNumbers[$number] = true;
            }
        }
    }

    $renamed = 0;
    $failed = 0;

    foreach ($files as $file) {
        if (hasBookingNumber(basename($file))) {
            continue;
        }

        $nextNumber = null;
        for ($number = 1; $number <= 99; $number++) {
            if (!isset($usedNumbers[$number])) {
                $nextNumber = $number;
                break;
            }
        }

        if ($nextNumber === null) {
            $failed++;
            continue;
        }

        $sourcePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        $targetName = sprintf('%s%02d-%s', $bookingDate, $nextNumber, basename($file));
        $targetPath = dirname($sourcePath) . DIRECTORY_SEPARATOR . $targetName;

        if (@rename($sourcePath, $targetPath)) {
            $usedNumbers[$nextNumber] = true;
            $renamed++;
        } else {
            $failed++;
        }
    }

    $query = http_build_query([
        'booking_renamed' => $renamed,
        'booking_failed' => $failed,
    ]);
    header('Location: ./?' . $query);
    exit;
}

$renamed = isset($_GET['booking_renamed']) && ctype_digit((string) $_GET['booking_renamed'])
    ? (int) $_GET['booking_renamed']
    : null;
$failed = isset($_GET['booking_failed']) && ctype_digit((string) $_GET['booking_failed'])
    ? (int) $_GET['booking_failed']
    : 0;
$removeResult = in_array($_GET['remove_result'] ?? null, ['success', 'failed'], true)
    ? $_GET['remove_result']
    : null;
$archiveResult = in_array($_GET['archive_result'] ?? null, ['success', 'failed'], true)
    ? $_GET['archive_result']
    : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF New Web Interface</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .scrollable-list {
            height: calc(100vh - 62px);
            overflow-y: auto;
            background-color: #f1f3f5;
            border: 1px solid #ced4da;
            border-radius: .5rem;
            box-shadow: 0 .125rem .375rem rgba(0, 0, 0, .08);
        }

        .pdf-viewer {
            width: 100%;
            flex: 1 1 auto;
            min-height: 0;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            background-color: #fff;
        }

        .pdf-viewer-column {
            height: calc(100vh - 62px);
        }

        .pdf-viewer-filename {
            overflow-wrap: anywhere;
        }

        #fileList .list-group-item {
            background-color: #f1f3f5;
        }

        #fileList .file-list-item {
            padding: 0;
        }

        #fileList .file-list-item a {
            color: inherit;
            text-decoration: none;
            background-color: transparent;
            padding: .5rem 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background-color .15s ease-in-out;
        }

        #fileList .file-list-item a:hover {
            color: inherit;
            background-color: #dee2e6;
            text-decoration: none;
        }

        #fileList .file-list-item.active a,
        #fileList .file-list-item.active a:hover {
            color: #fff;
            background-color: var(--bs-list-group-active-bg);
        }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid py-2">
    <div class="d-flex align-items-center gap-2 mb-2">
        <form method="post">
            <input type="hidden" name="action" value="assign_booking_numbers">
            <button type="submit" class="btn btn-primary">Belegnummern</button>
        </form>
        <form method="post" id="archiveForm">
            <input type="hidden" name="action" value="archive_pdf">
            <input type="hidden" name="file" id="archiveFile">
            <button type="submit" class="btn btn-secondary" id="archiveButton" disabled>Archivieren</button>
        </form>
        <form method="post" id="removeForm">
            <input type="hidden" name="action" value="remove_pdf">
            <input type="hidden" name="file" id="removeFile">
            <button type="submit" class="btn btn-danger" id="removeButton" disabled>Entfernen</button>
        </form>
        <?php if ($renamed !== null): ?>
            <div class="alert <?= $failed > 0 ? 'alert-warning' : 'alert-success' ?> py-1 px-2 mb-0" role="status">
                <?= $renamed ?> PDF-Datei<?= $renamed === 1 ? '' : 'en' ?> umbenannt.
                <?php if ($failed > 0): ?>
                    <?= $failed ?> PDF-Datei<?= $failed === 1 ? '' : 'en' ?> konnte<?= $failed === 1 ? '' : 'n' ?> nicht umbenannt werden.
                <?php endif; ?>
            </div>
        <?php elseif ($archiveResult !== null): ?>
            <div class="alert <?= $archiveResult === 'success' ? 'alert-success' : 'alert-danger' ?> py-1 px-2 mb-0" role="status">
                <?= $archiveResult === 'success'
                    ? 'Die PDF-Datei wurde archiviert.'
                    : 'Die PDF-Datei konnte nicht archiviert werden.' ?>
            </div>
        <?php elseif ($removeResult !== null): ?>
            <div class="alert <?= $removeResult === 'success' ? 'alert-success' : 'alert-danger' ?> py-1 px-2 mb-0" role="status">
                <?= $removeResult === 'success'
                    ? 'Die PDF-Datei wurde entfernt.'
                    : 'Die PDF-Datei konnte nicht entfernt werden.' ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="row g-2">
        <div class="col-4">
            <div class="scrollable-list p-0">
                <div class="list-group list-group-flush" id="fileList">
                    <?php foreach ($files as $file): ?>
                        <div class="list-group-item file-list-item"
                             data-filepath="<?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>"
                             data-has-booking-number="<?= hasBookingNumber(basename($file)) ? '1' : '0' ?>">
                            <a class="d-block"
                               href="<?= implode('/', array_map('rawurlencode', explode('/', $file))) ?>#pagemode=none&amp;zoom=page-width"
                               title="<?= htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8') ?>"
                               target="pdfViewer">
                                <?= htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($files === []): ?>
                        <div class="list-group-item text-muted">Keine PDF-Dateien gefunden.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-8 d-flex flex-column pdf-viewer-column">
            <div class="pdf-viewer-filename bg-white border rounded px-2 py-1 mb-2"
                 id="pdfViewerFilename">Kein PDF ausgewählt</div>
            <iframe class="pdf-viewer" id="pdfViewer" name="pdfViewer" title="PDF-Vorschau"></iframe>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('#fileList a').forEach(link => {
        link.addEventListener('click', function () {
            document.querySelectorAll('#fileList .list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            const listItem = this.closest('.list-group-item');
            listItem.classList.add('active');
            document.getElementById('pdfViewerFilename').textContent = listItem.dataset.filepath;
            document.getElementById('archiveFile').value = listItem.dataset.filepath;
            document.getElementById('archiveButton').disabled = listItem.dataset.hasBookingNumber !== '1';
            document.getElementById('removeFile').value = listItem.dataset.filepath;
            document.getElementById('removeButton').disabled = false;
        });
    });

    document.getElementById('removeForm').addEventListener('submit', event => {
        if (!confirm('Soll die ausgewählte PDF-Datei wirklich entfernt werden?')) {
            event.preventDefault();
        }
    });
</script>
</body>
</html>
