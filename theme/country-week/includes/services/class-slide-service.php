<?php
/**
 * Generates a downloadable 16:9 presentation slide for a country —
 * for churches/leaders to project during a service, matching the
 * "featured this week" branding.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\CPT\Country_Meta_Fields;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pure GD image generation, no external rendering service or headless
 * browser — consistent with the QR code approach (Qr_Code_Service).
 * PT Serif (SIL Open Font License, vendored in includes/vendor/fonts/)
 * is used for all text since GD requires a local TTF file.
 */
class Slide_Service
{
    private const WIDTH = 1920;
    private const HEIGHT = 1080;

    private const COLOR_ACCENT_TOP = [22, 110, 138];
    private const COLOR_ACCENT_BOTTOM = [9, 58, 74];
    private const COLOR_WHITE = [255, 255, 255];
    private const COLOR_GOLD = [224, 171, 92];

    public static function font_bold(): string
    {
        return dirname(__DIR__) . '/vendor/fonts/PTSerif-Bold.ttf';
    }

    public static function font_regular(): string
    {
        return dirname(__DIR__) . '/vendor/fonts/PTSerif-Regular.ttf';
    }

    /**
     * Build the slide and return it as raw PNG binary data.
     */
    public static function generate(WP_Post $country): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($image, true);

        self::draw_gradient_background($image);
        self::draw_flag($image, $country);
        self::draw_text_content($image, $country);
        self::draw_footer($image);

        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();
        imagedestroy($image);

