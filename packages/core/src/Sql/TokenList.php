<?php

declare(strict_types=1);

namespace Preflight\Core\Sql;

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;

/**
 * The significant tokens of one statement (or a slice of one), with access to the original text.
 */
final class TokenList
{
    /**
     * @param  list<Tok>  $toks
     * @param  list<string>  $raw  Original token texts (including whitespace) the toks point into.
     */
    public function __construct(
        public readonly array $toks,
        private readonly array $raw,
    ) {}

    /**
     * Split a SQL script into statements.
     *
     * @return list<self>
     */
    public static function statements(string $sql): array
    {
        $lexer = new Lexer($sql);
        $statements = [];
        $raw = [];
        $toks = [];

        foreach ($lexer->list->tokens as $token) {
            if ($token->type === Token::TYPE_DELIMITER) {
                if ($toks !== []) {
                    $statements[] = new self($toks, $raw);
                }
                $raw = [];
                $toks = [];

                continue;
            }

            $index = count($raw);
            $raw[] = (string) $token->token;

            if ($token->type === Token::TYPE_WHITESPACE || $token->type === Token::TYPE_COMMENT) {
                continue;
            }

            foreach (self::normalise($token, $index) as $tok) {
                $toks[] = $tok;
            }
        }

        if ($toks !== []) {
            $statements[] = new self($toks, $raw);
        }

        return $statements;
    }

    /**
     * @return list<Tok>
     */
    private static function normalise(Token $token, int $index): array
    {
        $text = (string) $token->token;

        switch ($token->type) {
            case Token::TYPE_KEYWORD:
                // Composed keywords ("PRIMARY KEY", "NOT NULL") become separate words.
                $words = preg_split('/\s+/', trim($text)) ?: [$text];

                return array_map(static fn (string $w) => new Tok(Tok::WORD, $w, $index), $words);
            case Token::TYPE_SYMBOL:
                if (str_starts_with($text, '@')) {
                    return [new Tok(Tok::WORD, $text, $index)];
                }

                return [new Tok(Tok::IDENT, self::scalar($token->value), $index)];
            case Token::TYPE_STRING:
                return [new Tok(Tok::STRING, self::scalar($token->value), $index)];
            case Token::TYPE_NUMBER:
            case Token::TYPE_BOOL:
                return [new Tok(Tok::NUMBER, $text, $index)];
            case Token::TYPE_OPERATOR:
                return [new Tok(Tok::PUNCT, $text, $index)];
            default:
                return [new Tok(Tok::WORD, $text, $index)];
        }
    }

    /** Lexer token values are untyped; identifiers and strings are always scalars. */
    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public function count(): int
    {
        return count($this->toks);
    }

    public function at(int $i): ?Tok
    {
        return $this->toks[$i] ?? null;
    }

    public function isWord(int $i, string ...$words): bool
    {
        return isset($this->toks[$i]) && $this->toks[$i]->isWord(...$words);
    }

    /**
     * If the words appear in order starting at $i, return the index after them.
     */
    public function matches(int $i, string ...$words): ?int
    {
        $words = array_values($words);
        for ($offset = 0, $n = count($words); $offset < $n; $offset++) {
            if (! $this->isWord($i + $offset, $words[$offset])) {
                return null;
            }
        }

        return $i + count($words);
    }

    /** Skip an optional "=" (as in ENGINE = InnoDB). */
    public function skipEquals(int $i): int
    {
        return isset($this->toks[$i]) && $this->toks[$i]->isPunct('=') ? $i + 1 : $i;
    }

    /**
     * Read a possibly schema-qualified name (db.table → table).
     *
     * @return array{0: ?string, 1: int} [name, next index]
     */
    public function name(int $i): array
    {
        $tok = $this->toks[$i] ?? null;
        if ($tok === null || ! $tok->isName()) {
            return [null, $i];
        }

        $next = $this->toks[$i + 1] ?? null;
        $after = $this->toks[$i + 2] ?? null;
        if ($next !== null && $next->isPunct('.') && $after !== null && $after->isName()) {
            return [$after->value, $i + 3];
        }

        // The lexer sometimes keeps "db.table" (or ".table") as one bare word.
        if ($tok->kind === Tok::WORD && str_contains($tok->value, '.')) {
            $parts = explode('.', $tok->value);

            return [trim((string) end($parts), '`'), $i + 1];
        }

        return [$tok->value, $i + 1];
    }

    /**
     * Index of the token that closes the parenthesis opened at $open.
     */
    public function closingParen(int $open): ?int
    {
        $depth = 0;
        for ($i = $open, $n = count($this->toks); $i < $n; $i++) {
            if ($this->toks[$i]->isPunct('(')) {
                $depth++;
            } elseif ($this->toks[$i]->isPunct(')')) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Split tokens from $start on top-level commas.
     *
     * @return list<self>
     */
    public function splitTopLevel(int $start): array
    {
        $parts = [];
        $current = [];
        $depth = 0;

        for ($i = $start, $n = count($this->toks); $i < $n; $i++) {
            $tok = $this->toks[$i];
            if ($tok->isPunct('(')) {
                $depth++;
            } elseif ($tok->isPunct(')')) {
                $depth--;
            } elseif ($depth === 0 && $tok->isPunct(',')) {
                if ($current !== []) {
                    $parts[] = new self($current, $this->raw);
                }
                $current = [];

                continue;
            }
            $current[] = $tok;
        }

        if ($current !== []) {
            $parts[] = new self($current, $this->raw);
        }

        return $parts;
    }

    public function slice(int $from, ?int $to = null): self
    {
        $length = $to === null ? null : max(0, $to - $from);

        return new self(array_values(array_slice($this->toks, $from, $length)), $this->raw);
    }

    /**
     * Identifiers listed inside the parenthesis at $open, e.g. (`a`, `b`(10) DESC) → [a, b].
     *
     * @return array{0: list<string>, 1: int} [columns, index after the closing paren]
     */
    public function columnList(int $open): array
    {
        $close = $this->closingParen($open);
        if ($close === null) {
            return [[], count($this->toks)];
        }

        $columns = [];
        $expectName = true;
        for ($i = $open + 1; $i < $close; $i++) {
            $tok = $this->toks[$i];
            if ($tok->isPunct(',')) {
                $expectName = true;

                continue;
            }
            if ($tok->isPunct('(')) {
                $inner = $this->closingParen($i);
                $i = $inner ?? $close;
                $expectName = false;

                continue;
            }
            if ($expectName && ($tok->kind === Tok::IDENT || $tok->kind === Tok::WORD)) {
                $columns[] = $tok->value;
            }
            $expectName = false;
        }

        return [$columns, $close + 1];
    }

    /** Original SQL text from token $from to token $to (inclusive), whitespace preserved. */
    public function text(int $from = 0, ?int $to = null): string
    {
        $to ??= count($this->toks) - 1;
        if (! isset($this->toks[$from], $this->toks[$to]) || $to < $from) {
            return '';
        }

        $start = $this->toks[$from]->raw;
        $end = $this->toks[$to]->raw;

        return trim(implode('', array_slice($this->raw, $start, $end - $start + 1)));
    }
}
