 <?php

/**
 * ViewFish - A PHP templating engine
 *
 * @see https://code.adamscheinberg.com/ViewFish
 * @see https://github.com/sethadam1/ViewFish
 */

declare(strict_types=1);

namespace ViewFish;

class viewfish
{
    public $tmpl_path = '';
    public $mc = false;
    public $ttl = 0;

    private bool $caching = false;
    private bool $cache_compiled = false;
    private bool $replace_empty = false;
    private array $discovered = [];
    private array $default_fx = [];
    private array $all_supported = [];
    private array $allow_fx = [];
    private int $max_depth = 10;

    /** @var list<string> Functions that are never allowed regardless of extend() calls */
    private const BLOCKED_FUNCTIONS = [
        'exec', 'system', 'passthru', 'shell_exec', 'popen',
        'proc_open', 'pcntl_exec', 'eval', 'assert',
        'create_function', 'call_user_func', 'call_user_func_array',
        'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
        'unlink', 'rmdir', 'mkdir', 'rename', 'copy',
        'move_uploaded_file', 'curl_exec', 'curl_multi_exec',
        'parse_str', 'extract', 'putenv', 'ini_set', 'dl',
        'mail', 'header', 'preg_replace',
    ];

    public function __construct(array $options = [])
    {
        $this->mc = false;
        $this->ttl = 0;
        $this->caching = false;
        $this->cache_compiled = false;
        $this->replace_empty = false;
        $this->discovered = [];

        if (!empty($options['replace_empty'])) {
            $this->replace_empty = true;
        }
        if (!empty($options['cache_compiled'])) {
            $this->cache_compiled = true;
        }
        if (isset($options['memcached']) && is_object($options['memcached'])) {
            $this->enable_cache($options['memcached']);
        }
        if (!empty($options['ttl'])) {
            $this->ttl = (int) $options['ttl'];
        }

        $this->default_fx = [
            'ucfirst', 'ucwords', 'strtoupper', 'strtolower',
            'htmlspecialchars', 'trim', 'nl2br', 'number_format',
            'stripslashes', 'strip_tags', 'md5', 'intval',
        ];

        $this->all_supported = [
            'addcslashes', 'addslashes', 'bin2hex', 'chop', 'chr',
            'chunk_split', 'convert_uudecode', 'convert_uuencode',
            'count_chars', 'crc32', 'crypt', 'hex2bin',
            'html_entity_decode', 'htmlentities', 'htmlspecialchars_decode',
            'lcfirst', 'ltrim', 'metaphone', 'ord', 'quotemeta', 'rtrim',
            'sha1', 'soundex', 'str_rot13', 'str_word_count',
            'stripcslashes', 'strlen', 'strrev', 'strtok',
            'floatval', 'ceil', 'floor',
        ];

        $this->allow_fx = $this->default_fx;
    }

    /**
     * Set the path to the template directory.
     *
     * @param string $tmpl_path
     * @return void
     */
    public function set_template_path(string $tmpl_path): void
    {
        if ($tmpl_path !== '') {
            $this->tmpl_path = rtrim($tmpl_path, '/') . '/';
        }
    }

    /**
     * Enable memcached/redis caching.
     *
     * @param object $memcached  A cache object with get/set/delete methods
     * @param int    $ttl        Cache TTL in seconds
     * @return void
     */
    public function enable_cache(object $memcached, int $ttl = 300): void
    {
        if (is_object($memcached)) {
            $this->mc = $memcached;
            $this->ttl = $ttl;
            $this->caching = true;
        }
    }

    /**
     * Toggle compiled template caching.
     *
     * @param bool $cache_status
     * @return void
     */
    public function cache_compiled(bool $cache_status = false): void
    {
        $this->cache_compiled = ($cache_status === true);
    }

    /**
     * Add a template to cache.
     *
     * @param string $tmpl  Template name
     * @param string $text  Template content
     * @param int    $ttl   TTL in seconds
     * @return void
     */
    public function cache_create(string $tmpl, string $text, int $ttl = 86400): void
    {
        if ($this->caching) {
            $this->mc->add(md5("." . $tmpl), $text, $ttl);
        }
    }

    /**
     * Update a cached template.
     *
     * @param string $tmpl  Template name
     * @param string $text  Template content
     * @param int    $ttl   TTL in seconds
     * @return void
     */
    public function cache_update(string $tmpl, string $text, int $ttl = 86400): void
    {
        if ($this->caching) {
            $this->mc->set(md5("." . $tmpl), $text, $ttl);
        }
    }

