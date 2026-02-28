<?php
function parse_headers(string $dir): array {
    $classes = [];

    foreach (glob($dir . '/*.h') as $file) {
        $content = file_get_contents($file);

        if (!preg_match('/namespace\s+(Lua\w+)\s*\{/', $content, $nsMatch))
            continue;

        $namespace = $nsMatch[1];
        $className = preg_replace('/^Lua/', '', $namespace);

        $classDesc = '';
        $inherits  = [];

        $nsPos  = strpos($content, 'namespace ' . $namespace);
        $before = substr($content, 0, $nsPos);

        if (preg_match_all('/\/\*\*\s*(.*?)\s*\*\//s', $before, $classDocMatches)) {
            $raw     = end($classDocMatches[1]);
            $lines   = explode("\n", $raw);
            $cleaned = [];
            foreach ($lines as $line) {
                $line      = preg_replace('/^\s*\*\s?/', '', $line);
                $cleaned[] = $line;
            }
            $classDesc = implode("\n", $cleaned);

            if (preg_match('/Inherits all methods from:\s*(.+)/i', $classDesc, $inhMatch)) {
                $inhRaw = $inhMatch[1];
                if (stripos($inhRaw, 'none') === false) {
                    preg_match_all('/\[(\w+)\]/', $inhRaw, $inhNames);
                    $inherits = $inhNames[1];
                }
                $classDesc = trim(preg_replace('/Inherits all methods from:.+/i', '', $classDesc));
            }
        }

        $nsStart = strpos($content, '{', strpos($content, 'namespace ' . $namespace));
        if ($nsStart === false) continue;
        $body = substr($content, $nsStart + 1);

        $methods = [];
        $pattern = '/\/\*\*\s*(.*?)\s*\*\/\s*int\s+(\w+)\s*\(lua_State\*/s';
        preg_match_all($pattern, $body, $methodMatches, PREG_SET_ORDER);

        foreach ($methodMatches as $m) {
            $rawDoc     = $m[1];
            $methodName = $m[2];

            $lines     = explode("\n", $rawDoc);
            $descLines = [];
            $params    = [];
            $returns   = [];
            $notes     = [];

            foreach ($lines as $line) {
                $line = preg_replace('/^\s*\*\s?/', '', $line);
                $line = rtrim($line);
                if (preg_match('/^@param\s+(.+)/', $line, $pm)) {
                    $params[] = parse_tag($pm[1]);
                } elseif (preg_match('/^@return\s+(.+)/', $line, $rm)) {
                    $returns[] = parse_tag($rm[1]);
                } elseif (preg_match('/^@proto\s+(.+)/', $line, $prm)) {
                    $notes[] = ['kind' => 'proto', 'text' => trim($prm[1])];
                } elseif (preg_match('/^@/', $line)) {
                    // skip unknown tags
                } else {
                    $descLines[] = $line;
                }
            }

            // Strip bare <pre> and </pre> lines
            $descLines = array_values(array_filter(
                array_map(function ($dl) {
                    $trimmed = trim($dl);
                    if ($trimmed === '<pre>' || $trimmed === '</pre>') return null;
                    return $dl;
                }, $descLines),
                fn($dl) => $dl !== null
            ));

            // Tag each line
            // 'code'   = 4-space indented
            // 'struct' = enum/brace/flag-value/comment/blank lines that should be absorbed into code blocks
            // 'prose'  = everything else
            $n    = count($descLines);
            $tags = [];
            for ($i = 0; $i < $n; $i++) {
                $trimmed = trim($descLines[$i]);
                if (preg_match('/^    /', $descLines[$i])) {
                    $tags[$i] = 'code';
                } elseif (preg_match('/^(enum\s+\w+|\{|\}|};)\s*$/', $trimmed)) {
                    $tags[$i] = 'struct';
                } elseif (preg_match('/^\w+\s*=\s*(0x[0-9a-fA-F]+|\d+),?\s*(\/\/.*)?$/', $trimmed)) {
                    // NAME = 0xVALUE or NAME = 123 lines (flag/enum definitions without indent)
                    $tags[$i] = 'struct';
                } elseif (preg_match('/^\/\//', $trimmed)) {
                    // standalone comment lines inside enum blocks
                    $tags[$i] = 'struct';
                } elseif ($trimmed === '') {
                    // blank lines — may be absorbed into code blocks by promotion
                    $tags[$i] = 'struct';
                } else {
                    $tags[$i] = 'prose';
                }
            }

            // Promote struct lines to code if within 3 lines of another code or struct line
            $changed = true;
            while ($changed) {
                $changed = false;
                for ($i = 0; $i < $n; $i++) {
                    if ($tags[$i] !== 'struct') continue;
                    for ($j = max(0, $i - 3); $j <= min($n - 1, $i + 3); $j++) {
                        if ($i !== $j && ($tags[$j] === 'code' || $tags[$j] === 'struct')) {
                            $tags[$i] = 'code';
                            $changed  = true;
                            break;
                        }
                    }
                }
            }

            // Build collapsed result
            $result  = [];
            $inBlock = false;
            for ($i = 0; $i < $n; $i++) {
                $dl = $descLines[$i];
                if ($tags[$i] === 'code') {
                    if (!$inBlock) {
                        $result[] = '[[CODE]]';
                        $inBlock  = true;
                    }
                    $result[] = preg_match('/^    /', $dl) ? substr($dl, 4) : trim($dl);
                } else {
                    if ($inBlock) {
                        $result[] = '[[/CODE]]';
                        $inBlock  = false;
                    }
                    $result[] = $dl;
                }
            }
            if ($inBlock) {
                $result[] = '[[/CODE]]';
            }

            $methods[$methodName] = [
                'name'    => $methodName,
                'desc'    => trim(implode("\n", $result)),
                'params'  => $params,
                'returns' => $returns,
                'notes'   => $notes,
            ];
        }

        $classes[$className] = [
            'name'     => $className,
            'desc'     => $classDesc,
            'inherits' => $inherits,
            'methods'  => $methods,
            'file'     => basename($file),
        ];
    }

    ksort($classes);
    return $classes;
}

