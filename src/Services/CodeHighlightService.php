<?php
/**
 * CodeVault 代码高亮优化服务
 * 
 * 功能：
 * - 多语言语法高亮
 * - 语法错误检测
 * - 代码折叠
 * - 行号显示
 * - 主题切换
 */

namespace Services;

class CodeHighlightService
{
    // 支持的语言
    private const SUPPORTED_LANGUAGES = [
        // Web 前端
        'html' => ['ext' => ['html', 'htm'], 'name' => 'HTML'],
        'css' => ['ext' => ['css'], 'name' => 'CSS'],
        'javascript' => ['ext' => ['js', 'mjs', 'cjs'], 'name' => 'JavaScript'],
        'typescript' => ['ext' => ['ts', 'tsx'], 'name' => 'TypeScript'],
        'vue' => ['ext' => ['vue'], 'name' => 'Vue'],
        'jsx' => ['ext' => ['jsx'], 'name' => 'JSX'],
        
        // 后端语言
        'php' => ['ext' => ['php'], 'name' => 'PHP'],
        'python' => ['ext' => ['py', 'pyw'], 'name' => 'Python'],
        'ruby' => ['ext' => ['rb'], 'name' => 'Ruby'],
        'java' => ['ext' => ['java'], 'name' => 'Java'],
        'go' => ['ext' => ['go'], 'name' => 'Go'],
        'rust' => ['ext' => ['rs'], 'name' => 'Rust'],
        'c' => ['ext' => ['c', 'h'], 'name' => 'C'],
        'cpp' => ['ext' => ['cpp', 'cc', 'cxx', 'hpp', 'hh'], 'name' => 'C++'],
        'csharp' => ['ext' => ['cs'], 'name' => 'C#'],
        'swift' => ['ext' => ['swift'], 'name' => 'Swift'],
        'kotlin' => ['ext' => ['kt', 'kts'], 'name' => 'Kotlin'],
        'scala' => ['ext' => ['scala', 'sc'], 'name' => 'Scala'],
        
        // 脚本语言
        'bash' => ['ext' => ['sh', 'bash', 'zsh'], 'name' => 'Bash'],
        'shell' => ['ext' => ['sh'], 'name' => 'Shell'],
        'powershell' => ['ext' => ['ps1', 'psm1'], 'name' => 'PowerShell'],
        'perl' => ['ext' => ['pl', 'pm'], 'name' => 'Perl'],
        'lua' => ['ext' => ['lua'], 'name' => 'Lua'],
        
        // 数据格式
        'json' => ['ext' => ['json'], 'name' => 'JSON'],
        'xml' => ['ext' => ['xml'], 'name' => 'XML'],
        'yaml' => ['ext' => ['yaml', 'yml'], 'name' => 'YAML'],
        'toml' => ['ext' => ['toml'], 'name' => 'TOML'],
        'ini' => ['ext' => ['ini', 'conf', 'cfg'], 'name' => 'INI'],
        
        // 数据库
        'sql' => ['ext' => ['sql'], 'name' => 'SQL'],
        
        // 配置文件
        'dockerfile' => ['ext' => ['dockerfile'], 'name' => 'Dockerfile'],
        'makefile' => ['ext' => ['makefile', 'mk'], 'name' => 'Makefile'],
        'nginx' => ['ext' => ['nginx.conf'], 'name' => 'Nginx'],
        'apache' => ['ext' => ['htaccess'], 'name' => 'Apache'],
        
        // 其他
        'markdown' => ['ext' => ['md', 'markdown'], 'name' => 'Markdown'],
        'diff' => ['ext' => ['diff', 'patch'], 'name' => 'Diff'],
        'git' => ['ext' => ['gitignore', 'gitattributes'], 'name' => 'Git'],
        'plaintext' => ['ext' => ['txt'], 'name' => 'Plain Text'],
    ];
    
