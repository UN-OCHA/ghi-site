<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_common\Helpers\ThemeHelper;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for project related modals.
 */
class ProjectModalController extends ControllerBase {

  use FtsLinkTrait;
  use PlanQueryTrait;

  /**
   * Config object for legacy project source URLs.
   */
  private const LEGACY_PROJECT_CONFIG_NAME = 'ghi_plans.legacy_projects';

  /**
   * Base cache id for the indexed set of available legacy project files.
   */
  private const LEGACY_PROJECT_AVAILABILITY_CACHE_ID = 'ghi_plans:legacy_project_availability';

  /**
   * Cache lifetime for a successfully loaded legacy project index.
   */
  private const LEGACY_PROJECT_AVAILABILITY_SUCCESS_TTL = 21600;

  /**
   * Short cache lifetime for unavailable legacy project indexes.
   */
  private const LEGACY_PROJECT_AVAILABILITY_FAILURE_TTL = 300;

  /**
   * Cache lifetime for fetched legacy project fragments and proxied assets.
   */
  private const LEGACY_PROJECT_RENDER_CACHE_MAX_AGE = 600;

  /**
   * Legacy project tags that can be rendered safely inside the Drupal page.
   */
  private const LEGACY_PROJECT_ALLOWED_TAGS = [
    'a',
    'b',
    'br',
    'div',
    'em',
    'fieldset',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'hr',
    'img',
    'label',
    'li',
    'ol',
    'p',
    'section',
    'span',
    'strong',
    'table',
    'tbody',
    'td',
    'tfoot',
    'th',
    'thead',
    'tr',
    'ul',
  ];

  /**
   * Legacy project tags that should be removed with their full contents.
   */
  private const LEGACY_PROJECT_DROP_TAGS = [
    'audio',
    'button',
    'canvas',
    'embed',
    'form',
    'iframe',
    'input',
    'link',
    'object',
    'script',
    'select',
    'style',
    'textarea',
    'video',
  ];

  /**
   * Attributes preserved while normalizing the extracted legacy project HTML.
   */
  private const LEGACY_PROJECT_ALLOWED_ATTRIBUTES = [
    'alt',
    'class',
    'colspan',
    'href',
    'rel',
    'rowspan',
    'scope',
    'src',
    'target',
    'title',
  ];

  /**
   * Legacy classes that carry project semantics or stable visual meaning.
   */
  private const LEGACY_PROJECT_ALLOWED_CLASSES = [
    'align-items-center',
    'bg-light',
    'border-top',
    'clusterImg',
    'col',
    'col-3',
    'col-4',
    'col-5',
    'col-9',
    'col-12',
    'create-project',
    'dependent',
    'disableCluster',
    'd-flex',
    'heading-text',
    'ind-1',
    'ind-2',
    'ind-3',
    'ind-4',
    'justify-content-between',
    'mb-1',
    'me-1',
    'mt-4',
    'mx-3',
    'p-2',
    'p-5',
    'pb-3',
    'pe-1',
    'pe-2',
    'padding-text',
    'ps-1',
    'pt-1',
    'pt-2',
    'pt-5',
    'px-3',
    'px-4',
    'py-2',
    'py-5',
    'review',
    'row',
    'section-child',
    'section-header',
    'segmentation',
    'table',
    'table-dark',
    'text-secondary',
  ];

  /**
   * The endpoint query manager.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  public $fabricQueryManager;

  /**
   * The endpoint query manager.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  public $endpointQueryManager;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  public $httpClient;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $requestStack;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public $cacheBackend;

  /**
   * The known legacy project ids, keyed by project id.
   *
   * NULL means availability could not be confirmed and links should fail open.
   *
   * @var array<int, bool>|null
   */
  private ?array $legacyProjectIds = NULL;

  /**
   * Whether the legacy project availability index has been loaded this request.
   *
   * @var bool
   */
  private bool $legacyProjectAvailabilityLoaded = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ProjectModalController {
    $instance = new static();
    $instance->fabricQueryManager = $container->get('plugin.manager.fabric_query_manager');
    $instance->endpointQueryManager = $container->get('plugin.manager.endpoint_query_manager');
    $instance->httpClient = $container->get('http_client');
    $instance->requestStack = $container->get('request_stack');
    $instance->cacheBackend = $container->get('cache.default');
    return $instance;
  }

