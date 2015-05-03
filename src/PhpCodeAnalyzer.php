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
    private $functionsSet = array();
    private $classesSet = array();
    private $constantsSet = array();
    private $extensions = array();
    private $usedExtensions = array();

    public function loadData() {
        foreach (glob(dirname(dirname(__FILE__)).'/data/*.php') as $extension_file) {
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
        echo 'Scanning '.$dir.' ...'.PHP_EOL;
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
        echo 'Analyzing file '.$file.PHP_EOL;
        $source = file_get_contents($file);
        $tokens = token_get_all($source);

        // cut off heredoc, comments
        while (in_array_column($tokens, T_START_HEREDOC, 0)) {
            $start = array_search_column($tokens, T_START_HEREDOC, 0);
            $end = array_search_column($tokens, T_END_HEREDOC, 0);
            array_splice($tokens, $start, ($end - $start));
        }

        // find for used class names
        $t = count($tokens);
        for ($i = 0; $i < $t; $i++) {
            // use statement
            if (is_array($tokens[$i]) && $tokens[$i][0] == T_USE) {
                $i++;
                if (is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE)
                    $i++;
                $class_name = $this->catchAsStringUntilSemicolon($tokens, $i);
                if (isset($this->classesSet[$class_name])) {
                    $ext = $this->classesSet[$class_name];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    fwrite(STDOUT, '['.$ext.'] Class "'.$class_name.'" used in file '.$file.'['.$tokens[$i][2].']'.PHP_EOL);
                }
            }
            // new statement
            else if (is_array($tokens[$i]) && $tokens[$i][0] == T_NEW) {
                $i++;
                if (is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE)
                    $i++;
                $class_name = $this->catchAsStringUntilOpenBrace($tokens, $i);
                if (isset($this->classesSet[$class_name])) {
                    $ext = $this->classesSet[$class_name];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    fwrite(STDOUT, '['.$ext.'] Class "'.$class_name.'" used in file '.$file.'['.$tokens[$i][2].']'.PHP_EOL);
                }
            }
            // extends statement
            else if (is_array($tokens[$i]) && $tokens[$i][0] == T_EXTENDS) {
                $i++;
                if (is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE)
                    $i++;
                $class_name = $this->catchAsStringUntilOpenCurlyBrace($tokens, $i);
                if (isset($this->classesSet[$class_name])) {
                    $ext = $this->classesSet[$class_name];
                    if (isset($this->usedExtensions[$ext])) $this->usedExtensions[$ext]++;
                    else $this->usedExtensions[$ext] = 1;
                    fwrite(STDOUT, '['.$ext.'] Class "'.$class_name.'" used in file '.$file.'['.$tokens[$i][2].']'.PHP_EOL);
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
        echo 'Used non-built-in extensions in your code:'.PHP_EOL;
        arsort($this->usedExtensions);
        foreach ($this->usedExtensions as $extension => $uses_number) {
            $extension_data = $this->extensions[$extension];
            if (isset($extension_data['description']))
                echo '- ['.$extension.'] '.$extension_data['description'].'.';
            else
                echo '- '.$extension.'.';

            if (isset($extension_data['php_version'])) {
                echo ' This extension is bundled with php since PHP '.$extension_data['php_version'].'.';
            } else if (isset($extension_data['before_php_version'])) {
                echo ' This extension has been bundled with php prior PHP '.$extension_data['php_version'].'.';
            } else if (isset($extension_data['no_php_version'])) {
                echo ' This extension has not been bundled with php in PHP '.$extension_data['no_php_version'][0].' - '.$extension_data['no_php_version'][1].'.';
            }

            if (isset($extension_data['dead'])) {
                echo ' This extension is seen to be dead now.';
            }

            if (isset($extension_data['pecl_name'])) {
                echo ' Extension is available in pecl: '.$extension_data['pecl_name'].'.';
            } else if (!isset($extension_data['pecl']) || $extension_data['pecl']) {
                echo ' Extension is available in pecl: '.$extension.'.';
            } else if (isset($extension_data['download_link'])) {
                echo ' Extension can be downloaded from external site: '.$extension_data['download_link'].'.';
            }

            if (isset($extension_data['windows']) && !$extension_data['windows']) {
                echo ' Windows is not supported.';
            }

            echo PHP_EOL;
        }
    }
}