    // 主题配置
    private const THEMES = [
        'light' => [
            'name' => 'Light',
            'background' => '#ffffff',
            'text' => '#24292e',
            'comment' => '#6a737d',
            'keyword' => '#d73a49',
            'string' => '#032f62',
            'number' => '#005cc5',
            'function' => '#6f42c1',
            'variable' => '#e36209',
            'operator' => '#d73a49',
            'line_number' => '#959da5',
            'line_highlight' => '#f6f8fa',
        ],
        'dark' => [
            'name' => 'Dark',
            'background' => '#0d1117',
            'text' => '#c9d1d9',
            'comment' => '#8b949e',
            'keyword' => '#ff7b72',
            'string' => '#a5d6ff',
            'number' => '#79c0ff',
            'function' => '#d2a8ff',
            'variable' => '#ffa657',
            'operator' => '#ff7b72',
            'line_number' => '#484f58',
            'line_highlight' => '#161b22',
        ],
        'monokai' => [
            'name' => 'Monokai',
            'background' => '#272822',
            'text' => '#f8f8f2',
            'comment' => '#75715e',
            'keyword' => '#f92672',
            'string' => '#e6db74',
            'number' => '#ae81ff',
            'function' => '#a6e22e',
            'variable' => '#fd971f',
            'operator' => '#f92672',
            'line_number' => '#75715e',
            'line_highlight' => '#3e3d32',
        ],
        'solarized-light' => [
            'name' => 'Solarized Light',
            'background' => '#fdf6e3',
            'text' => '#657b83',
            'comment' => '#93a1a1',
            'keyword' => '#859900',
            'string' => '#2aa198',
            'number' => '#d33682',
            'function' => '#268bd2',
            'variable' => '#b58900',
            'operator' => '#859900',
            'line_number' => '#93a1a1',
            'line_highlight' => '#eee8d5',
        ],
        'solarized-dark' => [
            'name' => 'Solarized Dark',
            'background' => '#002b36',
            'text' => '#839496',
            'comment' => '#586e75',
            'keyword' => '#859900',
            'string' => '#2aa198',
            'number' => '#d33682',
            'function' => '#268bd2',
            'variable' => '#b58900',
            'operator' => '#859900',
            'line_number' => '#586e75',
            'line_highlight' => '#073642',
        ],
    ];
    
    /**
     * 根据文件扩展名检测语言
     */
    public function detectLanguage(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        foreach (self::SUPPORTED_LANGUAGES as $lang => $config) {
            if (in_array($ext, $config['ext'])) {
                return $lang;
            }
        }
        
        // 特殊文件名检测
        $basename = strtolower(basename($filename));
        if ($basename === 'dockerfile') return 'dockerfile';
        if ($basename === 'makefile') return 'makefile';
        if ($basename === '.gitignore') return 'git';
        if ($basename === '.gitattributes') return 'git';
        
        return 'plaintext';
    }
    
    /**
     * 获取支持的语言列表
     */
    public function getSupportedLanguages(): array
    {
        return array_map(function ($lang, $config) {
            return [
                'id' => $lang,
                'name' => $config['name'],
                'extensions' => $config['ext'],
            ];
        }, array_keys(self::SUPPORTED_LANGUAGES), self::SUPPORTED_LANGUAGES);
    }
    
    /**
     * 获取主题列表
     */
    public function getThemes(): array
    {
        return array_map(function ($id, $config) {
            return [
                'id' => $id,
                'name' => $config['name'],
            ];
        }, array_keys(self::THEMES), self::THEMES);
    }
    
    /**
     * 高亮代码
     */
    public function highlight(string $code, string $language, string $theme = 'light'): string
    {
        $themeConfig = self::THEMES[$theme] ?? self::THEMES['light'];
        
        // 转义 HTML
        $code = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 分割行
        $lines = explode("\n", $code);
        $highlighted = [];
        
        foreach ($lines as $i => $line) {
            $highlightedLine = $this->highlightLine($line, $language, $themeConfig);
            $highlighted[] = $highlightedLine;
        }
        
        return implode("\n", $highlighted);
    }
    
