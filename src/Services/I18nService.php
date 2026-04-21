/**
 * CodeVault - 国际化服务
 * 
 * 提供多语言支持、翻译管理、时区处理等功能
 */

namespace CodeVault\Services;

use PDO;

class I18nService
{
    private PDO $pdo;
    private string $defaultLocale = 'zh-CN';
    private string $currentLocale;
    private array $translations = [];
    private array $supportedLocales = [
        'zh-CN' => '简体中文',
        'zh-TW' => '繁體中文',
        'en-US' => 'English (US)',
        'en-GB' => 'English (UK)',
        'ja-JP' => '日本語',
        'ko-KR' => '한국어',
        'es-ES' => 'Español',
        'fr-FR' => 'Français',
        'de-DE' => 'Deutsch',
        'ru-RU' => 'Русский',
        'ar-SA' => 'العربية',
    ];
    
    public function __construct(PDO $pdo, string $locale = null)
    {
        $this->pdo = $pdo;
        $this->currentLocale = $locale ?? $this->detectLocale();
    }
    
    // ==================== 语言检测 ====================
    
    /**
     * 检测用户语言
     */
    private function detectLocale(): string
    {
        // 1. 从 URL 参数检测
        if (isset($_GET['lang']) && $this->isSupported($_GET['lang'])) {
            return $_GET['lang'];
        }
        
        // 2. 从 Session 检测
        if (isset($_SESSION['locale']) && $this->isSupported($_SESSION['locale'])) {
            return $_SESSION['locale'];
        }
        
        // 3. 从 Cookie 检测
        if (isset($_COOKIE['locale']) && $this->isSupported($_COOKIE['locale'])) {
            return $_COOKIE['locale'];
        }
        
        // 4. 从 Accept-Language 头检测
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $locales = $this->parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($locales as $locale) {
                if ($this->isSupported($locale)) {
                    return $locale;
                }
            }
        }
        
