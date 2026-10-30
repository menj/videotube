<?php
final class MediaRange {
    /** @return array{0:int,1:int}|null */
    public static function parse(?string $header, int $size): ?array {
        if ($size < 1) throw new RangeException('Empty media.');
        if ($header === null || $header === '') return null;
        if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $header, $m) || ($m[1] === '' && $m[2] === '')) {
            throw new RangeException('Unsupported byte range.');
        }
        if ($m[1] === '') {
            $length = (int)$m[2];
            if ($length < 1) throw new RangeException('Invalid suffix.');
            return [max(0, $size - $length), $size - 1];
        }
        $start = (int)$m[1];
        $end = $m[2] === '' ? $size - 1 : min((int)$m[2], $size - 1);
        if ($start >= $size || $start > $end) throw new RangeException('Unsatisfiable range.');
        return [$start, $end];
    }
}
