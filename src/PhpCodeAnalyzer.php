<?php
namespace wapmorgan\PhpCodeAnalyzer;

function in_array_column($haystack, $needle, $column, $strict = false) {
    if ($strict) {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] === $needle)
                return true;
        }
        return false;
    } else {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] == $needle)
                return true;
        }
        return false;
    }
}

function array_search_column($haystack, $needle, $column, $strict = false) {
    if ($strict) {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] === $needle)
                return $k;
        }
        return false;
    } else {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] == $needle)
                return $k;
        }
        return false;
    }
}

function array_filter_by_column($source, $needle, $column) {
    $filtered = array();
    foreach ($source as $elem) {
        if ($elem[$column] == $needle)
            $filtered[] = $elem;
    }
    return $filtered;
}

class PhpCodeAnalyzer {
    const VERSION = '1.0.6';

    private $functionsSet = array();
    private $classesSet = array();
    private $constantsSet = array();
    private $extensions = array();
    private $usedExtensions = array();
    private $sinceVersion = null;

    const XML_ENTRY_TEMPLATE = '<file path="%s" extension="%s" line="%d" type="%s">%s</file>';
    const XML_BEGINNING_TEMPLATE = '<?xml version="1.0" encoding="UTF-8"?><php-code-analyzer version="%s"><files>';
    const XML_ENDING = '</files></php-code-analyzer>';

    public function setSinceVersion($version){
        if (!preg_match('~^[[:digit:]]{1}\.[[:digit:]]{1}(\.[[:digit:]]{1})?$~', $version, $match))
            return false;
        // fix for short version like 5.2
        if (empty($match[3]))
            $version .= '.0';
        $this->sinceVersion = $version;
    }

    public function loadData() {
        $extensions = $this->getExtensionFiles();
        foreach ($extensions as $extension_file) {
            $ext = basename($extension_file, '.php');
            $extension_data = include $extension_file;

            // skip extensions bundled since lower version
            if (!empty($this->sinceVersion) && isset($extension_data['php_version'])) {
                if (version_compare($extension_data['php_version'], $this->sinceVersion, '<')){
                    continue;
                }
            }

            if (isset($extension_data['functions'])) {
                foreach ($extension_data['functions'] as $extension_function) {
                    $this->functionsSet[$extension_function] = $ext;
                }
                unset($extension_data['functions']);
            }

            if (isset($extension_data['classes'])) {
                foreach ($extension_data['classes'] as $extension_class) {
                    $this->classesSet[$extension_class] = $ext;
                }
                unset($extension_data['classes']);
            }

            if (isset($extension_data['constants'])) {
                foreach ($extension_data['constants'] as $extension_constant) {
                    $this->constantsSet[$extension_constant] = $ext;
                }
                unset($extension_data['constants']);
            }

            $this->extensions[$ext] = $extension_data;
        }
    }

    public function loadOneExtensionData($ext) {
        if (file_exists($extension_file = dirname(dirname(__FILE__)).'/data/'.$ext.'.php')) {
            $ext = basename($extension_file, '.php');
            $extension_data = include $extension_file;
            if (isset($extension_data['functions'])) {
                foreach ($extension_data['functions'] as $extension_function) {
                    $this->functionsSet[$extension_function] = $ext;
                }
                unset($extension_data['functions']);
            }

            if (isset($extension_data['classes'])) {
                foreach ($extension_data['classes'] as $extension_class) {
                    $this->classesSet[$extension_class] = $ext;
                }
                unset($extension_data['classes']);
            }

            if (isset($extension_data['constants'])) {
                foreach ($extension_data['constants'] as $extension_constant) {
                    $this->constantsSet[$extension_constant] = $ext;
                }
                unset($extension_data['constants']);
            }

            $this->extensions[$ext] = $extension_data;
        }
    }

    public function analyzeDir($dir) {
        $this->println('Scanning '.$dir.' ...');
        $this->analyzeDirInternal($dir);
    }

