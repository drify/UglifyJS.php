<?php

/* -----[ Utilities ]----- */

$curry = function ($f) {
    $args = array_slice(func_get_args(), 1);
    return function() use ($f, $args) { return call_user_func_array($f, array_merge($args, func_get_args())); };
};

$prog1 = function ($ret) {
    if ($ret instanceof Closure)
        $ret = $ret();
    $arguments = func_get_args();
    for ($i = 1, $n = func_num_args(); --$n > 0; ++$i)
        $arguments[$i]();
    return $ret;
};

$array_to_hash = function ($a) {
    return array_fill_keys($a, true);
};

$characters = function ($str, $no_unicode = true) {
    if ($no_unicode) return str_split($str);
    return preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
};

if (!isset($warn)) $warn = 'error_log';

/* -----[ Tokenizer (constants) ]----- */

$KEYWORDS = $array_to_hash([
    "break",
    "case",
    "catch",
    "const",
    "continue",
    "debugger",
    "default",
    "delete",
    "do",
    "else",
    "finally",
    "for",
    "function",
    "if",
    "in",
    "instanceof",
    "new",
    "return",
    "switch",
    "throw",
    "try",
    "typeof",
    "var",
    "void",
    "while",
    "with"
]);

$RESERVED_WORDS = $array_to_hash([
    "abstract",
    "boolean",
    "byte",
    "char",
    "class",
    "double",
    "enum",
    "export",
    "extends",
    "final",
    "float",
    "goto",
    "implements",
    "import",
    "int",
    "interface",
    "long",
    "native",
    "package",
    "private",
    "protected",
    "public",
    "short",
    "static",
    "super",
    "synchronized",
    "throws",
    "transient",
    "volatile"
]);

$KEYWORDS_BEFORE_EXPRESSION = $array_to_hash([
    "return",
    "new",
    "delete",
    "throw",
    "else",
    "case"
]);

$KEYWORDS_ATOM = $array_to_hash([
    "false",
    "null",
    "true",
    "undefined"
]);

$OPERATOR_CHARS = $array_to_hash($characters("+-*&%=<>!?|~^"));

$RE_HEX_NUMBER = '/^0x[0-9a-f]+$/i';
$RE_OCT_NUMBER = '/^0[0-7]+$/';
$RE_DEC_NUMBER = '/^\d*\.?\d*(?:e[+-]?\d*(?:\d\.?|\.?\d)\d*)?$/i';

$OPERATORS = $array_to_hash([
    "in",
    "instanceof",
    "typeof",
    "new",
    "void",
    "delete",
    "++",
    "--",
    "+",
    "-",
    "!",
    "~",
    "&",
    "|",
    "^",
    "*",
    "/",
    "%",
    ">>",
    "<<",
    ">>>",
    "<",
    ">",
    "<=",
    ">=",
    "==",
    "===",
    "!=",
    "!==",
    "?",
    "=",
    "+=",
    "-=",
    "/=",
    "*=",
    "%=",
    ">>=",
    "<<=",
    ">>>=",
    "|=",
    "^=",
    "&=",
    "&&",
    "||"
]);

//$WHITESPACE_CHARS = $array_to_hash($characters(json_decode('" \u00a0\n\r\t\f\u000b\u200b\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u202f\u205f\u3000\uFEFF"'), false));
$WHITESPACE_CHARS = $array_to_hash($characters(" \n\r\t\f\v"));

$PUNC_BEFORE_EXPRESSION = $array_to_hash($characters("[{(,.;:"));

$PUNC_CHARS = $array_to_hash($characters("[]{}(),;:"));

$REGEXP_MODIFIERS = $array_to_hash($characters("gmsiy"));

/* -----[ Parser (constants) ]----- */

$UNARY_PREFIX = $array_to_hash([
    "typeof",
    "void",
    "delete",
    "--",
    "++",
    "!",
    "~",
    "-",
    "+"
]);

$UNARY_POSTFIX = $array_to_hash([ "--", "++" ]);

$ASSIGNMENT = call_user_func_array(function($a, $ret, $i){
    while ($i < count($a)) {
        $ret[$a[$i]] = substr($a[$i], 0, strlen($a[$i]) - 1);
        $i++;
    }
    return $ret;
}, [
    ["+=", "-=", "/=", "*=", "%=", ">>=", "<<=", ">>>=", "|=", "^=", "&="],
    [ "=" => true ],
    0
]);

$PRECEDENCE = call_user_func_array(function($a, $ret){
    for ($i = 0, $n = 1; $i < count($a); ++$i, ++$n) {
        $b = $a[$i];
        for ($j = 0; $j < count($b); ++$j) {
            $ret[$b[$j]] = $n;
        }
    }
    return $ret;
}, [
    [
        ["||"],
        ["&&"],
        ["|"],
        ["^"],
        ["&"],
        ["==", "===", "!=", "!=="],
        ["<", ">", "<=", ">=", "in", "instanceof"],
        [">>", "<<", ">>>"],
        ["+", "-"],
        ["*", "/", "%"]
    ],
    []
]);

$STATEMENTS_WITH_LABELS = $array_to_hash([ "for", "do", "while", "switch" ]);

$ATOMIC_START_TOKEN = $array_to_hash([ "atom", "num", "string", "regexp", "name" ]);

/* -----[ Tokenizer ]----- */