function parse_tag(string $raw): array {
    $raw     = trim($raw);
    $type    = '';
    $name    = '';
    $default = '';
    $desc    = '';

    // [Type] name = default : desc
    if (preg_match('/^\[(\w+)\]\s+(\w+)\s*=\s*([^\s:]+)\s*(?::\s*(.+))?$/', $raw, $m)) {
        $type = $m[1]; $name = $m[2]; $default = $m[3]; $desc = $m[4] ?? '';
    }
    // [Type] name : desc
    elseif (preg_match('/^\[(\w+)\]\s+(\w+)\s*(?::\s*(.+))?$/', $raw, $m)) {
        $type = $m[1]; $name = $m[2]; $desc = $m[3] ?? '';
    }
    // [Type] free-form desc (no single-word name)
    elseif (preg_match('/^\[(\w+)\]\s+(.+)$/', $raw, $m)) {
        $type = $m[1]; $desc = $m[2];
    }
    // type name = default : desc
    elseif (preg_match('/^(\w+)\s+(\w+)\s*=\s*([^\s:]+)\s*(?::\s*(.+))?$/', $raw, $m)) {
        $type = $m[1]; $name = $m[2]; $default = $m[3]; $desc = $m[4] ?? '';
    }
    // type name : desc
    elseif (preg_match('/^(\w+)\s+(\w+)\s*(?::\s*(.+))?$/', $raw, $m)) {
        $type = $m[1]; $name = $m[2]; $desc = $m[3] ?? '';
    }
    else {
        $desc = $raw;
    }

    return ['type' => $type, 'name' => $name, 'default' => $default, 'desc' => $desc];
}

function get_all_methods(string $className, array $classes): array {
    if (!isset($classes[$className])) return [];
    $own       = $classes[$className]['methods'];
    $inherited = [];
    foreach ($classes[$className]['inherits'] as $parent) {
        foreach (get_all_methods($parent, $classes) as $mName => $method) {
            if (!isset($own[$mName])) {
                $method['inherited_from'] = $parent;
                $inherited[$mName]        = $method;
            }
        }
    }
    $all = array_merge($own, $inherited);
    ksort($all);
    return $all;
}

function build_tree(array $classes): array {
    $children = [];

    foreach ($classes as $name => $cls) {
        $parents = $cls['inherits'];

        $directParents = array_filter($parents, function ($p) use ($parents, $classes) {
            foreach ($parents as $otherParent) {
                if ($otherParent === $p) continue;
                if (isset($classes[$otherParent]) && in_array($p, $classes[$otherParent]['inherits'])) {
                    return false;
                }
            }
            return true;
        });

        foreach ($directParents as $parent) {
            $children[$parent][] = $name;
        }
    }

    $roots = [];
    foreach ($classes as $name => $cls) {
        if (empty($cls['inherits'])) {
            $roots[] = $name;
        }
    }

    return ['children' => $children, 'roots' => $roots];
}

function build_search_index(array $classes): array {
    $index = [];
    foreach ($classes as $cls) {
        $index[] = [
            'type' => 'class',
            'name' => $cls['name'],
            'desc' => mb_substr(strip_tags($cls['desc']), 0, 80),
        ];
        foreach ($cls['methods'] as $m) {
            $index[] = [
                'type'  => 'method',
                'class' => $cls['name'],
                'name'  => $m['name'],
                'desc'  => mb_substr(strip_tags($m['desc']), 0, 80),
            ];
        }
    }
    return $index;
}