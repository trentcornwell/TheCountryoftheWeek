<?php
/**
 * Shortcodes for embedding country content inside ordinary Pages/Posts
 * (e.g. the "Suggest an Edit" page, or a blog post referencing a
 * specific country).
 *
 * @package CountryWeek
 */

namespace CountryWeek\Shortcodes;

use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\Forms\Suggest_Edit_Form;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Country templates themselves never use shortcodes — they call
 * template parts directly, since the Country CPT has no editor field
 * for shortcodes to live in. These two exist purely for content authors
 * who need country data or the suggestion form inside regular editable
 * content.
 */
class Shortcodes
{
    public function register(): void
    {
        add_shortcode('country_suggest_edit', [$this, 'render_suggest_edit']);
        add_shortcode('country_quick_facts', [$this, 'render_quick_facts']);
    }

    public function render_suggest_edit(array $atts): string
    {
        $atts = shortcode_atts(['country' => ''], $atts, 'country_suggest_edit');
        $country = $atts['country'] !== '' ? $this->find_country($atts['country']) : null;

        return (new Suggest_Edit_Form())->render($country);
    }

    public function render_quick_facts(array $atts): string
    {
        $atts = shortcode_atts(['country' => ''], $atts, 'country_quick_facts');
        $country = $this->find_country($atts['country']);

        if (!$country instanceof WP_Post) {
            return '';
        }

        ob_start();
        get_template_part('templates/parts/quick-facts', null, ['country' => $country]);

        return (string) ob_get_clean();
    }

    private function find_country(string $slug_or_title): ?WP_Post
    {
        if ($slug_or_title === '') {
            return null;
        }

        $by_slug = get_page_by_path($slug_or_title, OBJECT, Country_Post_Type::POST_TYPE);

        if ($by_slug instanceof WP_Post) {
            return $by_slug;
        }

        $query = get_posts([
            'post_type' => Country_Post_Type::POST_TYPE,
            'title' => $slug_or_title,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        return $query[0] ?? null;
    }
}