$UNICODE = ['letter' => '/\p{L}|\p{Nl}/u', 'combining_mark' => '/\p{Mn}|\p{Mc}/u', 'connector_punctuation' => '/\p{Pc}/u', 'digit' => '/\p{Nd}/u'];
// Drop Unicode support

$is_identifier_start = function ($ch) {
    //return (bool)preg_match('/[$_A-Za-z]/', $ch);
    return $ch === "$" || $ch === "_" || ctype_alpha($ch);
};

$is_identifier_char = function ($ch) {
    //return (bool)preg_match('/[$_A-Za-z0-9]/', $ch);
    return $ch === "$" || $ch === "_" || ctype_alnum($ch);
};

$is_unicode_identifier_char = function ($ch) use ($UNICODE) {
    return $ch === "$" || $ch === "_" || preg_match($UNICODE['letter'], $ch)
        || preg_match($UNICODE['combining_mark'], $ch)
        || preg_match($UNICODE['digit'], $ch)
        || preg_match($UNICODE['connector_punctuation'], $ch)
        || $ch === json_decode('"\u200c"') // zero-width non-joiner <ZWNJ>
        || $ch === json_decode('"\u200d"') // zero-width joiner <ZWJ> (in my ECMA-262 PDF, this is also 200c)
    ;
};

$parse_js_number = function ($num) use ($RE_HEX_NUMBER, $RE_OCT_NUMBER/*, $RE_DEC_NUMBER*/) {
    if (preg_match($RE_HEX_NUMBER, $num)) {
        return hexdec($num);
    } elseif (preg_match($RE_OCT_NUMBER, $num)) {
        return octdec($num);
    } elseif (is_numeric($num)) return 1 * $num;
    return NAN;
};

$js_error = function ($message, $line, $col, $pos) {
    $line = $line + 1;
    $col = $col + 1;
    $pos = $pos + 1;
    $message = $message . " (line: " . $line . ", col: " . $col . ", pos: " . $pos . ")";
    throw new Exception($message);
};

$is_token = function ($token, $type, $val) {
    return $token['type'] === $type && ($val === null || $token['value'] === $val);
};

$EX_EOF = new Exception();

