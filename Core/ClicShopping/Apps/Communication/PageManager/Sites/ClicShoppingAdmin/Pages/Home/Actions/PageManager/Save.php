<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\PageManager;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Save extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_PageManager = Registry::get('PageManager');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $page_error = false;

    $languages = $CLICSHOPPING_Language->getLanguages();

    if (isset($_POST['pages_id'])) {
      $pages_id = HTML::sanitize($_POST['pages_id']);
    } else {
      $pages_id = null;
      $page_error = true;
    }

    if (isset($_POST['sort_order'])) {
      $sort_order = HTML::sanitize($_POST['sort_order']);
    } else {
      $sort_order = 0;
    }

    if (isset($_POST['page_type'])) {
      $page_type = HTML::sanitize($_POST['page_type']);
    } else {
      $page_type = '';
      $page_error = true;
    }

    if (isset($_POST['links_target'])) {
      $links_target = HTML::sanitize($_POST['links_target']);
    } else {
      $links_target = '';
    }

    if (isset($_POST['page_box'])) {
      $page_box = HTML::sanitize($_POST['page_box']);
    } else {
      $page_box = 0;
    }

    if (isset($_POST['page_time'])) {
      $page_time = HTML::sanitize($_POST['page_time']);
    } else {
      $page_time = null;
    }

    if (isset($_POST['page_date_start'])) {
      $page_date_start = HTML::sanitize($_POST['page_date_start']);
    } else {
      $page_date_start = null;
    }

    if (isset($_POST['page_date_closed'])) {
      $page_date_closed = HTML::sanitize($_POST['page_date_closed']);
    } else {
      $page_date_closed = null;
    }

    if (isset($_POST['page_general_condition'])) {
      $page_general_condition = HTML::sanitize($_POST['page_general_condition']);
    } else {
      $page_general_condition = null;
    }

    if (isset($_POST['customers_group_id'])) {
      $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
    } else {
      $customers_group_id = 0;
    }

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $title_field_name = $_POST['pages_title_' . $languages[$i]['id']];

      if (empty($title_field_name)) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('error_page_title_required'), 'error');
        $page_error = true;
      }
}

    if ($page_error === false) {
      $sql_data_array_pages = [
        'links_target' => $links_target,
        'sort_order' => (int)$sort_order,
        'status' => 1,
        'page_type' => (int)$page_type,
        'page_box' => (int)$page_box,
        'page_time' => (int)$page_time,
        'page_general_condition' => (int)$page_general_condition,
        'customers_group_id' => (int)$customers_group_id
      ];

      if (isset($_GET['Insert'])) {
        $insert_sql_data_pages = ['date_added' => 'now()'];
        $sql_data_array = array_merge($sql_data_array_pages, $insert_sql_data_pages);

        $CLICSHOPPING_PageManager->db->save('pages_manager', $sql_data_array);

        $pages_id = $CLICSHOPPING_PageManager->db->lastInsertId();

        $_POST['pages_id'] = $pages_id;
      } else {
        $insert_sql_data_pages = ['last_modified' => 'now()'];
        $sql_data_array_pages = array_merge($sql_data_array_pages, $insert_sql_data_pages);

        $CLICSHOPPING_PageManager->db->save('pages_manager', $sql_data_array_pages, ['pages_id' => (int)$pages_id]);
      }

      if (!empty($page_date_start)) {
        $sql_array = ['page_date_start' => $page_date_start];
        $CLICSHOPPING_PageManager->db->save('pages_manager', $sql_array, ['pages_id' => (int)$pages_id]);
      } else {
        $sql_array = ['page_date_start' => null];
        $CLICSHOPPING_PageManager->db->save('pages_manager', $sql_array, ['pages_id' => (int)$pages_id]);
      }

      if (!empty($page_date_closed)) {
        $sql_array = ['page_date_closed' => $page_date_closed];
        $CLICSHOPPING_PageManager->db->save('pages_manager', $sql_array, ['pages_id' => (int)$pages_id]);
      } else {
        $sql_array = ['page_date_closed' => null];
        $CLICSHOPPING_PageManager->db->save('pages_manager', $sql_array, ['pages_id' => (int)$pages_id]);
      }

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];

        if (isset($_POST['pages_title_' . $languages[$i]['id']])) {
          $pages_title = HTML::sanitize($_POST['pages_title_' . $languages[$i]['id']]);
        } else {
          $pages_title = '';
        }

        if (isset($_POST['pages_html_text_'][$languages[$i]['id']])) {
          $pages_html_text = $_POST['pages_html_text_'][$languages[$i]['id']];
        } else {
          $pages_html_text = null;
        }

        if (isset($_POST['externallink_' . $languages[$i]['id']])) {
          $externallink = HTML::sanitize($_POST['externallink_' . $languages[$i]['id']]);
        } else {
          $externallink = null;
        }

        if (isset($_POST['page_manager_head_title_tag_' . $languages[$i]['id']])) {
          $page_manager_head_title_tag = HTML::sanitize($_POST['page_manager_head_title_tag_' . $languages[$i]['id']]);
        } else {
          $page_manager_head_title_tag = null;
        }

        if (isset($_POST['page_manager_head_desc_tag_' . $languages[$i]['id']])) {
          $page_manager_head_desc_tag = HTML::sanitize($_POST['page_manager_head_desc_tag_' . $languages[$i]['id']]);
        } else {
          $page_manager_head_desc_tag = null;
        }

        if (isset($_POST['page_manager_head_keywords_tag_' . $languages[$i]['id']])) {
          $page_manager_head_keywords_tag = HTML::sanitize($_POST['page_manager_head_keywords_tag_' . $languages[$i]['id']]);
        } else {
          $page_manager_head_keywords_tag = null;
        }

        if (isset($_GET['Insert'])) {
          $sql_data_array = [
            'pages_id' => $pages_id,
            'pages_title' => $pages_title,
            'pages_html_text' => $pages_html_text,
            'externallink' => $externallink,
            'page_manager_head_title_tag' => strip_tags($page_manager_head_title_tag),
            'page_manager_head_keywords_tag' => strip_tags($page_manager_head_keywords_tag),
            'page_manager_head_desc_tag' => strip_tags($page_manager_head_desc_tag),
            'language_id' => $language_id
          ];

          $CLICSHOPPING_PageManager->db->save('pages_manager_description', $sql_data_array);
        } else {
          $sql_data_array = [
            'pages_title' => $pages_title,
            'pages_html_text' => $pages_html_text,
            'externallink' => $externallink,
            'page_manager_head_title_tag' => strip_tags($page_manager_head_title_tag),
            'page_manager_head_keywords_tag' => strip_tags($page_manager_head_keywords_tag),
            'page_manager_head_desc_tag' => strip_tags($page_manager_head_desc_tag)
          ];

          $insert_sql_data = [
            'pages_id' => $pages_id,
            'language_id' => $language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_PageManager->db->save('pages_manager_description', $sql_data_array, $insert_sql_data);
        }
}

      $CLICSHOPPING_Hooks->call('PageManager', 'Save');

      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('success_page_updated'), 'success');

      Cache::clear('boxe_page_manager_primary-');
      Cache::clear('boxe_page_manager_secondary-');
      Cache::clear('page_manager_display_header_menu-');
      Cache::clear('page_manager_display_footer_menu-');
      Cache::clear('page_manager_display_footer-');
      Cache::clear('boxe_page_manager_display_information-');
      Cache::clear('boxe_page_manager_display_title-');

      $CLICSHOPPING_PageManager->redirect('PageManager&page=' . $page . '&bID=' . (int)$pages_id . '&error=' . $page_error);
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('success_page_not_updated'), 'error');

      $CLICSHOPPING_PageManager->redirect('Edit');
    }
}
}