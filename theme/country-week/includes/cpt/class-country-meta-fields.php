<?php
/**
 * Defines and registers every piece of structured data a Country post
 * can hold.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for the Country content model. Every field a
 * country can have is declared once, here, as a small array describing
 * its storage key, admin label, group, and field type. Country_Meta_Boxes
 * (admin editing UI) and every display template read from this same
 * definition list, so adding a new fact only ever requires one edit.
 *
 * No ACF or other forms plugin is used — every field is a plain,
 * native `register_post_meta()` entry. "List" type fields (Interesting
 * Facts, Prayer Points, Suggested Reading) are stored as newline-
 * delimited text and split into arrays on render via lines(), which
 * avoids needing a repeater-field plugin for simple one-item-per-line
 * content.
 */
class Country_Meta_Fields
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_LIST = 'list';
    public const TYPE_ATTACHMENT = 'attachment';

    public function register(): void
    {
        add_action('init', [$this, 'register_meta_fields']);
    }

    /**
     * The full field registry, grouped for the admin meta boxes.
     * Group keys double as meta box IDs.
     *
     * @return array<string, array{label: string, fields: array<string, array{label: string, type: string, description?: string}>}>
     */
    public static function groups(): array
    {
        return [
            'quick_facts' => [
                'label' => __('Quick Facts', 'country-week'),
                'fields' => [
                    'capital' => ['label' => __('Capital', 'country-week'), 'type' => self::TYPE_TEXT],
                    'population' => ['label' => __('Population', 'country-week'), 'type' => self::TYPE_TEXT],
                    'area' => ['label' => __('Area', 'country-week'), 'type' => self::TYPE_TEXT],
                    'government' => ['label' => __('Government', 'country-week'), 'type' => self::TYPE_TEXT],
                    'languages' => ['label' => __('Languages', 'country-week'), 'type' => self::TYPE_TEXT],
                    'religions' => ['label' => __('Religions', 'country-week'), 'type' => self::TYPE_TEXT],
                    'ethnic_groups' => ['label' => __('Ethnic Groups', 'country-week'), 'type' => self::TYPE_TEXT],
                    'currency' => ['label' => __('Currency', 'country-week'), 'type' => self::TYPE_TEXT],
                    'life_expectancy' => ['label' => __('Life Expectancy', 'country-week'), 'type' => self::TYPE_TEXT],
                    'internet_tld' => ['label' => __('Internet Domain', 'country-week'), 'type' => self::TYPE_TEXT],
                    'calling_code' => ['label' => __('Calling Code', 'country-week'), 'type' => self::TYPE_TEXT],
                    'driving_side' => ['label' => __('Driving Side', 'country-week'), 'type' => self::TYPE_TEXT],
                    'climate' => ['label' => __('Climate', 'country-week'), 'type' => self::TYPE_TEXT],
                    'terrain' => ['label' => __('Terrain', 'country-week'), 'type' => self::TYPE_TEXT],
                    'natural_resources' => ['label' => __('Natural Resources', 'country-week'), 'type' => self::TYPE_TEXT],
                    'major_exports' => ['label' => __('Major Exports', 'country-week'), 'type' => self::TYPE_TEXT],
                ],
            ],
            'summaries' => [
                'label' => __('Summaries', 'country-week'),
                'fields' => [
                    'economy_summary' => ['label' => __('Economy Summary', 'country-week'), 'type' => self::TYPE_TEXTAREA],
                    'history_summary' => ['label' => __('History Summary', 'country-week'), 'type' => self::TYPE_TEXTAREA],
                    'geography_summary' => ['label' => __('Geography Summary', 'country-week'), 'type' => self::TYPE_TEXTAREA],
                    'government_summary' => ['label' => __('Government Summary', 'country-week'), 'type' => self::TYPE_TEXTAREA],
                    'people_summary' => ['label' => __('People Summary', 'country-week'), 'type' => self::TYPE_TEXTAREA],
                    'culture_section' => ['label' => __('Culture', 'country-week'), 'type' => self::TYPE_TEXTAREA],
                ],
            ],
            'facts_and_lists' => [
                'label' => __('Facts & Lists', 'country-week'),
                'fields' => [
                    'interesting_facts' => [
                        'label' => __('Interesting Facts', 'country-week'),
                        'type' => self::TYPE_LIST,
                        'description' => __('One fact per line.', 'country-week'),
                    ],
                    'did_you_know' => [
                        'label' => __('Did You Know', 'country-week'),
                        'type' => self::TYPE_LIST,
                        'description' => __('One item per line.', 'country-week'),
                    ],
                    'suggested_reading' => [
                        'label' => __('Suggested Reading', 'country-week'),
                        'type' => self::TYPE_LIST,
                        'description' => __('One resource per line, formatted as: Title | https://example.com', 'country-week'),
                    ],
                ],
            ],
            'prayer_and_mission' => [
                'label' => __('Prayer & Mission', 'country-week'),
                'fields' => [
                    'prayer_intro' => [
                        'label' => __('Prayer Introduction', 'country-week'),
                        'type' => self::TYPE_TEXTAREA,
                        'description' => __('A short paragraph framing why/how to pray for this country. Write original content or licensed material only — do not paste copyrighted sources.', 'country-week'),
                    ],
                    'prayer_points' => [
                        'label' => __('Prayer Points', 'country-week'),
                        'type' => self::TYPE_LIST,
                        'description' => __('One prayer point per line.', 'country-week'),
                    ],
                    'mission_emphasis' => [
                        'label' => __('Mission Emphasis', 'country-week'),
                        'type' => self::TYPE_TEXTAREA,
                    ],
                    'prayer_source' => [
                        'label' => __('Prayer Content Source', 'country-week'),
                        'type' => self::TYPE_TEXT,
                        'description' => __('Only fill this in if the prayer content above is actually adapted/quoted from a specific licensed source — Joshua Project and Operation World are this site\'s two expected sources for Prayer & Mission content (see docs/decisions/0003-multi-source-country-data-model.md) — with proper attribution and staying brief. Leave blank for original team-written content — this determines whether a "Source: ..." credit line displays publicly, so it must stay accurate.', 'country-week'),
                    ],
                ],
            ],
            'media' => [
                'label' => __('Media', 'country-week'),
                'fields' => [
                    'flag_image_id' => ['label' => __('Flag Image', 'country-week'), 'type' => self::TYPE_ATTACHMENT],
                    'map_image_id' => ['label' => __('Location Map', 'country-week'), 'type' => self::TYPE_ATTACHMENT],
                ],
            ],
        ];
    }

    /**
     * Flatten groups() into a single [meta_key => field_definition] map.
     */
    public static function all_fields(): array
    {
        $fields = [];

        foreach (self::groups() as $group) {
            foreach ($group['fields'] as $key => $definition) {
                $fields[$key] = $definition;
            }
        }

        return $fields;
    }

    public function register_meta_fields(): void
    {
        foreach (self::all_fields() as $key => $definition) {
            register_post_meta(Country_Post_Type::POST_TYPE, $key, [
                'type' => $definition['type'] === self::TYPE_ATTACHMENT ? 'integer' : 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => $this->sanitize_callback_for_type($definition['type']),
                'auth_callback' => fn () => current_user_can('edit_posts'),
            ]);
        }

        // Gallery images are naturally repeating, so they use WordPress's
        // native support for multiple meta rows under one key rather than
        // a single serialized array — simpler to add_post_meta()/
        // delete_post_meta() one at a time from the media picker.
        register_post_meta(Country_Post_Type::POST_TYPE, 'gallery_image_id', [
            'type' => 'integer',
            'single' => false,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);
    }

    private function sanitize_callback_for_type(string $type): callable
    {
        return match ($type) {
            self::TYPE_TEXTAREA, self::TYPE_LIST => 'sanitize_textarea_field',
            self::TYPE_ATTACHMENT => 'absint',
            default => 'sanitize_text_field',
        };
    }

    /**
     * Read a list-type meta field as an array of non-empty, trimmed lines.
     */
    public static function lines(int $post_id, string $key): array
    {
        $raw = get_post_meta($post_id, $key, true);

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);

        return array_values(array_filter($lines, fn ($line) => $line !== ''));
    }

    /**
     * Parse a "Title | URL" formatted line (used by suggested_reading)
     * into ['title' => ..., 'url' => ...]. If no "|" is present, the
     * whole line is treated as the title with no link.
     */
    public static function parse_title_url_line(string $line): array
    {
        if (str_contains($line, '|')) {
            [$title, $url] = array_map('trim', explode('|', $line, 2));

            return ['title' => $title, 'url' => $url];
        }

        return ['title' => $line, 'url' => ''];
    }

    /**
     * All gallery attachment IDs for a country, in the order stored.
     *
     * @return int[]
     */
    public static function gallery_ids(int $post_id): array
    {
        $ids = get_post_meta($post_id, 'gallery_image_id', false);

        return array_map('absint', $ids);
    }

    /**
     * The best available attachment ID to represent a country in social
     * previews and schema markup: hero (featured image) first, falling
     * back to the flag, then the map, then nothing.
     */
    public static function social_image_id(int $post_id): int
    {
        $featured = get_post_thumbnail_id($post_id);

        if ($featured) {
            return (int) $featured;
        }

        $flag = (int) get_post_meta($post_id, 'flag_image_id', true);

        if ($flag) {
            return $flag;
        }

        return (int) get_post_meta($post_id, 'map_image_id', true);
    }
}