$tokenizer = function ($TEXT) use (
    $KEYWORDS,
    $KEYWORDS_BEFORE_EXPRESSION,
    $KEYWORDS_ATOM,
    $OPERATOR_CHARS,
    $OPERATORS,
    $WHITESPACE_CHARS,
    $PUNC_BEFORE_EXPRESSION,
    $PUNC_CHARS,
    $UNARY_POSTFIX,
    $EX_EOF,
    $warn,
    $is_identifier_start,
    $is_identifier_char,
    $is_unicode_identifier_char,
    $parse_js_number,
    $js_error
) {

    $S = [
        'text'            => preg_replace(['/\r\n?|[\n\x{2028}\x{2029}]/u', '/^\x{FEFF}/u'], ["\n", ''], $TEXT),
        'pos'             => 0,
        'tokpos'          => 0,
        'line'            => 0,
        'tokline'         => 0,
        'col'             => 0,
        'tokcol'          => 0,
        'newline_before'  => false,
        'regex_allowed'   => false,
        'comments_before' => []
    ];

    $peek = function () use (&$S) { return isset($S['text'][$S['pos']]) ? $S['text'][$S['pos']] : ''; };

    $next = function ($signal_eof = false, $in_string = false) use (&$S, $EX_EOF) {
        //$ch = substr($S['text'], $S['pos']++, 1);
        $ch = isset($S['text'][$S['pos']]) ? $S['text'][$S['pos']++] : '';
        if ($signal_eof && $ch === '')
            throw $EX_EOF;
        if ($ch === "\n") {
            $S['newline_before'] = $S['newline_before'] || !$in_string;
            ++$S['line'];
            $S['col'] = 0;
        } else {
            ++$S['col'];
        }
        return $ch;
    };

    $find = function ($what, $signal_eof = false) use (&$S, $EX_EOF) {
        $pos = strpos($S['text'], $what, $S['pos']);
        if ($signal_eof && $pos === false) throw $EX_EOF;
        return $pos;
    };

    $start_token = function () use (&$S) {
        $S['tokline'] = $S['line'];
        $S['tokcol'] = $S['col'];
        $S['tokpos'] = $S['pos'];
    };

    $token = function ($type, $value = null, $is_comment = false) use (
        &$S,
        $UNARY_POSTFIX,
        $KEYWORDS_BEFORE_EXPRESSION,
        $PUNC_BEFORE_EXPRESSION
    ) {
        $S['regex_allowed'] = (($type === "operator" && !isset($UNARY_POSTFIX[$value])) || 
                           ($type === "keyword" && isset($KEYWORDS_BEFORE_EXPRESSION[$value])) || 
                           ($type === "punc" && isset($PUNC_BEFORE_EXPRESSION[$value])));
        $ret = [
            'type'   => $type,
            'value'  => $value,
            'line'   => $S['tokline'],
            'col'    => $S['tokcol'],
            'pos'    => $S['tokpos'],
            'endpos' => $S['pos'],
            'nlb'    => $S['newline_before']
        ];
        if (!$is_comment) {
            $ret['comments_before'] = $S['comments_before'];
            $S['comments_before'] = [];
            // make note of any newlines in the comments that came before
            for ($i = 0, $len = count($ret['comments_before']); $i < $len; $i++) {
                $ret['nlb'] = $ret['nlb'] || $ret['comments_before'][$i]['nlb'];
            }
        }
        $S['newline_before'] = false;
        return $ret;
    };

    $read_while = function ($pred) use ($peek, $next) {
        $ret = ''; $ch = $peek(); $i = 0;
        while ($ch !== '' && $pred($ch, $i++)) {
            $ret .= $next();
            $ch = $peek();
        }
        return $ret;
    };

    $parse_error = function ($err) use (&$S, $js_error) {
        $js_error($err, $S['tokline'], $S['tokcol'], $S['tokpos']);
    };

    $read_num = function ($prefix = '') use (
        $read_while,
        $parse_js_number,
        $token,
        $parse_error
    ) {
        $has_e = false; $after_e = false; $has_x = false; $has_dot = $prefix === ".";
        $num = $read_while(function ($ch, $i) use (
            $prefix,
            &$has_e,
            &$after_e,
            &$has_x,
            &$has_dot
        ) {
            if ($ch === "x" || $ch === "X") {
                if ($has_x) return false;
                return $has_x = true;
            }
            if (!$has_x && ($ch === "E" || $ch === "e")) {
                if ($has_e) return false;
                return $has_e = $after_e = true;
            }
            if ($ch === "-") {
                if ($after_e || ($i === 0 && !$prefix)) return true;
                return false;
            }
            if ($ch === "+") return $after_e;
            $after_e = false;
            if ($ch === ".") {
                if (!$has_dot && !$has_x && !$has_e)
                    return $has_dot = true;
                return false;
            }
            return ctype_xdigit($ch);
        });
        if ($prefix)
            $num = $prefix . $num;
        $valid = $parse_js_number($num);
        if (!is_nan($valid)) {
            return $token("num", $valid);
        } else {
            $parse_error("Invalid syntax: " . $num);
        }
    };

    $hex_bytes = function ($n) use ($next, $parse_error) {
        for ($num = ''; $n > 0; --$n) $num .= $next(true);
        if (!ctype_xdigit($num)) $parse_error("Invalid hex-character pattern in string");
        return $num;
    };

    $read_escaped_char = function ($in_string = false) use ($next, $hex_bytes) {
        $ch = $next(true, $in_string);
        switch ($ch) {
          case "n" : return "\n";
          case "r" : return "\r";
          case "t" : return "\t";
          case "b" : return chr(8);
          case "v" : return "\v";
          case "f" : return "\f";
          case "0" : return "\0";
          case "x" : return json_decode('"\u00' . $hex_bytes(2) . '"');
          case "u" : return json_decode('"\u'   . $hex_bytes(4) . '"');
          case "\n": return "";
          default  : return $ch;
        }
    };

    $with_eof_error = function ($eof_error, $cont) use ($EX_EOF, $parse_error) {
        try {
            return $cont();
        } catch (Exception $ex) {
            if ($ex === $EX_EOF) $parse_error($eof_error);
            else throw $ex;
        }
    };

    $read_string = function () use (
        $EX_EOF,
        $next,
        $token,
        $read_while,
        $read_escaped_char,
        $with_eof_error
    ) {
        return $with_eof_error("Unterminated string constant", function () use (
            $EX_EOF,
            $next,
            $token,
            $read_while,
            $read_escaped_char
        ) {
            $quote = $next(); $ret = "";
            for (;;) {
                $ch = $next(true);
                if ($ch === "\\") {
                    // read OctalEscapeSequence (XXX: deprecated if "strict mode")
                    // https://github.com/mishoo/UglifyJS/issues/178
                    $octal_len = 0;
                    $ch = $read_while(function ($ch) use (&$octal_len, &$first) {
                        if ($ch >= "0" && $ch <= "7") {
                            if (!isset($first)) {
                                $first = $ch;
                                return ++$octal_len;
                            }
                            elseif ($first <= "3" && $octal_len <= 2) return ++$octal_len;
                            elseif ($first >= "4" && $octal_len <= 1) return ++$octal_len;
                        }
                        return false;
                    });
                    if ($octal_len > 0) $ch = utf8_encode(chr(octdec($ch)));
                    else $ch = $read_escaped_char(true);
                }
                elseif ($ch === $quote) break;
                elseif ($ch === "\n") throw $EX_EOF;
                $ret .= $ch;
            }
            return $token("string", $ret);
        });
    };

    $read_line_comment = function () use (&$S, $next, $find, $token) {
        $next();
        $i = $find("\n");
        if ($i === false) {
            $ret = substr($S['text'], $S['pos']);
            $S['pos'] = strlen($S['text']);
        } else {
            $ret = substr($S['text'], $S['pos'], $i - $S['pos']);
            $S['pos'] = $i;
        }
        return $token("comment1", $ret, true);
    };

    $read_multiline_comment = function () use (&$S, $next, $find, $token, $with_eof_error, $warn) {
        $next();
        return $with_eof_error("Unterminated multiline comment", function () use (&$S, $find, $token, $warn) {
            $i = $find("*/", true);
            $text = substr($S['text'], $S['pos'], $i - $S['pos']);
            $S['pos'] = $i + 2;
            $S['line'] += count(explode("\n", $text)) - 1;
            $S['newline_before'] = $S['newline_before'] || strpos($text, "\n") !== false;

            // https://github.com/mishoo/UglifyJS/issues/#issue/100
            if (strncasecmp($text, '@cc_on', 6) === 0) {
                $warn("WARNING: at line " . $S['line']);
                $warn("*** Found \"conditional comment\": " . $text);
                $warn("*** UglifyJS DISCARDS ALL COMMENTS.  This means your code might no longer work properly in Internet Explorer.");
            }

            return $token("comment2", $text, true);
        });
    };

    $read_name = function () use (
        $KEYWORDS,
        $peek,
        $next,
        $is_identifier_char,
        $is_unicode_identifier_char,
        $parse_error,
        $read_escaped_char
    ) {
        $backslash = false; $name = ''; $escaped = false; $ch = $peek();
        while ($ch !== '') {
            if (!$backslash) {
                if ($ch === "\\") { $escaped = true; $backslash = true; $next(); }
                elseif ($is_identifier_char($ch)) $name .= $next();
                else break;
            }
            else {
                if ($ch !== "u") $parse_error("Expecting UnicodeEscapeSequence -- uXXXX");
                $ch = $read_escaped_char();
                if (!$is_unicode_identifier_char($ch)) {
                    $ord = ord($ch);
                    if ($ord > 127) $ord = hexdec(json_encode($ch));
                    $parse_error("Unicode char: " . $ord . " is not valid in identifier");
                }
                $name .= $ch;
                $backslash = false;
            }
            $ch = $peek();
        }
        if ($escaped && isset($KEYWORDS[$name])) {
            $hex = strtoupper(dechex(ord($name)));
            $name = "\\u" . substr("0000", strlen($hex)) . $hex . substr($name, 1);
        }
        return $name;
    };

    $read_regexp = function ($regexp) use ($next, $token, $read_name, $with_eof_error) {
        return $with_eof_error("Unterminated regular expression", function () use ($regexp, $next, $token, $read_name) {
            $prev_backslash = false; $in_class = false;
            while (($ch = $next(true)) !== '') if ($prev_backslash) {
                $regexp .= "\\" . $ch;
                $prev_backslash = false;
            } elseif ($ch === "[") {
                $in_class = true;
                $regexp .= $ch;
            } elseif ($ch === "]" && $in_class) {
                $in_class = false;
                $regexp .= $ch;
            } elseif ($ch === "/" && !$in_class) {
                break;
            } elseif ($ch === "\\") {
                $prev_backslash = true;
            } else {
                $regexp .= $ch;
            }
            $mods = $read_name();
            return $token("regexp", [ $regexp, $mods ]);
        });
    };

    $read_operator = function ($prefix = '') use ($OPERATORS, $peek, $next, $token) {
        $grow = function ($op) use (&$grow, $OPERATORS, $peek, $next) {
            $ch = $peek();
            if ($ch === '') return $op;
            $bigger = $op . $ch;
            if (isset($OPERATORS[$bigger])) {
                $next();
                return $grow($bigger);
            } else {
                return $op;
            }
        };
        return $token("operator", $grow($prefix ?: $next()));
    };

    $handle_slash = function () use (
        &$S,
        $peek,
        $next,
        $read_line_comment,
        $read_multiline_comment,
        $read_regexp,
        $read_operator,
        &$next_token
    ) {
        $next();
        $regex_allowed = $S['regex_allowed'];
        switch ($peek()) {
          case "/":
            $S['comments_before'][] = $read_line_comment();
            $S['regex_allowed'] = $regex_allowed;
            return $next_token();
          case "*":
            $S['comments_before'][] = $read_multiline_comment();
            $S['regex_allowed'] = $regex_allowed;
            return $next_token();
        }
        return $S['regex_allowed'] ? $read_regexp("") : $read_operator("/");
    };

    $handle_dot = function () use (
        $peek,
        $next,
        $token,
        $read_num
    ) {
        $next();
        return ctype_digit($peek())
            ? $read_num(".")
            : $token("punc", ".");
    };

    $read_word = function () use (
        $KEYWORDS,
        $KEYWORDS_ATOM,
        $OPERATORS,
        $token,
        $read_name
    ) {
        $word = $read_name();
        return !isset($KEYWORDS[$word])
            ? $token("name", $word)
            : (isset($OPERATORS[$word])
            ? $token("operator", $word)
            : (isset($KEYWORDS_ATOM[$word])
            ? $token("atom", $word)
            : $token("keyword", $word)));
    };

    $next_token = function ($force_regexp = null) use (
        $OPERATOR_CHARS,
        $WHITESPACE_CHARS,
        $PUNC_CHARS,
        $peek,
        $next,
        $start_token,
        $token,
        $parse_error,
        $read_num,
        $read_string,
        $read_regexp,
        $read_operator,
        $handle_slash,
        $handle_dot,
        $read_word,
        $is_identifier_start
    ) {
        if ($force_regexp !== null)
            return $read_regexp($force_regexp);
        $ch = $peek();
        while (isset($WHITESPACE_CHARS[$ch])) { $next(); $ch = $peek(); }
        $start_token();
        if ($ch === '') return $token("eof");
        if (ctype_digit($ch)) return $read_num();
        if ($ch === '"' || $ch === "'") return $read_string();
        if (isset($PUNC_CHARS[$ch])) return $token("punc", $next());
        if ($ch === ".") return $handle_dot();
        if ($ch === "/") return $handle_slash();
        if (isset($OPERATOR_CHARS[$ch])) return $read_operator();
        if ($ch === "\\" || $is_identifier_start($ch)) return $read_word();
        $parse_error("Unexpected character '" . $ch . "'");
    };

    $next_token_context = function ($nc = null) use (&$S) {
        if ($nc) return $nc;
        return $S;
    };

    return [ $next_token, 'context' => $next_token_context ];

};

