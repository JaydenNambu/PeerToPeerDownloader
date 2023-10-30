<?php
set_include_path ( get_include_path () . PATH_SEPARATOR . dirname ( __FILE__ ) );
echo shell_exec('ls log/stats|wc -l');
?>