  /**
   * Get the title for the modal.
   *
   * This will prefix the build title with the base object label (and icon).
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   * @param string $build_title
   *   A title for the build.
   */
  private function modalTitleBaseObject(BaseObjectInterface $base_object, $build_title) {
    $title = '';
    if ($base_object instanceof GoverningEntity && $icon = $base_object->getIconEmbedCode()) {
      $title = $icon;
    }
    $title .= $base_object->label();
    return Markup::create($title . ' | ' . $build_title);
  }

  /**
   * Enhance the build array.
   *
   * @param array $_build
   *   The original build array.
   * @param string $title
   *   A title for the build.
   * @param string $caption
   *   An optional caption to add before the actual build.
   *
   * @return array
   *   A render array.
   */
  private function returnBuild(array $_build, $title, $caption = NULL) {
    $build = [
      '#type' => 'container',
    ];

    if ($caption) {
      $build[] = $caption;
    }
    $build[] = $_build;

    $build['#attached'] = [
      'library' => ['ghi_blocks/modal'],
      'drupalSettings' => [
        'ghi_modal_title' => $title,
      ],
    ];
    return $build;
  }

  /**
   * Build a project table.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return array
   *   A render array.
   */
  public function buildProjectTable(BaseObjectInterface $base_object) {
    $plan_object = $this->getPlanObject($base_object);
    $cluster_context = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    $project_query = $this->getProjectQuery();
    $projects = $project_query->getProjectsForPlanId($plan_object->getSourceId(), $cluster_context);
    $build = $this->getProjectTable($projects, $plan_object);
    return $this->returnBuild($build, $this->modalTitleBaseObject($base_object, $this->t('Projects', [], ['langcode' => $plan_object?->getPlanLanguage()])));
  }

  /**
   * Build an organization list.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return array
   *   A render array.
   */
  public function buildOrganizationList(BaseObjectInterface $base_object) {
    $plan_object = $this->getPlanObject($base_object);
    $cluster_context = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    $project_query = $this->getProjectQuery();
    $organizations = $project_query->getProjectOrganizationsForPlan($plan_object, $cluster_context);

    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $build = $this->getOrganizationList($organizations);
    $fts_link = NULL;
    if ($base_object instanceof GoverningEntity) {
      $link_title = $this->t('For more details, view on <img src="@logo_url" />', [
        '@logo_url' => ThemeHelper::getUriToFtsIcon(),
      ], $t_options);
      $fts_link = self::buildFtsLink($link_title, $plan_object, 'recipients', $base_object);
    }
    return $this->returnBuild($build, $this->modalTitleBaseObject($base_object, $this->t('Organizations', [], $t_options)), $fts_link);
  }

  /**
   * Build a project table for an organization.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   * @param int $organization_id
   *   The id of the organization for which to display the projects.
   *
   * @return array
   *   A render array.
   */
  public function buildOrganizationProjectTable(BaseObjectInterface $base_object, $organization_id) {
    $plan_object = $this->getPlanObject($base_object);
    $organization = $this->getOrganizationQuery()->getOrganization($organization_id);
    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    if (!$organization) {
      $build = [
        '#markup' => $this->t('An error occured. The requested ressource is not available.', [], $t_options),
      ];
      return $this->returnBuild($build, $this->t('Error', [], $t_options));
    }

    $cluster_context = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    $project_query = $this->getProjectQuery();
    $projects = $project_query->getProjectsForPlanId($plan_object->getSourceId(), $cluster_context, $organization_id);

    $build = $this->getOrganizationProjectTable($projects, $plan_object);
    $title = $this->t('@organization_name | Projects', [
      '@organization_name' => $organization->getName(),
    ], $t_options);
    return $this->returnBuild($build, $title);
  }

  /**
   * Build the standalone legacy project page.
   *
   * This is also used as the modal content for project links in project tables.
   *
   * @param int $project_id
   *   The project id.
   *
   * @return array
   *   A render array.
   */
  public function buildLegacyProject($project_id): array {
    $markup = $this->buildLegacyProjectMarkup((int) $project_id, $this->isDialogRequest());
    return [
      '#markup' => Markup::create($markup['html']),
      '#attached' => [
        'library' => ['ghi_plans/legacy_project'],
      ],
      '#cache' => [
        'max-age' => self::LEGACY_PROJECT_RENDER_CACHE_MAX_AGE,
      ],
    ];
  }

