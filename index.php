<?php
// SPDX-License-Identifier: MIT

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

function addUsedBookingNumbers(array &$usedNumbers, array $files, string $bookingDate): void
{
    foreach ($files as $file) {
        if (preg_match('/^' . preg_quote($bookingDate, '/') . '(\d{2})-/', basename($file), $matches)) {
            $number = (int) $matches[1];
            if ($number >= 1 && $number <= 99) {
                $usedNumbers[$number] = true;
            }
        }
    }
}

function resolvePdfPath(string $baseDir, string $relativePath): string|false
{
    $sourcePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $sourceRealPath = realpath($sourcePath);
    $isInsideBaseDir = $sourceRealPath !== false
        && strncmp($sourceRealPath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) === 0;
    $isPdf = $sourceRealPath !== false
        && strtolower(pathinfo($sourceRealPath, PATHINFO_EXTENSION)) === 'pdf';

    return $relativePath !== ''
        && $isInsideBaseDir
        && $isPdf
        && is_file($sourceRealPath)
        && !is_link($sourcePath)
        ? $sourceRealPath
        : false;
}

function selectedPdfPaths(): array
{
    $paths = $_POST['files'] ?? [];
    if (!is_array($paths)) {
        return [];
    }

    return array_values(array_unique(array_filter($paths, 'is_string')));
}

$baseDir = realpath(__DIR__);
$files = findPdfFiles($baseDir);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'remove_pdf') {
    $removed = 0;
    $failed = 0;
    foreach (selectedPdfPaths() as $relativePath) {
        $sourceRealPath = resolvePdfPath($baseDir, $relativePath);
        if ($sourceRealPath !== false && !file_exists($sourceRealPath . '.deleted')
            && @rename($sourceRealPath, $sourceRealPath . '.deleted')) {
            $removed++;
        } else {
            $failed++;
        }
    }

    header('Location: ./?' . http_build_query(['remove_success' => $removed, 'remove_failed' => $failed]));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'archive_pdf') {
    $archived = 0;
    $failed = 0;
    foreach (selectedPdfPaths() as $relativePath) {
        $sourceRealPath = resolvePdfPath($baseDir, $relativePath);
        if ($sourceRealPath !== false && hasBookingNumber(basename($sourceRealPath))) {
            $filename = basename($sourceRealPath);
            $archiveDir = dirname($baseDir) . DIRECTORY_SEPARATOR . 'PDF_Archive'
                . DIRECTORY_SEPARATOR . substr($filename, 0, 4);
            $targetPath = $archiveDir . DIRECTORY_SEPARATOR . $filename;
            $archiveDirReady = is_dir($archiveDir) || @mkdir($archiveDir, 0777, true);

            $success = $archiveDirReady
                && !file_exists($targetPath)
                && @rename($sourceRealPath, $targetPath);
        } else {
            $success = false;
        }
        $success ? $archived++ : $failed++;
    }

    header('Location: ./?' . http_build_query(['archive_success' => $archived, 'archive_failed' => $failed]));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'assign_booking_numbers') {
    $bookingDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Ymd');
    $usedNumbers = [];
    addUsedBookingNumbers($usedNumbers, $files, $bookingDate);

    $archiveYearDir = dirname($baseDir) . DIRECTORY_SEPARATOR . 'PDF_Archive'
        . DIRECTORY_SEPARATOR . substr($bookingDate, 0, 4);
    if (is_dir($archiveYearDir)) {
        addUsedBookingNumbers($usedNumbers, findPdfFiles($archiveYearDir), $bookingDate);
    }

    $renamed = 0;
    $failed = 0;
    $alreadyNumbered = 0;
    $selectedFileAfterAction = '';
    foreach (selectedPdfPaths() as $relativePath) {
        $sourceRealPath = resolvePdfPath($baseDir, $relativePath);
        if ($sourceRealPath === false) {
            $failed++;
            continue;
        }

        if (hasBookingNumber(basename($sourceRealPath))) {
            $alreadyNumbered++;
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

        $targetName = sprintf('%s%02d-%s', $bookingDate, $nextNumber, basename($sourceRealPath));
        $targetPath = dirname($sourceRealPath) . DIRECTORY_SEPARATOR . $targetName;
        if (!file_exists($targetPath) && @rename($sourceRealPath, $targetPath)) {
            $usedNumbers[$nextNumber] = true;
            $renamed++;
            $selectedFileAfterAction = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($targetPath, strlen($baseDir) + 1)
            );
        } else {
            $failed++;
        }
    }

    header('Location: ./?' . http_build_query([
        'booking_success' => $renamed,
        'booking_failed' => $failed,
        'booking_existing' => $alreadyNumbered,
        'selected_file' => $selectedFileAfterAction,
    ]));
    exit;
}

function queryCount(string $key): int|false
{
    return isset($_GET[$key]) && is_string($_GET[$key]) && ctype_digit($_GET[$key])
        ? (int) $_GET[$key]
        : false;
}

