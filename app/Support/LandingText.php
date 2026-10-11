<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Safe renderer for admin-entered landing page text.
 *
 * Admins never supply HTML. The text is HTML-escaped first; only two
 * conveniences are then re-introduced by this class itself:
 *   **bold**   -> <strong>bold</strong>
 *   newlines   -> <br>
 * so no tag, attribute or script from user input can ever reach the page.
 */
class LandingText
{
    public static function format(?string $text, bool $bold = true): HtmlString
    {
        $safe = e((string) $text);

        if ($bold) {
            $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe);
        }

        return new HtmlString(nl2br($safe, false));
    }

    /** Lines of a multi-line setting, trimmed, blanks removed. */
    public static function lines(?string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $text) ?: []),
            fn ($l) => $l !== ''
        ));
    }

    /** Only same-page anchors, site paths and http(s) URLs are ever linked. */
    public static function isSafeUrl(?string $url): bool
    {
        $url = (string) $url;

        if ($url === '') {
            return true;
        }
        if (preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
            return false;
        }
        if (preg_match('/^#[A-Za-z0-9_\-]*$/', $url)) {
            return true;
        }
        if (preg_match('#^/(?!/)[A-Za-z0-9_\-./?=&%\#]*$#', $url)) {
            return true;
        }

        return (bool) preg_match('#^https?://[^\s]+$#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
