<?php

require 'nfsTester.php';

createModuleDirectory($moduleName);

for ($i = 0; $i < $fileCount; $i++) {
    createClassFile(
        $moduleName,
        $baseClassName . $i
    );
}
