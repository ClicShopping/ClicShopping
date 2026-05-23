<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use PDO;
use function in_array;
use function is_array;
use function is_null;

/**
 * Class DbStatement
 * Extends the PDOStatement class to provide additional features for database operations,
 * including custom data binding, pagination, caching, and result handling.
 */
class DbStatement extends \PDOStatement
{
  public mixed $page_set = null;
  protected $pdo;
  protected bool $is_error = false;
  protected string $page_set_keyword = 'page';
  protected mixed $page_set_results_per_page;
  protected $cache;
  protected int $cache_expire;
  protected $cache_data;
  protected bool $cache_read = false;
  protected bool $cache_empty_results = false;
  protected string $query_call;
  protected int|null $page_set_total_rows;
  protected $result;

  /**
   * Binds a boolean value to a parameter for use in a prepared statement.
   *
   * @param string|int $parameter The parameter identifier to bind the value to.
   * @param bool $value The boolean value to bind.
   * @return bool True on success, false on failure.
   */
  public function bindBool(string|int $parameter, bool $value): bool
  {
    return $this->bindValue($parameter, (bool)$value, PDO::PARAM_BOOL);
  }

  /**
   * Binds a value to a parameter for use in a prepared statement.
   *
   * @param string|int $parameter The parameter identifier to bind the value to.
   * @param mixed $value The value to bind.
   * @param int $data_type The PDO data type constant (default: PDO::PARAM_STR).
   * @return bool True on success, false on failure.
   */
  public function bindValue(string|int $parameter, mixed $value, int $data_type = PDO::PARAM_STR): bool
  {
    return parent::bindValue($parameter, $value, $data_type);
  }

   /**
   * Binds a string value to a parameter for use in a prepared statement.
   *
   * @param string|int $parameter The parameter identifier to bind the value to.
   * @param string|null $value The string value to bind.
   * @return bool True on success, false on failure.
   */
  public function bindDecimal(string|int $parameter, float $value): bool
  {
    return $this->bindValue($parameter, (float)$value); // there is no \PDO::PARAM_FLOAT
  }

  /**
   * Binds a null value to a parameter for use in a prepared statement.
   *
   * @param string|int $parameter The parameter identifier to bind the value to.
   * @return bool True on success, false on failure.
   */
  public function bindNull(string|int $parameter): bool
  {
    return $this->bindValue($parameter, null, PDO::PARAM_NULL);
  }

  /**
   * Sets the page set parameters for pagination.
   *
   * @param int $max_results The maximum number of results per page.
   * @param string|null $page_set_keyword The keyword used for the page set in the URL.
   * @param string $placeholder_offset The placeholder for the offset in the SQL query.
   * @param string $placeholder_max_results The placeholder for the maximum results in the SQL query.
   */
  public function setPageSet(int $max_results, string|null $page_set_keyword = null, string $placeholder_offset = 'page_set_offset', string $placeholder_max_results = 'page_set_max_results')
  {
    if (!empty($page_set_keyword)) {
      $this->page_set_keyword = $page_set_keyword;
    }

    $this->page_set = (isset($_GET[$this->page_set_keyword]) && is_numeric($_GET[$this->page_set_keyword]) && ($_GET[$this->page_set_keyword] > 0)) ? $_GET[$this->page_set_keyword] : 1;
    $this->page_set_results_per_page = $max_results;

    $offset = max(($this->page_set * $max_results) - $max_results, 0);

    $this->bindInt(':' . $placeholder_offset, $offset);
    $this->bindInt(':' . $placeholder_max_results, $max_results);
  }

  /**
   * Binds a value to a parameter for use in a prepared statement.
   *
   * @param string|int $parameter The parameter identifier to bind the value to.
   * @param string|int|null $value The value to bind.
   * @return bool True on success, false on failure.
   */
  public function bindInt(string|int $parameter, string|int|null $value): bool
  {
    return $this->bindValue($parameter, (int)$value, PDO::PARAM_INT);
  }

