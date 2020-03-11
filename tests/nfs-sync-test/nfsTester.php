<?php

/**
 * @param string $moduleName
 * @param string $className
 *
 * @return string
 */
function getFileContent(string $moduleName, string $className): string
{
    return "<?php

namespace Pyz\Zed\\$moduleName;

class $className
{

}
";

}

/**
 * @param string $moduleName
 *
 * @return string
 */
function getModulePath(string $moduleName): string
{
    return 'src/Pyz/Zed/' . $moduleName;
}

/**
 * @param string $moduleName
 * @param string $className
 *
 * @return string
 */
function getClassPath(string $moduleName, string $className): string
{
    return getModulePath($moduleName)
        . DIRECTORY_SEPARATOR
        . $className
        . '.php';
}

/**
 * @param string $moduleName
 *
 * @return bool
 */
function createModuleDirectory(string $moduleName): bool
{
    $modulePath = getModulePath($moduleName);

    return mkdir($modulePath);
}

/**
 * @param string $moduleName
 * @param string $className
 *
 * @return void
 */
function createClassFile(string $moduleName, string $className): void
{
    file_put_contents(
        getClassPath($moduleName, $className),
        getFileContent($moduleName, $className)
    );
}

$moduleName = 'ATestModule';
$fileCount = 10000;
$baseClassName = 'SomeClass';
