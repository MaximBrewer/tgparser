<?php

/**
 * Plugin Name: Telegram Channel Parser
 * Description: Telegram Channel Parser
 * Plugin URI:  
 * Author URI:  
 * Author:      
 * Version:     1.04
 *
 * Text Domain: 
 * Domain Path: 
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:    false
 * Update URI: 
 */

// Add a new interval of 300 seconds
// See http://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules

require_once(__DIR__ . '/classes/TgParser.php');
require_once(__DIR__ . '/classes/TgPost.php');

add_filter('cron_schedules', 'isa_add_every_5_minutes');
function isa_add_every_5_minutes($schedules)
{
    $schedules['every_5_minutes'] = array(
        'interval'  => 300,
        'display'   => __('Every 5 Minutes', 'textdomain')
    );
    return $schedules;
}
register_deactivation_hook(__FILE__, 'tgparser_deactivate');
function tgparser_deactivate()
{
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS `wp_tgparser`;');
    $wpdb->query('DROP TABLE IF EXISTS `wp_tgparser_attachments`;');
}
register_activation_hook(__FILE__, 'tgparser_activate');
function tgparser_activate()
{
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS `wp_tgparser`;');

    $wpdb->query('CREATE TABLE `wp_tgparser` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tgid` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `posted` tinyint(4) NOT NULL DEFAULT 0,
  `channel` varchar(1023) COLLATE utf8_unicode_ci DEFAULT NULL,
  `datetime` bigint(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tgid_UNIQUE` (`tgid`)
) ENGINE=InnoDB AUTO_INCREMENT=65453 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');

    $wpdb->query('DROP TABLE IF EXISTS `wp_tgparser_attachments`;');

    $wpdb->query('CREATE TABLE `wp_tgparser_attachments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tgparser_id` bigint(20) unsigned DEFAULT NULL,
    `image` varchar(1023) COLLATE utf8_unicode_ci DEFAULT NULL,
    `tg_path` varchar(1023) COLLATE utf8_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tg_path_UNIQUE` (`tg_path`)
  ) ENGINE=InnoDB AUTO_INCREMENT=23454 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');

    wp_clear_scheduled_hook('every_5_minutes_event');
    wp_schedule_event(time(), 'every_5_minutes', 'every_5_minutes_event');
    wp_schedule_event(time(), 'daily', 'remove_tg_channels');
}
add_action('every_5_minutes_event', 'parse_tg_channels');
add_action('every_5_minutes_event', 'remove_tg_channels');


function tgparser_register_settings()
{
    register_setting('tgparser_plugin_options', 'tgparser_plugin_options', 'tgparser_plugin_options_validate');
}
add_action('admin_init', 'tgparser_register_settings');

function tgparser_add_settings_page()
{
    add_options_page('Telegram Parser Settings', 'Telegram Parser Settings', 'manage_options', 'tgparser-plugin-settings', 'tgparser_render_plugin_settings_page');
}
add_action('admin_menu', 'tgparser_add_settings_page');

function tgparser_add_posts_page()
{
    add_options_page('Telegram Parser Posts', 'Telegram Parser Posts', 'manage_options', 'tgparser-plugin-posts', 'tgparser_render_plugin_posts_page');
}

add_action('admin_menu', 'tgparser_add_posts_page');

function tgparser_add_post_page()
{
    add_options_page('Telegram Parser Post', 'Telegram Parser Post', 'manage_options', 'tgparser-plugin-post', 'tgparser_render_plugin_post_page');
}

add_action('admin_menu', 'tgparser_add_post_page');

function tgparser_render_plugin_post_page()
{
    global $wpdb;

    if (isset($_REQUEST['post']) && (int)$_REQUEST['post']) {

        $results = $wpdb->get_results("SELECT * FROM `wp_tgparser` WHERE posted = 0 AND id= '" . (int)$_REQUEST['post'] . "' ORDER BY datetime DESC");
        $html = "<div style=\"padding: 1rem 1rem 1rem 0rem;\">";
        $html .= "<table cellpadding=\"5\" cellspacing=\"0\" border width=\"100%\" style=\"margin-bottom:1rem;\">";
        $html .= "<thead>";
        $html .= "<tr>";
        $html .= "<th>Канал</th>";
        $html .= "<th>Дата</th>";
        $html .= "<th>Текст</th>";
        $html .= "<th></th>";
        $html .= "</tr>";
        $html .= "</thead>";
        $html .= "<tbody>";
        foreach ($results as $result) {
            // var_dump($result);

            $html .= "<tr>";
            $html .= "<td>" . str_replace(["https://t.me/s/", "https://t.me/"], "", $result->channel) . "</td>";
            $html .= "<td style=\"white-space:nowrap;\">" . wp_date("d.m.Y H:i", $result->datetime) . "</td>";
            $html .= "<td>" . stripslashes($result->text) . "<br/>";


            $images = $wpdb->get_results("SELECT * FROM `wp_tgparser_attachments` WHERE tgparser_id = " . $result->id . "");

            foreach ($images as $image) {
                $attach = $wpdb->get_row("SELECT * FROM `wp_posts` WHERE ID = " . $image->image . "");
                $html .=  "<img src=\"" . $attach->guid . "\" alt=\"\" style=\"max-width:100%\"/><br/>";
            }

            $html .= "</td>";
            $html .= "<td>";
            $html .= "<a href=\"/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-create&post={$result->id}\" class=\"button action\">Создать пост</a><br/><br/>";
            $html .= "<a href=\"/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-add&post={$result->id}\" class=\"button action\">Добавить в существующий</a><br/><br/>";
            $html .= "<a href=\"/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-del&post={$result->id}\" class=\"button action\">Удалить из ленты</a>";
            $html .= "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";
        echo $html;
        return;
    }

?>
    <h2>Telegram Parser Get Post</h2>
    <form action="/wp-admin/options-general.php?page=tgparser-plugin-post" method="post">
        <input name="action" type="hidden" value="tgparser-getpost" />
        <input name="url" type="text" style="width:40%" />
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
<?php

}

function tgparser_render_plugin_settings_page()
{
    global $wpdb;
?>
    <h2>Telegram Parser Settings</h2>
    <form action="options.php" method="post">
        <?php
        settings_fields('tgparser_plugin_options');
        do_settings_sections('tgparser_plugin');

        $options = get_option('tgparser_plugin_options');
        $channels = $options['channels'] ?? [];
        $html = "<div style=\"padding: 1rem 1rem 1rem 0rem;\">";
        $html .= "<table cellpadding=\"5\" cellspacing=\"0\" border width=\"100%\" style=\"margin-bottom:1rem;\">";
        $html .= "<thead>";
        $html .= "<tr>";
        $html .= "<th>Канал</th>";
        $html .= "<th>Последнее обновление</th>";
        $html .= "</tr>";
        $html .= "</thead>";
        $html .= "<tbody>";
        foreach ($channels as $key => $channel) {
            if (!$channel['channel']) continue;
            $html .= "<tr>";
            $html .= "<td><input style=\"width:100%\" id='tgparser_plugin_setting_channels_$key' name='tgparser_plugin_options[channels][$key][channel]' type='text' value='" . esc_attr($channel['channel']) . "' /></td>";
            $row = $wpdb->get_row("SELECT datetime FROM `wp_tgparser` WHERE channel = '" . esc_attr($channel['channel']) . "' ORDER BY datetime DESC");
            if ($row) {
                $html .= "<td>" . wp_date("d.m.Y H:i", $row->datetime) . "</td>";
            } else {
                $html .= "<td></td>";
            }
            $html .= "</tr>";
        }
        $key = isset($key) ? $key : 0;
        $html .= "<tr>";
        $html .= "<td><input style=\"width:100%\" id='tgparser_plugin_setting_channels_$key' name='tgparser_plugin_options[channels][$key][channel]' type='text'/></td>";
        $html .= "<td></td>";
        $html .= "</tr>";
        ++$key;
        $html .= "<tr>";
        $html .= "<td><input style=\"width:100%\" id='tgparser_plugin_setting_channels_$key' name='tgparser_plugin_options[channels][$key][channel]' type='text'/></td>";
        $html .= "<td></td>";
        $html .= "</tr>";
        ++$key;
        $html .= "<tr>";
        $html .= "<td><input style=\"width:100%\" id='tgparser_plugin_setting_channels_$key' name='tgparser_plugin_options[channels][$key][channel]' type='text'/></td>";
        $html .= "<td></td>";
        $html .= "</tr>";
        ++$key;
        $html .= "<tr>";
        $html .= "<td><input style=\"width:100%\" id='tgparser_plugin_setting_channels_$key' name='tgparser_plugin_options[channels][$key][channel]' type='text'/></td>";
        $html .= "<td></td>";
        $html .= "</tr>";
        ++$key;
        $html .= "<tr>";
        $html .= "<td><input style=\"width:100%\" id='tgparser_plugin_setting_channels_$key' name='tgparser_plugin_options[channels][$key][channel]' type='text'/></td>";
        $html .= "<td></td>";
        $html .= "</tr>";
        $html .= "</tbody>";
        $html .= "</table>";
        echo $html;
        ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
    <?php
}

function tgparser_admin()
{
    global $wpdb;

    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'tgparser-getpost' && isset($_REQUEST['url']) && $_REQUEST['url']) {

        $url = esc_attr($_REQUEST['url']);

        if ($url) {

            $parser = new TgParser($url);

            $dom = new DOMDocument;
            libxml_use_internal_errors(true);
            $dom->loadHTML($parser->getHtml());
            $xpath = new DOMXpath($dom);
            $elements = $xpath->query('//*[@data-post]');

            $posts = [];

            foreach ($elements as $element) {
                $tgid = $element->getAttribute('data-post');
                if (strstr($url, $tgid)) {
                    $post = new TgPost();
                    $post->tgid = $tgid;
                    $textDivs = $xpath->query('.//*[contains(@class, "tgme_widget_message_text")]', $element);
                    foreach ($textDivs as $textDiv) {
                        $doc = $textDiv->ownerDocument;
                        $html = '';
                        foreach ($textDiv->childNodes as $node) {
                            if ($node->hasAttributes()) $node->removeAttribute('onclick');
                            $html .= $doc->saveHTML($node);
                        }
                        $post->text = $html;
                    }
                    $videoDivs = $xpath->query('.//*[contains(@class, "tgme_widget_message_video_thumb")]', $element);
                    foreach ($videoDivs as $videoDiv) {
                        if ($videoDiv->hasAttributes()) {
                            $styles = explode(';', $videoDiv->getAttribute('style'));
                            foreach ($styles as $style) {
                                $style = str_replace(' ', '', $style);
                                if (strstr($style, 'background-image:')) {
                                    $img = str_replace(['background-image:url(\'', '\')'], '', $style);
                                    $post->images[] = $img;
                                }
                            }
                        }
                    }
                    $timeDivs = $xpath->query('.//*[@datetime]', $element);
                    foreach ($timeDivs as $timeDiv) {
                        if ($timeDiv->hasAttributes()) {
                            $post->time = strtotime($timeDiv->getAttribute('datetime'));
                        }
                    }
                    $imageDivs = $xpath->query('.//*[contains(@class, "tgme_widget_message_photo_wrap")]', $element);
                    foreach ($imageDivs as $imgDiv) {
                        if ($imgDiv->hasAttributes()) {
                            $styles = explode(';', $imgDiv->getAttribute('style'));
                            foreach ($styles as $style) {
                                $style = str_replace(' ', '', $style);
                                if (strstr($style, 'background-image:')) {
                                    $img = str_replace(['background-image:url(\'', '\')'], '', $style);
                                    $post->images[] = $img;
                                }
                            }
                        }
                    }
                }
            }

            if (isset($post) && $post) {
                $id = false;
                $text = esc_sql(wp_encode_emoji($post->text));
                $tgid = esc_sql($post->tgid);
                $time = esc_sql($post->time);
                $row = $wpdb->get_row("SELECT ID FROM `wp_tgparser` WHERE tgid = '$tgid'");
                if ($row) {
                    $wpdb->update('wp_tgparser', ['tgid' => $tgid, 'text' => $text, 'channel' => $url, 'datetime' => $time], ['ID' => $row->ID]);
                    $id = $row->ID;
                } else {
                    $result = $wpdb->insert('wp_tgparser', ['tgid' => $tgid, 'text' => $text, 'channel' => $url, 'datetime' => $time]);
                    if ($result) $id = $wpdb->insert_id;
                }
                if ($id) {
                    $dir = wp_upload_dir()['subdir'] . '/tg/' . $id;
                    wp_mkdir_p(wp_upload_dir()['basedir'] . $dir);
                    foreach ($post->images as $key => $image) {
                        $attachment_id = false;
                        $row = $wpdb->get_row("SELECT ID FROM `wp_tgparser_attachments` WHERE tg_path = '$image'");
                        if (!$row) {
                            $imageContent = file_get_contents($image);
                            $file = file_put_contents('/tmp/tgparser', $imageContent);
                            $name = TgParser::randomString(20) . ($key + 1);
                            switch (mime_content_type('/tmp/tgparser')) {
                                case "image/jpeg":
                                    $name .= '.jpg';
                                    break;
                                case "image/png":
                                    $name .= '.png';
                                    break;
                                case "image/gif":
                                    $name .= '.gif';
                                    break;
                                case "image/wepb":
                                    $name .= '.wepb';
                                    break;
                                case "image/svg+xml":
                                    $name .= '.svg';
                                    break;
                            }
                            $file = file_put_contents(wp_upload_dir()['basedir'] . $dir . DIRECTORY_SEPARATOR . $name, $imageContent);
                            $attach_id = wp_insert_attachment(
                                array(
                                    'guid' => wp_upload_dir()['baseurl'] . $dir . DIRECTORY_SEPARATOR . $name,
                                    'post_title' => '',
                                    'post_excerpt' => '',
                                    'post_content' => '',
                                    'post_mime_type' => mime_content_type('/tmp/tgparser'),
                                )
                            );
                            $wpdb->insert('wp_tgparser_attachments', ['tgparser_id' => $id, 'tg_path' => $image, 'image' => $attach_id]);
                            usleep(200000);
                        }
                    }
                    wp_redirect(admin_url() . "options-general.php?page=tgparser-plugin-post&post=$id");
                    exit;
                }
                usleep(1000000);
            }
        }
    }
    if (isset($_REQUEST['action']) && isset($_REQUEST['post']) && (int)$_REQUEST['post']) {
        switch ($_REQUEST['action']) {
            case "tgparser-create":
                $row = $wpdb->get_row("SELECT * FROM `wp_tgparser` WHERE id = " . (int)$_REQUEST['post']);
                if ($row) {
                    $wpdb->update('wp_tgparser', ['posted' => 1], ['id' => $_REQUEST['post']]);
                    $text = $row->text;

                    $images = $wpdb->get_results("SELECT * FROM `wp_tgparser_attachments` WHERE tgparser_id = " . $row->id . "");
                    foreach ($images as $image) {
                        $attach = $wpdb->get_row("SELECT * FROM `wp_posts` WHERE ID = " . $image->image . "");
                        $text .=  "<!-- wp:image {\"className\":\"size-full\"} --> <figure class=\"wp-block-image size-full\"><img src=\"" . $attach->guid . "\" alt=\"\"/></figure> <!-- /wp:image -->";
                    }

                    $text .= "<br/>";
                    $text .= "<br/>";
                    $text .= "<a href=\"https://t.me/" . $row->tgid . "\">Источник</a>";
                    $text .= "<br/>";

                    $data = [
                        'post_content' => stripslashes($text),
                        'post_date' => wp_date("Y-m-d H:i:s", $row->datetime),
                        'post_date_gmt' => date("Y-m-d H:i:s", $row->datetime)
                    ];

                    if (isset($_REQUEST['post_id']) && (int)$_REQUEST['post_id']) {
                        $post = $wpdb->get_row("SELECT post_content, ID FROM `wp_posts` WHERE ID = " . (int)$_REQUEST['post_id'] . "");
                        if ($post) {
                            $data['post_content'] = $post->post_content . '<hr/>' . $data['post_content'];
                            $data['ID'] = $post->ID;
                        }
                    }

                    $post_id = wp_insert_post(wp_slash($data));
                    $post_id = isset($data['ID']) ? $data['ID'] : $post_id;

                    wp_redirect(admin_url() . "post.php?post=$post_id&action=edit");
                    exit;
                }
                break;
            case "tgparser-del":
                $wpdb->update('wp_tgparser', ['posted' => 2], ['id' => $_REQUEST['post']]);
                break;
        }
    }
}
add_action('admin_init', 'tgparser_admin');

function tgparser_render_plugin_posts_page()
{
    global $wpdb;

    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'tgparser-add' && isset($_REQUEST['post']) && (int)$_REQUEST['post']) {
        $my_posts = get_posts(array(
            'numberposts' => 50,
            // 'category'    => 0,
            'orderby'     => 'date',
            'order'       => 'DESC',
            // 'include'     => array(),
            // 'exclude'     => array(),
            // 'meta_key'    => '',
            // 'meta_value'  => '',
            'post_type'   => 'post',
            'post_status' => 'any'
            // 'suppress_filters' => true, 
        ));

        global $post;
    ?>
        <style>
            .tgparser-post-table img {
                max-width: 200px;
            }

            .tgparser-post-table .wp-block-image {
                display: inline-block;
                margin: 0 .5rem .5rem;
            }
        </style>
        <div style="padding: 1rem 1rem 1rem 0rem;">
            <table class="tgparser-post-table" cellpadding="5" cellspacing="0" border="1" width="100%" style="margin-bottom:1rem;">
                <thead>
                    <tr>
                        <th>Пост</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    foreach ($my_posts as $post) { ?>
                        <tr>
                            <?php
                            setup_postdata($post);
                            ?>
                            <td>
                                <?php the_content(); ?>
                            </td>
                            <td>
                                <a href="/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-create&post=<?php echo (int)$_REQUEST['post'] ?>&post_id=<?php the_ID(); ?>" class="button action">Добавить в пост</a>
                            </td>
                        <?php
                    }

                    wp_reset_postdata(); ?>
                        </tr>
                        <?php
                        ?>
                </tbody>
            </table>
        </div>
    <?php
        return;
    }
    ?>


    <style>
        .tgparser-post-table img {
            max-width: 200px;
        }

        .tgparser-post-table .wp-block-image {
            display: inline-block;
            margin: 0 .5rem .5rem;
        }
    </style>

    <?php

    $results = $wpdb->get_results("SELECT * FROM `wp_tgparser` WHERE posted = 0 ORDER BY datetime DESC");
    $html = "<div style=\"padding: 1rem 1rem 1rem 0rem;\">";
    $html .= "<table class=\"tgparser-post-table\" cellpadding=\"5\" cellspacing=\"0\" border width=\"100%\" style=\"margin-bottom:1rem;\">";
    $html .= "<thead>";
    $html .= "<tr>";
    $html .= "<th>Канал</th>";
    $html .= "<th>Дата</th>";
    $html .= "<th>Текст</th>";
    $html .= "<th></th>";
    $html .= "</tr>";
    $html .= "</thead>";
    $html .= "<tbody>";
    foreach ($results as $result) {
        // var_dump($result);

        $html .= "<tr>";
        $html .= "<td>" . str_replace(["https://t.me/s/", "https://t.me/"], "", $result->channel) . "</td>";
        $html .= "<td style=\"white-space:nowrap;\">" . wp_date("d.m.Y H:i", $result->datetime) . "</td>";
        $html .= "<td>" . stripslashes($result->text) . "<br/>";

        $images = $wpdb->get_results("SELECT * FROM `wp_tgparser_attachments` WHERE tgparser_id = " . $result->id . "");

        foreach ($images as $image) {
            $attach = $wpdb->get_row("SELECT * FROM `wp_posts` WHERE ID = " . $image->image . "");
            $html .=  "<img src=\"" . $attach->guid . "\" alt=\"\"/><br/>";
        }

        $html .= "</td>";
        $html .= "<td>";
        $html .= "<a href=\"/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-create&post={$result->id}\" class=\"button action\">Создать пост</a><br/><br/>";
        $html .= "<a href=\"/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-add&post={$result->id}\" class=\"button action\">Добавить в существующий</a><br/><br/>";
        $html .= "<a href=\"/wp-admin/options-general.php?page=tgparser-plugin-posts&action=tgparser-del&post={$result->id}\" class=\"button action\">Удалить из ленты</a>";
        $html .= "</td>";
        $html .= "</tr>";
    }
    $html .= "</tbody>";
    $html .= "</table>";
    echo $html;
    ?>
<?php
}

