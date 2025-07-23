<?php
session_start();

$pagePassword = 'f11235813/';

// Şifre kontrolü
if (!isset($_SESSION['sql_access_granted']) || $_SESSION['sql_access_granted'] !== true) {
    // Eğer form gönderilmişse şifreyi kontrol et
    if (isset($_POST['sql_password'])) {
        if ($_POST['sql_password'] === $pagePassword) {
            $_SESSION['sql_access_granted'] = true;
            // Şifre doğruysa sayfayı yenile
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $error = "Yanlış Kod";
        }
    }
    // Şifre formu göster
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Giriş Gerekiyor</title>
        <style>
            body { font-family: sans-serif; background: #f0f0f0; display: flex; height: 100vh; justify-content: center; align-items: center; }
            form { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            input[type=password] { padding: 10px; width: 250px; font-size: 16px; }
            button { padding: 10px 20px; font-size: 16px; margin-top: 10px; cursor: pointer; }
            .error { color: red; margin-top: 10px; }
        </style>
    </head>
    <body>
    <form method="post" autocomplete="off">
        <label>BİLGİSAYARI KAPATMAK İÇİN KOD:</label><br>
        <input type="password" name="sql_password" required autofocus><br>
        <button type="submit">ONAYLA</button>
        <?php if (!empty($error)): ?>
            <div class="error"><?=htmlspecialchars($error)?></div>
        <?php endif; ?>
    </form>
    </body>
    </html>
    <?php
    exit; // Şifre doğrulanmadan sayfa yüklenmesin
}
?>



<?php
require_once __DIR__ . '/includes/db.php';

ini_set("display_errors", "1");
error_reporting(E_ALL);
function renderInput($col, $value = '') {
    $field = htmlspecialchars($col['Field']);
    $type = strtolower($col['Type']);
    $nullable = ($col['Null'] === 'YES') ? '' : 'required';
    $html = '';

    if (preg_match('/^enum\((.*?)\)$/', $type, $matches)) {
        $options = explode(',', str_replace("'", '', $matches[1]));
        $html .= "<select name=\"$field\" $nullable>";
        foreach ($options as $option) {
            $selected = ($option == $value) ? 'selected' : '';
            $html .= "<option value=\"$option\" $selected>$option</option>";
        }
        $html .= "</select>";
    }
    elseif (strpos($type, 'tinyint(1)') !== false) {
        $checked = ($value) ? 'checked' : '';
        $html .= "<input type=\"checkbox\" name=\"$field\" value=\"1\" $checked>";
    }
    elseif (preg_match('/^(int|bigint|smallint|mediumint|tinyint)/', $type)) {
        $html .= "<input type=\"number\" name=\"$field\" value=\"" . htmlspecialchars($value) . "\" $nullable>";
    }
    elseif (preg_match('/^(float|double|decimal)/', $type)) {
        $html .= "<input type=\"number\" step=\"any\" name=\"$field\" value=\"" . htmlspecialchars($value) . "\" $nullable>";
    }
    elseif (strpos($type, 'date') !== false && strpos($type, 'datetime') === false) {
        $html .= "<input type=\"date\" name=\"$field\" value=\"" . htmlspecialchars($value) . "\" $nullable>";
    }
    elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
        $datetime = str_replace(' ', 'T', $value);
        $html .= "<input type=\"datetime-local\" name=\"$field\" value=\"" . htmlspecialchars($datetime) . "\" $nullable>";
    }
    elseif (strpos($type, 'time') !== false) {
        $html .= "<input type=\"time\" name=\"$field\" value=\"" . htmlspecialchars($value) . "\" $nullable>";
    }
    // TEXT için textarea
    elseif (strpos($type, 'text') === 0 && strpos($type, 'longtext') === false) {
        $html .= "<textarea name=\"$field\" rows=\"5\" cols=\"40\" $nullable>" . htmlspecialchars($value) . "</textarea>";
    }
    // LONGTEXT için WYSIWYG editör alanı (örneğin TinyMCE)
    elseif (strpos($type, 'longtext') === 0) {
        $html .= "<textarea class=\"wysiwyg\" name=\"$field\" rows=\"10\" cols=\"80\" $nullable>" . htmlspecialchars($value) . "</textarea>";
    }
    // BLOB / LONGBLOB için file input
    elseif (strpos($type, 'blob') !== false) {
        $html .= "<input type=\"file\" name=\"$field\" $nullable>";
        if ($value) {
            $html .= "<p>Mevcut dosya: <a href=\"download.php?table={$field}&id=" . urlencode($value) . "\">İndir</a></p>";
        }
    }
    else {
        $html .= "<input type=\"text\" name=\"$field\" value=\"" . htmlspecialchars($value) . "\" $nullable>";
    }

    return $html;
}


//$pdo = $db->pdo;

// Tabloları al
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$selectedTable = $_GET['table'] ?? null;

// Eğer tablo seçildiyse kolonları al
$columns = [];
if ($selectedTable) {
    $columns = $db->query("DESCRIBE `$selectedTable`")->fetchAll(PDO::FETCH_ASSOC);
}

// Silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $col = $_POST['delete_column'] ?? null;
    $val = $_POST['delete_value'] ?? null;
    if ($col && $val !== null) {
        try {
            $stmt = $db->prepare("DELETE FROM `$selectedTable` WHERE `$col` = :val");
            $stmt->execute([':val' => $val]);
            // Başarılı mesaj yerine direkt yönlendirme
            header("Location: ?table=" . urlencode($selectedTable));
            exit;
        } catch (Exception $e) {
            echo "<p style='color:red;'>Silme hatası: {$e->getMessage()}</p>";
        }
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $updateCol = $_POST['update_column'];
    $updateVal = $_POST['update_value'];

    $data = [];
    foreach ($columns as $col) {
        $field = $col['Field'];
        if ($col['Extra'] === 'auto_increment') continue;

        if (strpos(strtolower($col['Type']), 'tinyint(1)') !== false) {
            // Checkbox: Eğer gönderilmediyse 0 olarak set et
            $data[$field] = isset($_POST[$field]) ? 1 : 0;
        }
        else {
            $data[$field] = $_POST[$field] ?? null;
        }
    }

    try {
        // $data: ['kolon1' => 'deger1', 'kolon2' => 'deger2']
        $setParts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setParts[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }
        $params[":whereVal"] = $updateVal;

        $sql = "UPDATE `$selectedTable` SET " . implode(", ", $setParts) . " WHERE `$updateCol` = :whereVal";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        header("Location: ?table=" . urlencode($selectedTable));
        exit;
    } catch (Exception $e) {
        echo "<p style='color:red;'>Güncelleme hatası: {$e->getMessage()}</p>";
    }
}

// Ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['insert'])) {
    $data = [];
    foreach ($columns as $col) {
        $field = $col['Field'];
        if ($col['Extra'] === 'auto_increment') continue;
        $data[$field] = $_POST[$field] ?? null;
    }

    try {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = "INSERT INTO `$selectedTable` (`" . implode("`, `", $columns) . "`) 
        VALUES (" . implode(", ", $placeholders) . ")";

        $stmt = $db->prepare($sql);

        $params = [];
        foreach ($data as $key => $value) {
            $params[":$key"] = $value;
        }

        $stmt->execute($params);

        header("Location: ?table=" . urlencode($selectedTable));
        exit;
    } catch (Exception $e) {
        echo "<p style='color:red;'>Hata: {$e->getMessage()}</p>";
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Veritabanı Yönetimi</title>
    <style>
        body { font-family: sans-serif; display: flex; }
        .sidebar { width: 250px; border-right: 1px solid #ccc; padding: 10px; }
        .content { flex: 1; padding: 10px; overflow-x: auto; }
        a { text-decoration: none; display: block; margin-bottom: 5px; color: blue; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 5px; }
    </style>
    <!--<style>
        body { font-family: sans-serif; display: flex;}
        .sidebar { width: 250px; border-right: 1px solid #ccc; padding: 10px; height: 100vh; overflow-y: auto; }
        .content { flex: 1; padding: 10px; overflow-x: auto; }
        a { text-decoration: none; display: block; margin-bottom: 5px; color: blue; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 5px; white-space: normal !important; word-wrap: break-word; }
        .dataTables_wrapper { width: 100% !important; overflow-x: auto; }

    </style>-->

    <!--<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: 'textarea.wysiwyg',
            height: 300,
            menubar: false
        });
    </script>-->

    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/eclipse.min.css" />

    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/sql/sql.min.js"></script>


</head>
<body>
<div class="sidebar">
    <a href="?page=sqlquery" style="font-weight: bold; color: darkred;">📝 SQL Sorgu</a>
    <!--<hr>
    <a href="?page=ctable" style="font-weight: bold; color: darkred;">➕ Create Table</a>-->
    <hr>
    <h3>📁 Tablolar</h3>
    <?php foreach ($tables as $table): ?>
        <a href="?table=<?= urlencode($table) ?>"><?= htmlspecialchars($table) ?></a>
    <?php endforeach; ?>
</div>

<div class="content">
    <?php
    // Burada üstteki ortak kodlar ve $db, $pdo, $tables vs. tanımlı olduğunu varsayıyorum

    $page = $_GET['page'] ?? null;

    if ($page === 'sqlquery'):
        // SQL sorgu sayfası

        $queryResult = null;
        $errorMessage = null;
        $query = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
            $query = trim($_POST['sql_query']);
            try {
                if (stripos($query, 'SELECT') === 0) {
                    // SELECT sorgusu ise sonuçları çek
                    $stmt = $pdo->query($query);
                    $queryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Diğer sorguları çalıştır
                    $affected = $pdo->exec($query);
                    $queryResult = "İşlem başarılı, etkilenen satır sayısı: " . $affected;
                }
            } catch (PDOException $e) {
                $errorMessage = "Hata: " . $e->getMessage();
            }
        }
        ?>

        <p style="text-align: right"><a href="?">⬅️ Ana Sayfaya Dön</a></p>
        <hr>
        <h2>SQL Sorgusu Çalıştır</h2>
        <form method="post">
            <textarea id="sql_editor" name="sql_query" rows="6" style="width: 100%; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($query) ?></textarea><br>
            <button style="float: right" type="submit">Çalıştır</button>
            <br>
        </form>

        <?php if ($errorMessage): ?>
        <p style="color: red;"><?= htmlspecialchars($errorMessage) ?></p>
    <?php endif; ?>

        <?php if ($queryResult): ?>
        <?php if (is_string($queryResult)): ?>
            <p style="color: green;"><?= htmlspecialchars($queryResult) ?></p>
        <?php else: ?>
            <table border="1" cellpadding="5" cellspacing="0" style="margin-top: 15px; border-collapse: collapse; width: 100%;">
                <thead>
                <tr>
                    <?php foreach (array_keys($queryResult[0]) as $header): ?>
                        <th><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($queryResult as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <?php
                            $maxLength = 30; // kaç karakterden sonra kısaltma yapalım
                            $cellStr = (string)$cell;
                            $shortText = (mb_strlen($cellStr) > $maxLength) ? mb_substr($cellStr, 0, $maxLength) . '...' : $cellStr;
                            ?>

                            <td title="<?= htmlspecialchars($cellStr) ?>">
                                <?= htmlspecialchars($shortText) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php elseif ($selectedTable): ?>

        <?php if (isset($_GET['edit_column'], $_GET['edit_value'])): ?>
            <?php
            $editCol = $_GET['edit_column'];
            $editVal = $_GET['edit_value'];
            $stmt = $db->prepare("SELECT * FROM `$selectedTable` WHERE `$editCol` = :val LIMIT 1");
            $stmt->execute([':val' => $editVal]);
            $editingRow = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <h3>✏️ Kayıt Güncelle: <?= htmlspecialchars($editCol) ?> => <?= htmlspecialchars($editVal) ?></h3>
            <form method="post">
                <input type="hidden" name="update_column" value="<?= htmlspecialchars($editCol) ?>">
                <input type="hidden" name="update_value" value="<?= htmlspecialchars($editVal) ?>">
                <table>
                    <tr><td colspan="2" style="text-align: right"><a href="?table=<?= urlencode($selectedTable) ?>" style="margin-bottom: 15px; display: inline-block;">⬅️ İptal / Yeni Kayıt Ekle</a></td></tr>
                    <?php foreach ($columns as $col): ?>
                        <?php if ($col['Extra'] === 'auto_increment') continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($col['Field']) ?>:</td>
                            <td><?= renderInput($col, $editingRow[$col['Field']] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="2" style="text-align: right"><button type="submit" name="update">Güncelle</button></td></tr>
                </table>
            </form>
        <?php else: ?>
            <h3>➕ <?= htmlspecialchars($selectedTable) ?> Tablosuna Yeni Kayıt Ekle</h3>
            <form method="post">
                <table>
                    <?php foreach ($columns as $col): ?>
                        <?php if ($col['Extra'] === 'auto_increment') continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($col['Field']) ?>:</td>
                            <td><?= renderInput($col) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="2" style="text-align: right"><button type="submit" name="insert">Ekle</button></td></tr>
                </table>
            </form>
        <?php endif; ?>

        <hr>
        <?php // Satır sayısını PDO ile al
        $rowCount = $db->query("SELECT COUNT(*) FROM `$selectedTable`")->fetchColumn();

        ?>
        <h3>📋 <?= htmlspecialchars($selectedTable) ?> Tablosundaki Kayıtlar (Toplam: <?= $rowCount ?>)</h3>
        <?php
        $stmt = $db->prepare("SELECT * FROM `$selectedTable`");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results && count($results) > 0):
            ?>
            <table id="dt_table1">
                <thead>
                <tr>
                    <th>Sil</th>
                    <th>Düzenle</th>
                    <?php foreach (array_keys($results[0]) as $column): ?>
                        <th><?= htmlspecialchars($column) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <?php
                        $rowArray = (array) $row;
                        reset($rowArray);
                        $firstColName = key($rowArray);
                        $firstColValue = $rowArray[$firstColName];
                        ?>
                        <!-- Sil -->
                        <td>
                            <form method="post" onsubmit="return confirm('Bu kaydı silmek istiyor musunuz?');">
                                <input type="hidden" name="delete_column" value="<?= htmlspecialchars($firstColName) ?>">
                                <input type="hidden" name="delete_value" value="<?= htmlspecialchars($firstColValue) ?>">
                                <button type="submit" name="delete">Sil</button>
                            </form>
                        </td>
                        <!-- Düzenle -->
                        <td>
                            <form method="get">
                                <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
                                <input type="hidden" name="edit_column" value="<?= htmlspecialchars($firstColName) ?>">
                                <input type="hidden" name="edit_value" value="<?= htmlspecialchars($firstColValue) ?>">
                                <button type="submit">Düzenle</button>
                            </form>
                        </td>
                        <?php
                        $maxLength = 30; // kaç karakterden sonra kısaltma yapalım
                        foreach ($rowArray as $cell):
                            $cellStr = (string)$cell;
                            $shortText = (mb_strlen($cellStr) > $maxLength) ? mb_substr($cellStr, 0, $maxLength) . '...' : $cellStr;
                            ?>
                            <td title="<?= htmlspecialchars($cellStr) ?>">
                                <?= htmlspecialchars($shortText) ?>
                            </td>
                        <?php endforeach; ?>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Hiç veri yok.</p>
        <?php endif; ?>

    <?php else: ?>
        <p>Sol menüden bir tablo seçin ya da SQL sorgu sayfasına gidin.</p>
    <?php endif; ?>
    <hr>
    <p style="text-align: right;font-size: 30px;">𝓕ℬ ©</p>
</div>

</body>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css" />

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<!-- Buttons extension -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>

<!-- Excel, CSV export için -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    var textarea = document.getElementById("sql_editor");
    if (textarea) {
        var editor = CodeMirror.fromTextArea(textarea, {
            mode: "text/x-sql",
            theme: "eclipse",
            lineNumbers: true,
            matchBrackets: true,
            autofocus: true,
            indentWithTabs: true,
            smartIndent: true,
            tabSize: 4,
            extraKeys: {"Ctrl-Space": "autocomplete"}
        });
    }
</script>



<!-- Responsive CSS -->
<!--<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css" />-->

<!-- Responsive JS -->
<!--<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>-->

<script>
    $(document).ready(function() {
        $('#dt_table1').DataTable({
            dom: 'Bfrtip', // Butonları ve arama çubuğunu göster
            paging: true,  // Sayfalama açık (default zaten true)
            ordering: true, // Sıralama açık (default true)
            searching: true, // Arama açık (default true)
            /*responsive: true,*/    // Responsive aktif
            buttons: [
                'copyHtml5', // Kopyala butonu
                'csvHtml5',  // CSV indir
                'excelHtml5' // Excel indir
            ]
        });
    });

</script>
</html>