$bookingSuccess = queryCount('booking_success');
$bookingFailed = queryCount('booking_failed');
$bookingExisting = queryCount('booking_existing');
$removeSuccess = queryCount('remove_success');
$removeFailed = queryCount('remove_failed');
$archiveSuccess = queryCount('archive_success');
$archiveFailed = queryCount('archive_failed');
$selectedFile = is_string($_GET['selected_file'] ?? null) ? $_GET['selected_file'] : '';
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
            display: flex;
            align-items: center;
            padding: 0;
        }

        #fileList .file-list-item a {
            flex: 1 1 auto;
            min-width: 0;
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
<body class="bg-light" data-selected-file="<?= htmlspecialchars($selectedFile, ENT_QUOTES, 'UTF-8') ?>">
<div class="container-fluid py-2">
    <div class="d-flex align-items-center gap-2 mb-2">
        <form method="post" id="bookingForm">
            <input type="hidden" name="action" value="assign_booking_numbers">
            <button type="submit" class="btn btn-primary" id="bookingButton" disabled>Belegnummer vergeben</button>
        </form>
        <form method="post" id="archiveForm">
            <input type="hidden" name="action" value="archive_pdf">
            <button type="submit" class="btn btn-secondary" id="archiveButton" disabled>Archivieren</button>
        </form>
        <form method="post" id="removeForm">
            <input type="hidden" name="action" value="remove_pdf">
            <button type="submit" class="btn btn-danger" id="removeButton" disabled>Entfernen</button>
        </form>
        <?php if ($bookingSuccess !== false || $archiveSuccess !== false || $removeSuccess !== false): ?>
            <div class="alert alert-info py-1 px-2 mb-0" role="status">
                <?php if ($bookingSuccess !== false): ?>
                    <?= (int) $bookingSuccess ?> Belegnummer<?= (int) $bookingSuccess === 1 ? '' : 'n' ?> vergeben.
                    <?php if ((int) $bookingExisting > 0): ?> <?= (int) $bookingExisting ?> bereits nummeriert übersprungen.<?php endif; ?>
                    <?php if ((int) $bookingFailed > 0): ?> <?= (int) $bookingFailed ?> fehlgeschlagen.<?php endif; ?>
                <?php elseif ($archiveSuccess !== false): ?>
                    <?= (int) $archiveSuccess ?> PDF-Datei<?= (int) $archiveSuccess === 1 ? '' : 'en' ?> archiviert.
                    <?php if ((int) $archiveFailed > 0): ?> <?= (int) $archiveFailed ?> fehlgeschlagen.<?php endif; ?>
                <?php else: ?>
                    <?= (int) $removeSuccess ?> PDF-Datei<?= (int) $removeSuccess === 1 ? '' : 'en' ?> entfernt.
                    <?php if ((int) $removeFailed > 0): ?> <?= (int) $removeFailed ?> fehlgeschlagen.<?php endif; ?>
                <?php endif; ?>
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
                            <input class="form-check-input mx-2 file-checkbox" type="checkbox"
                                   aria-label="<?= htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8') ?> auswählen">
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
    function selectPdf(link) {
        document.querySelectorAll('#fileList .list-group-item').forEach(item => {
            item.classList.remove('active');
        });
        const listItem = link.closest('.list-group-item');
        listItem.classList.add('active');
        listItem.querySelector('.file-checkbox').checked = true;
        document.getElementById('pdfViewerFilename').textContent = listItem.dataset.filepath;
        localStorage.setItem('currentPdfFile', listItem.dataset.filepath);
        updateSelection();
    }

    const selectionForms = ['bookingForm', 'archiveForm', 'removeForm'];
    function updateSelection() {
        const checked = [...document.querySelectorAll('.file-checkbox:checked')];
        const selectedItems = checked.map(checkbox => checkbox.closest('.file-list-item'));
        const hasUnnumbered = selectedItems.some(item => item.dataset.hasBookingNumber !== '1');
        const hasNumbered = selectedItems.some(item => item.dataset.hasBookingNumber === '1');
        document.getElementById('bookingButton').disabled = !hasUnnumbered;
        document.getElementById('archiveButton').disabled = !hasNumbered;
        document.getElementById('removeButton').disabled = checked.length === 0;

        selectionForms.forEach(formId => {
            const form = document.getElementById(formId);
            form.querySelectorAll('input[name="files[]"]').forEach(input => input.remove());
            checked.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'files[]';
                input.value = checkbox.closest('.file-list-item').dataset.filepath;
                form.appendChild(input);
            });
        });
    }

    document.querySelectorAll('.file-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const listItem = this.closest('.file-list-item');
            if (!this.checked && listItem.classList.contains('active')) {
                listItem.classList.remove('active');
                localStorage.removeItem('currentPdfFile');
            }
            updateSelection();
        });
        checkbox.addEventListener('click', event => event.stopPropagation());
    });

    document.querySelectorAll('#fileList a').forEach(link => {
        link.addEventListener('click', function () {
            selectPdf(this);
        });
    });

    const selectedFile = document.body.dataset.selectedFile || localStorage.getItem('currentPdfFile') || '';
    if (selectedFile !== '') {
        const selectedLink = [...document.querySelectorAll('#fileList a')]
            .find(link => link.closest('.file-list-item').dataset.filepath === selectedFile);
        if (selectedLink) {
            selectPdf(selectedLink);
            document.getElementById('pdfViewer').src = selectedLink.href;
        } else {
            localStorage.removeItem('currentPdfFile');
        }
    }

    document.getElementById('removeForm').addEventListener('submit', event => {
        const count = document.querySelectorAll('.file-checkbox:checked').length;
        if (!confirm(`Sollen die ${count} ausgewählten PDF-Dateien wirklich entfernt werden?`)) {
            event.preventDefault();
        }
    });
</script>
</body>
</html>
