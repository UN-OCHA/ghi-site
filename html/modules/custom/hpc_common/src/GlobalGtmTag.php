<?php

namespace Drupal\hpc_common;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Attaches the additional global Google Tag Manager container.
 *
 * This additional tag is meant to be used for the UN Secretariat.
 *
 * Configure the additional container ID with a settings.php config override:
 * $config['hpc_common.settings']['additional_google_tag'] = 'GTM-XXXXXXX';.
 */
class GlobalGtmTag {

  /**
   * Constructs a GlobalGtmTag service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
    protected AdminContext $adminContext,
  ) {}

  /**
   * Attaches the GTM script to the page head.
   *
   * @param array $page
   *   The page render array.
   */
  public function attachPageHead(array &$page): void {
    $google_tag = $this->getGoogleTag();
    if (!$google_tag || !$this->isEnabled()) {
      return;
    }

    $script = "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$google_tag}');\n";
    $page['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#value' => $script,
      ],
      'hpc_common_global_gtm_tag',
    ];
  }

  /**
   * Attaches the GTM noscript iframe to the top of the page body.
   *
   * @param array $pageTop
   *   The page_top render array.
   */
  public function attachPageTop(array &$pageTop): void {
    $google_tag = $this->getGoogleTag();
    if (!$google_tag || !$this->isEnabled()) {
      return;
    }

    $pageTop['hpc_common_global_gtm_tag'] = [
      '#noscript' => TRUE,
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => "https://www.googletagmanager.com/ns.html?id={$google_tag}",
        'height' => 0,
        'width' => 0,
        'style' => 'display:none;visibility:hidden;',
      ],
    ];
  }

  /**
   * Gets the configured GTM container ID.
   *
   * @return string
   *   The sanitized GTM container ID, or an empty string if none is configured.
   */
  protected function getGoogleTag(): string {
    $google_tag = (string) $this->configFactory
      ->get('hpc_common.settings')
      ->get('additional_google_tag');
    return preg_replace('/[^a-zA-Z0-9\-]/', '', $google_tag);
  }

  /**
   * Determines whether the global GTM container should be attached.
   *
   * @return bool
   *   TRUE when the GTM tag should be attached, otherwise FALSE.
   */
  protected function isEnabled(): bool {
    $config = $this->configFactory->get('gtm.settings');
    if (!$config->get('enable')) {
      return FALSE;
    }

    if ($this->currentUser->id() == 1 && $config->get('admin-disable')) {
      return FALSE;
    }

    return $config->get('admin-pages') || !$this->adminContext->isAdminRoute();
  }

}
