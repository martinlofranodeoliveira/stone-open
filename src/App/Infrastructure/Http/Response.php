<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Response
{
    public int $status;
    public array $headers = [];
    public string $body;

    public function __construct(int $status = 200, array $headers = [], string $body = '')
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function json(array $data, int $status = 200): self
    {
        // Small normalization: deduplicate repeated error text segments like
        // "msg – field: msg". If equal after stripping a possible "field: " prefix,
        // keep only one occurrence.
        if (isset($data['error']) && is_string($data['error'])) {
            $data['error'] = self::dedupeErrorString($data['error']);
        }
        return new self($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private static function dedupeErrorString(string $text): string
    {
        $t = trim($text);
        if ($t === '') return $t;
        // Normalize separators to en-dash like pattern for splitting
        $norm = str_replace([' — ', ' - '], ' – ', $t);
        // Split by dash separators (en/em/ascii with spaces around)
        $parts = preg_split('/\s+[\x{2013}\x{2014}\-]\s+/u', $norm) ?: [$norm];
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            $seg = trim($p);
            if ($seg === '') continue;
            // Drop leading "field: " prefixes for comparison
            $cmp = preg_replace('/^[A-Za-z0-9_\-\.\[\]]+:\s*/u', '', $seg) ?? $seg;
            if (!in_array($cmp, $seen, true)) {
                $seen[] = $cmp;
                $out[] = $seg;
            }
        }
        return implode(' – ', $out);
    }

    public static function csv(array $rows, array $columns, string $filename = 'export.csv', bool $spreadsheetSafe = true): self
    {
        $fh = fopen('php://temp', 'r+');
        // Header
        $header = array_values($columns);
        if ($spreadsheetSafe) {
            foreach ($header as $i => $label) {
                if (is_string($label)) {
                    $header[$i] = \App\Support\CsvFormulaGuard::protect($label);
                }
            }
        }
        fputcsv($fh, $header, ',', '"', '');
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $raw = $row[$key] ?? null;
                if (is_string($raw)) {
                    $line[] = $spreadsheetSafe ? \App\Support\CsvFormulaGuard::protect($raw) : $raw;
                } elseif (is_scalar($raw)) {
                    $line[] = (string)$raw;
                } else {
                    $line[] = json_encode($raw, JSON_UNESCAPED_UNICODE);
                }
            }
            fputcsv($fh, $line, ',', '"', '');
       }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        return new self(200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], $csv);
    }

    public static function xls(array $rows, array $columns, string $filename = 'export.xls'): self
    {
        $html = '<table><thead><tr>';
        foreach ($columns as $label) {
            $html .= '<th>' . htmlspecialchars((string)$label, ENT_QUOTES) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $val = $row[$key] ?? '';
                if (!is_scalar($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                $html .= '<td>' . htmlspecialchars((string)$val, ENT_QUOTES) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return new self(200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], $html);
    }

    public static function send(Response $res): void
    {
        http_response_code($res->status);
        foreach ($res->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $res->body;
    }
}
