<?php

$libraryPath = dirname(__FILE__). '/../lib/';
$loaderClass = 'PayPal_Merchant_SDK_Autoloader';
$loaderFile  = $libraryPath . $loaderClass . '.php';

/**
 * From comment by "Mike" on http://us2.php.net/manual/en/function.glob.php
 *
 * @param string $pattern
 * @param int $flags - as per glob function
 * @return array of strings
 */
function glob_recursive($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);

    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
    {
        $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
    }

    return $files;
}

/**
 * Use built in parser to get class and interface names from a script
 *
 * Based on code from http://stackoverflow.com/questions/7153000/get-class-name-from-file
 *
 * @param string $source php source to parse
 * @return array of strings
 */
function get_classes_defined($source)
{

    $classes = array();
    $i       = 0;
    $tokens  = token_get_all($source);

    $length = count($tokens);
    for ($i = 0; $i < $length; $i++) {
        if (in_array($tokens[$i][0], array(T_CLASS, T_INTERFACE))) {
            for ($j = $i + 1; $j < $length; $j++) {
                if ($tokens[$j] === '{') {
                    $classes[] = $tokens[$i+2][1];
                    break;
                }
            }
        }
    }

    return $classes;
}

$fileList = glob_recursive($libraryPath . '*.php');
$classes  = array();

foreach ($fileList as $file) {
    // Trim off the absolute path
    $filename = str_replace($libraryPath, '', $file);

    if ($filename === $loaderFile) {
        // Don't include the autoloader in the autoloader map
        continue;
    }

    $found = get_classes_defined(file_get_contents($file));

    foreach ($found as $class) {
        $class = strtolower($class);

        if (isset($classes[$class])) {
            echo "Warning: class [{$class}] is defined in both\n\t{$filename}\n\t{$classes[$class]}\n";
        }

        $classes[$class] = $filename;
    }
}

ksort($classes, SORT_STRING);

$classList = var_export($classes, true);


$script = <<< SCRIPT
<?php
/**
 * Basic class-map auto loader, generated by ant target "create-autoloader"
 * Do not modify
 */
class {$loaderClass}
{
    private static \$map = {$classList};

    public static function loadClass(\$class)
    {
        \$class = strtolower(trim(\$class, '\\\\'));

        if (isset(self::\$map[\$class])) {
            require dirname(__FILE__) . '/' . self::\$map[\$class];
        }
    }

    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'loadClass'), true);
    }
}

SCRIPT;

file_put_contents($loaderFile, $script);