  /**
   * Get the title for a standalone legacy project page.
   *
   * @param int $project_id
   *   The project id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function legacyProjectTitle($project_id) {
    $project = $this->getProjectQuery()->getProject((int) $project_id);
    if ($project) {
      return $this->t('@project_code: @project_name', [
        '@project_code' => $project->getProjectCode(),
        '@project_name' => $project->getName(),
      ]);
    }
    return $this->t('Project @project_id', [
      '@project_id' => $project_id,
    ]);
  }

  /**
   * Build the HTML fragment used for client-side project detail paging.
   *
   * @param int $project_id
   *   The project id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The fragment response.
   */
  public function buildLegacyProjectFragment($project_id): Response {
    $markup = $this->buildLegacyProjectMarkup((int) $project_id, TRUE);
    $response = new Response($markup['html'], $markup['status']);
    $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
    $response->headers->set('Cache-Control', 'public, max-age=' . self::LEGACY_PROJECT_RENDER_CACHE_MAX_AGE);
    return $response;
  }

  /**
   * Build the sanitized legacy project wrapper markup.
   *
   * @param int $project_id
   *   The project id.
   * @param bool $modal_display
   *   Whether the wrapper is being rendered inside a dialog.
   *
   * @return array{html: string, status: int}
   *   The wrapped HTML and HTTP-like status for raw fragment responses.
   */
  private function buildLegacyProjectMarkup(int $project_id, bool $modal_display): array {
    $fragment = $this->loadLegacyProjectFragment($project_id);
    $classes = [
      'legacy-project-wrapper',
      $modal_display ? 'legacy-project-wrapper--modal' : 'legacy-project-wrapper--standalone',
    ];
    if (!$modal_display) {
      $classes[] = 'content-width';
    }

    $html = '<div class="' . implode(' ', $classes) . '" data-legacy-project-id="' . $project_id . '">';
    $html .= '<div class="legacy-project-content">' . $fragment['html'] . '</div>';
    $html .= '</div>';

    return [
      'html' => $html,
      'status' => $fragment['status'],
    ];
  }

  /**
   * Load and extract the legacy project content from the GitHub Pages export.
   *
   * @param int $project_id
   *   The project id.
   *
   * @return array{html: string, status: int}
   *   The sanitized fragment HTML and HTTP-like status.
   */
  private function loadLegacyProjectFragment(int $project_id): array {
    $legacy_project_url = $this->getLegacyProjectExternalUrl($project_id);
    if (!$legacy_project_url) {
      return $this->buildUnavailableLegacyProjectFragment(Response::HTTP_NOT_FOUND);
    }

    try {
      $response = $this->httpClient->get($legacy_project_url, [
        'http_errors' => FALSE,
        'timeout' => 10,
      ]);
      $html = (string) $response->getBody();
    }
    catch (GuzzleException $e) {
      return $this->buildUnavailableLegacyProjectFragment(Response::HTTP_BAD_GATEWAY);
    }

    if ($response->getStatusCode() !== Response::HTTP_OK) {
      return $this->buildUnavailableLegacyProjectFragment(Response::HTTP_NOT_FOUND);
    }

    $fragment = $this->prepareLegacyProjectFragment($html);
    if ($fragment === NULL) {
      return $this->buildUnavailableLegacyProjectFragment(Response::HTTP_NOT_FOUND);
    }

    return [
      'html' => $fragment,
      'status' => Response::HTTP_OK,
    ];
  }

  /**
   * Build a small fallback fragment for missing legacy project data.
   *
   * @param int $status
   *   The HTTP-like status for the missing data.
   *
   * @return array{html: string, status: int}
   *   The fallback fragment and status.
   */
  private function buildUnavailableLegacyProjectFragment(int $status): array {
    return [
      'html' => '<p class="legacy-project-message">' . Html::escape((string) $this->t('The requested project details are not available.')) . '</p>',
      'status' => $status,
    ];
  }

  /**
   * Extract the project body from the legacy full HTML document.
   *
   * @param string $html
   *   The legacy project HTML document.
   *
   * @return string|null
   *   The sanitized project fragment, or NULL if the expected body is absent.
   */
  private function prepareLegacyProjectFragment(string $html): ?string {
    $document = new \DOMDocument('1.0', 'UTF-8');
    $document->preserveWhiteSpace = FALSE;
    $document->formatOutput = FALSE;
    $previous_errors = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);
    if (!$loaded) {
      return NULL;
    }

