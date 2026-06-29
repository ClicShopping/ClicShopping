<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Service\Shop;

  use ClicShopping\OM\HTML;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Interfaces\ServiceInterface;
  use ClicShopping\OM\Registry;
  use ClicShopping\Sites\Shop\BotDetector;

  /**
   * WhosOnline Service Class
   *
   * Handles the functionalities related to tracking users currently active on the shop.
   * This includes logging customer activities, tracking guest or spider activities, and updating session IDs.
   * It also determines if the active user is identified as a recognized spider.
   */
  class WhosOnline implements ServiceInterface
  {
    private static $spider_flag;

    /**
     * Tracks the current user's session and updates the 'whos_online' table with session, user, and activity details.
     *
     * @return false|void Returns false if the 'WhosOnline' registry already exists, otherwise returns nothing.
     */
    public static function start()
    {
      if (!Registry::exists('WhosOnline')) {
        $CLICSHOPPING_Customer = Registry::get('Customer');
        $CLICSHOPPING_Db = Registry::get('Db');

        $wo_session_id = session_id();
        $wo_ip_address = HTTP::getIpAddress();
        $wo_last_page_url = HTML::outputProtected(substr(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', 0, 255));

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $current_time = time();
        $xx_mins_ago = ($current_time - 900);

        if ($CLICSHOPPING_Customer->isLoggedOn()) {
          $wo_customer_id = $CLICSHOPPING_Customer->getID();
          $wo_full_name = $CLICSHOPPING_Customer->getName();
        } else {
          $wo_customer_id = null;
          $wo_full_name = 'Guest';
          self::$spider_flag = false;

          $botDetector = new BotDetector();

          if ($botDetector->isSearchEngine()) {
            self::$spider_flag = true;
            $wo_full_name = 'Search Engine (SEO)';
          } elseif ($botDetector->isWebMcpAgent() || $botDetector->isAiBot()) {
            self::$spider_flag = true;
            $wo_full_name = 'AI Bot (WebMCP)';
          } elseif ($botDetector->isBot()) {
            self::$spider_flag = true;
            $wo_full_name = 'Other Robot / Spider';
          }
        }

        // remove entries that have expired
        $Qwhosonline = $CLICSHOPPING_Db->prepare('delete
                                                  from :table_whos_online
                                                  where time_last_click < :time_last_click
                                                 ');
        $Qwhosonline->bindInt(':time_last_click', $xx_mins_ago);
        $Qwhosonline->execute();

        $Qsession = $CLICSHOPPING_Db->prepare('select session_id
                                               from :table_whos_online
                                               where session_id = :session_id
                                               limit 1
                                              ');
        $Qsession->bindValue(':session_id', $wo_session_id);
        $Qsession->execute();

        if ($Qsession->fetch() !== false) {
          $insert_array = [
            'customer_id' => (int)$wo_customer_id,
            'full_name' => $wo_full_name,
            'ip_address' => $wo_ip_address,
            'time_last_click' => $current_time,
            'last_page_url' => $wo_last_page_url
          ];

          $CLICSHOPPING_Db->save('whos_online', $insert_array, ['session_id' => $wo_session_id]);

        } else {
          if (!isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] === null) {
            $http_referer = '';
          } else {
            $http_referer = $_SERVER['HTTP_REFERER'];
          }

          $update_array = [
            'customer_id' => (int)$wo_customer_id,
            'full_name' => $wo_full_name,
            'session_id' => $wo_session_id,
            'ip_address' => $wo_ip_address,
            'time_entry' => $current_time,
            'time_last_click' => $current_time,
            'last_page_url' => $wo_last_page_url,
            'http_referer' => $http_referer,
            'user_agent' => $user_agent
          ];

          $CLICSHOPPING_Db->save('whos_online', $update_array);
        }
      } else {
        return false;
      }
    }

    /**
     * Retrieves the value of the spider flag.
     *
     * @return bool|null The spider flag state. True if a spider or bot was detected; false otherwise.
     */
    public static function getResultSpiderFlag(): ?bool
    {
      return self::$spider_flag;
    }

    /**
     * Stops a running process or operation.
     *
     * @return bool Returns true upon successful execution.
     */
    public static function stop(): bool
    {
      return true;
    }

    /**
     * Updates the session ID in the 'whos_online' table to reflect a new session ID.
     *
     * @param string $old_id The current session ID to be replaced.
     * @param string $new_id The new session ID to update in the database.
     * @return void
     */
    public static function whosOnlineUpdateSessionId(string $old_id, string $new_id): void
    {
      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Db->save('whos_online', ['session_id' => $new_id], ['session_id' => $old_id]);
    }
  }