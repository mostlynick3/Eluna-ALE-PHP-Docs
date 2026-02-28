<?php
function render_links(string $text, array $classes = []): string {
    // **bold**
    $text = preg_replace_callback('/\*\*(.+?)\*\*/', function ($m) {
        return '<strong>' . htmlspecialchars($m[1]) . '</strong>';
    }, $text);

    // `inline code`
    $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
        return '<code class="inline-code">' . htmlspecialchars($m[1]) . '</code>';
    }, $text);

    // [Class:Method]
    $text = preg_replace_callback('/\[(\w+):(\w+)\]/', function ($m) use ($classes) {
        $rawClass  = $m[1];
        $method    = $m[2];
        $realClass = $rawClass === 'Global' ? 'GlobalFunctions' : $rawClass;
        if (!empty($classes) && !isset($classes[$realClass])) {
            return htmlspecialchars($rawClass . ':' . $method);
        }
        $url = '?class=' . urlencode($realClass) . '&method=' . urlencode($method);
        return '<a class="doc-link" href="' . $url . '">' . htmlspecialchars($rawClass . ':' . $method) . '</a>';
    }, $text);

    // [Class]
    $text = preg_replace_callback('/\[(\w+)\](?![^<]*<\/a>)/', function ($m) use ($classes) {
        $rawClass  = $m[1];
        $realClass = $rawClass === 'Global' ? 'GlobalFunctions' : $rawClass;
        if (!empty($classes) && !isset($classes[$realClass])) {
            return htmlspecialchars($rawClass);
        }
        $url = '?class=' . urlencode($realClass);
        return '<a class="doc-link" href="' . $url . '">' . htmlspecialchars($rawClass) . '</a>';
    }, $text);

    return $text;
}

function is_enum_block(string $code): bool {
    // Matches decimal or hex values: NAME = 0 or NAME = 0x00000001
    return (bool) preg_match('/\w+\s*=\s*(0x[0-9a-fA-F]+|\d+)/', $code);
}

function render_desc(string $desc, array $classes = []): string {
    if (empty($desc)) return '';

    $segments = preg_split('/(\[\[CODE\]\].*?\[\[\/CODE\]\])/s', $desc, -1, PREG_SPLIT_DELIM_CAPTURE);
    $output   = '';

    foreach ($segments as $seg) {
        if (str_starts_with($seg, '[[CODE]]')) {
            $code = preg_replace('/^\[\[CODE\]\]\n?|\n?\[\[\/CODE\]\]$/', '', $seg);
            $code = trim($code);
            if ($code === '') continue;
            if (is_enum_block($code)) {
                $output .= render_pre_block($code, $classes);
            } else {
                $output .= '<pre class="code-block"><code>' . htmlspecialchars($code) . '</code></pre>';
            }
        } else {
            $trimmed = trim($seg);
            if ($trimmed !== '') {
                $output .= '<p class="method-desc-text">'
                    . render_links(nl2br(htmlspecialchars($trimmed)), $classes)
                    . '</p>';
            }
        }
    }

    return $output;
}

function render_pre_block(string $inner, array $classes = []): string {
    $lines  = explode("\n", $inner);
    $rows   = [];
    $header = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (preg_match('/^enum\s+(\w+)/', $line, $m)) {
            $header = $m[1];
        } elseif (in_array($line, ['{', '};', '}'])) {
            // skip braces
        } elseif (preg_match('/^([\w]+)\s*=\s*(0x[0-9a-fA-F]+|\d+),?\s*(?:\/\/\s*(.*))?$/', $line, $m)) {
            $rows[] = [
                'kind'    => 'entry',
                'name'    => $m[1],
                'value'   => $m[2],
                'comment' => $m[3] ?? '',
            ];
        } else {
            $rows[] = ['kind' => 'note', 'text' => $line];
        }
    }

    if (empty($rows) && empty($header)) return '';

    $hasEntries = !empty(array_filter($rows, fn($r) => $r['kind'] === 'entry'));

    $html = '<div class="pre-block">';

    if ($header !== '') {
        $html .= '<div class="pre-block-header">' . htmlspecialchars($header) . '</div>';
    }

    if ($hasEntries) {
        $hasDesc = !empty(array_filter($rows, fn($r) => $r['kind'] === 'entry' && $r['comment'] !== ''));

        $html .= '<table class="enum-table"><thead><tr><th>Name</th><th>Value</th>';
        if ($hasDesc) $html .= '<th>Description</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            if ($row['kind'] === 'entry') {
                $comment = render_links(htmlspecialchars($row['comment']), $classes);
                $html .= '<tr>'
                    . '<td class="enum-name">' . htmlspecialchars($row['name'])  . '</td>'
                    . '<td class="enum-val">'  . htmlspecialchars($row['value']) . '</td>';
                if ($hasDesc) $html .= '<td class="enum-desc">' . $comment . '</td>';
                $html .= '</tr>';
            } else {
                $colspan = $hasDesc ? 3 : 2;
                $html .= '<tr><td colspan="' . $colspan . '" class="enum-note">'
                    . render_links(htmlspecialchars($row['text']), $classes)
                    . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';
    } else {
        foreach ($rows as $row) {
            $html .= '<div class="pre-note">' . render_links(htmlspecialchars($row['text']), $classes) . '</div>';
        }
    }

    $html .= '</div>';
    return $html;
}