    /**
     * 高亮单行代码
     */
    private function highlightLine(string $line, string $language, array $theme): string
    {
        // 根据语言应用不同的高亮规则
        switch ($language) {
            case 'php':
                return $this->highlightPhp($line, $theme);
            case 'javascript':
            case 'typescript':
                return $this->highlightJs($line, $theme);
            case 'python':
                return $this->highlightPython($line, $theme);
            case 'java':
                return $this->highlightJava($line, $theme);
            case 'go':
                return $this->highlightGo($line, $theme);
            case 'rust':
                return $this->highlightRust($line, $theme);
            case 'html':
                return $this->highlightHtml($line, $theme);
            case 'css':
                return $this->highlightCss($line, $theme);
            case 'sql':
                return $this->highlightSql($line, $theme);
            case 'json':
                return $this->highlightJson($line, $theme);
            case 'bash':
            case 'shell':
                return $this->highlightBash($line, $theme);
            case 'diff':
                return $this->highlightDiff($line, $theme);
            default:
                return $line;
        }
    }
    
    /**
     * PHP 高亮
     */
    private function highlightPhp(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['<?php', '?>', 'public', 'private', 'protected', 'class', 'function', 
                     'interface', 'trait', 'extends', 'implements', 'use', 'namespace',
                     'return', 'if', 'else', 'elseif', 'switch', 'case', 'break', 'continue',
                     'for', 'foreach', 'while', 'do', 'try', 'catch', 'finally', 'throw',
                     'new', 'static', 'self', 'parent', 'const', 'var', 'echo', 'print',
                     'true', 'false', 'null', 'void', 'int', 'string', 'float', 'bool', 'array'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . preg_quote($keyword, '/') . ')\b/i',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\'])(.*?)\1/',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(\/\/.*$|#.*$|\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 函数调用
        $line = preg_replace(
            '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            '<span style="color:' . $theme['function'] . '">$1</span>(',
            $line
        );
        
        // 变量
        $line = preg_replace(
            '/(\$[a-zA-Z_][a-zA-Z0-9_]*)/',
            '<span style="color:' . $theme['variable'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * JavaScript/TypeScript 高亮
     */
    private function highlightJs(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['const', 'let', 'var', 'function', 'class', 'extends', 'constructor',
                     'return', 'if', 'else', 'switch', 'case', 'break', 'continue',
                     'for', 'while', 'do', 'try', 'catch', 'finally', 'throw',
                     'new', 'this', 'super', 'import', 'export', 'default', 'from',
                     'async', 'await', 'yield', 'typeof', 'instanceof', 'in', 'of',
                     'true', 'false', 'null', 'undefined', 'void', 'delete'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . preg_quote($keyword, '/') . ')\b/',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\'`])(.*?)\1/s',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(\/\/.*$|\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 函数调用
        $line = preg_replace(
            '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            '<span style="color:' . $theme['function'] . '">$1</span>(',
            $line
        );
        
        return $line;
    }
    
    /**
     * Python 高亮
     */
    private function highlightPython(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['def', 'class', 'if', 'elif', 'else', 'for', 'while', 'try',
                     'except', 'finally', 'with', 'as', 'import', 'from', 'return',
                     'yield', 'raise', 'pass', 'break', 'continue', 'lambda',
                     'True', 'False', 'None', 'and', 'or', 'not', 'in', 'is',
                     'async', 'await', 'global', 'nonlocal', 'assert', 'del'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . preg_quote($keyword, '/') . ')\b/',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\']{3}.*?["\']{3}|["\'](?:[^"\'\\]|\\.)*["\'])/s',
            '<span style="color:' . $theme['string'] . '">$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(#.*$)/',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 函数调用
        $line = preg_replace(
            '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            '<span style="color:' . $theme['function'] . '">$1</span>(',
            $line
        );
        
        // 装饰器
        $line = preg_replace(
            '/(@[a-zA-Z_][a-zA-Z0-9_]*)/',
            '<span style="color:' . $theme['function'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * Java 高亮
     */
    private function highlightJava(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['public', 'private', 'protected', 'class', 'interface', 'enum',
                     'extends', 'implements', 'static', 'final', 'abstract', 'native',
                     'synchronized', 'volatile', 'transient', 'return', 'if', 'else',
                     'switch', 'case', 'break', 'continue', 'for', 'while', 'do',
                     'try', 'catch', 'finally', 'throw', 'throws', 'new', 'this', 'super',
                     'import', 'package', 'void', 'int', 'long', 'short', 'byte',
                     'float', 'double', 'boolean', 'char', 'true', 'false', 'null'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . preg_quote($keyword, '/') . ')\b/',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\'])(.*?)\1/',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(\/\/.*$|\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*[fFdDlL]?)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 注解
        $line = preg_replace(
            '/(@[a-zA-Z_][a-zA-Z0-9_]*)/',
            '<span style="color:' . $theme['function'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * Go 高亮
     */
    private function highlightGo(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['package', 'import', 'func', 'return', 'var', 'const', 'type',
                     'struct', 'interface', 'map', 'chan', 'if', 'else', 'switch',
                     'case', 'default', 'for', 'range', 'go', 'select', 'defer',
                     'break', 'continue', 'goto', 'fallthrough', 'true', 'false', 'nil'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . preg_quote($keyword, '/') . ')\b/',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["`])(.*?)\1/s',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(\/\/.*$|\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 函数调用
        $line = preg_replace(
            '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/',
            '<span style="color:' . $theme['function'] . '">$1</span>(',
            $line
        );
        
        return $line;
    }
    
    /**
     * Rust 高亮
     */
    private function highlightRust(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['fn', 'let', 'mut', 'const', 'static', 'pub', 'mod', 'use',
                     'struct', 'enum', 'trait', 'impl', 'type', 'where', 'for',
                     'loop', 'while', 'if', 'else', 'match', 'return', 'break',
                     'continue', 'move', 'ref', 'self', 'Self', 'super', 'crate',
                     'true', 'false', 'as', 'in', 'unsafe', 'extern', 'async', 'await'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . preg_quote($keyword, '/') . ')\b/',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\'])(.*?)\1/',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(\/\/.*$|\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 宏调用
        $line = preg_replace(
            '/\b([a-zA-Z_][a-zA-Z0-9_]*!)/',
            '<span style="color:' . $theme['function'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * HTML 高亮
     */
    private function highlightHtml(string $line, array $theme): string
    {
        // 标签
        $line = preg_replace(
            '/(&lt;\/?)([\w-]+)/',
            '$1<span style="color:' . $theme['keyword'] . '">$2</span>',
            $line
        );
        
        // 属性
        $line = preg_replace(
            '/\s([\w-]+)=/',
            ' <span style="color:' . $theme['variable'] . '">$1</span>=',
            $line
        );
        
        // 属性值
        $line = preg_replace(
            '/=(&quot;.*?&quot;)/',
            '=<span style="color:' . $theme['string'] . '">$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(&lt;!--.*?--&gt;)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * CSS 高亮
     */
    private function highlightCss(string $line, array $theme): string
    {
        // 选择器
        $line = preg_replace(
            '/^([\w\-\.\#\[\]\:\s,]+)\{/',
            '<span style="color:' . $theme['keyword'] . '">$1</span>{',
            $line
        );
        
        // 属性
        $line = preg_replace(
            '/([\w-]+)\s*:/',
            '<span style="color:' . $theme['variable'] . '">$1</span>:',
            $line
        );
        
        // 值
        $line = preg_replace(
            '/:\s*([^;]+);/',
            ': <span style="color:' . $theme['string'] . '">$1</span>;',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*(px|em|rem|%|vh|vw|s|ms)?)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * SQL 高亮
     */
    private function highlightSql(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER',
                     'ON', 'AND', 'OR', 'NOT', 'IN', 'LIKE', 'BETWEEN', 'IS', 'NULL',
                     'ORDER', 'BY', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'ASC', 'DESC',
                     'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE', 'CREATE',
                     'TABLE', 'INDEX', 'DROP', 'ALTER', 'ADD', 'COLUMN', 'PRIMARY', 'KEY',
                     'FOREIGN', 'REFERENCES', 'UNIQUE', 'DEFAULT', 'AUTO_INCREMENT'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . $keyword . ')\b/i',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\'])(.*?)\1/',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(--.*$|#.*$|\/\*.*?\*\/)/s',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/\b(\d+\.?\d*)\b/',
            '<span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 函数
        $line = preg_replace(
            '/\b([A-Z_][A-Z0-9_]*)\s*\(/i',
            '<span style="color:' . $theme['function'] . '">$1</span>(',
            $line
        );
        
        return $line;
    }
    
    /**
     * JSON 高亮
     */
    private function highlightJson(string $line, array $theme): string
    {
        // 键
        $line = preg_replace(
            '/(&quot;[\w-]+&quot;)\s*:/',
            '<span style="color:' . $theme['variable'] . '">$1</span>:',
            $line
        );
        
        // 字符串值
        $line = preg_replace(
            '/:\s*(&quot;.*?&quot;)/',
            ': <span style="color:' . $theme['string'] . '">$1</span>',
            $line
        );
        
        // 数字
        $line = preg_replace(
            '/:\s*(\d+\.?\d*)/',
            ': <span style="color:' . $theme['number'] . '">$1</span>',
            $line
        );
        
        // 布尔值
        $line = preg_replace(
            '/:\s*(true|false|null)/i',
            ': <span style="color:' . $theme['keyword'] . '">$1</span>',
            $line
        );
        
        return $line;
    }
    
    /**
     * Bash 高亮
     */
    private function highlightBash(string $line, array $theme): string
    {
        // 关键字
        $keywords = ['if', 'then', 'else', 'elif', 'fi', 'for', 'do', 'done', 'while',
                     'until', 'case', 'esac', 'function', 'return', 'exit', 'break',
                     'continue', 'local', 'export', 'source', 'alias', 'unset'];
        
        foreach ($keywords as $keyword) {
            $line = preg_replace(
                '/\b(' . $keyword . ')\b/',
                '<span style="color:' . $theme['keyword'] . '">$1</span>',
                $line
            );
        }
        
        // 字符串
        $line = preg_replace(
            '/(["\'])(.*?)\1/',
            '<span style="color:' . $theme['string'] . '">$1$2$1</span>',
            $line
        );
        
        // 注释
        $line = preg_replace(
            '/(#.*$)/',
            '<span style="color:' . $theme['comment'] . '">$1</span>',
            $line
        );
        
        // 变量
        $line = preg_replace(
            '/(\$[a-zA-Z_][a-zA-Z0-9_]*|\$\{[^}]+\})/',
            '<span style="color:' . $theme['variable'] . '">$1</span>',
            $line
        );
        
        // 命令
        $commands = ['echo', 'printf', 'read', 'cd', 'pwd', 'ls', 'mkdir', 'rm', 'cp',
                     'mv', 'cat', 'grep', 'sed', 'awk', 'find', 'chmod', 'chown',
                     'ps', 'kill', 'top', 'htop', 'curl', 'wget', 'git', 'docker'];
        
        foreach ($commands as $cmd) {
            $line = preg_replace(
                '/\b(' . $cmd . ')\b/',
                '<span style="color:' . $theme['function'] . '">$1</span>',
                $line
            );
        }
        
        return $line;
    }
    
    /**
     * Diff 高亮
     */
    private function highlightDiff(string $line, array $theme): string
    {
        // 添加的行
        if (strpos($line, '+') === 0) {
            return '<span style="background-color:rgba(40,167,69,0.15);color:#22863a">' . $line . '</span>';
        }
        
        // 删除的行
        if (strpos($line, '-') === 0) {
            return '<span style="background-color:rgba(220,53,69,0.15);color:#cb2431">' . $line . '</span>';
        }
        
        // 文件头
        if (strpos($line, '---') === 0 || strpos($line, '+++') === 0) {
            return '<span style="color:' . $theme['keyword'] . '">' . $line . '</span>';
        }
        
        // 位置信息
        if (strpos($line, '@@') === 0) {
            return '<span style="color:' . $theme['function'] . '">' . $line . '</span>';
        }
        
        return $line;
    }
    
    /**
     * 生成带行号的代码块
     */
    public function highlightWithLineNumbers(string $code, string $language, string $theme = 'light'): string
    {
        $highlighted = $this->highlight($code, $language, $theme);
        $lines = explode("\n", $highlighted);
        $themeConfig = self::THEMES[$theme] ?? self::THEMES['light'];
        
        $html = '<div class="code-block" style="background:' . $themeConfig['background'] . ';color:' . $themeConfig['text'] . '">';
        $html .= '<table class="code-table" style="width:100%;border-collapse:collapse">';
        
        foreach ($lines as $i => $line) {
            $lineNum = $i + 1;
            $html .= '<tr class="code-line" data-line="' . $lineNum . '">';
            $html .= '<td class="line-number" style="width:50px;padding:0 12px;text-align:right;color:' . $themeConfig['line_number'] . ';user-select:none;border-right:1px solid ' . $themeConfig['line_number'] . '">' . $lineNum . '</td>';
            $html .= '<td class="line-content" style="padding:0 12px;white-space:pre">' . $line . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table></div>';
        
        return $html;
    }
    
    /**
     * 语法错误检测
     */
    public function detectSyntaxErrors(string $code, string $language): array
    {
        $errors = [];
        
        switch ($language) {
            case 'php':
                $errors = $this->detectPhpErrors($code);
                break;
            case 'javascript':
            case 'typescript':
                $errors = $this->detectJsErrors($code);
                break;
            case 'python':
                $errors = $this->detectPythonErrors($code);
                break;
            case 'json':
                $errors = $this->detectJsonErrors($code);
                break;
        }
        
        return $errors;
    }
    
    /**
     * PHP 语法错误检测
     */
    private function detectPhpErrors(string $code): array
    {
        $errors = [];
        
        // 检查括号匹配
        $openBraces = substr_count($code, '{');
        $closeBraces = substr_count($code, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = [
                'line' => 0,
                'message' => "括号不匹配：{ 数量 {$openBraces}，} 数量 {$closeBraces}",
                'severity' => 'error',
            ];
        }
        
        // 检查分号
        $lines = explode("\n", $code);
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            
            // 检查是否需要分号
            if (preg_match('/^\$|^\b(return|echo|print|break|continue)\b/', $trimmed)) {
                if (!preg_match('/;$/', $trimmed) && !preg_match('/\{\s*$/', $trimmed)) {
                    $errors[] = [
                        'line' => $i + 1,
                        'message' => '可能缺少分号',
                        'severity' => 'warning',
                    ];
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * JavaScript 语法错误检测
     */
    private function detectJsErrors(string $code): array
    {
        $errors = [];
        
        // 检查括号匹配
        $openBraces = substr_count($code, '{');
        $closeBraces = substr_count($code, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = [
                'line' => 0,
                'message' => "括号不匹配",
                'severity' => 'error',
            ];
        }
        
        // 检查圆括号匹配
        $openParens = substr_count($code, '(');
        $closeParens = substr_count($code, ')');
        if ($openParens !== $closeParens) {
            $errors[] = [
                'line' => 0,
                'message' => "圆括号不匹配",
                'severity' => 'error',
            ];
        }
        
        return $errors;
    }
    
    /**
     * Python 语法错误检测
     */
    private function detectPythonErrors(string $code): array
    {
        $errors = [];
        $lines = explode("\n", $code);
        
        // 检查缩进一致性
        $indentChar = null;
        foreach ($lines as $i => $line) {
            if (empty(trim($line))) continue;
            
            preg_match('/^(\s+)/', $line, $matches);
            $indent = $matches[1] ?? '';
            
            if (!empty($indent)) {
                $firstChar = $indent[0];
                if ($indentChar === null) {
                    $indentChar = $firstChar;
                } elseif ($indentChar !== $firstChar) {
                    $errors[] = [
                        'line' => $i + 1,
                        'message' => '缩进不一致：混用空格和制表符',
                        'severity' => 'warning',
                    ];
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * JSON 语法错误检测
     */
    private function detectJsonErrors(string $code): array
    {
        $errors = [];
        
        json_decode($code);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = [
                'line' => 0,
                'message' => 'JSON 格式错误：' . json_last_error_msg(),
                'severity' => 'error',
            ];
        }
        
        return $errors;
    }
}