        return $this->defaultLocale;
    }
    
    /**
     * 解析 Accept-Language 头
     */
    private function parseAcceptLanguage(string $header): array
    {
        $locales = [];
        $parts = explode(',', $header);
        
        foreach ($parts as $part) {
            $pair = explode(';', trim($part));
            $locale = trim($pair[0]);
            $quality = 1.0;
            
            if (count($pair) > 1 && strpos($pair[1], 'q=') === 0) {
                $quality = (float)substr($pair[1], 2);
            }
            
            // 转换格式 (en -> en-US)
            if (strpos($locale, '-') === false) {
                $locale = $locale . '-' . strtoupper($locale);
            }
            
            $locales[$locale] = $quality;
        }
        
        arsort($locales);
        return array_keys($locales);
    }
    
    // ==================== 语言管理 ====================
    
    /**
     * 设置当前语言
     */
    public function setLocale(string $locale): bool
    {
        if (!$this->isSupported($locale)) {
            return false;
        }
        
        $this->currentLocale = $locale;
        $_SESSION['locale'] = $locale;
        setcookie('locale', $locale, time() + 86400 * 365, '/');
        
        return true;
    }
    
    /**
     * 获取当前语言
     */
    public function getLocale(): string
    {
        return $this->currentLocale;
    }
    
    /**
     * 获取默认语言
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }
    
    /**
     * 获取支持的语言列表
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }
    
    /**
     * 检查语言是否支持
     */
    public function isSupported(string $locale): bool
    {
        return isset($this->supportedLocales[$locale]);
    }
    
    // ==================== 翻译管理 ====================
    
    /**
     * 加载翻译
     */
    public function loadTranslations(string $domain = 'messages'): void
    {
        $cacheKey = "translations:{$this->currentLocale}:{$domain}";
        
        // 尝试从缓存加载
        if (isset($this->translations[$cacheKey])) {
            return;
        }
        
        // 从数据库加载
        $stmt = $this->pdo->prepare(
            "SELECT `key`, value FROM translations 
             WHERE locale = ? AND domain = ?"
        );
        $stmt->execute([$this->currentLocale, $domain]);
        
        $translations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $translations[$row['key']] = $row['value'];
        }
        
        $this->translations[$cacheKey] = $translations;
    }
    
    /**
     * 翻译文本
     */
    public function translate(string $key, array $params = [], string $domain = 'messages'): string
    {
        $this->loadTranslations($domain);
        
        $cacheKey = "translations:{$this->currentLocale}:{$domain}";
        $text = $this->translations[$cacheKey][$key] ?? $key;
        
        // 替换参数
        if (!empty($params)) {
            foreach ($params as $name => $value) {
                $text = str_replace(':' . $name, $value, $text);
            }
        }
        
        return $text;
    }
    
    /**
     * 翻译复数形式
     */
    public function translatePlural(string $key, int $count, array $params = [], string $domain = 'messages'): string
    {
        $pluralKey = $key . '.' . $this->getPluralForm($count);
        $text = $this->translate($pluralKey, array_merge($params, ['count' => $count]), $domain);
        
        // 如果没有找到复数形式，尝试基本形式
        if ($text === $pluralKey) {
            $text = $this->translate($key, array_merge($params, ['count' => $count]), $domain);
        }
        
        return $text;
    }
    
    /**
     * 获取复数形式
     */
    private function getPluralForm(int $count): string
    {
        $locale = substr($this->currentLocale, 0, 2);
        
        switch ($locale) {
            case 'zh':
            case 'ja':
            case 'ko':
                // 这些语言没有复数形式
                return 'other';
            
            case 'en':
            case 'de':
            case 'fr':
            case 'es':
                return $count === 1 ? 'one' : 'other';
            
            case 'ru':
                $mod10 = $count % 10;
                $mod100 = $count % 100;
                if ($mod10 === 1 && $mod100 !== 11) return 'one';
                if (in_array($mod10, [2, 3, 4]) && !in_array($mod100, [12, 13, 14])) return 'few';
                return 'many';
            
            case 'ar':
                if ($count === 0) return 'zero';
                if ($count === 1) return 'one';
                if ($count === 2) return 'two';
                if ($count % 100 >= 3 && $count % 100 <= 10) return 'few';
                if ($count % 100 >= 11 && $count % 100 <= 99) return 'many';
                return 'other';
            
            default:
                return $count === 1 ? 'one' : 'other';
        }
    }
    
    // ==================== 时区处理 ====================
    
    /**
     * 获取用户时区
     */
    public function getUserTimezone(): string
    {
        // 从用户设置获取
        if (isset($_SESSION['user']['timezone'])) {
            return $_SESSION['user']['timezone'];
        }
        
        // 从 Cookie 获取
        if (isset($_COOKIE['timezone'])) {
            return $_COOKIE['timezone'];
        }
        
        // 默认时区
        return 'Asia/Shanghai';
    }
    
    /**
     * 设置用户时区
     */
    public function setUserTimezone(string $timezone): bool
    {
        if (!in_array($timezone, timezone_identifiers_list())) {
            return false;
        }
        
        $_SESSION['user']['timezone'] = $timezone;
        setcookie('timezone', $timezone, time() + 86400 * 365, '/');
        
        return true;
    }
    
    /**
     * 转换时间到用户时区
     */
    public function convertTimezone(string $datetime, string $fromTz = 'UTC'): string
    {
        $toTz = $this->getUserTimezone();
        
        try {
            $date = new \DateTime($datetime, new \DateTimeZone($fromTz));
            $date->setTimezone(new \DateTimeZone($toTz));
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $datetime;
        }
    }
    
    /**
     * 格式化日期时间
     */
    public function formatDateTime(string $datetime, string $format = null): string
    {
        $converted = $this->convertTimezone($datetime);
        
        if ($format === null) {
            $format = $this->getDateTimeFormat();
        }
        
        return date($format, strtotime($converted));
    }
    
    /**
     * 获取日期时间格式
     */
    public function getDateTimeFormat(): string
    {
        $formats = [
            'zh-CN' => 'Y年m月d日 H:i',
            'zh-TW' => 'Y年m月d日 H:i',
            'en-US' => 'm/d/Y h:i A',
            'en-GB' => 'd/m/Y H:i',
            'ja-JP' => 'Y年m月d日 H:i',
            'ko-KR' => 'Y. m. d. H:i',
        ];
        
        return $formats[$this->currentLocale] ?? 'Y-m-d H:i';
    }
    
    /**
     * 获取日期格式
     */
    public function getDateFormat(): string
    {
        $formats = [
            'zh-CN' => 'Y年m月d日',
            'zh-TW' => 'Y年m月d日',
            'en-US' => 'm/d/Y',
            'en-GB' => 'd/m/Y',
            'ja-JP' => 'Y年m月d日',
            'ko-KR' => 'Y. m. d.',
        ];
        
        return $formats[$this->currentLocale] ?? 'Y-m-d';
    }
    
    // ==================== 数字和货币格式化 ====================
    
    /**
     * 格式化数字
     */
    public function formatNumber(float $number, int $decimals = 0): string
    {
        $locale = $this->currentLocale;
        
        // 获取格式化信息
        $info = $this->getLocaleInfo($locale);
        
        return number_format(
            $number,
            $decimals,
            $info['decimal_point'],
            $info['thousands_sep']
        );
    }
    
    /**
     * 格式化货币
     */
    public function formatCurrency(float $amount, string $currency = 'CNY'): string
    {
        $symbols = [
            'CNY' => '¥',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'KRW' => '₩',
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        
        // 中文环境：¥100
        // 英文环境：$100.00
        if (strpos($this->currentLocale, 'zh') === 0) {
            return $symbol . $this->formatNumber($amount, 2);
        } else {
            return $symbol . $this->formatNumber($amount, 2);
        }
    }
    
    /**
     * 获取语言环境信息
     */
    private function getLocaleInfo(string $locale): array
    {
        $info = [
            'zh-CN' => ['decimal_point' => '.', 'thousands_sep' => ','],
            'zh-TW' => ['decimal_point' => '.', 'thousands_sep' => ','],
            'en-US' => ['decimal_point' => '.', 'thousands_sep' => ','],
            'en-GB' => ['decimal_point' => '.', 'thousands_sep' => ','],
            'de-DE' => ['decimal_point' => ',', 'thousands_sep' => '.'],
            'fr-FR' => ['decimal_point' => ',', 'thousands_sep' => ' '],
        ];
        
        return $info[$locale] ?? ['decimal_point' => '.', 'thousands_sep' => ','];
    }
    
    // ==================== RTL 支持 ====================
    
    /**
     * 检查是否为 RTL 语言
     */
    public function isRtl(): bool
    {
        $rtlLocales = ['ar-SA', 'he-IL', 'fa-IR', 'ur-PK'];
        return in_array($this->currentLocale, $rtlLocales);
    }
    
    /**
     * 获取文本方向
     */
    public function getDirection(): string
    {
        return $this->isRtl() ? 'rtl' : 'ltr';
    }
    
    // ==================== 翻译管理 API ====================
    
    /**
     * 添加翻译
     */
    public function addTranslation(string $locale, string $key, string $value, string $domain = 'messages'): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO translations (locale, domain, `key`, value, created_at) 
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()"
        );
        
        return $stmt->execute([$locale, $domain, $key, $value]);
    }
    
    /**
     * 删除翻译
     */
    public function deleteTranslation(string $locale, string $key, string $domain = 'messages'): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM translations WHERE locale = ? AND domain = ? AND `key` = ?"
        );
        
        return $stmt->execute([$locale, $domain, $key]);
    }
    
    /**
     * 导出翻译
     */
    public function exportTranslations(string $locale, string $domain = 'messages'): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT `key`, value FROM translations WHERE locale = ? AND domain = ?"
        );
        $stmt->execute([$locale, $domain]);
        
        $translations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $translations[$row['key']] = $row['value'];
        }
        
        return $translations;
    }
    
    /**
     * 导入翻译
     */
    public function importTranslations(string $locale, array $translations, string $domain = 'messages'): int
    {
        $count = 0;
        
        foreach ($translations as $key => $value) {
            if ($this->addTranslation($locale, $key, $value, $domain)) {
                $count++;
            }
        }
        
        return $count;
    }
}