    /**
     * Delete a template from cache.
     *
     * @param string $tmpl  Template name
     * @return void
     */
    public function cache_destroy(string $tmpl): void
    {
        if ($this->caching) {
            $this->mc->delete(md5("." . $tmpl));
        }
    }

    /**
     * Backwards-compatible typo alias.
     */
    public function cache_detroy(string $tmpl): void
    {
        $this->cache_destroy($tmpl);
    }

    /**
     * Read a template from cache.
     *
     * @param string $tmpl  Template name
     * @return string|false
     */
    public function cache_read(string $tmpl)
    {
        if ($this->caching) {
            $cached = $this->mc->get(md5("." . $tmpl));
            if ($cached) {
                return $cached;
            }
        }
        return false;
    }

    /**
     * Add functions to the allowed list.
     *
     * @param array $functions
     * @return void
     */
    public function extend(array $functions = []): void
    {
        foreach ($functions as $fx) {
            $fx = strtolower(trim($fx));
            if ($fx === '') {
                continue;
            }
            if (in_array($fx, self::BLOCKED_FUNCTIONS, true)) {
                continue; // silently reject dangerous functions
            }
            if (!in_array($fx, $this->allow_fx, true)) {
                $this->allow_fx[] = $fx;
            }
        }
    }

    /**
     * Remove a template from cache by name.
     *
     * @param string $tmpl
     * @return void
     */
    public function uncache(string $tmpl): void
    {
        if ($this->caching) {
            $this->mc->delete(md5($tmpl));
        }
    }

    /**
     * Reset allowed functions to defaults.
     *
     * @return void
     */
    public function unextend(): void
    {
        $this->allow_fx = $this->default_fx;
    }

    /**
     * Toggle replacement of unmatched placeholders.
     *
     * @param bool $toggle
     * @return void
     */
    public function replace_empty(bool $toggle = true): void
    {
        $this->replace_empty = (bool) $toggle;
    }

    /**
     * Load all known safe functions into the allowed list.
     *
     * @return void
     */
    public function load_supported_functions(): void
    {
        $this->allow_fx = array_unique(
            array_merge($this->all_supported, $this->default_fx)
        );
    }

    /**
     * Load a template file. Returns content string or false on failure.
     *
     * @param string $tmpl
     * @return string|false
     */
    public function load_template(string $tmpl)
    {
        if ($tmpl === '') {
            return false;
        }

        // Check cache
        if ($this->caching) {
            $text = $this->mc->get(md5($tmpl));
            if ($text) {
                return $text;
            }
        }

        $filePath = $this->resolve_path($tmpl);
        if ($filePath === false) {
            return false;
        }

        $text = file_get_contents($filePath);
        if ($text === false) {
            return false;
        }

        if ($this->caching) {
            $this->mc->set(md5($tmpl), $text, $this->ttl);
        }

        return $text;
    }

    /**
     * Render a template string with data.
     *
     * @param string $template  Template content
     * @param array  $args      Associative array of variables
     * @return string
     */
    public function render(string $template, array $args = []): string
    {
        $this->discovered = [];
        return $this->do_render($template, $args, 0);
    }

    /**
     * Get variables discovered during the last render pass.
     *
     * @return array
     */
    public function get_discovered(): array
    {
        return $this->discovered;
    }

    // ─── Private Implementation ──────────────────────────────────────────

