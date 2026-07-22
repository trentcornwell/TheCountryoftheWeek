<?php
/**
 * Builds the subject/HTML body for the weekly upcoming-country preview
 * email. No wp_mail() call and no side effects — see Subscriber_Notifier
 * for the actual send.
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
 * Every piece of content here is read from the same structured post
 * meta the rest of the site already uses (Country_Meta_Fields, the
 * flag attachment, the excerpt) — nothing is re-derived or fetched
 * from a remote/AI source. Colors match Slide_Service's existing
 * brand palette for visual consistency between the two subscriber
 * deliverables.
 */
class Weekly_Preview_Email
{
    private const COLOR_ACCENT = '#166E8A';
    private const COLOR_ACCENT_DARK = '#093A4A';
    private const COLOR_GOLD = '#C4872B';
    private const COLOR_TEXT = '#1A1A1A';
    private const COLOR_TEXT_MUTED = '#5A5A5A';
    private const COLOR_BORDER = '#E2E2E2';

    /**
     * Quick Facts are extensive on the site itself; email keeps to a
     * short, universally-relevant subset so the message stays scannable.
     */
    private const QUICK_FACT_KEYS = ['capital', 'population', 'languages', 'religions'];

    public static function subject(WP_Post $country): string
    {
        return sprintf(
            /* translators: %s: country name. */
            __('Coming up: %s — The Country of the Week', 'country-week'),
            get_the_title($country)
        );
    }

