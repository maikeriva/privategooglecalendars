<?php
/*
Plugin Name: Private Google Calendars
Description: Display multiple private Google Calendars
Plugin URI: http://blog.michielvaneerd.nl/private-google-calendars/
Version: 20191211
Author: Michiel van Eerd
Author URI: http://michielvaneerd.nl/
License: GPL2
*/

// Always set this to the same version as "Version" in header! Used for query parameters added to style and scripts.
define('PGC_PLUGIN_VERSION', '20191211');

if (!class_exists('PGC_GoogleClient')) {
  require_once(plugin_dir_path(__FILE__) . 'lib/google-client.php');
}

define('PGC_PLUGIN_NAME', __('Private Google Calendars'));

define('PGC_NOTICES_VERIFY_SUCCESS', __('Verify OK!'));
define('PGC_NOTICES_REVOKE_SUCCESS', __('Access revoked. This plugin does not have access to your calendars anymore.'));
define('PGC_NOTICES_REMOVE_SUCCESS', sprintf(__('Plugin data removed. Make sure to also manually revoke access to your calendars in the Google <a target="__blank" href="%s">Permissions</a> page!'), 'https://myaccount.google.com/permissions'));
define('PGC_NOTICES_CALENDARLIST_UPDATE_SUCCESS', __('Calendars updated.'));
define('PGC_NOTICES_CACHE_DELETED', __('Cache deleted.'));

define('PGC_ERRORS_CLIENT_SECRET_MISSING', __('No client secret.'));
define('PGC_ERRORS_CLIENT_SECRET_INVALID', __('Invalid client secret.'));
define('PGC_ERRORS_ACCESS_TOKEN_MISSING', __('No access token.'));
define('PGC_ERRORS_REFRESH_TOKEN_MISSING', sprintf(__('Your refresh token is missing!<br><br>This can only be solved by manually revoking this plugin&#39;s access in the Google <a target="__blank" href="%s">Permissions</a> page and remove all plugin data.'), 'https://myaccount.google.com/permissions'));
define('PGC_ERRORS_ACCESS_REFRESH_TOKEN_MISSING', __('No access and refresh tokens.'));
define('PGC_ERRORS_REDIRECT_URI_MISSING', __('URI <code>%s</code> missing in the client secret file. Adjust your Google project and upload the new client secret file.'));
define('PGC_ERRORS_INVALID_FORMAT', __('Invalid format'));
define('PGC_ERRORS_NO_CALENDARS', __('No calendars'));
define('PGC_ERRORS_NO_SELECTED_CALENDARS',  __('No selected calendars'));

define('PGC_TRANSIENT_PREFIX', 'pgc_ev_');
define('PGC_EVENTS_MAX_RESULTS', 100);

// Priority for the enqueue css and javascript.
// We need to be sure to load them after the Wordpress theme css files, so we can override some things to make the fullcalendar look good.
// If someone wants to override this or add their own styles, they have to enqueue their style with a higher priority.
define('PGC_ENQUEUE_ACTION_PRIORITY', 11);

/**
 * Add shortcode.
 */
add_action('init', 'pgc_shortcodes_init');
function pgc_shortcodes_init() {
  add_shortcode('pgc', 'pgc_shortcode');
  pgc_register_block();
}

function pgc_register_block() {
  
  $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');
  
  wp_register_script(
    'pgc-plugin-script',
    plugins_url('build/index.js', __FILE__),
    $asset_file['dependencies'],
    PGC_PLUGIN_VERSION
  );

  wp_register_style('pgc-plugin-style',
    plugins_url('css/block-style.css', __FILE__),
    ['wp-edit-blocks'],
    PGC_PLUGIN_VERSION);

  register_block_type('pgc-plugin/calendar', array(
    'editor_script' => 'pgc-plugin-script',
    'editor_style' => 'pgc-plugin-style'
  ));

  // Make the selected calendars available for the block.
  $selectedCalendarIds = get_option('pgc_selected_calendar_ids', []);
  $calendarList = getDecoded('pgc_calendarlist', []);
  $selectedCalendars = [];
  foreach ($calendarList as $calendar) {
    if (in_array($calendar['id'], $selectedCalendarIds)) {
      $selectedCalendars[$calendar['id']] = $calendar;
    }
  }
  wp_add_inline_script('pgc-plugin-script', 'window.pgc_selected_calendars=' . json_encode($selectedCalendars) . ';', 'before');

}

function pgc_shortcode($atts = [], $content = null, $tag) {

  // When we have no attributes, $atts is an empty string
  if (!is_array($atts)) {
    $atts = [];
  }
  
  // Very wierd: you can enter uppercase in attribute name
  // but after parsing they will have all lowercase...
  // So  we have to match lowercase with known camelCase fullCalendar properties...
  // "It should be noted that even though attributes can be used with mixed case in the editor, they will always be lowercase after parsing."
  // https://codex.wordpress.org/Shortcode_API#Attributes

  // Add some default fullcalendar options.
  // See for available options: https://fullcalendar.io/docs/
  // We accept nested attributes like this:
  // [pgc header-left="today" header-center="title"] which becomes:
  // ['header' => ['left' => 'today', 'ccenter' => 'title']]
  $defaultConfig = [
    'header' => [
      'left' => 'prev,next today',
      'center' => 'title',
      'right' => 'dayGridMonth,timeGridWeek,listWeek'
    ]
  ];
  $userConfig = $defaultConfig; // copy
  $userFilter = 'true';
  $userEventPopup = 'true';
  $userEventLink = 'false';
  $userHidePassed = 'false';
  $userHideFuture = 'false';
  $userEventDescription = 'false';
  $userEventLocation = 'false';
  $userEventAttendees = 'false';
  $userEventAttachments = 'false';
  $userEventCreator = 'false';
  $userEventCalendarname = 'false';
  $calendarIds = '';
  // Get all non-fullcalendar known properties
  foreach ($atts as $key => $value) {
    if ($key === 'filter') {
      $userFilter = $value;
      continue;
    }
    if ($key === 'eventpopup') {
      $userEventPopup = $value;
      continue;
    }
    if ($key === 'eventlink') {
      $userEventLink = $value;
      continue;
    }
    if ($key === 'hidepassed') {
      $userHidePassed = $value;
      continue;
    }
    if ($key === 'hidefuture') {
      $userHideFuture = $value;
      continue;
    }
    if ($key === 'eventdescription') {
      $userEventDescription = $value;
      continue;
    }
    if ($key === 'eventattachments') {
      $userEventAttachments = $value;
      continue;
    }
    if ($key === 'eventattendees') {
      $userEventAttendees = $value;
      continue;
    }
    if ($key === 'eventlocation') {
      $userEventLocation = $value;
      continue;
    }
    if ($key === 'eventcreator') {
      $userEventCreator = $value;
      continue;
    }
    if ($key === 'eventcalendarname') {
      $userEventCalendarname = $value;
      continue;
    }
    if ($key === 'calendarids' && !empty($value)) {
      $calendarIds = $value; // comma separated string
      continue;
    }
    if ($key === 'fullcalendarconfig') {
      // A JSON string that we can directly send to FullCalendar
      $userConfig = json_decode($value, true);
    } else {
      // Fullcalendar properties that get passed to fullCalendar instance.
      $parts = explode('-', $key);
      $partsCount = count($parts);
      if ($partsCount > 1) {
        $currentUserConfigLayer = &$userConfig;
        for ($i = 0; $i < $partsCount; $i++) {
          $part = $parts[$i];
          if ($i + 1 === $partsCount) {
            if ($value === 'true') {
              $value = true;
            } elseif ($value === 'false') {
              $value = $value;
            }
            $currentUserConfigLayer[$part] = $value;
          } else {
            if (!array_key_exists($part, $currentUserConfigLayer)) {
              $currentUserConfigLayer[$part] = [];
            }
            $currentUserConfigLayer = &$currentUserConfigLayer[$part];
          }
        }
      } else {
        $userConfig[$key] = $value;
      }
    }
    
  }

  $dataCalendarIds = '';
  if (!empty($calendarIds)) {
    $dataCalendarIds = 'data-calendarids=\'' . json_encode(explode(',', $calendarIds)) . '\'';
  }

  return '<div class="pgc-calendar-wrapper pgc-calendar-page"><div class="pgc-calendar-filter"></div><div ' . $dataCalendarIds . ' data-filter=\'' . $userFilter . '\' data-eventpopup=\'' . $userEventPopup . '\' data-eventlink=\'' . $userEventLink . '\' data-eventdescription=\'' . $userEventDescription . '\' data-eventlocation=\'' . $userEventLocation . '\' data-eventattachments=\'' . $userEventAttachments . '\' data-eventattendees=\'' . $userEventAttendees . '\' data-eventcreator=\'' . $userEventCreator . '\' data-eventcalendarname=\'' . $userEventCalendarname . '\' data-hidefuture=\'' . $userHideFuture . '\' data-hidepassed=\'' . $userHidePassed . '\' data-config=\'' . json_encode($userConfig) . '\' data-locale="' . get_locale() . '" class="pgc-calendar"></div></div>';
}

