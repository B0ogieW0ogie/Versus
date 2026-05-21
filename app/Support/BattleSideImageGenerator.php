<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BattleSideImageGenerator
{
    /**
     * @return array{0: string, 1: string} URL paths for side A and side B
     */
    public function generatePair(string $sideALabel, string $sideBLabel): array
    {
        $token = Str::lower(Str::random(12));

        return [
            $this->store($sideALabel, 'a', $token, '#1d4ed8', '#38bdf8'),
            $this->store($sideBLabel, 'b', $token, '#be123c', '#fb7185'),
        ];
    }

    private function store(string $label, string $side, string $token, string $from, string $to): string
    {
        $path = 'battles/sides/generated/'.$token.'-'.$side.'.svg';
        Storage::disk('public')->put($path, $this->svg($label, $from, $to));

        return Storage::disk('public')->url($path);
    }

    private function svg(string $label, string $from, string $to): string
    {
        $safe = htmlspecialchars(mb_strtoupper($label), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lines = $this->wrap($label, 14);
        $lineCount = count($lines);
        $startY = 200 - (($lineCount - 1) * 22) / 2;
        $tspans = '';
        foreach ($lines as $i => $line) {
            $y = $startY + ($i * 44);
            $text = htmlspecialchars(mb_strtoupper($line), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $tspans .= "<tspan x=\"400\" y=\"{$y}\">{$text}</tspan>";
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400" viewBox="0 0 800 400" role="img">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$from}"/>
      <stop offset="100%" stop-color="{$to}"/>
    </linearGradient>
  </defs>
  <rect width="800" height="400" fill="url(#bg)"/>
  <text text-anchor="middle" fill="#ffffff" font-family="system-ui, -apple-system, sans-serif" font-size="32" font-weight="700">
    {$tspans}
  </text>
</svg>
SVG;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $maxChars): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            $current = mb_strlen($word) > $maxChars
                ? mb_substr($word, 0, $maxChars)
                : $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : ['VS'];
    }
}
