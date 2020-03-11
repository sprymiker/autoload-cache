<?php

require 'nfsTester.php';

/**
 * @param string $moduleName
 *
 * @return bool
 */
function assertDoesModuleDirectoryExist(string $moduleName): bool
{
    return file_exists(getModulePath($moduleName));
}

/**
 * @param string $moduleName
 * @param string $className
 *
 * @return bool
 */
function assertDoesClassFileExist(string $moduleName, string $className): bool
{
    return file_exists(getClassPath($moduleName, $className));
}

while(!assertDoesModuleDirectoryExist($moduleName)) {
    sleep(1);
}

for ($i = 0; $i < $fileCount; $i++) {
    $className = $baseClassName . $i;

    if (!assertDoesClassFileExist($moduleName, $className)) {
        echo $className . ' doesn\'t exist' . PHP_EOL;
    }
}