/**
 * Add CSS and Javascript for admin.
 */
add_action('admin_enqueue_scripts', 'pgc_admin_enqueue_scripts');
function pgc_admin_enqueue_scripts($hook) {
  if ($hook === 'settings_page_pgc' || $hook === 'widgets.php') {
    wp_enqueue_script('pgc-admin', plugin_dir_url(__FILE__) . 'js/pgc-admin.js', null, PGC_PLUGIN_VERSION);
    wp_enqueue_style('pgc-admin', plugin_dir_url(__FILE__) . 'css/pgc-admin.css', null, PGC_PLUGIN_VERSION);
  }
}

/**
 * Add CSS and Javascript for frontend.
 */
//add_action('wp_enqueue_scripts', 'pgc_enqueue_scripts', PHP_INT_MAX);
add_action('wp_enqueue_scripts', 'pgc_enqueue_scripts', PGC_ENQUEUE_ACTION_PRIORITY);
// make sure we load last after theme files so we can override.
function pgc_enqueue_scripts() {
  wp_enqueue_style('dashicons');
  wp_enqueue_style('fullcalendar',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/core/main.min.css', null, PGC_PLUGIN_VERSION);
  wp_enqueue_style('fullcalendar_daygrid',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/daygrid/main.min.css', ['fullcalendar'], PGC_PLUGIN_VERSION);
  wp_enqueue_style('fullcalendar_timegrid',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/timegrid/main.min.css', ['fullcalendar_daygrid'], PGC_PLUGIN_VERSION);
  wp_enqueue_style('fullcalendar_list',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/list/main.min.css', ['fullcalendar'], PGC_PLUGIN_VERSION);
  wp_enqueue_style('pgc',
      plugin_dir_url(__FILE__) . 'css/pgc.css', ['fullcalendar_timegrid'], PGC_PLUGIN_VERSION);
  wp_enqueue_style('tippy_light',
      plugin_dir_url(__FILE__) . 'lib/tippy/light-border.css', null, PGC_PLUGIN_VERSION);
  wp_enqueue_script('popper',
      plugin_dir_url(__FILE__) . 'lib/popper.min.js', null, PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('tippy',
      plugin_dir_url(__FILE__) . 'lib/tippy/tippy-bundle.iife.min.js', ['popper'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('my_moment',
      plugin_dir_url(__FILE__) . 'lib/moment/moment-with-locales.min.js', null, PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('my_moment_timezone',
      plugin_dir_url(__FILE__) . 'lib/moment/moment-timezone-with-data.min.js', ['my_moment'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/core/main.min.js', ['my_moment_timezone'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar_moment',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/moment/main.min.js', ['fullcalendar'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar_moment_timezone',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/moment-timezone/main.min.js', ['fullcalendar_moment'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar_daygrid',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/daygrid/main.min.js', ['fullcalendar'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar_timegrid',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/timegrid/main.min.js', ['fullcalendar_daygrid'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar_list',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/list/main.min.js', ['fullcalendar'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('fullcalendar_locales',
      plugin_dir_url(__FILE__) . 'lib/fullcalendar4/core/locales-all.min.js',
      ['fullcalendar'], PGC_PLUGIN_VERSION, true);
  wp_enqueue_script('pgc', plugin_dir_url(__FILE__) . 'js/pgc.js',
      ['fullcalendar'], PGC_PLUGIN_VERSION, true);
  $nonce = wp_create_nonce('pgc_nonce');
  wp_localize_script('pgc', 'pgc_object', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => $nonce,
  ]);
  
}

/**
 * Validates date that should be in MySQL format (Y-m-d) optionally with T00:00:00 appended.
 * @return valid $date with T00:00:00 time format appended or false
 */
function pgc_validate_date($date) {
  if (preg_match("/^\d{4}\-\d{2}\-\d{2}$/", $date)) {
    return $date . 'T00:00:00';
  }
  if (preg_match("/^\d{4}\-\d{2}\-\d{2}T\d{2}:\d{2}:\d{2}$/", $date)) {
    return $date;
  }
  return false;
}

/**
 * Handle AJAX request from frontend.
 */
add_action('wp_ajax_pgc_ajax_get_calendar', 'pgc_ajax_get_calendar');
add_action('wp_ajax_nopriv_pgc_ajax_get_calendar', 'pgc_ajax_get_calendar');
function pgc_ajax_get_calendar() {

  check_ajax_referer('pgc_nonce');

  try {
  
    if (empty($_POST['start']) || empty($_POST['end'])) {
      throw new Exception(PGC_ERRORS_INVALID_FORMAT);
    }

    $start = pgc_validate_date($_POST['start']);
    if (!$start) {
      throw new Exception(PGC_ERRORS_INVALID_FORMAT);
    }
    $end = pgc_validate_date($_POST['end']);
    if (!$end) {
      throw new Exception(PGC_ERRORS_INVALID_FORMAT);
    }

    $calendarIds = get_option('pgc_selected_calendar_ids');
    if (empty($calendarIds) || !is_array($calendarIds)) {
      throw new Exception(PGC_ERRORS_NO_SELECTED_CALENDARS);
    }
    $thisCalendarids = $calendarIds;
    if (array_key_exists('thisCalendarids', $_POST) && !empty($_POST['thisCalendarids'])) {
      $postedCalendarIds = explode(',', $_POST['thisCalendarids']);
      if (!empty($postedCalendarIds)) {
        $thisCalendarids = [];
        foreach ($calendarIds as $calId) {
          if (in_array($calId, $postedCalendarIds)) {
            $thisCalendarids[] = $calId;
          }
        }
      }
    }

    //$calendarList = getDecoded('pgc_calendarlist');
    //if (empty($calendarList)) {
    //  throw new Exception(PGC_ERRORS_NO_CALENDARS);
    //}

    $cacheTime = get_option('pgc_cache_time'); // empty == no cache!

    // We can have mutiple calendars with different calendar selections,
    // so key should be including calendar selection.
    $transientKey = PGC_TRANSIENT_PREFIX . $start . $end . md5(implode('-', $thisCalendarids));
    
    $transientItems = !empty($cacheTime) ? get_transient($transientKey) : false;

    $calendarListByKey = pgc_get_calendars_by_key($thisCalendarids);

    if ($transientItems !== false) {
      // We have a transient for this request, so serve it.
      wp_send_json(['items' => $transientItems, 'calendars' => $calendarListByKey]);
      wp_die();
    }

  // We don't have a transient, so query Google and save it in a transient.
    $client = getGoogleClient(true);
    if ($client->isAccessTokenExpired()) {
      if (!$client->getRefreshTOken()) {
        throw new Exception(PGC_ERRORS_REFRESH_TOKEN_MISSING);
      }
      $client->refreshAccessToken();
    }
    $service = new PGC_GoogleCalendarClient($client);
    
    $optParams = array(
      'maxResults' => PGC_EVENTS_MAX_RESULTS,
      'orderBy' => 'startTime',
      'singleEvents' => 'true',
      'timeMin' => $start . 'Z',
      'timeMax' => $end . 'Z'
    );

    $results = [];
    foreach ($thisCalendarids as $calendarId) {
      $results[$calendarId] = $service->getEvents($calendarId, $optParams);
    }

    //var_dump($results); exit;
    
    $items = [];
    foreach ($results as $calendarId => $events) {
      foreach ($events as $item) {
        $newItem = [
          'title' => $item['summary'],
          'htmlLink' => $item['htmlLink'],
          'description' => !empty($item['description']) ? $item['description'] : '',
          'calId' => $calendarId,
          'creator' => !empty($item['creator']) ? $item['creator'] : [],
          'attendees' => !empty($item['attendees']) ? $item['attendees'] : [],
          'attachments' => !empty($item['attachments']) ? $item['attachments'] : [],
          'location' => !empty($item['location']) ? $item['location'] : ''
        ];
        if (!empty($item['start']['date'])) {
          $newItem['allDay'] = true;
          $newItem['start'] = $item['start']['date'];
          $newItem['end'] = $item['end']['date'];
        } else {
          $newItem['start'] = $item['start']['dateTime'];
          $newItem['end'] = $item['end']['dateTime'];
        }
        $items[] = $newItem; 
      }
    }
    
    if (!empty($cacheTime)) {
      set_transient($transientKey, $items, $cacheTime * MINUTE_IN_SECONDS);
    }

    wp_send_json(['items' => $items, 'calendars' => $calendarListByKey]);
    wp_die();
  } catch (PGC_GoogleClient_RequestException $ex) {
    wp_send_json([
      'error' => $ex->getMessage(),
      'errorCode' => $ex->getCode(),
      'errorDescription' => $ex->getDescription()]);
    wp_die();
  } catch (Exception $ex) {
    wp_send_json([
      'error' => $ex->getMessage(),
      'errorCode' => $ex->getCode()]);
    wp_die();
  }
}

function pgc_get_calendars_by_key($calendarIds) {
  $calendarList = getDecoded('pgc_calendarlist');
  if (empty($calendarList)) {
    throw new Exception(PGC_ERRORS_NO_CALENDARS);
  }
  $calendarListByKey = [];
  foreach ($calendarList as $cal) {
    if (array_search($cal['id'], $calendarIds) === false) continue;
    $calendarListByKey[$cal['id']] = [
      'summary' => $cal['summary'],
      'backgroundColor' => $cal['backgroundColor']
    ];
  }
  return $calendarListByKey;
}

/**
 * Add new settings page to menu.
 */
add_action('admin_menu', 'pgc_settings_page');
function pgc_settings_page() {
  $page = add_options_page(
      PGC_PLUGIN_NAME,
      PGC_PLUGIN_NAME,
      'manage_options',
      'pgc',
      'pgc_settings_page_html');
  add_action('load-' . $page, 'pgc_admin_add_help_tab');
  add_action('load-' . $page, 'pgc_admin_add_faq_tab');
  add_action('load-' . $page, 'pgc_admin_add_shortcode_tab');
}

/**
 * Callback function that outputs settings page.
 */
function pgc_settings_page_html() {
  if (!current_user_can('manage_options')) {
    return;
  }

  ?>
  <div class="wrap">
  <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
  <p><?php _e('See the <a class="pgc-link" onclick="pgc_fire_help_tab_click()">Help</a> tab for information.'); ?></p>
  <?php
    
    $clientSecretError = '';
    $clientSecret = pgc_get_valid_client_secret($clientSecretError);

    if (empty($clientSecret) || !empty($clientSecretError)) {
      echo '<h2>' . __('Step 1: Upload client secret') . '</h2>';
      pgc_show_settings();
    } else {
      // Valid Client Secret, check access and refresh tokens
      $accessToken = getDecoded('pgc_access_token');
      $refreshToken = get_option('pgc_refresh_token');

      if (empty($accessToken)) {
        echo '<h2 style="opacity:1; color:green;">' . __('Step 1: Upload client secret') . ' &#10003;</h2>';
        echo '<h2>' . __('Step 2: Authorize') . '</h2>';
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
          <input type="hidden" name="action" value="pgc_authorize">
          <?php submit_button(__('Authorize')); ?>
        </form>
        <?php
      } else {
        // CalendarList
        $okay = get_option('pgc_selected_calendar_ids') ? '&#10003;' : '';
        echo '<h2 style="opacity:1; color:green;">' . __('Step 1: Upload client secret') . ' &#10003;</h2>';
        echo '<h2 style="opacity:1; color:green;">' . __('Step 2: Authorize') . ' &#10003;</h2>';
        if (get_option('pgc_selected_calendar_ids')) {
          echo '<h2 style="opacity:1; color:green;">' . __('Step 3: Select calendars') . ' &#10003;</h2>';
        } else {
          echo '<h2>' . __('Step 3: Select calendars') . '</h2>';
        }
        echo '<p>' . __('Select the calendars you want to display.') . '</p>';
        pgc_show_settings();
      }
    }

  ?>

    <?php pgc_show_tools(); ?>

  </div><!-- .wrap -->
  <?php

}

/**
 * Main function for showing settings page.
 */
 function pgc_show_settings() {
  ?>
  <form enctype="multipart/form-data" action="options.php"
      method="post" id="pgc-settings-form" onsubmit="return pgc_on_submit();">
    <?php settings_fields('pgc'); ?>
    <?php do_settings_sections('pgc'); ?>
    <?php submit_button(__('Save'), 'primary', 'pgc-settings-submit'); ?>
  </form>
  <?php
}

/**
 * Callback function to add faq help tab.
 */
 function pgc_admin_add_shortcode_tab() {
  $screen = get_current_screen();
  $screen->add_help_tab([
    'id' => 'pgc_shortcode_tab',
    'title' => __('Shortcode usage'),
    'callback' => 'pgc_help_tab_shortcode'
  ]);
}

/**
 * Callback function that outputs shortcode help tab.
 */
 function pgc_help_tab_shortcode() {
  ?>
  <p><?php printf(__('See <a href="%s" target="__blank">examples and options</a>.'), 'https://blog.michielvaneerd.nl/private-google-calendars/'); ?></p>
  <?php
}

/**
 * Callback function to add faq help tab.
 */
function pgc_admin_add_faq_tab() {
  $screen = get_current_screen();
  $screen->add_help_tab([
    'id' => 'pgc_faq_tab',
    'title' => __('FAQ'),
    'callback' => 'pgc_help_tab_faq'
  ]);
}

/**
 * Callback function that outputs faq help tab.
 */
function pgc_help_tab_faq() {
  ?>
  <p><strong><?php _e('I get a \'Token has been expired or revoked\' error'); ?></strong></p>
  <p><?php printf(__('This usually means you don\'t have a valid access or refresh token anymore. This can only be solved by manually revoke access on the Google <a href="%s" target="__blank">Permissions</a> page and remove all plugin data.'), 'https://myaccount.google.com/permissions'); ?></p>
  <p><strong><?php _e('I get an \'Error: redirect_uri_mismatch\' error when I want to authorize'); ?></strong></p>
  <p><?php printf(__('This means that you didn\'t add your current URL <code>%s</code> to the authorized redirect URIs as explained in the Getting Started section.'), admin_url('options-general.php?page=pgc')); ?></p>
  <p><strong><?php _e('How can I override the calendar look?'); ?></strong></p>
  <p><?php printf(__('Create a child theme and enqueue a css file with a dependency on <em>fullcalendar</em> for example:<br><code>%s</code>.'), 'wp_enqueue_style(\'fullcalendar-override\', get_stylesheet_directory_uri() . \'/fullcalendar-override.css\', [\'fullcalendar\']);'); ?></p>
  <?php
}

/**
 * Callback function to add getting started help tab.
 */
function pgc_admin_add_help_tab() {
  $screen = get_current_screen();
  $screen->add_help_tab([
    'id' => 'pgc_help_tab',
    'title' => __('Getting started'),
    'callback' => 'pgc_help_tab_getting_started'
  ]);
}

// TODO: add some examples of shorcode usage with attributes (nested!)
// and also widget use.

/**
 * Callback function that outputs getting started help tab.
 */
function pgc_help_tab_getting_started() {
  ?>
  <ol>
    <li><?php printf(__('First setup a Google project. <a target="__blank" href="%s">Read the instructions</a>. And make sure you use <code>%s</code> as the redirect URL!'), 'https://blog.michielvaneerd.nl/private-google-calendars/setup/', admin_url('options-general.php?page=pgc')); ?></li>
    <li><?php _e('Download the client secret and upload this file in step 1.'); ?></li>
    <li><?php _e('Authorize the plugin to access the calendar(s) in step 2.'); ?></li>
    <li><?php _e('Select the calendar(s) you want to display in step 3.'); ?></li>
    <li><?php _e('Use the widget or the shortcode <code>[pgc]</code> to dispay the selected calendar(s).'); ?></li>
  </ol>
  <?php
}

/**
 * Outputs tools section.
 */
function pgc_show_tools() {
  global $wpdb;

  $clientSecretError = '';
  $clientSecret = pgc_get_valid_client_secret($clientSecretError);
  if (empty($clientSecret)) {
    return;
  }
  $accessToken = getDecoded('pgc_access_token');
  $refreshToken = get_option('pgc_refresh_token');

  ?><h1><?php _e('Tools'); ?></h1><?php

  if (empty($clientSecretError) && !empty($accessToken) && !empty($refreshToken)) {
  
  ?>

  <h2><?php _e('Update calendars'); ?></h2>
  <p><?php _e('Use this when you add or remove calendars.'); ?></p>
      <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="pgc_calendarlist">
        <?php submit_button(__('Update calendars'), 'small', 'submit-calendarlist', false); ?>
      </form>

  <h2><?php _e('Verify'); ?></h2>
  <p><?php _e('Verify if have setup everything correctly.'); ?></p>
  <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
  <input type="hidden" name="action" value="pgc_verify">
  <?php submit_button(__('Verify'), 'small', 'submit-verify', false); ?>
  </form>
      
  <h2><?php _e('Cache'); ?></h2>
  <?php
  $cachedEvents = $wpdb->get_var("SELECT option_name FROM " . $wpdb->options
      . " WHERE option_name LIKE '_transient_timeout_" . PGC_TRANSIENT_PREFIX . "%' OR option_name LIKE '_transient_" . PGC_TRANSIENT_PREFIX . "%' LIMIT 1");
  $cacheArgs = [];
  if (empty($cachedEvents)) {
    $cacheArgs['disabled'] = true;
  }
  ?>
  <p><?php _e('Remove cached calendar events.'); ?></p>
  <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
  <input type="hidden" name="action" value="pgc_deletecache">
  <?php
  submit_button(__('Remove cache'), 'small', 'submit-deletecache', false, $cacheArgs);
  if (empty($cachedEvents)) { ?>
    <em><?php _e('Cache is empty.'); ?></em>
  <?php } ?>
  </form>
  

    <h2><?php _e('Revoke access'); ?></h2>
    <p><?php _e('Revoke this plugins access to your calendars.'); ?></p>
<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
  <input type="hidden" name="action" value="pgc_revoke">
  <?php submit_button(__('Revoke access'), 'small', 'submit-revoke', false); ?>
</form>
    
  <?php } ?>
    
<h2><?php _e('Remove plugin data'); ?></h2>
<p><?php printf(_('Removes all saved plugin data.<br>If you have authorized this plugin access to your calendars, manually revoke access on the Google <a href="%s" target="__blank">Permissions</a> page.'), 'https://myaccount.google.com/permissions'); ?></p>
<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
  <input type="hidden" name="action" value="pgc_remove">
  <?php submit_button(__('Remove plugin data'), 'small', 'submit-remove', false); ?>
</form>
      
  

<?php
}

function pgc_sort_calendars(&$items) {
  // Set locale to UTF-8 variant if this is not the case.
  if (strpos(setlocale(LC_COLLATE, 0), '.UTF-8') === false) {
    // If we set this to a non existing locale it will be the default locale after this call.
    setlocale(LC_COLLATE, get_locale() . '.UTF-8');
  }
  usort($items, function($a, $b) {
    return strcoll($a['summary'], $b['summary']);
  });
}

/**
 * Admin post action to update calendar list.
 */
add_action('admin_post_pgc_calendarlist', 'pgc_admin_post_calendarlist');
function pgc_admin_post_calendarlist() {
  try {
    $client = getGoogleClient(true);
    if ($client->isAccessTokenExpired()) {
      if (!$client->getRefreshToken()) {
        throw new Exception(PGC_ERRORS_REFRESH_TOKEN_MISSING);
      }
      $client->refreshAccessToken();
    }
    $service = new PGC_GoogleCalendarClient($client);
    $items = $service->getCalendarList();

    pgc_sort_calendars($items);

    update_option('pgc_calendarlist', getPrettyJSONString($items), false);
    pgc_add_notice(PGC_NOTICES_CALENDARLIST_UPDATE_SUCCESS, 'success', true);
    exit;
  } catch (Exception $ex) {
    pgc_die($ex);
  }
}

/**
 * Admin post action to delete calendar cache.
 */
add_action('admin_post_pgc_deletecache', 'pgc_admin_post_deletecache');
function pgc_admin_post_deletecache() {
  pgc_delete_calendar_cache();
  pgc_add_notice(PGC_NOTICES_CACHE_DELETED, 'success', true);
  exit;
}

/**
 * Admin post action to verify if we have valid access and refresh token.
 */
add_action('admin_post_pgc_verify', 'pgc_admin_post_verify');
function pgc_admin_post_verify() {
  try {
    $client = getGoogleClient(true);
    $client->refreshAccessToken();
    pgc_add_notice(PGC_NOTICES_VERIFY_SUCCESS, 'success', true);
    exit;
  } catch (Exception $ex) {
    pgc_die($ex);
  }
}

/**
 * Admin post action to delete all plugin data.
 */
add_action('admin_post_pgc_remove', 'pgc_admin_post_remove');
function pgc_admin_post_remove() {
  pgc_delete_plugin_data();
  pgc_add_notice(PGC_NOTICES_REMOVE_SUCCESS, 'success', true);
  exit;
}

/**
 * Admin post action to revoke access and if that succeeds remove all plugin data.
 */
add_action('admin_post_pgc_revoke', 'pgc_admin_post_revoke');
function pgc_admin_post_revoke() {
  try {
    $client = getGoogleClient();
    $accessToken = getDecoded('pgc_access_token');
    if (!empty($accessToken)) {
      $client->setAccessTokenInfo($accessToken);
    }
    $refreshToken = get_option("pgc_refresh_token");
    if (!empty($refreshToken)) {
      $client->setRefreshToken($refreshToken);
    }
    if (empty($accessToken) && empty($refreshToken)) {
      throw new Exception(PGC_ERRORS_ACCESS_REFRESH_TOKEN_MISSING);
    }
    $client->revoke();
    // Clear access and refresh tokens
    pgc_delete_plugin_data();
    pgc_add_notice(PGC_NOTICES_REVOKE_SUCCESS, 'success', true);
    exit;
  } catch (Exception $ex) {
    pgc_die($ex);
  }
}

/**
 * Admin post action to authorize access.
 */
add_action('admin_post_pgc_authorize', 'pgc_admin_post_authorize');
function pgc_admin_post_authorize() {

  try {
    $client = getGoogleClient();
    $client->authorize();
    exit;
  } catch (Exception $ex) {
    pgc_die($ex);
  }
}


/**
 * Uninstall hook: try to revoke access and always delete all plugin data.
 */
register_uninstall_hook(__FILE__, 'pgc_uninstall');
function pgc_uninstall() {
  try {
    $client = getGoogleClient();
    $accessToken = getDecoded('pgc_access_token');
    if (!empty($accessToken)) {
      $client->setAccessTokenInfo($accessToken);
    }
    $refreshToken = get_option("pgc_refresh_token");
    if (!empty($refreshToken)) {
      $client->setRefreshToken($refreshToken);
    }
    if (empty($accessToken) && empty($refreshToken)) {
      throw new Exception(PGC_ERRORS_ACCESS_REFRESH_TOKEN_MISSING);
    }
    $client->revoke();
  } catch (Exception $ex) {
    // Too bad...
  } finally {
    // Clear all plugin data
    pgc_delete_plugin_data();
  }
}

/**
 * Helper function to delete cache.
 */
function pgc_delete_calendar_cache() {
  global $wpdb;
  $wpdb->query("DELETE FROM " . $wpdb->options
      . " WHERE option_name LIKE '_transient_timeout_" . PGC_TRANSIENT_PREFIX . "%' OR option_name LIKE '_transient_" . PGC_TRANSIENT_PREFIX . "%'");
}

/**
 * Helper function to delete all plugin options.
 */
function pgc_delete_options() {
  delete_option('pgc_access_token');
  delete_option('pgc_refresh_token');
  delete_option('pgc_selected_calendar_ids');
  delete_option('pgc_calendarlist');
  delete_option('pgc_client_secret');
  delete_option('pgc_cache_time');
}

/**
 * Helper function to delete all plugin data.
 */
function pgc_delete_plugin_data() {
  pgc_delete_calendar_cache();
  pgc_delete_options();
}

/**
 * Helper function die die with different kind of errors.
 */
function pgc_die($error = null) {
  $backLink = '<br><br>See the <em>Help</em> tab for more information.<br><br><a href="' . admin_url('options-general.php?page=pgc') . '">Back</a>';
  if (empty($error)) {
    wp_die(__('Unknown error') . $backLink);
  }
  if ($error instanceof Exception) {
    $s = [];
    if ($error->getCode()) {
      $x[] = $error->getCode();
    }
    $s[] = $error->getMessage();
    if ($error instanceof PGC_GoogleClient_RequestException) {
      if ($error->getDescription()) {
        $s[] = $error->getDescription();
      }
    }
    wp_die(implode("<br>", $s) . $backLink);
  } elseif (is_array($error)) {
    wp_die(implode("<br>", $error) . $backLink);
  } elseif (is_string($error)) {
    wp_die($error . $backLink);
  } else {
    wp_die(__('Unknown error format') . $backLink);
  }
}

/**
 * Validate secret client JSON file.
 */
function pgc_validate_client_secret_input($input) {
  if (!empty($_FILES) && !empty($_FILES['pgc_client_secret'])
      && is_uploaded_file($_FILES['pgc_client_secret']['tmp_name'])) {
    $content = trim(file_get_contents($_FILES['pgc_client_secret']['tmp_name']));
    $decoded = json_decode($content, true);
    if (!empty($decoded)) {
      return getPrettyJSONString($decoded);
    }
    add_settings_error('pgc', 'client_secret_input_error', PGC_ERRORS_CLIENT_SECRET_INVALID, 'error');
  }
  return null;
}

/**
 * Decide which settings to register.
 */
add_action('admin_init', 'pgc_settings_init');
function pgc_settings_init() {

  if (!empty($_GET['code'])) {
    // Redirect from Google authorize with code that we can use to get access and refreh tokens.
    try {
      $client = getGoogleClient();
      // This will also set the access and refresh tokens on the client
      // and call the tokencallback we have set to save them in the options table.
      $client->handleCodeRedirect();
      $service = new PGC_GoogleCalendarClient($client);
      $items = $service->getCalendarList();
      pgc_sort_calendars($items);
      update_option('pgc_calendarlist', getPrettyJSONString($items), false);
      wp_redirect(admin_url('options-general.php?page=pgc'));
      exit;
    } catch (Exception $ex) {
      pgc_die($ex);
    }
  
  }

  $clientSecretError = '';
  $clientSecret = pgc_get_valid_client_secret($clientSecretError);

  $accessToken = getDecoded('pgc_access_token');

  if (empty($clientSecret) || !empty($clientSecretError)) {
    // Make the options we use with register_settings not autoloaded.
    update_option('pgc_client_secret', get_option('pgc_client_secret', ''), false);
    update_option('pgc_selected_calendar_ids', get_option('pgc_selected_calendar_ids', []), false);
    register_setting('pgc', 'pgc_client_secret', [
      'show_in_rest' => false,
      'sanitize_callback' => 'pgc_validate_client_secret_input'
      ]);
  } else {
    if (!empty($accessToken)) {
      register_setting('pgc', 'pgc_selected_calendar_ids', [
        'show_in_rest' => false,
        'sanitize_callback' => 'pgc_validate_selected_calendar_ids'
      ]);
      register_setting('pgc', 'pgc_cache_time', [
        'show_in_rest' => false
      ]);
    }
  }
  
  add_settings_section(
    'pgc_settings_section',
    '',
    'pgc_settings_section_cb',
    'pgc');
    
    if (empty($clientSecret) || !empty($clientSecretError)) {
      add_settings_field(
        'pgc_settings_client_secret_json',
        __('Upload client secret'),
        'pgc_settings_client_secret_json_cb',
        'pgc',
        'pgc_settings_section');
    
  } elseif (getDecoded('pgc_calendarlist')) {

    add_settings_field(
        'pgc_settings_selected_calendar_ids_json',
        __('Select calendars'),
        'pgc_settings_selected_calendar_ids_json_cb',
        'pgc',
        'pgc_settings_section');
    add_settings_field(
        'pgc_settings_cache_time',
        __('Cache time in minutes'),
        'pgc_settings_cache_time_cb',
        'pgc',
        'pgc_settings_section');
  }
      

}

/**
 * Sanitize callback specified in register_setting.
 * Is used here to know when we save this setting, so we can remove the cache
 */
function pgc_validate_selected_calendar_ids($input) {
  pgc_delete_calendar_cache();
  return $input;
}

/**
 * Empty callback function
 */
function pgc_settings_section_cb() {}

/**
* Callback function to show cache time input.
**/
function pgc_settings_cache_time_cb() {
  $cacheTime = get_option('pgc_cache_time');
  ?>
    <input type="number" name="pgc_cache_time" id="pgc_cache_time" value="<?php echo esc_attr($cacheTime); ?>" />
    <p><em>Set to 0 to disable cache.</em></p>
  <?php
}

/**
 * Callback function to show calendar list checkboxes in admin.
 */
function pgc_settings_selected_calendar_ids_json_cb() {
  $calendarList = getDecoded('pgc_calendarlist');
  if (!empty($calendarList)) {
    $selectedCalendarIds = get_option('pgc_selected_calendar_ids'); // array
    if (empty($selectedCalendarIds)) {
      $selectedCalendarIds = [];
    }
    ?>
    <?php foreach($calendarList as $calendar) { ?>
      <?php
        $calendarId = $calendar['id'];
        $htmlId = md5($calendarId);
      ?>
      <p class="pgc-calendar-filter">
          <input id="<?php echo $htmlId; ?>" type="checkbox" name="pgc_selected_calendar_ids[]"
              <?php if (in_array($calendarId, $selectedCalendarIds)) echo ' checked '; ?>
              value="<?php echo esc_attr($calendarId); ?>" />
          <label for="<?php echo $htmlId; ?>">
            <span class="pgc-calendar-color" style="background-color:<?php echo esc_attr($calendar['backgroundColor']); ?>"></span>
            <?php echo esc_html($calendar['summary']); ?><?php if (!empty($calendar['primary'])) echo ' (primary)'; ?>
        </label>
        <br>ID: <?php echo esc_html($calendarId); ?>
      </p>
    <?php } ?>
    </ul>
    <?php
    $refreshToken = get_option("pgc_refresh_token");
    if (empty($refreshToken)) {
      pgc_show_notice(PGC_ERRORS_REFRESH_TOKEN_MISSING, 'error', false);
    }
  } else {
    ?>
    <p><?php _e('No calendars yet.'); ?></p>
    <?php
  }
}


/**
 * Callback function to show client secret file input.
 */
function pgc_settings_client_secret_json_cb() {
  
    $clientSecretError = '';
    $clientSecret = pgc_get_valid_client_secret($clientSecretError);
    $clientSecretString = '';
    
    if (!empty($clientSecret)) {
      $clientSecretString = getPrettyJSONString($clientSecret);
    }
    if (!empty($clientSecretError)) {
      pgc_show_notice($clientSecretError, 'error', false);
    }
    ?>
    <input type="file" name="pgc_client_secret" id="pgc_client_secret" />
    <?php
  }

/**
 * Helper function to check if we have a valid redirect uri in the client secret.
 * @return bool
 */
function pgc_check_redirect_uri($decodedClientSecret) {
  return !empty($decodedClientSecret)
    && !empty($decodedClientSecret['web'])
    && !empty($decodedClientSecret['web']['redirect_uris'])
    && in_array(admin_url('options-general.php?page=pgc'), $decodedClientSecret['web']['redirect_uris']);
}


/**
* Helper function to return pretty printed JSON string.
* @return string
*/
function getPrettyJSONString($jsonObject) {
  return json_encode($jsonObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
* Helper function to return array from option (that should be a JSON string).
* @return array or $default = null
*/
function getDecoded($optionName, $default = null) {
  $item = get_option($optionName);
  // $item should be a JSON string.
  if (!empty($item)) {
    return json_decode($item, true);
  }
  return $default;
}

/**
 * Helper function that returns a valid Google Client.
 * @return PGC_GoogleClient instance
 * @param bool $withTokens If true, also get tokens.
 * @throws Exception.
 */
function getGoogleClient($withTokens = false) {
  
    $authConfig = get_option('pgc_client_secret');
    if (empty($authConfig)) {
      throw new Exception(PGC_ERRORS_CLIENT_SECRET_MISSING);
    }
    $authConfig = getDecoded('pgc_client_secret');
    if (empty($authConfig)) {
      throw new Exception(PGC_ERRORS_CLIENT_SECRET_INVALID);
    }

    $c = new PGC_GoogleClient($authConfig);
    $c->setScope('https://www.googleapis.com/auth/calendar.readonly');
    if (!pgc_check_redirect_uri($authConfig)) {
      throw new Exception(sprintf(PGC_ERRORS_REDIRECT_URI_MISSING, admin_url('options-general.php?page=pgc')));
    }
    $c->setRedirectUri(admin_url('options-general.php?page=pgc'));
    $c->setTokenCallback(function($accessTokenInfo, $refreshToken) {
      update_option('pgc_access_token', json_encode($accessTokenInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), false);
      if (!empty($refreshToken)) {
        update_option('pgc_refresh_token', $refreshToken, false);
      }
    });

    if ($withTokens) {
      $accessToken = getDecoded('pgc_access_token');
      if (empty($accessToken)) {
        throw new Exception(PGC_ERRORS_ACCESS_TOKEN_MISSING);
      }
      $c->setAccessTokenInfo($accessToken);
      $refreshToken = get_option("pgc_refresh_token");
      if (empty($refreshToken)) {
        throw new Exception(PGC_ERRORS_REFRESH_TOKEN_MISSING);
      }
      $c->setRefreshToken($refreshToken);
    }
  
    return $c;
  
  }

/**
* Get a valid formatted client secret.
* @return Client Secret Array, false if no exists, Exception for invalid one
**/
function pgc_get_valid_client_secret(&$error = '') {
  $clientSecret = get_option('pgc_client_secret');
  if (empty($clientSecret)) {
    return false;
  }
  $clientSecret = getDecoded('pgc_client_secret');
  if (empty($clientSecret)
      || empty($clientSecret['web'])
      || empty($clientSecret['web']['client_secret'])
      || empty($clientSecret['web']['client_id']))
  {
    $error = PGC_ERRORS_CLIENT_SECRET_INVALID;
  } elseif (!pgc_check_redirect_uri($clientSecret))
  {
    $error = sprintf(PGC_ERRORS_REDIRECT_URI_MISSING, admin_url('options-general.php?page=pgc'));
  }
  return $clientSecret;
}

/**
 * Add 'pgcnotice' to the removable_query_args filter, so we can set this and
 * WP will remove it for us. We use this for our custom admin notices. This way
 * you can add parameters to the URL and check for them, but we won't see them
 * in the URL. See for examples:
 * wp-admin/options-head.php and edit-form-advanced.php
 */
add_filter('removable_query_args', 'pgc_removable_query_args');
function pgc_removable_query_args($removable_query_args) {
  $removable_query_args[] = 'pgcnotice';
  return $removable_query_args;
}

/**
 * Check for 'pgcnotice' parameter and show admin notice if we have a option.
 */
add_action('admin_init', 'pgc_notices_init');
function pgc_notices_init() {
  if (!empty($_GET['pgcnotice'])) {
    $pgcnotices = get_option('pgc_notices_' . get_current_user_id());
    if (empty($pgcnotices)) {
      return;
    }
    delete_option('pgc_notices_' . get_current_user_id());
    add_action('admin_notices', function() use ($pgcnotices) {
      foreach ($pgcnotices as $notice) {
        ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
          <p><?php echo $notice['content']; ?></p>
        </div>
        <?php
      }
    });
  }
}

/**
 * Helper function to add notice messages.
 * @param bool $redirect Redirect if true.
 */
function pgc_add_notice($content, $type = 'success', $redirect = false) {
  $pgcnotices = get_option('pgc_notices_' . get_current_user_id());
  if (empty($pgcnotices)) {
    $pgcnotices = [];
  }
  $pgcnotices[] = [
    'content' => $content,
    'type' => $type
  ];
  update_option('pgc_notices_' . get_current_user_id(), $pgcnotices, false);
  if ($redirect) {
    wp_redirect(admin_url("options-general.php?page=pgc&pgcnotice=true"));
  }
}

/**
 * Helper function to show a notice. WP will move this message to the correct place.
 */
function pgc_show_notice($notice, $type, $dismissable) {
  ?>
  <div class="notice notice-<?php echo esc_attr($type); ?> <?php echo $dismissable ? 'is-dismissible' : ''; ?>">
    <p><?php echo $notice; ?></p>
  </div>
  <?php
}

class Pgc_Calendar_Widget extends WP_Widget {

  private static $defaultConfig = [
    'header' => [
      'left' => 'title',
      'center' => '',
      'right' => 'today prev,next',
    ],
  ];
  
  public function __construct() {
    parent::__construct(
      'pgc_calender_widget', // Base ID
      __('Private Google Calendars Widget'),
      ['description' => __('Private Google Calendars Widget')]
    );
  }

  private function toBooleanString($value) {
    if ($value === 'true') return 'true';
    return 'false';
  }

  private function toBoolean($value) {
    if ($value === 'true') return true;
    return false;
  }

  private function instanceOptionToBooleanString($instance, $key, $defaultValue) {
    return isset($instance[$key]) ? $this->toBooleanString($instance[$key]) : $defaultValue;
  }
  
  public function widget($args, $instance) {

    $filter = $this->instanceOptionToBooleanString($instance, 'filter', 'true');
    $eventpopup = $this->instanceOptionToBooleanString($instance, 'eventpopup', 'true');
    $eventlink = $this->instanceOptionToBooleanString($instance, 'eventlink', 'false');
    $eventdescription = $this->instanceOptionToBooleanString($instance, 'eventdescription', 'false');
    $eventlocation = $this->instanceOptionToBooleanString($instance, 'eventlocation', 'false');
    $eventattachments = $this->instanceOptionToBooleanString($instance, 'eventattachments', 'false');
    $eventattendees = $this->instanceOptionToBooleanString($instance, 'eventattendees', 'false');
    $eventcreator = $this->instanceOptionToBooleanString($instance, 'eventcreator', 'false');
    $eventcalendarname = $this->instanceOptionToBooleanString($instance, 'eventcalendarname', 'false');
    $hidepassed = $this->instanceOptionToBooleanString($instance, 'hidepassed', 'false');
    $hidepasseddays = empty($instance['hidepasseddays']) ? 0 : $instance['hidepasseddays'];
    $hidefuture = $this->instanceOptionToBooleanString($instance, 'hidefuture', 'false');
    $hidefuturedays = empty($instance['hidefuturedays']) ? 0 : $instance['hidefuturedays'];
    $config = isset($instance['config']) ? $instance['config'] : self::$defaultConfig;
    $thisCalendarids = isset($instance['thiscalendarids']) ? $instance['thiscalendarids'] : [];

    if (is_string($config)) {
      $config = json_decode($config, true);
    }
    if (is_string($thisCalendarids)) {
      $thisCalendarids = json_decode($thisCalendarids, true);
    }

    echo $args['before_widget'];

    ?>
    <div class="pgc-calendar-wrapper pgc-calendar-widget">
      <div class="pgc-calendar-filter"></div>
      <div
          data-config='<?php echo json_encode($config); ?>'
          data-calendarids='<?php echo json_encode($thisCalendarids); ?>'
          data-filter='<?php echo $filter; ?>'
          data-eventpopup='<?php echo $eventpopup; ?>'
          data-eventlink='<?php echo $eventlink; ?>'
          data-eventdescription='<?php echo $eventdescription; ?>'
          data-eventlocation='<?php echo $eventlocation; ?>'
          data-eventattendees='<?php echo $eventattendees; ?>'
          data-eventattachments='<?php echo $eventattachments; ?>'
          data-eventcreator='<?php echo $eventcreator; ?>'
          data-eventcalendarname='<?php echo $eventcalendarname; ?>'
          data-hidepassed='<?php echo $hidepassed === 'true' ? $hidepasseddays : 'false'; ?>'
          data-hidefuture='<?php echo $hidefuture === 'true' ? $hidefuturedays : 'false'; ?>'
          data-locale='<?php echo get_locale(); ?>'
          class="pgc-calendar"></div>
    </div>
    <?php

    echo $args['after_widget'];

  }
  
  public function form($instance) {
    $filterValue = isset($instance['filter']) ? $instance['filter'] === 'true' : true;
    $eventpopupValue = isset($instance['eventpopup']) ? $instance['eventpopup'] === 'true' : true;
    $eventlinkValue = isset($instance['eventlink']) ? $instance['eventlink'] === 'true' : false;
    $eventdescriptionValue = isset($instance['eventdescription']) ? $instance['eventdescription'] === 'true' : false;
    $eventlocationValue = isset($instance['eventlocation']) ? $instance['eventlocation'] === 'true' : false;
    $eventattachmentsValue = isset($instance['eventattachments']) ? $instance['eventattachments'] === 'true' : false;
    $eventattendeesValue = isset($instance['eventattendees']) ? $instance['eventattendees'] === 'true' : false;
    $eventcreatorValue = isset($instance['eventcreator']) ? $instance['eventcreator'] === 'true' : false;
    $eventcalendarnameValue = isset($instance['eventcalendarname']) ? $instance['eventcalendarname'] === 'true' : false;
    $hidepassedValue = isset($instance['hidepassed']) ? $instance['hidepassed'] === 'true' : false;
    $hidepasseddaysValue = empty($instance['hidepasseddays']) ? 0 : $instance['hidepasseddays'];
    $hidefutureValue = isset($instance['hidefuture']) ? $instance['hidefuture'] === 'true' : false;
    $hidefuturedaysValue = empty($instance['hidefuturedays']) ? 0 : $instance['hidefuturedays'];
    $jsonValue = !empty($instance['config']) ? $instance['config'] : self::$defaultConfig;
    $allCalendarIds = get_option('pgc_selected_calendar_ids'); // selected calendar ids
    $calendarListByKey = pgc_get_calendars_by_key($allCalendarIds);
    $thisCalendaridsValue = isset($instance['thiscalendarids']) ? $instance['thiscalendarids'] : [];

    $popupCheckboxId = $this->get_field_id('eventpopup');
    $hidepassedCheckboxId = $this->get_field_id('hidepassed');
    $hidefutureCheckboxId = $this->get_field_id('hidefuture');

    ?>

    <script>
      window.onPgcPopupCheckboxClick = function(el) {
        el = el || this;
        var checked = el.checked;
        Array.prototype.forEach.call(document.querySelectorAll("input[data-linked-id='" + el.id + "']"), function(input) {
          if (checked) {
            input.removeAttribute("disabled");
          } else {
            input.setAttribute("disabled", "disabled");
          }
        });
      };

      window.onHidepassedCheckboxClick = function(el) {
        el = el || this;
        var input = document.querySelector("label[data-linked-id='" + el.id + "']");
        if (el.checked) {
            input.style.visibility = 'visible';
          } else {
            input.style.visibility = 'hidden';
          }
      };

      window.onHidefutureCheckboxClick = function(el) {
        el = el || this;
        var input = document.querySelector("label[data-linked-id='" + el.id + "']");
        if (el.checked) {
            input.style.visibility = 'visible';
          } else {
            input.style.visibility = 'hidden';
          }
      };

    </script>

      <p>
      <div><strong>Calendar selection</strong></div>
      <?php foreach($calendarListByKey as $calId => $calInfo) { ?>
        <label>
        <input type="checkbox"
        <?php checked(in_array($calId, $thisCalendaridsValue), true, true); ?>
        name="<?php echo $this->get_field_name('thiscalendarids'); ?>[]"
        value="<?php echo $calId; ?>" />
        <?php _e($calInfo['summary']); ?></label>
        <br>
      <?php } ?>
      <div><em>Note: no selection means all calendars.</em></div>
      </p>

      <p>
      <div><strong>Calendar options</strong></div>
      <label for="<?php echo $this->get_field_id('filter'); ?>"><input type="checkbox"
          <?php checked($filterValue, true, true); ?>
          id="<?php echo $this->get_field_id('filter'); ?>"
          name="<?php echo $this->get_field_name('filter'); ?>"
          value="true" />
        <?php _e('Show calendar filter'); ?></label>
      <br>
      <label for="<?php echo $hidepassedCheckboxId; ?>">      
      <input type="checkbox"
          <?php checked($hidepassedValue, true, true); ?>
          id="<?php echo $hidepassedCheckboxId; ?>"
          name="<?php echo $this->get_field_name('hidepassed'); ?>"
          onclick="window.onHidepassedCheckboxClick(this);"
          value="true" />
        <?php _e('Hide passed events'); ?></label>
        <label data-linked-id="<?php echo $hidepassedCheckboxId; ?>">more than <input min="0" class="pgc_small_numeric_input" type="number" name="<?php echo $this->get_field_name('hidepasseddays'); ?>"
          id="<?php echo $this->get_field_id('hidepasseddays'); ?>"
          value="<?php echo $hidepasseddaysValue; ?>" /> days ago</label>
      <br>
      <label for="<?php echo $hidefutureCheckboxId; ?>"><input type="checkbox"
          <?php checked($hidefutureValue, true, true); ?>
          id="<?php echo $hidefutureCheckboxId; ?>"
          name="<?php echo $this->get_field_name('hidefuture'); ?>"
          onclick="window.onHidefutureCheckboxClick(this);"
          value="true" />
        <?php _e('Hide future events'); ?></label>
        <label data-linked-id="<?php echo $hidefutureCheckboxId; ?>">more than <input min="0" class="pgc_small_numeric_input" type="number" name="<?php echo $this->get_field_name('hidefuturedays'); ?>"
          id="<?php echo $this->get_field_id('hidefuturedays'); ?>"
          value="<?php echo $hidefuturedaysValue; ?>" /> from now</label>
      </p>

      <p>
      <div><strong>Event popup options</strong></div>
      <label for="<?php echo $popupCheckboxId; ?>"><input type="checkbox"
          <?php checked($eventpopupValue, true, true); ?>
          id="<?php echo $popupCheckboxId; ?>"
          name="<?php echo $this->get_field_name('eventpopup'); ?>"
          value="true" onclick="window.onPgcPopupCheckboxClick(this);" />
        <?php _e('Show event popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventlink'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventlinkValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventlink'); ?>"
          name="<?php echo $this->get_field_name('eventlink'); ?>"
          value="true" />
        <?php _e('Show link to event in popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventdescription'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventdescriptionValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventdescription'); ?>"
          name="<?php echo $this->get_field_name('eventdescription'); ?>"
          value="true" />
        <?php _e('Show description in popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventlocation'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventlocationValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventlocation'); ?>"
          name="<?php echo $this->get_field_name('eventlocation'); ?>"
          value="true" />
        <?php _e('Show location in popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventattachments'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventattachmentsValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventattachments'); ?>"
          name="<?php echo $this->get_field_name('eventattachments'); ?>"
          value="true" />
        <?php _e('Show attachments in popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventattendees'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventattendeesValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventattendees'); ?>"
          name="<?php echo $this->get_field_name('eventattendees'); ?>"
          value="true" />
        <?php _e('Show attendees in popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventcalendarname'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventcalendarnameValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventcalendarname'); ?>"
          name="<?php echo $this->get_field_name('eventcalendarname'); ?>"
          value="true" />
        <?php _e('Show calendar name in popup'); ?></label>
      <br>
      <label for="<?php echo $this->get_field_id('eventcreator'); ?>"><input data-linked-id="<?php echo $popupCheckboxId; ?>" type="checkbox"
          <?php checked($eventcreatorValue, true, true); ?>
          id="<?php echo $this->get_field_id('eventcreator'); ?>"
          name="<?php echo $this->get_field_name('eventcreator'); ?>"
          value="true" />
        <?php _e('Show creator in popup'); ?></label>
      </p>
    
    <?php

    $jsonExample = self::$defaultConfig;

    $jsonValueTextarea = '';
    if (is_array($jsonValue)) {
      $jsonValueTextarea  = getPrettyJSONString($jsonValue);
    } else {
      $jsonValueTextarea = $jsonValue;
    }
    
    ?>
    <p>
      <label for="<?php echo $this->get_field_id('config'); ?>"><?php _e('JSON config:'); ?></label>
      <textarea
          name="<?php echo $this->get_field_name('config'); ?>"
          id="<?php echo $this->get_field_id('config'); ?>"
          class="widefat" rows="10"
          placeholder='<?php echo esc_attr(getPrettyJSONString($jsonExample)); ?>'
      ><?php echo esc_html($jsonValueTextarea); ?></textarea>
      <p><?php printf(__('See for config options the <a target="__blank" href="%s">FullCalendar docs</a>.'), 'https://fullcalendar.io/docs/'); ?></p>
    </p>
    <script>
      (function($) {

        window.onPgcPopupCheckboxClick.call(document.getElementById("<?php echo $popupCheckboxId; ?>"));
        window.onHidepassedCheckboxClick.call(document.getElementById("<?php echo $hidepassedCheckboxId; ?>"));
        window.onHidefutureCheckboxClick.call(document.getElementById("<?php echo $hidefutureCheckboxId; ?>"));

        // Note that form() is called 2 times in the widget area: ont time closed
        // and one time opened if you have it in your sidebar.
        var $area = $("#<?php echo $this->get_field_id('config'); ?>");
        var area = $area[0];
        var $form = $area.closest("form");
        // Does not work, so no real submit maybe?
        //$form.submit(function(e) {
        //  e.preventDefault();
        //  return false;
        //});
        $form.click(function(e) {
          var target = e.target;
          if (target.nodeName.toLowerCase() !== "input" || target.type !== "submit") {
            return;
          }
          if (!checkAreaJSON()) {
            e.stopPropagation();
            e.preventDefault();
            alert("<?php _e("Invalid JSON. Solve it before saving."); ?>");
            return false;
          }
        });
        var checkAreaJSON = function() {
          if (area.value === '') {
            area.style.outline = "2px solid green";
            return true;
          }
          try {
            JSON.parse(area.value);
            area.style.outline = "2px solid green";
            return true;
          } catch (ex) {
            area.style.outline = "3px solid red";
            return false;
          }
        };
        $area.on("input propertychange change", function() {
          checkAreaJSON(this);
        });
        $area.on("keydown", function(e) {
          if (e.keyCode==9 || e.which==9) {
            var start = this.selectionStart;
            var value = this.value;
            this.value = value.substring(0, start)
              + "    "
              + value.substring(this.selectionEnd);
            this.selectionStart = this.selectionEnd = start + 4;
            e.preventDefault();
          }
        });
        checkAreaJSON();
      }(jQuery));
    </script>
    <?php
  }

  public function update($new_instance, $old_instance) {
    $instance = [];
    $instance['config'] = (!empty($new_instance['config']))
        ? $new_instance['config']
        : getPrettyJSONString(self::$defaultConfig);
    $instance['filter'] = (!empty($new_instance['filter']))
        ? strip_tags($new_instance['filter'] )
        : '';
    $instance['eventpopup'] = (!empty($new_instance['eventpopup']))
        ? strip_tags($new_instance['eventpopup'] )
        : '';
    $instance['eventlink'] = (!empty($new_instance['eventlink']))
        ? strip_tags($new_instance['eventlink'] )
        : '';
    $instance['eventdescription'] = (!empty($new_instance['eventdescription']))
        ? strip_tags($new_instance['eventdescription'] )
        : '';
    $instance['eventlocation'] = (!empty($new_instance['eventlocation']))
        ? strip_tags($new_instance['eventlocation'] )
        : '';
    $instance['eventattachments'] = (!empty($new_instance['eventattachments']))
        ? strip_tags($new_instance['eventattachments'] )
        : '';
    $instance['eventattendees'] = (!empty($new_instance['eventattendees']))
        ? strip_tags($new_instance['eventattendees'] )
        : '';
    $instance['eventcreator'] = (!empty($new_instance['eventcreator']))
        ? strip_tags($new_instance['eventcreator'] )
        : '';
    $instance['eventcalendarname'] = (!empty($new_instance['eventcalendarname']))
        ? strip_tags($new_instance['eventcalendarname'] )
        : '';
    $instance['thiscalendarids'] = (!empty($new_instance['thiscalendarids']))
        ? $new_instance['thiscalendarids']
        : [];
    $instance['hidepassed'] = (!empty($new_instance['hidepassed']))
        ? strip_tags($new_instance['hidepassed'] )
        : '';
    $instance['hidepasseddays'] = (!empty($new_instance['hidepasseddays']))
        ? strip_tags($new_instance['hidepasseddays'] )
        : '0';
    $instance['hidefuture'] = (!empty($new_instance['hidefuture']))
        ? strip_tags($new_instance['hidefuture'] )
        : '';
    $instance['hidefuturedays'] = (!empty($new_instance['hidefuturedays']))
        ? strip_tags($new_instance['hidefuturedays'] )
        : '0';
    return $instance;
  }
  
 }
 add_action('widgets_init', function() {
   register_widget( 'Pgc_Calendar_Widget' );
  });
