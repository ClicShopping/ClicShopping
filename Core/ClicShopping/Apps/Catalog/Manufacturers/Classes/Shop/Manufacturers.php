<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Classes\Shop;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_null;

/**
 * Class representing manufacturers with methods to retrieve details such as ID, URL, name,
 * image, description, and other related information.
 */
class Manufacturers
{
  private mixed $db;
  private mixed $lang;
  protected  int|null $Id;

  protected $rewriteUrl;

  /**
   * Constructor method to initialize database, language, and manufacturer ID parameters
   * @return void
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');

    if (isset($_GET['manufacturersId']) && is_numeric($_GET['manufacturersId']) && !empty(HTML::sanitize($_GET['manufacturersId']))) {
      $this->Id = HTML::sanitize($_GET['manufacturersId']);
    } elseif (isset($_POST['manufacturersId']) && is_numeric($_POST['manufacturersId']) && !empty(HTML::sanitize($_POST['manufacturersId']))) {
      $this->Id = HTML::sanitize($_POST['manufacturersId']);
    } else {
      $this->Id = null;
    }

    $this->rewriteUrl = Registry::get('RewriteUrl');
  }

  /**
   * Retrieves the ID of the current object.
   *
   * @return mixed The ID of the object.
   */
  public function getID()
  {
    $id = $this->Id;

    return $id;
  }

  /**
   * Retrieves the rewritten URL for the manufacturer.
   *
   * @return string The rewritten manufacturer URL.
   */
  public function getManufacturerUrlRewrited()
  {
    return $this->rewriteUrl;
  }

