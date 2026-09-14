<?php
declare(strict_types=1);

namespace App\Services;

/**
 * CSV / Excel-compatible CSV / simple HTML-print-to-PDF export helpers.
 */
final class ExportService
{
    /** UTF-8 BOM makes Excel open Persian CSV correctly. */
    public static function csv(array $rows, array $headers = []): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        fwrite($out, "\xEF\xBB\xBF");
        if ($headers !== []) {
            fputcsv($out, $headers, ',', '"', '\\');
        } elseif ($rows !== []) {
            fputcsv($out, array_keys((array)$rows[0]), ',', '"', '\\');
        }
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v) => is_scalar($v) || $v === null ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE), (array)$row), ',', '"', '\\');
        }
        rewind($out);
        $content = (string)stream_get_contents($out);
        fclose($out);
        return $content;
    }

    /** Simple printable HTML document (browser "Save as PDF"). */
    public static function printableHtml(string $title, string $bodyHtml): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<title>{$safeTitle}</title>
<style>
  body{font-family:Vazirmatn,Tahoma,sans-serif;padding:24px;color:#1d1b1a}
  h1{font-size:20px;margin-bottom:16px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{border:1px solid #e6e1db;padding:8px;text-align:right}
  th{background:#faf7f3}
  .totals{margin-top:16px;font-weight:700}
  @media print{.no-print{display:none}}
</style>
</head>
<body>
<h1>{$safeTitle}</h1>
{$bodyHtml}
<script>window.addEventListener('load',()=>window.print());</script>
</body>
</html>
HTML;
    }

    public static function filename(string $base, string $ext = 'csv'): string
    {
        return $base . '-' . date('Ymd-His') . '.' . $ext;
    }
}
