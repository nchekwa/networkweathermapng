<?php
// Copy the current visual.php to temp file for repair
$content = file_get_contents('/mnt/d/Projects/zabbix-weathermap/src/app/Views/editor/visual.php');
file_put_contents('/mnt/d/Projects/zabbix-weathermap/src/app/Views/editor/visual_temp.php', $content);
