<form class="form-inline">
    <button type="submit" name="createMysqlDump" id="createMysqlDump" onclick="createDump(); return false;" class="btn btn-primary">Создать backup базы</button>
    <span id='textDumpStatus' style="padding-left: 10px;"></span>
</form>

<?php
use Ideal\Core\Config;
$config = Config::getInstance();

$error = '';
// Папка для хранения бэкапов
$backup_part =  $config->tmpDir . '/backup/';
$backup_dir = stream_resolve_include_path($backup_part);

// Проверяем существует ли папка для создания бэкапов
// если нет, то пытаемся её создать
if ($backup_dir === false) {
    if (mkdir($backup_part)) {
        $backup_dir = stream_resolve_include_path($backup_part);
    }
}

if ($backup_dir === false) {
    $error = 'Не удалось создать папку для сохранения дампа базы';
    //Проверяем доступнали папка для записи
} elseif (!is_writable($backup_dir)){
    $error = 'Папка для сохранения дампа базы недоступна для записи';
}

if ($error != ''){
    echo "<script>
            $('#textDumpStatus').removeClass().addClass('text-error').html('" . $error . "');
            $('#createMysqlDump').hide();
        </script>";
}

echo '<p>' . 'Раздел для сохранения дампов базы данных: &nbsp;' . $backup_dir . '</p>';

// Получение списка файлов
$dumpFiles = array();
if ($error == '') {
    if ($dh = opendir($backup_dir)) {
        echo '<table id="dumpTable" class="table table-hover">';
        while (($file = readdir($dh)) !== false) {
            if (strripos($file, 'dump_') === false) continue;
            //$dumpFiles[] = $file;
            //$fn = str_replace('dump_', '',$file);
            $year = substr($file,5,4);
            $month = substr($file,10,2);
            $day = substr($file,13,2);
            $hour = substr($file,16,2);
            $minute = substr($file,19,2);
            $second = substr($file,22,2);

            echo '<tr id="' . $file . '"><td><a href="' . $backup_dir . $file . '"> ' . "$day/$month/$year - $hour:$minute:$second" . '</a></td>';
            echo '<td><button class="btn btn-danger btn-mini" title="Удалить" onclick="delDump(\'' . $file . '\'); false;"> <i class="icon-remove icon-white"></i> </button></td>';
            echo '</tr>';
        }
        closedir($dh);
        echo '</table>';
    }
}
?>

<script>
dir = '<?php echo $backup_part ?>';
// Удаление файла
function delDump(idFile) {
    var nameFile = dir + idFile;
    if (confirm('Удалить файл дампа базы?')) {
        var path = window.location.href;
        $.ajax({
            url: path + "&action=ajaxDelete",
            type: 'POST',
            data: {
                name: nameFile
            },
            success: function(data){
                //Выводим сообщение
                var message = data;
                if (message == true) {
                    var el = document.getElementById(idFile);
                    el.parentNode.removeChild(el);
                    $('#textDumpStatus').removeClass().addClass('text-success').html('Файл успешно удалён');
                } else {
                    $('#textDumpStatus').removeClass().addClass('text-error').html('Ошибка при удалении файла');
                }
            },
            error: function() {
                $('#textDumpStatus').removeClass().addClass('text-error').html('Не удалось удалить файл');
            }
        })
    } else {
        // Do nothing!
    }
}

// Создание дампа базы данных
function createDump() {
    $('#textDumpStatus').removeClass().addClass('text-info').html('Идёт создание дампа базы');
    var path = window.location.href;
    $.ajax({
        url: path + "&action=ajaxCreateDump",
        type: 'POST',
        data: {
            createMysqlDump: true,
            backupPart: '<?php echo $backup_part?>'
        },
        success: function(data){
            //Выводим сообщение
            var message = data;
            if (message.length > 1) {
                $('#textDumpStatus').removeClass().addClass('text-success').html('Дамп базы создн');
                $('#dumpTable').append(data);
            } else {
                $('#textDumpStatus').removeClass().addClass('text-error').html('Ошибка при создании дампа базы');
            }
        },
        error: function() {
            $('#textDumpStatus').removeClass().addClass('text-error').html('Не удалось создать дамп базы');
        }
    })
}
</script>