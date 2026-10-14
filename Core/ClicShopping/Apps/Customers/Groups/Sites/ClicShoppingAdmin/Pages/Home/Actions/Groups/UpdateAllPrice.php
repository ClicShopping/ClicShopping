<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Customers\Groups\Sites\ClicShoppingAdmin\Pages\Home\Actions\Groups;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateAllPrice extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (!isset($_GET['cID'])) {
      return false;
    }

    $groups_id = (int)HTML::sanitize($_GET['cID']);

    $Qpricek = $CLICSHOPPING_Groups->db->prepare('select p.products_price,
                                                           p.products_id,
                                                           p.products_percentage,
                                                           pc.categories_id
                                                    from :table_products p,
                                                         :table_products_to_categories pc
                                                    where pc.products_id = p.products_id
                                                  ');
    $Qpricek->execute();

    if ($Qpricek->rowCount() > 0) {
      while ($Qpricek->fetch()) {

// if products is not manual update all price
        if ($Qpricek->valueInt('products_percentage') != 0) {

          // Default discount: 0 so a missing/deleted group leaves the base price unchanged
          // instead of triggering an undefined-variable warning further down.
          $ricarico = 0;

          $QcustomersGroup = $CLICSHOPPING_Groups->db->prepare('select distinct customers_group_id,
                                                                                  customers_group_name,
                                                                                  customers_group_discount,
                                                                                  customers_group_quantity_default
                                                                    from :table_customers_groups
                                                                    where customers_group_id = :customers_group_id
                                                                  ');
          $QcustomersGroup->bindInt(':customers_group_id', $groups_id);
          $QcustomersGroup->execute();

          if ($QcustomersGroup->rowCount() > 0) {
            $Qattributes = $CLICSHOPPING_Groups->db->prepare('select customers_group_id
                                                                from :table_products_groups
                                                                where customers_group_id = :customers_group_id
                                                                and products_id = :products_id
                                                              ');
            $Qattributes->bindInt(':customers_group_id', $groups_id);
            $Qattributes->bindInt(':products_id', $Qpricek->valueInt('products_id'));
            $Qattributes->execute();

            $Qdiscount = $CLICSHOPPING_Groups->db->prepare('select discount
                                                              from :table_groups_to_categories
                                                              where customers_group_id = :customers_group_id
                                                              and categories_id = :categories_id
                                                            ');
            $Qdiscount->bindInt(':customers_group_id', $groups_id);
            $Qdiscount->bindInt(':categories_id', $Qpricek->valueInt('categories_id'));
            $Qdiscount->execute();

            if (empty($Qdiscount->value('discount'))) {
              $ricarico = $QcustomersGroup->value('customers_group_discount');
            } else {
              $ricarico = $Qdiscount->value('discount');
            }
          } // end num_rows

// Applique le nouveau prix
          $pricek = $Qpricek->valueDecimal('products_price');

          if ($pricek > 0) {
            if (B2B == 'true') {
              if ($ricarico > 0) {
                $newprice = $pricek + ($pricek / 100) * $ricarico;
              } elseif ($ricarico == 0) {
                $newprice = $pricek;
              }
            } else {
              if ($ricarico > 0) {
                $newprice = $pricek - ($pricek / 100) * $ricarico;
              } elseif ($ricarico == 0) {
                $newprice = $pricek;
              }
            }
          } else {
            $newprice = 0;
          } // end $pricek

// Mise a jour de la base produits sur les groupes
          if (\is_null($Qattributes->valueInt('customers_group_id'))) {
            if ($groups_id != 0) {
              $CLICSHOPPING_Groups->db->save('products_groups', [
                  'customers_group_id' => (int)$groups_id,
                  'customers_group_price' => (float)$newprice,
                  'products_id' => (int)$Qpricek->valueInt('products_id'),
                  'products_price' => (float)$pricek
                ]
              );


              $CLICSHOPPING_Hooks->call('UpdateAllPrice', 'Save');
            }
          } else {
            if ($groups_id != 0) {

              $Qcheck = $CLICSHOPPING_Groups->db->get('products_groups', 'products_id', ['products_id' => (int)$Qpricek->valueInt('products_id'),
                  'customers_group_id' => (int)$groups_id
                ]
              );

              if ($Qcheck->fetch() === false) {
                $sql_data_array = ['customers_group_price' => $newprice,
                  'products_price' => (float)$pricek
                ];

                $insert_sql_data = ['customers_group_id' => (int)$groups_id,
                  'products_id' => $Qpricek->valueInt('products_id')
                ];

                $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

                $CLICSHOPPING_Groups->db->save('products_groups', $sql_data_array);
              } else {
                $Qupdate = $CLICSHOPPING_Groups->db->prepare('update :table_products_groups
                                                                set customers_group_price = :customers_group_price,
                                                                    products_price = :products_price
                                                                where customers_group_id = :customers_group_id
                                                                and products_id = :products_id
                                                              ');

                $Qupdate->bindDecimal(':customers_group_price', $newprice);
                $Qupdate->bindDecimal(':products_price', $pricek);
                $Qupdate->bindInt(':customers_group_id', $groups_id);
                $Qupdate->bindInt(':products_id', $Qpricek->valueInt('products_id'));
                $Qupdate->execute();
              }

              $CLICSHOPPING_Hooks->call('UpdateAllPrice', 'Save');
            }
          }
        }
      } // end while

      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Groups->getDef('text_price_success'), 'success');
      $CLICSHOPPING_Groups->redirect('Groups');
    }
  }
}