  /**
   * Retrieves the title (name) of a manufacturer based on its ID and the current language ID.
   *
   * @param int $id The unique identifier of the manufacturer.
   * @return string The name of the manufacturer, or an empty string if not found or inactive.
   */
  public function getTitle($id)
  {
    $name = '';

    $Qmanufacturer = $this->db->prepare('select m.manufacturers_name as name
                                            from :table_manufacturers m,
                                                 :table_manufacturers_info mi
                                            where m.manufacturers_id = :manufacturers_id
                                            and m.manufacturers_id = mi.manufacturers_id
                                            and mi.languages_id = :languages_id
                                            and m.manufacturers_status = 0
                                            ');
    $Qmanufacturer->bindInt(':manufacturers_id', $id);
    $Qmanufacturer->bindInt(':languages_id', $this->lang->getId());
    $Qmanufacturer->execute();

    if ($Qmanufacturer->fetch()) {
      $name = $Qmanufacturer->value('name');
    }

    return $name;
  }

  /**
   * Retrieves the image associated with a manufacturer by their ID.
   *
   * @param int $id The ID of the manufacturer.
   * @return string The image of the manufacturer, or an empty string if not available.
   */
  public function getImage($id)
  {
    $image = '';

    $Qmanufacturer = $this->db->prepare('select manufacturers_image as image
                                      from :table_manufacturers
                                      where manufacturers_id = :manufacturers_id
                                      and manufacturers_status = 0
                                      ');
    $Qmanufacturer->bindInt(':manufacturers_id', $id);
    $Qmanufacturer->execute();

    if ($Qmanufacturer->fetch()) {
      $image = $Qmanufacturer->value('image');
    }

    return $image;
  }

  /**
   * Retrieves the manufacturer description based on the provided ID.
   *
   * @param int $id The ID of the manufacturer whose description is to be retrieved.
   * @return string The description of the manufacturer.
   */
  public function getDescription($id)
  {
    $description = '';

    $Qmanufacturer = $this->db->prepare('select mi.manufacturer_description as description
                                            from :table_manufacturers m,
                                                 :table_manufacturers_info mi
                                            where m.manufacturers_id = :manufacturers_id
                                            and m.manufacturers_id = mi.manufacturers_id
                                            and mi.languages_id = :languages_id
                                            and m.manufacturers_status = 0
                                            ');
    $Qmanufacturer->bindInt(':manufacturers_id', $id);
    $Qmanufacturer->bindInt(':languages_id', $this->lang->getId());
    $Qmanufacturer->execute();

    if ($Qmanufacturer->fetch()) {
      $description = $Qmanufacturer->value('description');
    }

    return $description;
  }

  /**
   * Retrieves the URL for a manufacturer based on the given ID.
   *
   * @param int $id The manufacturer's ID for which the URL is to be retrieved.
   *
   * @return string The URL associated with the given manufacturer ID.
   */
  public function getUrl($id)
  {
    $url = '';

    $Qmanufacturer = $this->db->prepare('select mi.manufacturers_url as url
                                            from :table_manufacturers m,
                                                 :table_manufacturers_info mi
                                            where m.manufacturers_id = :manufacturers_id
                                            and m.manufacturers_id = mi.manufacturers_id
                                            and mi.languages_id = :languages_id
                                            and m.manufacturers_status = 0
                                            ');
    $Qmanufacturer->bindInt(':manufacturers_id', $id);
    $Qmanufacturer->bindInt(':languages_id', $this->lang->getId());
    $Qmanufacturer->execute();

    if ($Qmanufacturer->fetch()) {
      $url = $Qmanufacturer->value('url');
    }

    return $url;
  }

  /**
   * Retrieves all manufacturer data or data for a specific manufacturer based on the provided ID.
   *
   * @param int|null $id The ID of the manufacturer to retrieve data for. If null, retrieves data for all manufacturers.
   * @return array An array of manufacturer data.
   */
  public function getAll(?int $id = null)
  {
    if (!is_null($id)) {
      $Qmanufacturer = $this->db->prepare('select m.manufacturers_id as id,
                                                     m.manufacturers_name as name,
                                                     m.manufacturers_image as image,
                                                     mi.languages_id,
                                                     mi.manufacturers_url as url,
                                                     mi.manufacturer_description as description
                                              from :table_manufacturers m,
                                                   :table_manufacturers_info mi
                                              where m.manufacturers_id = :manufacturers_id
                                              and m.manufacturers_id = mi.manufacturers_id
                                              and mi.languages_id = :languages_id
                                              and m.manufacturers_status = 0
                                              ');
      $Qmanufacturer->bindInt(':manufacturers_id', $id);
      $Qmanufacturer->bindInt(':languages_id', $this->lang->getId());
      $Qmanufacturer->execute();
    } else {
      $Qmanufacturer = $this->db->prepare('select m.manufacturers_id as id,
                                                     m.manufacturers_name as name,
                                                     m.manufacturers_image as image,
                                                     mi.languages_id,
                                                     mi.manufacturers_url as url,
                                                     mi.manufacturer_description as description
                                              from :table_manufacturers m,
                                                   :table_manufacturers_info mi
                                              where m.manufacturers_id = mi.manufacturers_id
                                              and mi.languages_id = :languages_id
                                              and m.manufacturers_status = 0
                                              ');
      $Qmanufacturer->bindInt(':languages_id', $this->lang->getId());
      $Qmanufacturer->execute();
    }

    return $Qmanufacturer->fetchAll();
  }

  /**
   * Retrieves and sets a list of manufacturers associated with a specific category.
   *
   * @return array An array containing manufacturers' IDs and names, where each element is an associative array with keys 'id' and 'text'.
   */
  public function setManufacturersByCategories()
  {
    $CLICSHOPPING_Category = Registry::get('Category');
    $manufacturer_name_array = array();

    $Qmanufacturer = $this->db->prepare('select distinct m.manufacturers_id as id,
                                                  m.manufacturers_name as name
                                      from :table_products p,
                                      :table_products_to_categories p2c,
                                      :table_manufacturers m
                                      where p.products_status = 1
                                      and p.products_view = 1
                                      and p.manufacturers_id = m.manufacturers_id
                                      and p.products_id = p2c.products_id
                                      and p.products_archive = 0
                                      and p2c.categories_id = :categories_id
                                      and m.manufacturers_status = 0
                                      group by m.manufacturers_id
                                      order by m.manufacturers_name
                                      ');

    $Qmanufacturer->bindInt(':categories_id', $CLICSHOPPING_Category->getID());
    $Qmanufacturer->execute();

    while ($Qmanufacturer->fetch() !== false) {
      $manufacturer_name_array[] = [
        'id' => $Qmanufacturer->valueInt('id'),
        'text' => $Qmanufacturer->value('name')
      ];
    }

    return $manufacturer_name_array;
  }

  /**
   *
   * @return mixed The result of setting manufacturers by categories.
   */
  public function getManufacturersByCategories()
  {
    return $this->setManufacturersByCategories();
  }
}