    /**
     * Internal render with depth tracking to prevent infinite recursion.
     */
    private function do_render(string $template, array $args, int $depth): string
    {
        if ($depth > $this->max_depth) {
            return $template; // prevent infinite nesting
        }

        // ── Embedded sub-templates ──
        $template = $this->process_includes($template, $args, $depth);

        // ── Loops ──
        $template = $this->process_loops($template, $args, $depth);

        // ── Variable replacement with pipe functions ──
        if (is_array($args)) {
            $template = $this->process_piped_vars($template, $args);

            // ── Date placeholders ──
            $template = preg_replace_callback(
                '/\{\{date\|([A-Za-z0-9\-, |:]+)\}\}/U',
                function (array $m) {
                    $this->discovered[$m[0]] = date($m[2] ?? $m[1]);
                    return date($m[1]);
                },
                $template
            );

            // ── Strip comments: {* *} and /* */ ──
            $template = preg_replace('/\{\*.*?\*\}/s', '', $template);
            $template = preg_replace('/\/\*.*?\*\//s', '', $template);

            // ── Dynamic placeholders ──
            $repl = ['[[uniqid]]', '[[year]]', '[[timestamp]]', '[[datetime]]', '[[utcdatetime]]'];
            $with = [uniqid(), date("Y"), date("U"), date("Y-m-d G:i:s"), gmdate("Y-m-d G:i:s")];
            $template = str_ireplace($repl, $with, $template);

            // ── Simple variable replacement ──
            foreach ($args as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $sv = (string) $v;
                    $template = str_ireplace('{{' . $k . '}}', $sv, $template);
                    $template = str_ireplace('{{$' . $k . '}}', $sv, $template);
                    $template = str_ireplace('{{' . $k . '!}}', $sv, $template);
                    $template = str_ireplace('{{$' . $k . '!}}', $sv, $template);
                    if ($k !== '') {
                        $this->discovered[$k] = $sv;
                    }
                }
            }
        }

        // ── isset conditionals ──
        $template = $this->process_isset($template, $args);

        // ── Strip silent placeholders (!) that weren't matched ──
        $template = preg_replace('/\{\{[A-Za-z0-9\-_]+!\}\}/', '', $template);

        // ── Optionally strip all remaining unmatched placeholders ──
        if ($this->replace_empty) {
            $template = preg_replace('/\{\{[A-Za-z0-9\-_]+\}\}/', '', $template);
        }