  /**
   * Executes the prepared statement with optional input parameters.
   *
   * @param array|null $input_parameters An associative array of input parameters to bind to the statement.
   * @return bool True on success, false on failure.
   */
  public function execute(array|null $input_parameters = null): bool
  {
    if ($this->cache) {
      if (isset($this->page_set)) {
        $this->cache->setKey($this->cache->getKey() . '-pageset' . $this->page_set);
      }

      if ($this->cache->exists($this->cache_expire)) {
        $this->cache_data = $this->cache->get();

        if (isset($this->cache_data['data'], $this->cache_data['total'])) {
          $this->page_set_total_rows = $this->cache_data['total'];
          $this->cache_data = $this->cache_data['data'];
        }

        $this->cache_read = true;
      } else {
        $this->cache_read = false;
      }
    }

    if ($this->cache_read === false) {
      if (empty($input_parameters)) {
        $input_parameters = null;
      }

      try {
        $result = parent::execute($input_parameters);
        $this->is_error = ($result === false);
      } catch (\PDOException $e) {
        $this->is_error = true;
        error_log($e->getMessage());
        return false;
      }

      if ($this->is_error === true) {
        trigger_error($this->queryString);
      }

      if (str_contains($this->queryString, ' SQL_CALC_FOUND_ROWS ')) {
        $this->page_set_total_rows = $this->pdo->query('select found_rows()')->fetchColumn();
      } elseif (isset($this->page_set)) {
        trigger_error(
          'ClicShopping\OM\DbStatement::execute(): Page Set query does not contain SQL_CALC_FOUND_ROWS. Query: ' .
          $this->queryString
        );
      }
    }

    return ($this->is_error === false);
  }

  /**
   * Fetches all rows from the result set.
   *
   * @param int|null $fetch_style The fetch style to use (default: PDO::FETCH_BOTH).
   * @param mixed ...$args Additional arguments for the fetch style.
   * @return array The fetched rows.
   */
  public function fetchAll(int|null $fetch_style = PDO::FETCH_BOTH, mixed ...$args): array
  {
    if ($this->cache_read === true) {
      $this->result = $this->cache_data;
    } else {
      $fetch_argument = $args[0] ?? null;
      $ctor_args = $args[1] ?? [];

      if (in_array($fetch_style, [PDO::FETCH_COLUMN])) {
        $this->result = parent::fetchAll($fetch_style, $fetch_argument);
      } elseif (in_array($fetch_style, [PDO::FETCH_CLASS, PDO::FETCH_FUNC])) {
        $this->result = parent::fetchAll($fetch_style, $fetch_argument, $ctor_args);
      } else {
        $this->result = parent::fetchAll($fetch_style);
      }

      if (isset($this->cache) && ($this->result !== false)) {
        $this->cache_data = $this->result;
      }
    }

    return $this->result;
  }

  /**
   * Fetches a single column from the next row of the result set.
   *
   * @param int $column_number The column number to fetch (default: 0).
   * @return bool The value of the specified column or false on failure.
   */
  public function check()
  {
    if (!isset($this->result)) {
      $this->fetch();
    }

    return $this->result !== false;
  }

  /**
   * Fetches the next row from the result set.
   *
   * @param int $fetch_style The fetch style to use (default: PDO::FETCH_DEFAULT).
   * @param int $cursor_orientation The cursor orientation (default: PDO::FETCH_ORI_NEXT).
   * @param int $cursor_offset The cursor offset (default: 0).
   * @return bool|array The fetched row or false on failure.
   */
  public function fetch(
    int $fetch_style = PDO::FETCH_DEFAULT, //FETCH_ASSOC,
    int $cursor_orientation = PDO::FETCH_ORI_NEXT,
    int $cursor_offset = 0): bool|array
  {
    if ($this->cache_read === true) {
      $this->result = current($this->cache_data);
    } else {
      $this->result = parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);

      if (isset($this->cache) && ($this->result !== false)) {
        if (!isset($this->cache_data)) {
          $this->cache_data = [];
        }

        $this->cache_data[] = $this->result;
      }
    }