/* -----[ Parser ]----- */

// Remove unknown codes NodeWithToken, embed_tokens, maybe_embed_tokens

$parse = function ($TEXT, $exigent_mode = false) use (
    $tokenizer,
    $UNARY_PREFIX,
    $UNARY_POSTFIX,
    $ASSIGNMENT,
    $PRECEDENCE,
    $STATEMENTS_WITH_LABELS,
    $ATOMIC_START_TOKEN,
    $curry,
    $prog1,
    $js_error,
    $is_token
) {

    $S = [
        'input'         => is_string($TEXT) ? $tokenizer($TEXT, true) : $TEXT,
        'token'         => null,
        'prev'          => null,
        'peeked'        => null,
        'in_function'   => 0,
        'in_directives' => true,
        'in_loop'       => 0,
        'labels'        => []
    ];

    $is = function ($type, $value = null) use (&$S, $is_token) {
        return $is_token($S['token'], $type, $value);
    };

    $peek = function () use (&$S) { return $S['peeked'] ?: ($S['peeked'] = $S['input'][0]()); };

    $next = function () use (&$S, $is) {
        $S['prev'] = $S['token'];
        if ($S['peeked']) {
            $S['token'] = $S['peeked'];
            $S['peeked'] = null;
        } else {
            $S['token'] = $S['input'][0]();
        }
        $S['in_directives'] = $S['in_directives'] && (
            $S['token']['type'] === "string" || $is("punc", ";")
        );
        return $S['token'];
    };

    $S['token'] = $next();

    $croak = function ($msg, $line = null, $col = null, $pos = null) use (&$S, $js_error) {
        $ctx = $S['input']['context']();
        $js_error($msg,
                 $line !== null ? $line : $ctx['tokline'],
                 $col !== null ? $col : $ctx['tokcol'],
                 $pos !== null ? $pos : $ctx['tokpos']);
    };

    $token_error = function ($token, $msg) use ($croak) {
        $croak($msg, $token['line'], $token['col']);
    };

    $unexpected = function ($token = null) use (&$S, $token_error) {
        if ($token === null)
            $token = $S['token'];
        $token_error($token, "Unexpected token: " . $token['type'] . " (" . $token['value'] . ")");
    };

    $expect_token = function ($type, $val) use (&$S, $is, $next, $token_error) {
        if ($is($type, $val)) {
            return $next();
        }
        $token_error($S['token'], "Unexpected token " . $S['token']['type'] . ", expected " . $type);
    };

    $expect = function ($punc) use ($expect_token) { return $expect_token("punc", $punc); };

    $can_insert_semicolon = function () use (&$S, $is, $exigent_mode) {
        return !$exigent_mode && (
            $S['token']['nlb'] || $is("eof") || $is("punc", "}")
        );
    };

    $semicolon = function () use ($is, $next, $unexpected, $can_insert_semicolon) {
        if ($is("punc", ";")) $next();
        elseif (!$can_insert_semicolon()) $unexpected();
    };

    $parenthesised = function () use ($expect, &$expression) {
        $expect("(");
        $ex = $expression();
        $expect(")");
        return $ex;
    };

    $in_loop = function ($cont) use (&$S) {
        try {
            ++$S['in_loop'];
            $ret = $cont();
        } catch (Exception $ex) {
            --$S['in_loop'];
            throw $ex;
        }
        --$S['in_loop'];
        return $ret;
    };

    $vardefs = function ($no_in = false) use (
        &$S,
        $is,
        $next,
        $unexpected,
        &$expression
    ) {
        $a = [];
        for (;;) {
            if (!$is("name"))
                $unexpected();
            $name = $S['token']['value'];
            $next();
            if ($is("operator", "=")) {
                $next();
                $a[] = [ $name, $expression(false, $no_in) ];
            } else {
                $a[] = [ $name, null ];
            }
            if (!$is("punc", ","))
                break;
            $next();
        }
        return $a;
    };

    $var_ = function ($no_in = false) use ($vardefs) {
        return [ "var", $vardefs($no_in) ];
    };

    $const_ = function () use ($vardefs) {
        return [ "const", $vardefs() ];
    };

    $break_cont = function ($type) use (
        &$S,
        $is,
        $next,
        $croak,
        $can_insert_semicolon,
        $semicolon
    ) {
        $name = null;
        if (!$can_insert_semicolon()) {
            $name = $is("name") ? $S['token']['value'] : null;
        }
        if ($name !== null) {
            $next();
            if (!in_array($name, $S['labels']))
                $croak("Label " . $name . " without matching loop or statement");
        }
        elseif ($S['in_loop'] === 0)
            $croak($type . " not inside a loop or switch");
        $semicolon();
        return [ $type, $name ];
    };

    $simple_statement = function () use ($prog1, $semicolon, &$expression) {
        return [ "stat", $prog1($expression, $semicolon) ];
    };

    $statement = function () use (
        &$S,
        $is,
        $peek,
        $next,
        $croak,
        $unexpected,
        $expect_token,
        $can_insert_semicolon,
        $semicolon,
        $parenthesised,
        $in_loop,
        $prog1,
        $is_token,
        $var_,
        $const_,
        $break_cont,
        $simple_statement,
        &$statement,
        &$labeled_statement,
        &$for_,
        &$function_,
        &$if_,
        &$block_,
        &$switch_block_,
        &$try_,
        &$expression
    ) {
        if ($is("operator", "/") || $is("operator", "/=")) {
            $S['peeked'] = null;
            $S['token'] = $S['input'][0](substr($S['token']['value'], 1)); // force regexp
        }
        switch ($S['token']['type']) {
          case "string":
            $dir = $S['in_directives']; $stat = $simple_statement();
            if ($dir && $stat[1][0] === "string" && !$is("punc", ","))
                return [ "directive", $stat[1][1] ];
            return $stat;
          case "num":
          case "regexp":
          case "operator":
          case "atom":
            return $simple_statement();

          case "name":
            return $is_token($peek(), "punc", ":")
                ? $labeled_statement($prog1($S['token']['value'], $next, $next))
                : $simple_statement();

          case "punc":
            switch ($S['token']['value']) {
              case "{":
                return [ "block", $block_() ];
              case "[":
              case "(":
                return $simple_statement();
              case ";":
                $next();
                return [ "block" ];
              default:
                $unexpected();
            }

          case "keyword":
            switch ($prog1($S['token']['value'], $next)) {
              case "break":
                return $break_cont("break");

              case "continue":
                return $break_cont("continue");

              case "debugger":
                $semicolon();
                return [ "debugger" ];

              case "do":
                return call_user_func(function ($body) use (
                    $prog1,
                    $expect_token,
                    $semicolon,
                    $parenthesised
                ) {
                    $expect_token("keyword", "while");
                    return [ "do", $prog1($parenthesised, $semicolon), $body ];
                }, $in_loop($statement));

              case "for":
                return $for_();

              case "function":
                return $function_(true);

              case "if":
                return $if_();

              case "return":
                if ($S['in_function'] === 0)
                    $croak("'return' outside of function");
                return [ "return",
                          $is("punc", ";")
                          ? call_user_func(function () use ($next) { $next(); return null; })
                          : ($can_insert_semicolon()
                          ? null
                          : $prog1($expression, $semicolon)) ];

              case "switch":
                return [ "switch", $parenthesised(), $switch_block_() ];

              case "throw":
                if ($S['token']['nlb'])
                    $croak("Illegal newline after 'throw'");
                return [ "throw", $prog1($expression, $semicolon) ];

              case "try":
                return $try_();

              case "var":
                return $prog1($var_, $semicolon);

              case "const":
                return $prog1($const_, $semicolon);

              case "while":
                return [ "while", $parenthesised(), $in_loop($statement) ];

              case "with":
                return [ "with", $parenthesised(), $statement() ];

              default:
                $unexpected();
            }
        }
    };

    $labeled_statement = function ($label) use (
        &$S,
        $STATEMENTS_WITH_LABELS,
        $unexpected,
        $exigent_mode,
        $statement
    ) {
        $S['labels'][] = $label;
        $start = $S['token']; $stat = $statement();
        if ($exigent_mode && !isset($STATEMENTS_WITH_LABELS[$stat[0]]))
            $unexpected($start);
        array_pop($S['labels']);
        return [ "label", $label, $stat ];
    };

    $regular_for = function ($init) use (
        $is,
        $expect,
        $in_loop,
        $statement,
        &$expression
    ) {
        $expect(";");
        $test = $is("punc", ";") ? null : $expression();
        $expect(";");
        $step = $is("punc", ")") ? null : $expression();
        $expect(")");
        return [ "for", $init, $test, $step, $in_loop($statement) ];
    };

    $for_in = function ($init) use (
        $next,
        $expect,
        $in_loop,
        $statement,
        &$expression
    ) {
        $lhs = $init[0] === "var" ? [ "name", $init[1][0][0] ] : $init;
        $next();
        $obj = $expression();
        $expect(")");
        return [ "for-in", $init, $lhs, $obj, $in_loop($statement) ];
    };

    $for_ = function () use (
        $is,
        $next,
        $croak,
        $expect,
        $var_,
        $regular_for,
        $for_in,
        &$expression
    ) {
        $expect("(");
        $init = null;
        if (!$is("punc", ";")) {
            $init = $is("keyword", "var")
                ? call_user_func(function () use ($next, $var_) { $next(); return $var_(true); })
                : $expression(true, true);
            if ($is("operator", "in")) {
                if ($init[0] === "var" && count($init[1]) > 1)
                    $croak("Only one variable declaration allowed in for..in loop");
                return $for_in($init);
            }
        }
        return $regular_for($init);
    };

    $if_ = function () use ($is, $next, $parenthesised, $statement) {
        $cond = $parenthesised(); $body = $statement(); $belse = null;
        if ($is("keyword", "else")) {
            $next();
            $belse = $statement();
        }
        return [ "if", $cond, $body, $belse ];
    };

    $block_ = function () use ($is, $next, $unexpected, $expect, $statement) {
        $expect("{");
        $a = [];
        while (!$is("punc", "}")) {
            if ($is("eof")) $unexpected();
            $a[] = $statement();
        }
        $next();
        return $a;
    };

    $switch_block_ = $curry($in_loop, function () use (
        $is,
        $next,
        $unexpected,
        $expect,
        $statement,
        &$expression
    ) {
        $expect("{");
        $a = []; $cur = 0;
        while (!$is("punc", "}")) {
            if ($is("eof")) $unexpected();
            if ($is("keyword", "case")) {
                $next();
                $cur = array_push($a, [ $expression(), [] ]);
                $expect(":");
            }
            elseif ($is("keyword", "default")) {
                $next();
                $expect(":");
                $cur = array_push($a, [ null, [] ]);
            }
            else {
                if (!$cur) $unexpected();
                $a[$cur - 1][1][] = $statement();
            }
        }
        $next();
        return $a;
    });

    $function_ = function ($in_statement) use (
        &$S,
        $prog1,
        $is,
        $next,
        $unexpected,
        $expect,
        $block_
    ) {
        $name = $is("name") ? $prog1($S['token']['value'], $next) : null;
        if ($in_statement && !$name)
            $unexpected();
        $expect("(");
        return [ $in_statement ? "defun" : "function",
                 $name,
                 // arguments
                 call_user_func(function ($first, $a) use (&$S, $is, $next, $unexpected, $expect) {
                     while (!$is("punc", ")")) {
                         if ($first) $first = false; else $expect(",");
                         if (!$is("name")) $unexpected();
                         $a[] = $S['token']['value'];
                         $next();
                     }
                     $next();
                     return $a;
                 }, true, []),
                 // body
                 call_user_func(function () use (&$S, $block_) {
                     ++$S['in_function'];
                     $loop = $S['in_loop'];
                     $S['in_directives'] = true;
                     $S['in_loop'] = 0;
                     $a = $block_();
                     --$S['in_function'];
                     $S['in_loop'] = $loop;
                     return $a;
                 }) ];
    };

    $try_ = function () use (
        &$S,
        $is,
        $next,
        $croak,
        $expect,
        $block_
    ) {
        $body = $block_(); $bcatch = $bfinally = null;
        if ($is("keyword", "catch")) {
            $next();
            $expect("(");
            if (!$is("name"))
                $croak("Name expected");
            $name = $S['token']['value'];
            $next();
            $expect(")");
            $bcatch = [ $name, $block_() ];
        }
        if ($is("keyword", "finally")) {
            $next();
            $bfinally = $block_();
        }
        if ($bcatch === null && $bfinally === null)
            $croak("Missing catch/finally blocks");
        return [ "try", $body, $bcatch, $bfinally ];
    };

    $expr_list = function ($closing, $allow_trailing_comma = false, $allow_empty = false) use (
        $is,
        $next,
        $expect,
        &$expression
    ) {
        $first = true; $a = [];
        while (!$is("punc", $closing)) {
            if ($first) $first = false; else $expect(",");
            if ($allow_trailing_comma && $is("punc", $closing)) break;
            if ($allow_empty && $is("punc", ",")) {
                $a[] = [ "atom", "undefined" ];
            } else {
                $a[] = $expression(false);
            }
        }
        $next();
        return $a;
    };

    $array_ = function () use ($expr_list, $exigent_mode) {
        return [ "array", $expr_list("]", !$exigent_mode, true) ];
    };

    $new_ = function () use (
        $is,
        $next,
        $expr_list,
        &$expr_atom,
        &$subscripts
    ) {
        $newexp = $expr_atom(false);
        if ($is("punc", "(")) {
            $next();
            $args = $expr_list(")");
        } else {
            $args = [];
        }
        return $subscripts([ "new", $newexp, $args ], true);
    };

    $expr_atom = function ($allow_calls) use (
        &$S,
        $ATOMIC_START_TOKEN,
        $curry,
        $prog1,
        $is,
        $next,
        $unexpected,
        $expect,
        $function_,
        $new_,
        $array_,
        &$object_,
        &$subscripts,
        &$expression
    ) {
        if ($is("operator", "new")) {
            $next();
            return $new_();
        }
        if ($is("punc")) {
            switch ($S['token']['value']) {
              case "(":
                $next();
                return $subscripts($prog1($expression, $curry($expect, ")")), $allow_calls);
              case "[":
                $next();
                return $subscripts($array_(), $allow_calls);
              case "{":
                $next();
                return $subscripts($object_(), $allow_calls);
            }
            $unexpected();
        }
        if ($is("keyword", "function")) {
            $next();
            return $subscripts($function_(false), $allow_calls);
        }
        if (isset($ATOMIC_START_TOKEN[$S['token']['type']])) {
            $atom = $S['token']['type'] === "regexp"
                ? [ "regexp", $S['token']['value'][0], $S['token']['value'][1] ]
                : [ $S['token']['type'], $S['token']['value'] ];
            return $subscripts($prog1($atom, $next), $allow_calls);
        }
        $unexpected();
    };

    $as_name = function () use (&$S, $prog1, $next, $unexpected) {
        switch ($S['token']['type']) {
          case "name":
          case "operator":
          case "keyword":
          case "atom":
            return $prog1($S['token']['value'], $next);
          default:
            $unexpected();
        }
    };

    $as_property_name = function () use (&$S, $prog1, $next, $as_name) {
        switch ($S['token']['type']) {
          case "num":
          case "string":
            return $prog1($S['token']['value'], $next);
        }
        return $as_name();
    };

    $object_ = function () use (
        &$S,
        $is,
        $next,
        $expect,
        $exigent_mode,
        $function_,
        $as_name,
        $as_property_name,
        &$expression
    ) {
        $first = true; $a = [];
        while (!$is("punc", "}")) {
            if ($first) $first = false; else $expect(",");
            if (!$exigent_mode && $is("punc", "}"))
                // allow trailing comma
                break;
            $type = $S['token']['type'];
            $name = $as_property_name();
            if ($type === "name" && ($name === "get" || $name === "set") && !$is("punc", ":")) {
                $a[] = [ $as_name(), $function_(false), $name ];
            } else {
                $expect(":");
                $a[] = [ $name, $expression(false) ];
            }
        }
        $next();
        return [ "object", $a ];
    };

    $subscripts = function ($expr, $allow_calls) use (
        $curry,
        $prog1,
        $is,
        $next,
        $expect,
        $expr_list,
        $as_name,
        &$subscripts,
        &$expression
    ) {
        if ($is("punc", ".")) {
            $next();
            return $subscripts([ "dot", $expr, $as_name() ], $allow_calls);
        }
        if ($is("punc", "[")) {
            $next();
            return $subscripts([ "sub", $expr, $prog1($expression, $curry($expect, "]")) ], $allow_calls);
        }
        if ($allow_calls && $is("punc", "(")) {
            $next();
            return $subscripts([ "call", $expr, $expr_list(")") ], true);
        }
        return $expr;
    };

    $is_assignable = function ($expr) use ($exigent_mode) {
        if (!$exigent_mode) return true;
        switch ($expr[0]) {
          case "dot":
          case "sub":
          case "new":
          case "call":
            return true;
          case "name":
            return $expr[1] !== "this";
        }
    };

    $make_unary = function ($tag, $op, $expr) use ($croak, $is_assignable) {
        if (($op === "++" || $op === "--") && !$is_assignable($expr))
            $croak("Invalid use of " . $op . " operator");
        return [ $tag, $op, $expr ];
    };

    $maybe_unary = function ($allow_calls) use (
        &$S,
        $UNARY_PREFIX,
        $UNARY_POSTFIX,
        $prog1,
        $is,
        $next,
        $expr_atom,
        $make_unary,
        &$maybe_unary
    ) {
        if ($is("operator") && isset($UNARY_PREFIX[$S['token']['value']])) {
            return $make_unary("unary-prefix",
                              $prog1($S['token']['value'], $next),
                              $maybe_unary($allow_calls));
        }
        $val = $expr_atom($allow_calls);
        while ($is("operator") && isset($UNARY_POSTFIX[$S['token']['value']]) && !$S['token']['nlb']) {
            $val = $make_unary("unary-postfix", $S['token']['value'], $val);
            $next();
        }
        return $val;
    };

    $expr_op = function ($left, $min_prec, $no_in) use (
        &$S,
        $PRECEDENCE,
        $is,
        $next,
        $maybe_unary,
        &$expr_op
    ) {
        $op = $is("operator") ? $S['token']['value'] : null;
        if ($op === "in" && $no_in) $op = null;
        $prec = isset($op, $PRECEDENCE[$op]) ? $PRECEDENCE[$op] : null;
        if ($prec !== null && $prec > $min_prec) {
            $next();
            $right = $expr_op($maybe_unary(true), $prec, $no_in);
            return $expr_op([ "binary", $op, $left, $right ], $min_prec, $no_in);
        }
        return $left;
    };

    $expr_ops = function ($no_in) use ($maybe_unary, $expr_op) {
        return $expr_op($maybe_unary(true), 0, $no_in);
    };

    $maybe_conditional = function ($no_in) use (
        $is,
        $next,
        $expect,
        $expr_ops,
        &$expression
    ) {
        $expr = $expr_ops($no_in);
        if ($is("operator", "?")) {
            $next();
            $yes = $expression(false);
            $expect(":");
            return [ "conditional", $expr, $yes, $expression(false, $no_in) ];
        }
        return $expr;
    };

    $maybe_assign = function ($no_in) use (
        &$S,
        $ASSIGNMENT,
        $is,
        $next,
        $croak,
        $is_assignable,
        $maybe_conditional,
        &$maybe_assign
    ) {
        $left = $maybe_conditional($no_in); $val = $S['token']['value'];
        if ($is("operator") && isset($ASSIGNMENT[$val])) {
            if ($is_assignable($left)) {
                $next();
                return [ "assign", $ASSIGNMENT[$val], $left, $maybe_assign($no_in) ];
            }
            $croak("Invalid assignment");
        }
        return $left;
    };

    $expression = function ($commas = true, $no_in = false) use (
        $is,
        $next,
        $maybe_assign,
        &$expression
    ) {
        $expr = $maybe_assign($no_in);
        if ($commas && $is("punc", ",")) {
            $next();
            return [ "seq", $expr, $expression(true, $no_in) ];
        }
        return $expr;
    };

    return [ "toplevel", call_user_func(function ($a) use ($is, $statement) {
        while (!$is("eof"))
            $a[] = $statement();
        return $a;
    }, []) ];

};
