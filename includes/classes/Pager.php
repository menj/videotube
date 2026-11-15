<?php
/** Previous/next links for listings that fetch one row more than they show. */
final class Pager
{
    public const PAGE_SIZE = 24;

    /** Page number from the query string, never below 1. */
    public static function current(): int
    {
        return max(1, min(100000, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT)));
    }

    public static function offset(int $page): int
    {
        return ($page - 1) * self::PAGE_SIZE;
    }

    /** @param array<string,scalar> $parameters query parameters to keep on the links */
    public static function links(string $script, array $parameters, int $page, bool $hasNext): string
    {
        $link = static fn(int $target, string $label, string $rel): string =>
            "<a rel=\"$rel\" href=\"" . h($script . '?' . http_build_query($parameters + ['page' => $target])) . "\">$label</a>";
        $html = ($page > 1 ? $link($page - 1, 'Previous', 'prev') : '') . ($hasNext ? $link($page + 1, 'Next', 'next') : '');

        return $html === '' ? '' : "<nav class=\"pager\" aria-label=\"Pages\">$html</nav>";
    }
}