    protected function analyzeDirInternal($dir) {
        foreach (glob($dir.'/*') as $file) {
            if (is_dir($file))
                $this->analyzeDirInternal($file);
            else if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('php', 'php5', 'phtml'))) {
                $this->analyzeFile($file);
            }
        }
    }

    public function analyzeFile($file) {
        $this->println('Analyzing file '.$file, true);
        $source = file_get_contents($file);
        $tokens = token_get_all($source);

        // cut off heredoc, comments
        while (in_array_column($tokens, T_START_HEREDOC, 0)) {
            $start = array_search_column($tokens, T_START_HEREDOC, 0);
            $end = array_search_column($tokens, T_END_HEREDOC, 0);
            array_splice($tokens, $start, ($end - $start + 1));
        }

        // find for used class names
        $t = count($tokens);
        for ($i = 0; $i < $t; $i++) {
            // use statement
            if (is_array($tokens[$i]) && $tokens[$i][0] == T_USE) {
                $i++;
                if (is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE)
                    $i++;
                if ($tokens[$i] == '\\')
                    $i++;
                $class_name = $this->catchAsStringUntilSemicolon($tokens, $i);
                if (isset($this->classesSet[$class_name])) {
                    $ext = $this->classesSet[$class_name];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    $this->printXmlFileData($file, $tokens[$i][2], $ext, 'class', $class_name);
                    if (count($this->extensions) == 1 || !isset($_ENV['--no-progress']) || !$_ENV['--no-progress']) {
                        $this->println('[' . $ext . '] Class "' . $class_name . '" used in file ' . $file . '[' . $tokens[$i][2] . ']');
                    }
                }
            }
            // new statement
            else if (is_array($tokens[$i]) && $tokens[$i][0] == T_NEW) {
                $i++;
                if (is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE)
                    $i++;
                if ($tokens[$i] == '\\')
                    $i++;
                $class_name = $this->catchAsStringUntilOpenBrace($tokens, $i);
                if (isset($this->classesSet[$class_name])) {
                    $ext = $this->classesSet[$class_name];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    $this->printXmlFileData($file, $tokens[$i][2], $ext, 'class', $class_name);
                    if (count($this->extensions) == 1 || !isset($_ENV['--no-progress']) || !$_ENV['--no-progress'])
                        $this->println('['.$ext.'] Class "'.$class_name.'" used in file '.$file.'['.$tokens[$i][2].']');
                }
            }
            // extends statement
            else if (is_array($tokens[$i]) && $tokens[$i][0] == T_EXTENDS) {
                $i++;
                if (is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE)
                    $i++;
                if ($tokens[$i] == '\\')
                    $i++;
                $class_name = $this->catchAsStringUntilOpenCurlyBrace($tokens, $i);
                if (isset($this->classesSet[$class_name])) {
                    $ext = $this->classesSet[$class_name];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    $this->printXmlFileData($file, $tokens[$i][2], $ext, 'class', $class_name);
                    if (count($this->extensions) == 1 || !isset($_ENV['--no-progress']) || !$_ENV['--no-progress'])
                        $this->println('['.$ext.'] Class "'.$class_name.'" used in file '.$file.'['.$tokens[$i][2].']');
                }
            }
            // find other usages
            else if (is_array($tokens[$i]) && $tokens[$i][0] == T_STRING) {
                if (isset($this->functionsSet[$tokens[$i][1]])) {
                    $function_name = $tokens[$i][1];
                    $ext = $this->functionsSet[$tokens[$i][1]];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    $this->printXmlFileData($file, $tokens[$i][2], $ext, 'function', $function_name);
                    if (count($this->extensions) == 1 || !isset($_ENV['--no-progress']) || !$_ENV['--no-progress'])
                        $this->println('['.$ext.'] Function "'.$function_name.'" used in file '.$file.'['.$tokens[$i][2].']');
                } else if (isset($this->constantsSet[$tokens[$i][1]])) {
                    $constant_name = $tokens[$i][1];
                    $ext = $this->constantsSet[$tokens[$i][1]];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    $this->printXmlFileData($file, $tokens[$i][2], $ext, 'constant', $constant_name);
                    if (count($this->extensions) == 1 || !isset($_ENV['--no-progress']) || !$_ENV['--no-progress'])
                        $this->println('['.$ext.'] Constant "'.$constant_name.'" used in file '.$file.'['.$tokens[$i][2].']');
                }
            }
        }
    }

    /**
     * Catches class name after use statement;
     */
    protected function catchAsStringUntilSemicolon(array $tokens, $pos) {
        $t = count($tokens);
        $catched = null;
        for ($i = $pos; $i < $t; $i++) {
            if ($tokens[$i] == ';')
                return $catched;
            else if ($tokens[$i] == '\\')
                $catched .= '\\';
            else if (is_array($tokens[$i])) {
                $catched .= $tokens[$i][1];
            }
        }
        return $catched;
    }

    /**
     * Catches class name after new statement;
     */
    protected function catchAsStringUntilOpenBrace(array $tokens, $pos) {
        $t = count($tokens);
        $catched = null;
        for ($i = $pos; $i < $t; $i++) {
            if ($tokens[$i] == '(')
                return $catched;
            else if ($tokens[$i] == '\\')
                $catched .= '\\';
            else if (is_array($tokens[$i])) {
                $catched .= $tokens[$i][1];
            }
        }
        return $catched;
    }

    /**
     * Catches class name aft extends statement;
     */
    protected function catchAsStringUntilOpenCurlyBrace(array $tokens, $pos) {
        $t = count($tokens);
        $catched = null;
        for ($i = $pos; $i < $t; $i++) {
            if ($tokens[$i] == '{')
                return $catched;
            else if ($tokens[$i] == '\\')
                $catched .= '\\';
            else if (is_array($tokens[$i])) {
                $catched .= $tokens[$i][1];
            }
        }
        return $catched;
    }

    public function printUsedExtensions() {
        $this->println();
        if (empty($this->usedExtensions)) {
            $this->printMsg('Your code has no usage of non-built-in extension.');
            return null;
        }

        $this->println('Used non-built-in extensions in your code:');
        arsort($this->usedExtensions);

        foreach ($this->usedExtensions as $extension => $uses_number) {
            $extension_data = $this->extensions[$extension];

            if (isset($extension_data['description']))
                $this->printMsg('- ['.$extension.'] '.$extension_data['description'].'.');
            else
                $this->printMsg('- '.$extension.'.');

            if (isset($extension_data['php_version'])) {
                $this->printMsg(' This extension is bundled with php since PHP '.$extension_data['php_version'].'.');
            } else if (isset($extension_data['before_php_version'])) {
                $this->printMsg(' This extension has been bundled with php prior PHP '.$extension_data['php_version'].'.');
            } else if (isset($extension_data['no_php_version'])) {
                $this->printMsg(' This extension has not been bundled with php in PHP '.$extension_data['no_php_version'][0].' - '.$extension_data['no_php_version'][1].'.');
            }

            if (isset($extension_data['dead'])) {
                $this->printMsg(' This extension is seen to be dead now.');
            }

            if (isset($extension_data['pecl_name'])) {
                $this->printMsg(' Extension is available in pecl: '.$extension_data['pecl_name'].'.');
            } else if (!isset($extension_data['pecl']) || $extension_data['pecl']) {
                $this->printMsg(' Extension is available in pecl: '.$extension.'.');
            } else if (isset($extension_data['download_link'])) {
                $this->printMsg(' Extension can be downloaded from external site: '.$extension_data['download_link'].'.');
            }

            if (isset($extension_data['windows']) && !$extension_data['windows']) {
                $this->printMsg(' Windows is not supported by this extension.');
            } else if (isset($extension_data['windows_only']) && $extension_data['windows_only']) {
                $this->printMsg(' Only windows is supported by this extension.');
            }

            $this->println();
        }
    }

    protected function getExtensionFiles()
    {
        $files = [];
        $dir = dirname(dirname(__FILE__)).'/data/';
        $resource = opendir($dir);

        if ($resource === null)
            return [];

        while (($file = readdir($resource)) !== false) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) == 'php')
                $files[] = $dir.'/'.$file;
        }
        return $files;
    }

    private function println($message = null, $verbose = false)
    {
        $this->printMsg($message.PHP_EOL, $verbose);
    }

    private function printMsg($message, $verbose = false)
    {
        if ($_ENV['quiet']) {
            return null;
        }
        if ($_ENV['verbose'] || !$verbose) {
            fwrite(STDOUT, $message);
        }
    }

    private function printXmlFileData($file, $line, $extension, $type, $name)
    {
        if ($_ENV['output'] === null) {
            return;
        }
        file_put_contents($_ENV['output'], sprintf(self::XML_ENTRY_TEMPLATE, $file, $extension, $line, $type, $name), FILE_APPEND);
    }

    public function printXmlStart()
    {
        if ($_ENV['output'] === null) {
            return;
        }
        $version = file_exists(__DIR__.'/../bin/version.txt') ? trim(file_get_contents(__DIR__.'/../bin/version.txt')) : null;
        file_put_contents($_ENV['output'], sprintf(self::XML_BEGINNING_TEMPLATE, $version));
    }

    public function printXmlEnd()
    {
        if ($_ENV['output'] === null) {
            return;
        }
        file_put_contents($_ENV['output'], self::XML_ENDING, FILE_APPEND);
    }
}