        return (string) $png;
    }

    public static function filename(WP_Post $country): string
    {
        return $country->post_name . '-slide.png';
    }

    public static function download_url(WP_Post $country): string
    {
        return trailingslashit(get_permalink($country)) . 'slide/';
    }

    // Where the dark contrast panel behind the text begins horizontally.
    private const PANEL_X = 810;

    private static function draw_gradient_background($image): void
    {
        [$r1, $g1, $b1] = self::COLOR_ACCENT_TOP;
        [$r2, $g2, $b2] = self::COLOR_ACCENT_BOTTOM;

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / self::HEIGHT;
            $r = (int) ($r1 + ($r2 - $r1) * $ratio);
            $g = (int) ($g1 + ($g2 - $g1) * $ratio);
            $b = (int) ($b1 + ($b2 - $b1) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, self::WIDTH, $y, $color);
        }

        // A large soft translucent circle in the corner for visual
        // interest, avoiding a completely flat gradient background. Kept
        // subtle and confined to above the text panel so it never
        // competes with text contrast.
        $circle_color = imagecolorallocatealpha($image, 255, 255, 255, 100);
        imagefilledellipse($image, self::WIDTH - 120, 60, 500, 500, $circle_color);

        // A solid dark panel behind the entire text column. Projectors
        // and auditorium screens routinely wash out mid-tone gradients,
        // so text contrast can't depend on exactly where it lands on the
        // gradient — this guarantees white/gold text on near-black
        // regardless of ambient light or projector calibration.
        $panel_color = imagecolorallocatealpha($image, 0, 0, 0, 35);
        imagefilledrectangle($image, self::PANEL_X, 0, self::WIDTH, self::HEIGHT - 110, $panel_color);
    }

    private static function draw_flag($image, WP_Post $country): void
    {
        $flag_id = (int) get_post_meta($country->ID, 'flag_image_id', true);

        if (!$flag_id) {
            return;
        }

        $flag_path = get_attached_file($flag_id);

        if (!$flag_path || !file_exists($flag_path)) {
            return;
        }

        $flag_image = @imagecreatefrompng($flag_path);

        if (!$flag_image) {
            $flag_image = @imagecreatefromstring((string) file_get_contents($flag_path));
        }

        if (!$flag_image) {
            return;
        }

        $src_w = imagesx($flag_image);
        $src_h = imagesy($flag_image);

        $target_w = 620;
        $target_h = (int) round($src_w > 0 ? $target_w * ($src_h / $src_w) : $target_w * 0.66);
        $x = 130;
        $y = (int) ((self::HEIGHT - $target_h) / 2);

        // A white frame + soft shadow behind the flag so it reads
        // clearly against the gradient background.
        $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 70);
        imagefilledrectangle($image, $x - 18, $y - 18 + 14, $x + $target_w + 18, $y + $target_h + 18 + 14, $shadow_color);

        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, $x - 18, $y - 18, $x + $target_w + 18, $y + $target_h + 18, $white);

        imagecopyresampled($image, $flag_image, $x, $y, 0, 0, $target_w, $target_h, $src_w, $src_h);
        imagedestroy($flag_image);
    }

    /**
     * Font sizes here are deliberately large — this is designed to be
     * read from the back row of a church auditorium projected on a
     * screen, not viewed up close on a phone. As a rule of thumb, body
     * text under roughly 5% of the frame height (≈54px at 1080px tall)
     * becomes hard to read from a distance, so nothing here goes below
     * that even at its smallest auto-fit size.
     */
    private static function draw_text_content($image, WP_Post $country): void
    {
        $white = imagecolorallocate($image, ...self::COLOR_WHITE);
        $gold = imagecolorallocate($image, ...self::COLOR_GOLD);

        $text_x = self::PANEL_X + 60;
        $max_width = self::WIDTH - $text_x - 90;

        // Eyebrow label — shrink the letter-spacing/size together until
        // it fits, rather than a fixed size that can overflow.
        $eyebrow = self::fit_single_line('COUNTRY OF THE WEEK', self::font_bold(), $max_width, 36, 30, 2);
        self::draw_text($image, self::font_bold(), $eyebrow['size'], $text_x, 170, $gold, $eyebrow['text']);

        // Country name — auto-sized to fit, wrapping to a second line
        // if needed for long official names.
        $name = get_the_title($country);
        $lines = self::fit_and_wrap($name, self::font_bold(), $max_width, 150, 92);
        $name_y = 330;

        foreach ($lines['lines'] as $line) {
            imagettftext($image, $lines['size'], 0, $text_x, $name_y, $white, self::font_bold(), $line);
            $name_y += (int) ($lines['size'] * 1.15);
        }

        // Quick facts — kept to two short, universally-relevant facts
        // (rather than three) specifically to leave room for large,
        // legible text; each line independently sized/truncated to fit.
        $facts = self::key_facts($country);
        $fact_y = $name_y + 70;

        foreach ($facts as $fact) {
            $fitted = self::fit_single_line($fact, self::font_regular(), $max_width, 58, 44);
            self::draw_text($image, self::font_regular(), $fitted['size'], $text_x, $fact_y, $white, $fitted['text']);
            $fact_y += 90;
        }
    }

    /**
     * @return string[] Up to two short "Label: value" lines, skipping
     *                   any Quick Fact that isn't filled in yet.
     */
    private static function key_facts(WP_Post $country): array
    {
        $candidates = [
            'capital' => __('Capital', 'country-week'),
            'population' => __('Population', 'country-week'),
        ];

        $facts = [];

        foreach ($candidates as $key => $label) {
            $value = get_post_meta($country->ID, $key, true);

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $facts[] = $label . ':  ' . self::truncate($value, 45);
        }

        return $facts;
    }

    /**
     * Fit a single line of text within $max_width: first by shrinking
     * font size down to $min_size, then — if it still doesn't fit — by
     * truncating with an ellipsis at the largest size that does fit.
     * Optionally applies letter-spacing (for the all-caps eyebrow label)
     * before measuring, since spacing significantly affects width.
     *
     * @return array{text: string, size: int}
     */
    private static function fit_single_line(string $text, string $font, int $max_width, int $start_size, int $min_size, int $letter_spacing = 0): array
    {
        $spaced = $letter_spacing > 0 ? self::letter_spaced($text, $letter_spacing) : $text;

        for ($size = $start_size; $size >= $min_size; $size -= 2) {
            if (self::text_width($spaced, $font, $size) <= $max_width) {
                return ['text' => $spaced, 'size' => $size];
            }
        }

        // Still doesn't fit at the minimum size — truncate instead.
        $truncated = $text;

        while (mb_strlen($truncated) > 1) {
            $truncated = mb_substr($truncated, 0, -1);
            $candidate = rtrim($truncated) . '…';
            $spaced_candidate = $letter_spacing > 0 ? self::letter_spaced($candidate, $letter_spacing) : $candidate;

            if (self::text_width($spaced_candidate, $font, $min_size) <= $max_width) {
                return ['text' => $spaced_candidate, 'size' => $min_size];
            }
        }

        return ['text' => $text, 'size' => $min_size];
    }

    private static function draw_footer($image): void
    {
        $bar_color = imagecolorallocatealpha($image, 0, 0, 0, 25);
        imagefilledrectangle($image, 0, self::HEIGHT - 110, self::WIDTH, self::HEIGHT, $bar_color);

        $white = imagecolorallocate($image, ...self::COLOR_WHITE);
        self::draw_text($image, self::font_regular(), 36, 100, self::HEIGHT - 40, $white, 'thecountryoftheweek.com');

        $gold = imagecolorallocate($image, ...self::COLOR_GOLD);
        $label = 'Pray for the World, One Country at a Time';
        $bbox = imagettfbbox(36, 0, self::font_regular(), $label);
        $text_width = $bbox[2] - $bbox[0];
        self::draw_text($image, self::font_regular(), 36, self::WIDTH - 100 - $text_width, self::HEIGHT - 40, $gold, $label);
    }

    private static function draw_text($image, string $font, int $size, int $x, int $y, $color, string $text): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * Insert thin spaces between letters to approximate CSS letter-spacing
     * for an all-caps eyebrow label (GD has no native tracking control).
     */
    private static function letter_spaced(string $text, int $spaces): string
    {
        return implode(str_repeat(' ', $spaces), mb_str_split($text));
    }

    private static function truncate(string $text, int $max_chars): string
    {
        if (mb_strlen($text) <= $max_chars) {
            return $text;
        }

        return mb_substr($text, 0, $max_chars - 1) . '…';
    }

    /**
     * Find the largest font size (down to $min_size) at which $text fits
     * $max_width on one line; if even $min_size doesn't fit, wraps onto
     * a second line at the nearest word boundary instead of shrinking
     * further, so very long official country names stay legible.
     *
     * @return array{lines: string[], size: int}
     */
    private static function fit_and_wrap(string $text, string $font, int $max_width, int $start_size, int $min_size): array
    {
        for ($size = $start_size; $size >= $min_size; $size -= 4) {
            if (self::text_width($text, $font, $size) <= $max_width) {
                return ['lines' => [$text], 'size' => $size];
            }
        }

        // Doesn't fit even at the minimum size on one line — wrap at
        // the space closest to the middle of the string.
        $mid = (int) (mb_strlen($text) / 2);
        $space_pos = strrpos(substr($text, 0, $mid + 10), ' ');

        if ($space_pos === false || $space_pos === 0) {
            return ['lines' => [$text], 'size' => $min_size];
        }

        $first = trim(mb_substr($text, 0, $space_pos));
        $second = trim(mb_substr($text, $space_pos));

        $size = $min_size;

        foreach ([$first, $second] as $line) {
            while ($size > 40 && self::text_width($line, $font, $size) > $max_width) {
                $size -= 4;
            }
        }

        return ['lines' => [$first, $second], 'size' => $size];
    }

    private static function text_width(string $text, string $font, int $size): int
    {
        $bbox = imagettfbbox($size, 0, $font, $text);

        return $bbox[2] - $bbox[0];
    }
}
