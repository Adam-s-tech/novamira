<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Abilities\Gutenberg;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Below this many visible characters a page is too short for a ratio to mean anything.
 */
const CONTENT_LOSS_MIN_TEXT = 200;

/**
 * A candidate keeping less than this share of the live text or media counts as a loss.
 */
const CONTENT_LOSS_MIN_RATIO = 0.5;

/**
 * Length of the text a visitor would read: block delimiters, script and style bodies and tags removed,
 * entities decoded, whitespace collapsed.
 */
function content_visible_text_length(string $content): int
{
    $text = (string) preg_replace('/<!--.*?-->/s', replacement: '', subject: $content);
    $text = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', replacement: '', subject: $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, encoding: 'UTF-8');
    $text = trim((string) preg_replace('/\s+/u', replacement: ' ', subject: $text));

    return mb_strlen($text);
}

function content_media_count(string $content): int
{
    return (int) preg_match_all('/<(img|video|audio|iframe)\b/i', $content);
}

/**
 * @return array{text_before: int, text_after: int, media_before: int, media_after: int}|null
 */
function content_loss_report(string $live, string $candidate): ?array
{
    $report = [
        'text_before' => content_visible_text_length($live),
        'text_after' => content_visible_text_length($candidate),
        'media_before' => content_media_count($live),
        'media_after' => content_media_count($candidate),
    ];

    $text_lost =
        $report['text_before'] >= CONTENT_LOSS_MIN_TEXT
        && $report['text_after'] < ($report['text_before'] * CONTENT_LOSS_MIN_RATIO);
    $media_lost =
        $report['media_before'] > 0 && $report['media_after'] < ($report['media_before'] * CONTENT_LOSS_MIN_RATIO);

    return $text_lost || $media_lost ? $report : null;
}

/**
 * @param array{text_before: int, text_after: int, media_before: int, media_after: int} $report
 */
function content_loss_message(array $report): string
{
    return sprintf(
        'The change keeps %d of %d text characters and %d of %d media items, so live content was left unchanged. gutenberg-get-content omits text stored in block markup; resend the full content, or set allow_content_loss if the removal is intended.',
        $report['text_after'],
        $report['text_before'],
        $report['media_after'],
        $report['media_before'],
    );
}