function render_synopsis(string $mn, array $m, string $selectedClass): string {
    $isGlobal = $selectedClass === 'GlobalFunctions';

    // If the method has @proto overloads, render each one
    if (!empty($m['notes'])) {
        $protos = array_values(array_filter($m['notes'], fn($n) => $n['kind'] === 'proto'));
        if (!empty($protos)) {
            $lines = [];
            foreach ($protos as $proto) {
                $text = $proto['text'];
                if ($isGlobal) {
                    // Just wrap the method name before the paren, no class prefix
                    $prefixed = preg_replace('/\(/', htmlspecialchars($mn) . '(', $text, 1);
                } else {
                    $prefixed = preg_replace('/\(/', htmlspecialchars($selectedClass . ':' . $mn) . '(', $text, 1);
                }
                $lines[] = $prefixed;
            }
            return '<div class="synopsis-block">'
                . '<div class="section-header">Synopsis</div>'
                . '<pre class="synopsis-code"><code>' . implode("\n", $lines) . '</code></pre>'
                . '</div>';
        }
    }

    $paramParts  = [];
    $returnParts = [];

    foreach ($m['params'] as $p) {
        $name = htmlspecialchars($p['name'] ?: $p['type']);
        if ($p['default'] !== '') {
            $paramParts[] = '[' . $name . ']';
        } else {
            $paramParts[] = $name;
        }
    }

    foreach ($m['returns'] as $r) {
        $returnParts[] = htmlspecialchars($r['name'] ?: $r['type']);
    }

    if ($isGlobal) {
        $call = htmlspecialchars($mn) . '(' . implode(', ', $paramParts) . ')';
    } else {
        $call = htmlspecialchars($selectedClass . ':' . $mn) . '(' . implode(', ', $paramParts) . ')';
    }

    if (!empty($returnParts)) {
        $sig = implode(', ', $returnParts) . ' = ' . $call;
    } else {
        $sig = $call;
    }

    return '<div class="synopsis-block">'
        . '<div class="section-header">Synopsis</div>'
        . '<pre class="synopsis-code"><code>' . $sig . '</code></pre>'
        . '</div>';
}

function render_method_card(string $mn, array $m, bool $linkName = false, string $selectedClass = '', array $classes = []): string {
    $isInh = isset($m['inherited_from']);
    $html  = '<div class="method-card" id="mc-' . htmlspecialchars($mn) . '">';

    $nameHtml = $linkName
        ? '<a href="?class=' . urlencode($selectedClass) . '&method=' . urlencode($mn) . '" style="color:inherit;text-decoration:none">' . htmlspecialchars($mn) . '</a>'
        : htmlspecialchars($mn);

    $html .= '<div class="method-name">' . $nameHtml;
    if ($isInh) {
        $html .= '<span class="method-inherited-badge">inherited from '
            . htmlspecialchars($m['inherited_from']) . '</span>';
    }
    $html .= '</div>';

    if (!empty($m['desc'])) {
        $html .= render_desc($m['desc'], $classes);
    }

    $html .= render_synopsis($mn, $m, $selectedClass);

    if (!empty($m['params'])) {
        $html .= '<div class="tag-section"><div class="tag-label">Parameters</div>';
        foreach ($m['params'] as $p) {
            $html .= render_tag_row($p, $classes);
        }
        $html .= '</div>';
    }

    if (!empty($m['returns'])) {
        $html .= '<div class="tag-section"><div class="tag-label">Returns</div>';
        foreach ($m['returns'] as $r) {
            $html .= render_tag_row($r, $classes);
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function render_tag_row(array $tag, array $classes = []): string {
    $html = '<div class="tag-row">';
    if ($tag['type']) {
        $html .= '<span class="tag-type">' . render_links(htmlspecialchars($tag['type']), $classes) . '</span>';
    }
    if ($tag['name']) {
        $html .= '<span class="tag-name">' . htmlspecialchars($tag['name']) . '</span>';
    }
    if ($tag['default'] !== '') {
        $html .= '<span class="tag-default">default: ' . htmlspecialchars($tag['default']) . '</span>';
    }
    if ($tag['desc']) {
        $desc = str_replace('&lt;br&gt;', '<br>', htmlspecialchars($tag['desc']));
        $html .= '<span class="tag-desc">' . render_links($desc, $classes) . '</span>';
    }
    $html .= '</div>';
    return $html;
}