    $xpath = new \DOMXPath($document);
    $project = $xpath
      ->query('//*[contains(concat(" ", normalize-space(@class), " "), " create-project ")]')
      ?->item(0);
    if (!$project instanceof \DOMElement) {
      return NULL;
    }

    $fragment_document = new \DOMDocument('1.0', 'UTF-8');
    $fragment_document->preserveWhiteSpace = FALSE;
    $fragment_document->formatOutput = FALSE;
    $fragment = $fragment_document->importNode($project, TRUE);
    if (!$fragment instanceof \DOMElement) {
      return NULL;
    }
    $fragment_document->appendChild($fragment);
    $this->sanitizeLegacyProjectElement($fragment);

    return $fragment_document->saveHTML($fragment) ?: NULL;
  }

  /**
   * Sanitize an imported legacy project element in place.
   *
   * @param \DOMElement $element
   *   The imported element.
   */
  private function sanitizeLegacyProjectElement(\DOMElement $element): void {
    $this->sanitizeLegacyProjectAttributes($element);

    foreach (iterator_to_array($element->childNodes) as $child) {
      if (!$child instanceof \DOMElement) {
        continue;
      }

      $tag = strtolower($child->tagName);
      if (in_array($tag, self::LEGACY_PROJECT_DROP_TAGS, TRUE)) {
        $element->removeChild($child);
        continue;
      }

      if (!in_array($tag, self::LEGACY_PROJECT_ALLOWED_TAGS, TRUE)) {
        $this->sanitizeLegacyProjectElement($child);
        while ($child->firstChild) {
          $element->insertBefore($child->firstChild, $child);
        }
        $element->removeChild($child);
        continue;
      }

      $this->sanitizeLegacyProjectElement($child);
    }
  }

  /**
   * Normalize attributes on a safe legacy project element.
   *
   * @param \DOMElement $element
   *   The element to normalize.
   */
  private function sanitizeLegacyProjectAttributes(\DOMElement $element): void {
    foreach (iterator_to_array($element->attributes) as $attribute) {
      $name = strtolower($attribute->name);
      $value = $attribute->value;

      if (!in_array($name, self::LEGACY_PROJECT_ALLOWED_ATTRIBUTES, TRUE)) {
        $element->removeAttribute($attribute->name);
        continue;
      }

      switch ($name) {
        case 'class':
          $classes = $this->filterLegacyProjectClasses($value);
          $classes ? $element->setAttribute('class', $classes) : $element->removeAttribute('class');
          break;

        case 'href':
          $href = $this->normalizeLegacyProjectHref($value);
          $href ? $element->setAttribute('href', $href) : $element->removeAttribute('href');
          break;

        case 'src':
          $src = $this->normalizeLegacyProjectSource($value);
          $src ? $element->setAttribute('src', $src) : $element->removeAttribute('src');
          break;

        case 'colspan':
        case 'rowspan':
          ctype_digit($value) ? $element->setAttribute($name, $value) : $element->removeAttribute($name);
          break;

        case 'target':
        case 'rel':
          // Link targets are normalized from the final href below.
          $element->removeAttribute($name);
          break;
      }
    }

    if (strtolower($element->tagName) === 'a' && $element->hasAttribute('href')) {
      $element->setAttribute('target', '_blank');
      $element->setAttribute('rel', 'noopener noreferrer');
    }
  }

  /**
   * Keep only legacy classes that still carry meaning outside Bootstrap.
   *
   * @param string $class_attribute
   *   The original class attribute.
   *
   * @return string|null
   *   The normalized class attribute, or NULL if no classes remain.
   */
  private function filterLegacyProjectClasses(string $class_attribute): ?string {
    $classes = preg_split('/\s+/', trim($class_attribute)) ?: [];
    $classes = array_values(array_intersect($classes, self::LEGACY_PROJECT_ALLOWED_CLASSES));
    return $classes ? implode(' ', $classes) : NULL;
  }

  /**
   * Normalize safe link URLs inside extracted legacy project content.
   *
   * @param string $href
   *   The original href attribute.
   *
   * @return string|null
   *   The normalized href, or NULL if it is unsafe or unsupported.
   */
  private function normalizeLegacyProjectHref(string $href): ?string {
    $href = trim(UrlHelper::stripDangerousProtocols($href));
    if ($href === '') {
      return NULL;
    }

    $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
    if (in_array($scheme, ['http', 'https', 'mailto'], TRUE)) {
      return $href;
    }

    if ($scheme === '') {
      $asset_url = $this->normalizeLegacyProjectSource($href);
      return $asset_url;
    }

    return NULL;
  }

  /**
   * Normalize safe asset URLs inside extracted legacy project content.
   *
   * @param string $src
   *   The original src or relative asset href.
   *
   * @return string|null
   *   The local asset proxy URL, an allowed external URL, or NULL.
   */
  private function normalizeLegacyProjectSource(string $src): ?string {
    $src = trim(UrlHelper::stripDangerousProtocols($src));
    if ($src === '' || str_starts_with($src, '#')) {
      return NULL;
    }

    if (UrlHelper::isExternal($src)) {
      return preg_match('/^https?:\/\//i', $src) ? $src : NULL;
    }

    $asset_path = $this->normalizeLegacyProjectAssetPath('projects', $src);
    if ($asset_path === NULL) {
      return NULL;
    }
    if ($asset_path !== 'favicon.ico' && !str_starts_with($asset_path, '_assets/')) {
      return NULL;
    }
    return $this->getLegacyProjectAssetProxyUrl($asset_path);
  }

  /**
   * Build a proxied legacy project asset response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The asset response.
   */
  public function buildLegacyProjectAsset(): Response {
    $asset_path = (string) $this->requestStack->getCurrentRequest()->query->get('path');
    $asset_path = ltrim($asset_path, '/');
    if ($asset_path === '') {
      return new Response('', Response::HTTP_NOT_FOUND);
    }

    $legacy_asset_url = $this->getLegacyProjectAssetExternalUrl($asset_path);
    if (!$legacy_asset_url) {
      return new Response('', Response::HTTP_NOT_FOUND);
    }

    try {
      $asset_response = $this->httpClient->get($legacy_asset_url, [
        'http_errors' => FALSE,
        'timeout' => 10,
      ]);
    }
    catch (GuzzleException $e) {
      return new Response('', Response::HTTP_BAD_GATEWAY);
    }

    if ($asset_response->getStatusCode() !== Response::HTTP_OK) {
      return new Response('', Response::HTTP_NOT_FOUND);
    }

    $content = (string) $asset_response->getBody();
    $response = new Response($content);
    if ($content_type = $asset_response->getHeaderLine('Content-Type')) {
      $response->headers->set('Content-Type', $content_type);
    }
    $response->headers->set('Cache-Control', 'public, max-age=' . self::LEGACY_PROJECT_RENDER_CACHE_MAX_AGE);
    return $response;
  }

  /**
   * Get the popover content for project items.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to include in the table.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_object
   *   The plan object for context.
   *
   * @return array
   *   A render array.
   */
  private function getProjectTable(array $projects, ?Plan $plan_object = NULL) {
    $decimal_format = $plan_object?->getDecimalFormat();
    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $header = [
      [
        'data' => $this->t('Project code', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-sort-order' => 'ASC',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Project name', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Organizations', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Project Target', [], $t_options),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
      [
        'data' => $this->t('Requirements', [], $t_options),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
    ];

    $totals = [
      'targets' => 0,
      'requirements' => 0,
    ];
    $organization_ids_unique = [];

    $rows = [];
    foreach ($projects as $project) {
      $organinizations = $project->getOrganizations();
      $organization_ids_unique = array_unique(array_merge($organization_ids_unique, array_keys($organinizations)));

      $totals['targets'] += $project->target ?? 0;
      $totals['requirements'] += $project->getRequirements() ?? 0;

      $row = [];
      $row[] = [
        'data' => $this->buildLegacyProjectLink($project, $plan_object),
        'data-sort-value' => $project->getProjectCode(),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ];
      $row[] = $project->getName();
      $row[] = [
        'data' => [
          '#markup' => Markup::create(implode(' | ', $this->getOrganizationLinks($organinizations))),
        ],
        'data-sort-value' => implode(' | ', $this->getOrganizationNames($organinizations)),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_amount',
          '#amount' => $project->getTarget(),
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-value' => $project->getTarget(),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->getRequirements(),
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-value' => $project->getRequirements(),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $rows[] = $row;
    }

    $total_rows = [];
    $total_rows[] = [
      'data' => [
        $this->t('Total', [], $t_options),
        NULL,
        count($organization_ids_unique),
        [
          'data' => [
            '#theme' => 'hpc_amount',
            '#amount' => $totals['targets'],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
          'data-column-type' => 'amount',
        ],
        [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $totals['requirements'],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
          'data-column-type' => 'amount',
        ],
      ],
      'class' => 'totals-row',
    ];

    return [
      '#theme' => 'table',
      '#attributes' => [
        'class' => ['legacy-project-list-table'],
      ],
      '#cell_wrapping' => FALSE,
      '#header' => $header,
      '#sticky_rows' => $total_rows,
      '#rows' => $rows,
      '#sortable' => TRUE,
      '#attached' => [
        'library' => ['ghi_plans/legacy_project'],
      ],
    ];
  }

  /**
   * Get the popover content for project items.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to include in the table.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_object
   *   The plan object for context.
   *
   * @return array
   *   A render array.
   */
  private function getOrganizationProjectTable(array $projects, ?Plan $plan_object = NULL) {
    $decimal_format = $plan_object?->getDecimalFormat();
    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $header = [
      $this->t('Project code', [], $t_options),
      $this->t('Project name', [], $t_options),
      [
        'data' => $this->t('Requirements', [], $t_options),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
    ];

    $totals = [
      'requirements' => 0,
    ];

    $rows = [];
    foreach ($projects as $project) {
      $totals['requirements'] += $project->getRequirements() ?? 0;
      $row = [];
      $row[] = [
        'data' => $this->buildLegacyProjectLink($project, $plan_object),
      ];
      $row[] = $project->getName();
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->getRequirements(),
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-value' => $project->getRequirements(),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $rows[] = $row;
    }

    $total_rows = [];
    $total_rows[] = [
      'data' => [
        $this->t('Total', [], $t_options),
        NULL,
        [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $totals['requirements'],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
          'data-column-type' => 'amount',
        ],
      ],
      'class' => 'totals-row',
    ];

    return [
      '#theme' => 'table',
      '#cell_wrapping' => FALSE,
      '#header' => $header,
      '#sticky_rows' => $total_rows,
      '#rows' => $rows,
      '#sortable' => TRUE,
      '#attached' => [
        'library' => ['ghi_plans/legacy_project'],
      ],
    ];
  }

  /**
   * Get the popover content for oragnization items.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $organizations
   *   The organizations to include in the table.
   *
   * @return array
   *   A table render array.
   */
  private function getOrganizationList(array $organizations) {
    $links = $this->getOrganizationLinks($organizations);
    $popover_content = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#list_type' => 'ol',
      '#gin_lb_theme_suggestions' => FALSE,
    ];
    return $popover_content;
  }

  /**
   * Get organization links when available.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $objects
   *   The organization objects.
   *
   * @return \Drupal\Core\Link[]|string[]
   *   An array of organization links, or their names if no url is set.
   */
  private function getOrganizationLinks(array $objects) {
    $link_options = [
      'attributes' => [
        'target' => '_blank',
      ],
    ];
    return array_values(array_map(function ($object) use ($link_options) {
      $url = $object->getUrl($link_options);
      return $url ? Link::fromTextAndUrl($object->getName(), $url)->toString() : $object->getName();
    }, $objects));
  }

  /**
   * Get organization names when available.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $objects
   *   The organization objects.
   *
   * @return string[]
   *   An array of organization names.
   */
  private function getOrganizationNames(array $objects): array {
    return array_values(array_map(function ($object) {
      return $object->getName();
    }, $objects));
  }

  /**
   * Build a modal-enabled link to the legacy project details page.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project $project
   *   The project object.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_object
   *   The plan object for context.
   *
   * @return array
   *   The link render array.
   */
  private function buildLegacyProjectLink(Project $project, ?Plan $plan_object = NULL): array {
    if (!$this->legacyProjectExists($project->id())) {
      return [
        '#plain_text' => $project->getProjectCode(),
      ];
    }

    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $projects_label = $this->t('Projects', [], $t_options);
    $modal_title = $plan_object ? $this->t(
      '@plan_title | @projects_label | @project_code',
      [
        '@plan_title' => $plan_object->label(),
        '@projects_label' => $projects_label,
        '@project_code' => $project->getProjectCode(),
      ],
      $t_options,
    ) : $this->legacyProjectTitle($project->id());
    $modal_title = (string) $modal_title;
    $fragment_url = Url::fromRoute('ghi_plans.project.legacy_fragment', [
      'project_id' => $project->id(),
    ])->toString();

    $url = Url::fromRoute('ghi_plans.project.legacy', [
      'project_id' => $project->id(),
    ]);
    $url->setOptions([
      'attributes' => [
        'class' => ['use-ajax', 'project-detail-modal'],
        'data-dialog-type' => 'dialog',
        'data-legacy-project-code' => $project->getProjectCode(),
        'data-legacy-project-id' => $project->id(),
        'data-legacy-project-url' => $fragment_url,
        'data-legacy-project-title' => $modal_title,
        'data-dialog-options' => Json::encode([
          'target' => 'ghi-project-detail-modal',
          'modal' => TRUE,
          'width' => '90%',
          'title' => $modal_title,
          'classes' => [
            'ui-dialog' => 'project-detail-modal ghi-modal-dialog',
          ],
        ]),
        'rel' => 'nofollow',
      ],
    ]);

    return Link::fromTextAndUrl($project->getProjectCode(), $url)->toRenderable();
  }

  /**
   * Check if a legacy project page is known to exist.
   *
   * @param int $project_id
   *   The project id.
   *
   * @return bool
   *   TRUE if the project page exists, or if availability could not be checked.
   */
  private function legacyProjectExists($project_id): bool {
    if (!$this->legacyProjectSettingsConfigured()) {
      return FALSE;
    }

    $project_ids = $this->getLegacyProjectIds();
    if ($project_ids === NULL) {
      return TRUE;
    }
    return isset($project_ids[(int) $project_id]);
  }

  /**
   * Get known legacy project ids from the cached repository tree.
   *
   * @return array<int, bool>|null
   *   Project ids keyed by id, or NULL when the remote index is unavailable.
   */
  private function getLegacyProjectIds(): ?array {
    if ($this->legacyProjectAvailabilityLoaded) {
      return $this->legacyProjectIds;
    }
    $this->legacyProjectAvailabilityLoaded = TRUE;

    $tree_url = $this->getLegacyProjectTreeUrl();
    if (!$tree_url) {
      return NULL;
    }

    $cache_id = self::LEGACY_PROJECT_AVAILABILITY_CACHE_ID . ':' . hash('sha256', $tree_url);
    $cached = $this->cacheBackend->get($cache_id);
    if ($cached) {
      $this->legacyProjectIds = is_array($cached->data) ? $cached->data : NULL;
      return $this->legacyProjectIds;
    }

    try {
      // GitHub's Contents API caps large directories; the tree API gives us
      // the complete project file list in one cacheable request.
      $response = $this->httpClient->get($tree_url, [
        'headers' => [
          'Accept' => 'application/vnd.github+json',
        ],
        'http_errors' => FALSE,
        'timeout' => 10,
      ]);
    }
    catch (GuzzleException $e) {
      $this->cacheBackend->set($cache_id, NULL, time() + self::LEGACY_PROJECT_AVAILABILITY_FAILURE_TTL);
      return NULL;
    }

    if ($response->getStatusCode() !== Response::HTTP_OK) {
      $this->cacheBackend->set($cache_id, NULL, time() + self::LEGACY_PROJECT_AVAILABILITY_FAILURE_TTL);
      return NULL;
    }

    $data = Json::decode((string) $response->getBody());
    if (!is_array($data) || !empty($data['truncated']) || empty($data['tree']) || !is_array($data['tree'])) {
      $this->cacheBackend->set($cache_id, NULL, time() + self::LEGACY_PROJECT_AVAILABILITY_FAILURE_TTL);
      return NULL;
    }

    $project_ids = [];
    foreach ($data['tree'] as $item) {
      if (($item['type'] ?? NULL) !== 'blob' || empty($item['path'])) {
        continue;
      }
      if (preg_match('/^docs\/projects\/(\d+)\.html$/', $item['path'], $matches)) {
        $project_ids[(int) $matches[1]] = TRUE;
      }
    }

    $this->legacyProjectIds = $project_ids;
    $this->cacheBackend->set($cache_id, $project_ids, time() + self::LEGACY_PROJECT_AVAILABILITY_SUCCESS_TTL);
    return $this->legacyProjectIds;
  }

  /**
   * Build the external legacy project URL.
   *
   * @param int $project_id
   *   The project id.
   *
   * @return string|null
   *   The external URL, or NULL if legacy project settings are incomplete.
   */
  private function getLegacyProjectExternalUrl($project_id): ?string {
    $base_url = $this->getLegacyProjectBaseUrl();
    return $base_url ? $base_url . '/projects/' . (int) $project_id . '.html' : NULL;
  }

  /**
   * Build the external legacy project asset URL.
   *
   * @param string $asset_path
   *   The asset path relative to the legacy project base URL.
   *
   * @return string|null
   *   The external URL, or NULL if legacy project settings are incomplete.
   */
  private function getLegacyProjectAssetExternalUrl(string $asset_path): ?string {
    $asset_path = $this->normalizeLegacyProjectAssetPath('', $asset_path);
    if ($asset_path === NULL) {
      return NULL;
    }
    $base_url = $this->getLegacyProjectBaseUrl();
    return $base_url ? $base_url . '/' . $asset_path : NULL;
  }

  /**
   * Get the configured legacy project base URL.
   *
   * @return string|null
   *   The configured base URL, or NULL.
   */
  private function getLegacyProjectBaseUrl(): ?string {
    return $this->getLegacyProjectSetting('base_url');
  }

  /**
   * Get the configured legacy project tree URL.
   *
   * @return string|null
   *   The configured tree URL, or NULL.
   */
  private function getLegacyProjectTreeUrl(): ?string {
    return $this->getLegacyProjectSetting('tree_url');
  }

  /**
   * Check if the legacy project integration has the required settings.
   *
   * @return bool
   *   TRUE if the integration has enough settings to render links.
   */
  private function legacyProjectSettingsConfigured(): bool {
    return (bool) $this->getLegacyProjectBaseUrl();
  }

  /**
   * Get a legacy project setting value.
   *
   * @param string $key
   *   The settings key.
   *
   * @return string|null
   *   The configured value, or NULL.
   */
  private function getLegacyProjectSetting(string $key): ?string {
    $value = $this->config(self::LEGACY_PROJECT_CONFIG_NAME)->get($key);
    if (empty($value) || !is_string($value)) {
      return NULL;
    }
    return rtrim($value, '/');
  }

  /**
   * Build a local proxy URL for a legacy project asset path.
   *
   * @param string $asset_path
   *   The asset path relative to the legacy project base URL.
   *
   * @return string
   *   The local proxy URL.
   */
  private function getLegacyProjectAssetProxyUrl(string $asset_path): string {
    return Url::fromRoute('ghi_plans.project.legacy_asset', [], [
      'query' => [
        'path' => $asset_path,
      ],
    ])->toString();
  }

  /**
   * Normalize a legacy project asset path.
   *
   * @param string $base_path
   *   The base path for relative references.
   * @param string $asset_path
   *   The asset path to normalize.
   *
   * @return string|null
   *   The normalized path, or NULL when it escapes the legacy project root.
   */
  private function normalizeLegacyProjectAssetPath(string $base_path, string $asset_path): ?string {
    $asset_path = parse_url($asset_path, PHP_URL_PATH) ?: $asset_path;
    $path = str_starts_with($asset_path, '/') ? $asset_path : trim($base_path . '/' . $asset_path, '/');
    $parts = [];
    foreach (explode('/', $path) as $part) {
      if ($part === '' || $part === '.') {
        continue;
      }
      if ($part === '..') {
        if (empty($parts)) {
          return NULL;
        }
        array_pop($parts);
        continue;
      }
      $parts[] = $part;
    }
    return implode('/', $parts);
  }

  /**
   * Check if the current request is rendering dialog content.
   *
   * @return bool
   *   TRUE if rendering for a dialog.
   */
  private function isDialogRequest(): bool {
    $wrapper_format = (string) $this->requestStack->getCurrentRequest()->query->get('_wrapper_format');
    return str_contains($wrapper_format, 'drupal_dialog') || str_contains($wrapper_format, 'drupal_modal');
  }

  /**
   * Get the plan object for the current request.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan object.
   */
  private function getPlanObject(BaseObjectInterface $base_object): ?Plan {
    if ($base_object instanceof Plan) {
      return $base_object;
    }
    if ($base_object instanceof BaseObjectChildInterface && $base_object->getParentBaseObject() instanceof Plan) {
      return $base_object->getParentBaseObject();
    }
    return NULL;
  }

}