        return $template;
    }

    /**
     * Process {{@template file=name}} includes.
     */
    private function process_includes(string $template, array $args, int $depth): string
    {
        if (preg_match_all(
            '/\{\{@template\s+file=([A-Za-z0-9\-_.,]+)\}\}/U',
            $template,
            $matches
        )) {
            foreach ($matches[1] as $key => $tm) {
                $sub = $this->load_template($tm);
                if ($sub === false) {
                    $template = str_replace($matches[0][$key], '', $template);
                    continue;
                }

                $sub_data = $args;
                if (isset($args[$tm]) && is_array($args[$tm])) {
                    foreach ($args[$tm] as $k => $v) {
                        $sub_data[$k] = $v;
                    }
                }

                $rendered = $this->do_render($sub, $sub_data, $depth + 1);
                $template = str_replace($matches[0][$key], $rendered, $template);
            }
        }

        return $template;
    }

    /**
     * Process {{@loop data=key}}...{{/loop}} blocks.
     */
    private function process_loops(string $template, array $args, int $depth): string
    {
        if (!preg_match_all(
            '/((\{\{@loop data=)([A-Za-z0-9_= ]+)(\}\}))(.+?)((\{\{)\/loop(\}\}))/si',
            $template,
            $matches
        )) {
            return $template;
        }

        foreach ($matches[0] as $key => $pattern) {
            if (trim($pattern) === '' || $matches[3][$key] === '') {
                continue;
            }

            $start = $matches[1][$key];
            $end = $matches[6][$key];
            $template_text = str_replace($start, '', $pattern);
            $template_text = str_replace($end, '', $template_text);

            $data_key = trim($matches[3][$key]);
            $templ_data = $args[$data_key] ?? null;

            if (!is_array($templ_data)) {
                $template = str_replace($pattern, '', $template);
                continue;
            }

            $repl = '';
            foreach ($templ_data as $subdata) {
                if (!is_array($subdata)) {
                    $subdata = ['value' => $subdata];
                }
                $repl .= $this->do_render(trim($template_text), $subdata, $depth + 1);
            }
            $template = str_replace($pattern, $repl, $template);
        }

        return $template;
    }

    /**
     * Process variables with pipe functions: {{var|fn1|fn2}}
     */
    private function process_piped_vars(string $template, array $args): string
    {
        foreach ($args as $k => $v) {
            if (!is_scalar($v) && $v !== null) {
                continue;
            }
            $sv = (string) $v;

            // Match {{key|functions}} or {{$key|functions}} pattern using the specific key
            $escaped_k = preg_quote($k, '/');
            if (!preg_match_all(
                '/\{\{\$?' . $escaped_k . '\|([A-Za-z0-9\-_:|]+)(!?)\}\}/U',
                $template,
                $matches
            )) {
                continue;
            }

            foreach ($matches[0] as $idx => $pattern) {
                if ($pattern === '') {
                    continue;
                }

                $string = $sv;
                $functions = explode('|', $matches[1][$idx]);

                foreach ($functions as $fx) {
                    $silent = false;
                    if (substr($fx, -1) === '!') {
                        $silent = true;
                        $fx = rtrim($fx, '!');
                    }
                    $string = $this->apply_function($fx, $string);
                }

                $template = str_replace($pattern, $string, $template);

                if ($pattern !== '') {
                    $this->discovered[$pattern] = $sv;
                }
            }
        }

        return $template;
    }

    /**
     * Process {{isset $var}}...{{/isset}} blocks.
     */
    private function process_isset(string $template, array $args = []): string
    {
        if (!preg_match_all(
            '/\{\{isset \$([A-Za-z0-9_]+)\}\}(.+?)\{\{\/isset\}\}/si',
            $template,
            $matches
        )) {
            return $template;
        }

        foreach ($matches[0] as $k => $v) {
            $var_name = $matches[1][$k];
            if (isset($args[$var_name]) && $args[$var_name] !== '' && $args[$var_name] !== null) {
                $template = str_replace($v, $matches[2][$k], $template);
            } else {
                $template = str_replace($v, '', $template);
            }
        }

        return $template;
    }

    /**
     * Apply a single function (with or without parameters) to a string value.
     */
    private function apply_function(string $fx, string $string): string
    {
        // Check for parameterized functions (fn:arg1:arg2)
        if (strpos($fx, ':') !== false) {
            return $this->apply_parameterized_function($fx, $string);
        }

        // Built-in aliases
        switch ($fx) {
            case 'upper':
                return strtoupper($string);
            case 'lower':
                return strtolower($string);
            case 'sup':
                return '<sup>' . $string . '</sup>';
            case 'sub':
                return '<sub>' . $string . '</sub>';
            case 'escape':
            case 'e':
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }

        // Allowed PHP functions
        $fx_lower = strtolower($fx);
        if (in_array($fx_lower, self::BLOCKED_FUNCTIONS, true)) {
            return $string; // never execute blocked functions
        }
        if (function_exists($fx) && in_array($fx, $this->allow_fx, true)) {
            return (string) $fx($string);
        }

        return $string;
    }

    /**
     * Apply a parameterized function like date:format or substr:start:length.
     */
    private function apply_parameterized_function(string $fx, string $string): string
    {
        $parts = explode(':', $fx);
        $fn = strtolower($parts[0]);

        switch ($fn) {
            case 'date':
                $format = $parts[1] ?? 'Y-m-d';
                $ts = strtotime($string);
                if ($ts === false) {
                    return $string;
                }
                return date($format, $ts);

            case 'substr':
                $start = isset($parts[1]) ? (int) $parts[1] : 0;
                $length = isset($parts[2]) ? (int) $parts[2] : null;
                if ($length !== null) {
                    return substr($string, $start, $length);
                }
                return substr($string, $start);

            case 'ellipsis':
                $max = isset($parts[1]) ? (int) $parts[1] : 100;
                if (strlen($string) > $max) {
                    return substr($string, 0, $max) . '&#8230;';
                }
                return $string;

            default:
                return $string;
        }
    }

    /**
     * Resolve a template name to a safe file path.
     * Returns the path string or false if not found/not allowed.
     *
     * @param string $tmpl
     * @return string|false
     */
    private function resolve_path(string $tmpl)
    {
        // Block directory traversal attempts
        if (preg_match('/(\.\.[\/\\\\])/', $tmpl)) {
            return false;
        }

        // If tmpl_path is set, validate that resolved files stay within it
        $base = $this->tmpl_path;

        $candidates = [];
        if ($base !== '') {
            $candidates[] = $base . $tmpl;
            $candidates[] = $base . $tmpl . '.tmpl';
            $candidates[] = $base . $tmpl . '.tpl';
        }
        // Also try the raw path (for absolute or relative paths without tmpl_path)
        $candidates[] = $tmpl;

        foreach ($candidates as $path) {
            if (file_exists($path) && is_file($path) && is_readable($path)) {
                // If we have a base path, verify the file is inside it
                if ($base !== '') {
                    $real_base = realpath($base);
                    $real_file = realpath($path);
                    if ($real_base !== false && $real_file !== false) {
                        if (strpos($real_file, $real_base) !== 0) {
                            return false; // path escapes template directory
                        }
                    }
                }
                return $path;
            }
        }

        return false;
    }
}