function parse_tg_channels()
{
    global $wpdb;
    $options = get_option('tgparser_plugin_options');
    $channels = $options['channels'] ?? [];
    foreach ($channels as $channel) {

        $url = esc_attr($channel['channel']);
        if (!$url) continue;

        $parser = new TgParser($url);

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($parser->getHtml());
        $xpath = new DOMXpath($dom);
        $elements = $xpath->query('//*[@data-post]');

        $posts = [];

        foreach ($elements as $element) {
            $post = new TgPost();
            $post->tgid = $element->getAttribute('data-post');

            $textDivs = $xpath->query('.//*[contains(@class, "tgme_widget_message_text")]', $element);
            foreach ($textDivs as $textDiv) {
                $doc = $textDiv->ownerDocument;
                $html = '';
                foreach ($textDiv->childNodes as $node) {
                    if ($node->hasAttributes()) $node->removeAttribute('onclick');
                    $html .= $doc->saveHTML($node);
                }
                $post->text = $html;
            }
            $videoDivs = $xpath->query('.//*[contains(@class, "tgme_widget_message_video_thumb")]', $element);
            foreach ($videoDivs as $videoDiv) {
                if ($videoDiv->hasAttributes()) {
                    $styles = explode(';', $videoDiv->getAttribute('style'));
                    foreach ($styles as $style) {
                        $style = str_replace(' ', '', $style);
                        if (strstr($style, 'background-image:')) {
                            $img = str_replace(['background-image:url(\'', '\')'], '', $style);
                            $post->images[] = $img;
                        }
                    }
                }
            }
            $timeDivs = $xpath->query('.//*[@datetime]', $element);
            foreach ($timeDivs as $timeDiv) {
                if ($timeDiv->hasAttributes()) {
                    $post->time = strtotime($timeDiv->getAttribute('datetime'));
                }
            }
            $imageDivs = $xpath->query('.//*[contains(@class, "tgme_widget_message_photo_wrap")]', $element);
            foreach ($imageDivs as $imgDiv) {
                if ($imgDiv->hasAttributes()) {
                    $styles = explode(';', $imgDiv->getAttribute('style'));
                    foreach ($styles as $style) {
                        $style = str_replace(' ', '', $style);
                        if (strstr($style, 'background-image:')) {
                            $img = str_replace(['background-image:url(\'', '\')'], '', $style);
                            $post->images[] = $img;
                        }
                    }
                }
            }

            $posts[] = $post;
        }
        foreach ($posts as $post) {
            $id = false;
            $text = esc_sql(wp_encode_emoji($post->text));
            $tgid = esc_sql($post->tgid);
            $time = esc_sql($post->time);
            $row = $wpdb->get_row("SELECT ID FROM `wp_tgparser` WHERE tgid = '$tgid'");
            if ($row) {
                $wpdb->update('wp_tgparser', ['tgid' => $tgid, 'text' => $text, 'channel' => $url, 'datetime' => $time], ['ID' => $row->ID]);
                $id = $row->ID;
            } else {
                $result = $wpdb->insert('wp_tgparser', ['tgid' => $tgid, 'text' => $text, 'channel' => $url, 'datetime' => $time]);
                if ($result) $id = $wpdb->insert_id;
            }
            if ($id) {
                $dir = wp_upload_dir()['subdir'] . '/tg/' . $id;
                wp_mkdir_p(wp_upload_dir()['basedir'] . $dir);
                foreach ($post->images as $key => $image) {
                    $attachment_id = false;
                    $row = $wpdb->get_row("SELECT ID FROM `wp_tgparser_attachments` WHERE tg_path = '$image'");
                    if (!$row) {
                        $imageContent = file_get_contents($image);
                        $file = file_put_contents('/tmp/tgparser', $imageContent);
                        $name = TgParser::randomString(20) . ($key + 1);
                        switch (mime_content_type('/tmp/tgparser')) {
                            case "image/jpeg":
                                $name .= '.jpg';
                                break;
                            case "image/png":
                                $name .= '.png';
                                break;
                            case "image/gif":
                                $name .= '.gif';
                                break;
                            case "image/wepb":
                                $name .= '.wepb';
                                break;
                            case "image/svg+xml":
                                $name .= '.svg';
                                break;
                        }
                        $file = file_put_contents(wp_upload_dir()['basedir'] . $dir . DIRECTORY_SEPARATOR . $name, $imageContent);
                        $attach_id = wp_insert_attachment(
                            array(
                                'guid' => wp_upload_dir()['baseurl'] . $dir . DIRECTORY_SEPARATOR . $name,
                                'post_title' => '',
                                'post_excerpt' => '',
                                'post_content' => '',
                                'post_mime_type' => mime_content_type('/tmp/tgparser'),
                            )
                        );
                        $wpdb->insert('wp_tgparser_attachments', ['tgparser_id' => $id, 'tg_path' => $image, 'image' => $attach_id]);
                        usleep(200000);
                    }
                }
            }
            usleep(1000000);
        }
    }
}