    return $this->result;
  }

  /**
   * Retrieves the result set as an array.
   *
   * @return array The result set as an array.
   */
  public function toArray()
  {
    if (!isset($this->result)) {
      $this->fetch();
    }

    return $this->result;
  }

  /**
   * Sets the cache for the current statement.
   *
   * @param string $key The cache key.
   * @param int|null $expire The cache expiration time in seconds (default: null).
   * @param bool $cache_empty_results Whether to cache empty results (default: false).
   */
  public function setCache(string $key, int|null $expire = null, bool $cache_empty_results = false)
  {
    if (!is_numeric($expire)) {
      $expire = 0;
    }

    if (!is_bool($cache_empty_results)) {
      $cache_empty_results = false;
    }

    $this->cache = new Cache($key);
    $this->cache_expire = $expire;
    $this->cache_empty_results = $cache_empty_results;

    if ($this->query_call != 'prepare') {
      trigger_error('ClicShopping\\OM\\DbStatement::setCache(): Cannot set cache (\'' . $key . '\') on a non-prepare query. Please change the query to a prepare() query.');
    }
  }

  /**
   * Retrieves the value of a specified column from the result set.
   *
   * @param string $column The name of the column to retrieve the value from.
   * @return string The value of the specified column or false if not found.
   */
  public function value(string $column): string
  {
    return $this->valueMixed($column, 'string');
  }

  /**
   * Retrieves the value of a specified column from the result set.
   *
   * @param string $column The name of the column to retrieve the value from.
   * @param string $type The type of value to retrieve (default: 'string').
   * @return mixed The value of the specified column or false if not found.
   */
  protected function valueMixed(string $column, string $type = 'string')
  {
    if (!isset($this->result)) {
      $this->fetch();
    }

    switch ($type) {
      case 'protected':
        if (isset($this->result[$column])) {
          return HTML::outputProtected($this->result[$column]);
        }
        break;

      case 'int':
        if (isset($this->result[$column])) {
          return (int)$this->result[$column];
        }
        break;

      case 'decimal':
        if (isset($this->result[$column])) {
          return (float)$this->result[$column];
        }
        break;

      case 'string':
      default:
        if (isset($this->result[$column])) {
          return $this->result[$column];
        }
    }

    return false;
  }

  /**
   * Retrieves the value of a specified column cast as a protected string.
   *
   * @param string $column The name of the column from which to retrieve the value.
   * @return string The protected string value of the specified column.
   */
  public function valueProtected(string $column): string
  {
    return $this->valueMixed($column, 'protected');
  }

  /**
   * Retrieves the value of the specified column cast as an integer.
   *
   * @param string $column The name of the column from which to retrieve the value.
   * @return int The integer value of the specified column.
   */
  public function valueInt(string $column): int
  {
    return $this->valueMixed($column, 'int');
  }

  /**
   * Retrieves the value of the specified column cast as a decimal.
   *
   * @param string $column The name of the column from which to retrieve the value.
   * @return float The decimal value of the specified column.
   */
  public function valueDecimal(string $column): float
  {
    return $this->valueMixed($column, 'decimal');
  }

  /**
   * Checks if a specified column has a value in the result set.
   *
   * @param string $column The name of the column to check.
   **/
  public function hasValue(string $column): bool
  {
    if (!isset($this->result)) {
      $this->fetch();
    }

    return isset($this->result[$column]);
  }

  /**
   * Checks whether the current instance represents an error state.
   *
   * @return bool True if the instance is in
   */
  public function isError(): bool
  {
    return $this->is_error;
  }

  /**
   * Retrieves the query string.
   *
   * @return string The query string.
   */
  public function getQuery(): string
  {
    return $this->queryString;
  }

  /**
   * Retrieves the stored query call string.
   *
   * @return string The query call string.
   */
  public function getQueryCall(): string
  {
    return $this->query_call;
  }

  /**
   * Sets the query call string.
   *
   * @param string $type The type of query call (e.g., 'prepare', 'execute').
   */
  public function setQueryCall(string $type)
  {
    $this->query_call = $type;
  }

  /**
   * Retrieves the current page set.
   *
   * @return int The current page set.
   */
  public function getCurrentPageSet()
  {
    return $this->page_set;
  }

  /**
   * Retrieves the number of results displayed per page in the current page set.
   *
   * @return int The number of results per page.
   */
  public function getPageSetResultsPerPage()
  {
    return $this->page_set_results_per_page;
  }

  /**
   * Retrieves the total number of rows for the current page set.
   *
   * @return int The total number of rows if set, otherwise 0.
   */
  public function getPageSetTotalRows()
  {
    if (isset($this->page_set_total_rows)) {
      return $this->page_set_total_rows;
    } else {
      return 0;
    }
  }

  /**
   * Retrieves the PDO instance associated with this statement.
   *
   * @return PDO The PDO instance.
   */
  public function setPDO(PDO $instance)
  {
    $this->pdo = $instance;
  }

  /**
   * Retrieves the label for the current page set.
   *
   * @param string $text The text to be parsed for the label.
   * @return string The formatted label for the page set.
   */
  public function getPageSetLabel(string $text): string
  {
    if ($this->page_set_total_rows < 1) {
      $from = 0;
    } else {
      $from = max(($this->page_set * $this->page_set_results_per_page) - $this->page_set_results_per_page, 1);
    }

    $to = min($this->page_set * $this->page_set_results_per_page, $this->page_set_total_rows);

    if ($to > $this->page_set_results_per_page) {
      $from++;
    }

//return '<span class="pagination">
    return '<span class="pageSetTotalListing">' . Language::parseDefinition($text, [
          'listing_from' => $from,
          'listing_to' => $to,
          'listing_total' => $this->page_set_total_rows
        ]
      ) . '</span>';
  }

  /**
   * Generates pagination links for the current page set.
   *
   * @param string|null $parameters Additional parameters for the pagination links.
   * @param string|null $site The site context (default: null).
   * @return string The generated pagination links.
   */
  public function getPageSetLinks(mixed $parameters = null, string|null $site = null): string
  {
    $number_of_pages = ceil($this->page_set_total_rows / $this->page_set_results_per_page);

    if (empty($parameters)) {
      $parameters = '';
    }

    if (!empty($parameters)) {
      if (is_array($parameters)) {
        $p = $parameters;
      } else {
        parse_str($parameters, $p);
      }

      if (isset($p[$this->page_set_keyword])) {
        unset($p[$this->page_set_keyword]);
      }

      // Manually build the query string
      $queryString = '';

      foreach ($p as $key => $value) {
        if (is_array($value)) {
          foreach ($value as $v) {
            if ($v !== '') {
              $queryString .= $key . '[]=' . urlencode((string)$v) . '&';
            } else {
              $queryString .= $key . '[]&';
            }
          }
        } else {
          if ($value !== '') {
            $queryString .= $key . '=' . urlencode((string)$value) . '&';
          } else {
            $queryString .= $key . '&';
          }
        }
      }

      // Append the page parameter if it exists
      $parameters = !empty($queryString) ? '' . $queryString : '';
    }

    $pages = [];

    for ($i = 1; $i <= $number_of_pages; $i++) {
      $pages[] = [
        'id' => $i,
        'text' => $i
      ];
    }

    $output = '<nav aria-label="pagination">';
    $output .= '<ul class="pagination pagination-sm">';

    if (is_null($site)) {
//admin
      if ($number_of_pages > 1) {
        $output .= '<li class="page-item">' . HTML::selectField('pageset' . $this->page_set_keyword, $pages, $this->page_set, 'style="vertical-align: top; display: inline-block; float-start;" data-pageseturl="' . HTML::output(CLICSHOPPING::link(null, 'A&' . $parameters . $this->page_set_keyword . '=PAGESETGOTO')) . '"') . '</li>';
      } else {
        $output .= '<li class="page-item disabled"><a class="text-center page-link sr-only">1</a></li>';
      }

// previous button
      if ($this->page_set > 1) {
        $output .= '<li class="page-item active">' . HTML::link(CLICSHOPPING::link(null, $parameters . $this->page_set_keyword . '=' . ($this->page_set - 1)), null, 'title="' . CLICSHOPPING::getDef('prevnext_title_previous_page') . '" class="text-center page-link bi bi-chevron-left"') . '</li>';
      } else {
        $output .= '<li class="page-item disabled"><a class="text-center page-link bi bi-chevron-left"></a></li>';
      }

	// next button
      if (($this->page_set < $number_of_pages) && ($number_of_pages != 1)) {
        $output .= '<li class="page-item active">' . HTML::link(CLICSHOPPING::link(null, $parameters . $this->page_set_keyword . '=' . ($this->page_set + 1)), null, 'title="' . CLICSHOPPING::getDef('prevnext_title_next_page') . '" class="text-center page-link bi bi-chevron-right"') . '</li>';
      } else {
        $output .= '<li class="page-item disabled"><a class="text-center page-link bi bi-chevron-right"></a></li>';
      }
    } else {
      if ($number_of_pages > 1) {
        $output .= '<li class="page-item">' . HTML::selectField('pageset' . $this->page_set_keyword, $pages, $this->page_set, 'style="vertical-align: top; display: inline-block; float-start; height: 32px; width: 80px;" data-pageseturl="' . HTML::output(CLICSHOPPING::link(null, $parameters . $this->page_set_keyword . '=PAGESETGOTO')) . '"') . '</li>';
      } else {
        $output .= '<li class="page-item disabled"><a class="text-center page-link sr-only">1</a></li>';
      }

// previous button
      if ($this->page_set > 1) {
        $output .= '<li class="page-item active"><a href="' . CLICSHOPPING::link(null, $parameters . $this->page_set_keyword . '=' . ($this->page_set - 1)) . '" title="' . CLICSHOPPING::getDef('prevnext_title_previous_page') . '" class="text-center page-link bi bi-chevron-left"></a></li>';
      } else {
        $output .= '<li class="page-item disabled"><a class="text-center page-link bi bi-chevron-left"></a></li>';
      }
      
// next button
      if (($this->page_set < $number_of_pages) && ($number_of_pages != 1)) {
        $output .= '<li class="page-item active"><a href="' . CLICSHOPPING::link(null, $parameters . $this->page_set_keyword . '=' . ($this->page_set + 1)) . '" title="' . CLICSHOPPING::getDef('prevnext_title_next_page') . '" class="text-center page-link bi bi-chevron-right"></a></li>';
      } else {
        $output .= '<li class="page-item disabled"><a class="text-center page-link bi bi-chevron-right"></a></li>';
      }
    }

    $output .= '</ul>';
    $output .= '</nav>';

    if ($number_of_pages > 1) {
      $output .= <<<EOD
<script>
$(function() {
  $('select[name="pageset{$this->page_set_keyword}"]').on('change', function() {
    window.location = $(this).data('pageseturl').replace('PAGESETGOTO', $(this).children(':selected').val());
  });
});
</script>
EOD;
    }

    return $output;
  }

  /**
   * Destructor method responsible for handling cache operations.
   *
   * @return void
   */
  public function __destruct()
  {
    if (($this->cache_read === false) && isset($this->cache) && is_array($this->cache_data)) {
      if ($this->cache_empty_results || (isset($this->cache_data[0]) && ($this->cache_data[0] !== false))) {
        $cache_data = $this->cache_data;

        if (isset($this->page_set_total_rows)) {
          $cache_data = [
            'data' => $cache_data,
            'total' => $this->page_set_total_rows
          ];
        }

        $this->cache->save($cache_data);
      }
    }
  }
}
