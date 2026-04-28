<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\AM\Params;

use ClicShopping\OM\HTML;

/**
 * Anti-Spam Secret Key Configuration Parameter
 * 
 * Generates and stores a cryptographically secure random secret for HMAC-SHA256 validation.
 * This secret is automatically generated during module installation.
 * 
 * 
 * Configuration parameter for the anti-spam HMAC secret key.
 * Automatically generates a secure 64-character random hex string on installation.
 */
class secret extends \ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  /**
   * Default value: Auto-generated 64-character hex string (32 random bytes)
   * This is generated in the constructor to ensure uniqueness per installation
   */
  public $default;
  
  /**
   * Sort order for display in admin interface
   */
  public int|null $sort_order = 15;

  /**
   * Initialize the parameter
   * Sets title, description, and generates default secret if not already set
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_antispam_secret_title');
    $this->description = $this->app->getDef('cfg_antispam_secret_description');
    
    // Generate a secure random secret if not already set
    if (!isset($this->default) || empty($this->default)) {
      $this->default = $this->generateSecureSecret();
    }
  }

  /**
   * Generate a cryptographically secure random secret
   * 
   * @return string 64-character hexadecimal string (32 bytes)
   */
  private function generateSecureSecret(): string
  {
    try {
      // Generate 32 random bytes (256 bits)
      $randomBytes = random_bytes(32);
      
      // Convert to hexadecimal (64 characters)
      $secret = bin2hex($randomBytes);
      
      return $secret;
    } catch (\Exception $e) {
      // Fallback: use multiple sources of entropy
      // This should never happen on PHP 7.0+ but provides safety
      $fallback = hash('sha256', 
        uniqid('antispam_', true) . 
        microtime(true) . 
        mt_rand() . 
        (function_exists('random_bytes') ? bin2hex(random_bytes(16)) : '')
      );
      
      trigger_error(
        'Failed to generate cryptographically secure secret, using fallback. Error: ' . $e->getMessage(),
        E_USER_WARNING
      );
      
      return $fallback;
    }
  }

  /**
   * Get the input field for the admin interface
   * Displays the secret in a password field with regenerate option
   * 
   * @return string HTML input field
   */
  public function getInputField()
  {
    $value = $this->getInputValue();
    
    // If value is empty or default fallback, generate new secret
    if (empty($value) || $value === 'clicshopping_antispam_default_secret_2026') {
      $value = $this->generateSecureSecret();
    }
    
    $input = '<div class="antispam-secret-field">';
    
    // Password field to hide the secret
    $input .= HTML::inputField(
      $this->key, 
      $value, 
      'id="' . $this->key . '" class="form-control" style="font-family: monospace; max-width: 600px;" readonly'
    );
    
    // Info about the secret
    $input .= '<div class="form-text mt-2">';
    $input .= '<i class="bi bi-info-circle"></i> ';
    $input .= $this->app->getDef('cfg_antispam_secret_info');
    $input .= '<br><strong>' . $this->app->getDef('cfg_antispam_secret_length') . ':</strong> ' . strlen($value) . ' ' . $this->app->getDef('cfg_antispam_secret_characters');
    $input .= '</div>';
    
    // Warning about changing the secret
    $input .= '<div class="alert alert-warning mt-2" style="max-width: 600px;">';
    $input .= '<i class="bi bi-exclamation-triangle"></i> ';
    $input .= $this->app->getDef('cfg_antispam_secret_warning');
    $input .= '</div>';
    
    // Button to regenerate secret (optional)
    $input .= '<button type="button" class="btn btn-sm btn-secondary mt-2" onclick="regenerateAntispamSecret()">';
    $input .= '<i class="bi bi-arrow-clockwise"></i> ';
    $input .= $this->app->getDef('cfg_antispam_secret_regenerate');
    $input .= '</button>';
    
    $input .= '</div>';
    
    // JavaScript for regenerate button
    $input .= '<script>
    function regenerateAntispamSecret() {
      if (confirm("' . $this->app->getDef('cfg_antispam_secret_regenerate_confirm') . '")) {
        // Generate new secret via AJAX or reload with parameter
        var newSecret = generateRandomHex(32);
        document.getElementById("' . $this->key . '").value = newSecret;
        alert("' . $this->app->getDef('cfg_antispam_secret_regenerated') . '");
      }
    }
    
    function generateRandomHex(length) {
      var result = "";
      var characters = "0123456789abcdef";
      for (var i = 0; i < length * 2; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
      }
      return result;
    }
    </script>';
    
    return $input;
  }
}