    public static function html_body(WP_Post $country, int $user_id): string
    {
        $name = get_the_title($country);
        $permalink = get_permalink($country);
        $flag_id = (int) get_post_meta($country->ID, 'flag_image_id', true);
        $flag_url = $flag_id ? wp_get_attachment_image_url($flag_id, 'large') : false;

        ob_start();
        ?>
        <!doctype html>
        <html>
        <body style="margin:0;padding:0;background:#F4F4F4;font-family:Georgia,'Times New Roman',serif;color:<?php echo esc_attr(self::COLOR_TEXT); ?>;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F4F4;padding:24px 0;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;">
                            <tr>
                                <td style="background:<?php echo esc_attr(self::COLOR_ACCENT_DARK); ?>;padding:20px 32px;">
                                    <p style="margin:0;color:<?php echo esc_attr(self::COLOR_GOLD); ?>;font-size:13px;letter-spacing:2px;text-transform:uppercase;">
                                        <?php esc_html_e('Coming Up Next', 'country-week'); ?>
                                    </p>
                                </td>
                            </tr>
                            <?php if ($flag_url) : ?>
                                <tr>
                                    <td style="padding:32px 32px 0;text-align:center;">
                                        <img src="<?php echo esc_url($flag_url); ?>" width="120" alt="" style="max-width:120px;height:auto;border:1px solid <?php echo esc_attr(self::COLOR_BORDER); ?>;">
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding:16px 32px 0;text-align:center;">
                                    <h1 style="margin:0;font-size:28px;color:<?php echo esc_attr(self::COLOR_ACCENT_DARK); ?>;">
                                        <?php echo esc_html($name); ?>
                                    </h1>
                                </td>
                            </tr>
                            <?php echo self::excerpt_block($country); ?>
                            <?php echo self::quick_facts_block($country); ?>
                            <?php echo self::prayer_block($country); ?>
                            <tr>
                                <td style="padding:8px 32px 32px;text-align:center;">
                                    <a href="<?php echo esc_url($permalink); ?>" style="display:inline-block;margin:8px 8px 0;padding:12px 24px;background:<?php echo esc_attr(self::COLOR_ACCENT); ?>;color:#FFFFFF;text-decoration:none;font-family:Georgia,serif;">
                                        <?php esc_html_e('View This Country Online', 'country-week'); ?>
                                    </a>
                                    <br>
                                    <a href="<?php echo esc_url(Pdf_Service::print_url($country)); ?>" style="display:inline-block;margin:12px 8px 0;color:<?php echo esc_attr(self::COLOR_ACCENT); ?>;">
                                        <?php esc_html_e('Download the printable sheet', 'country-week'); ?>
                                    </a>
                                    <p style="margin:16px 0 0;font-size:13px;color:<?php echo esc_attr(self::COLOR_TEXT_MUTED); ?>;">
                                        <?php esc_html_e("This week's presentation slide is attached to this email.", 'country-week'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid <?php echo esc_attr(self::COLOR_BORDER); ?>;text-align:center;">
                                    <p style="margin:0;font-size:12px;color:<?php echo esc_attr(self::COLOR_TEXT_MUTED); ?>;">
                                        <?php
                                        printf(
                                            /* translators: %s: unsubscribe link. */
                                            esc_html__('You are receiving this because you have a free account at The Country of the Week. %s', 'country-week'),
                                            '<a href="' . esc_url(self::unsubscribe_url($user_id)) . '" style="color:' . esc_attr(self::COLOR_TEXT_MUTED) . ';">' . esc_html__('Unsubscribe from this weekly email', 'country-week') . '</a>'
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private static function excerpt_block(WP_Post $country): string
    {
        if (!has_excerpt($country)) {
            return '';
        }

        ob_start();
        ?>
        <tr>
            <td style="padding:16px 32px 0;text-align:center;">
                <p style="margin:0;font-size:15px;line-height:1.6;color:<?php echo esc_attr(self::COLOR_TEXT); ?>;">
                    <?php echo esc_html(get_the_excerpt($country)); ?>
                </p>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    private static function quick_facts_block(WP_Post $country): string
    {
        $labels = Country_Meta_Fields::groups()['quick_facts']['fields'];
        $rows = [];

        foreach (self::QUICK_FACT_KEYS as $key) {
            $value = get_post_meta($country->ID, $key, true);

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $rows[] = ['label' => $labels[$key]['label'] ?? $key, 'value' => $value];
        }

        if (empty($rows)) {
            return '';
        }

        ob_start();
        ?>
        <tr>
            <td style="padding:24px 32px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid <?php echo esc_attr(self::COLOR_BORDER); ?>;">
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid <?php echo esc_attr(self::COLOR_BORDER); ?>;font-size:13px;color:<?php echo esc_attr(self::COLOR_TEXT_MUTED); ?>;text-transform:uppercase;letter-spacing:0.5px;width:40%;">
                                <?php echo esc_html($row['label']); ?>
                            </td>
                            <td style="padding:10px 0;border-bottom:1px solid <?php echo esc_attr(self::COLOR_BORDER); ?>;font-size:14px;color:<?php echo esc_attr(self::COLOR_TEXT); ?>;">
                                <?php echo esc_html($row['value']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Same field reads and attribution shape as
     * templates/parts/prayer-section.php — do not re-derive independently.
     */
    private static function prayer_block(WP_Post $country): string
    {
        $intro = get_post_meta($country->ID, 'prayer_intro', true);
        $points = Country_Meta_Fields::lines($country->ID, 'prayer_points');
        $source = get_post_meta($country->ID, 'prayer_source', true);

        if (!is_string($intro) || $intro === '') {
            if (empty($points)) {
                return '';
            }
        }

        ob_start();
        ?>
        <tr>
            <td style="padding:24px 32px 0;">
                <h2 style="margin:0 0 8px;font-size:16px;color:<?php echo esc_attr(self::COLOR_ACCENT_DARK); ?>;">
                    <?php esc_html_e('Pray for This Country', 'country-week'); ?>
                </h2>
                <?php if (is_string($intro) && $intro !== '') : ?>
                    <p style="margin:0 0 8px;font-size:14px;line-height:1.6;"><?php echo esc_html($intro); ?></p>
                <?php endif; ?>
                <?php if (!empty($points)) : ?>
                    <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.7;">
                        <?php foreach (array_slice($points, 0, 5) as $point) : ?>
                            <li><?php echo esc_html($point); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (is_string($source) && $source !== '') : ?>
                    <p style="margin:8px 0 0;font-size:12px;font-style:italic;color:<?php echo esc_attr(self::COLOR_TEXT_MUTED); ?>;">
                        <?php
                        printf(
                            /* translators: %s: source name. */
                            esc_html__('Source: %s', 'country-week'),
                            esc_html($source)
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    private static function unsubscribe_url(int $user_id): string
    {
        return add_query_arg(
            [
                'action' => 'country_week_unsubscribe',
                'u' => $user_id,
                't' => Unsubscribe_Token::generate($user_id),
            ],
            admin_url('admin-post.php')
        );
    }
}
