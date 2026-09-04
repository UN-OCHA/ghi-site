<?php

namespace Drupal\ghi_blocks\ReliefWeb;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Fetches and parses ReliefWeb RSS feeds.
 */
class ReliefWebRssFeed {

  public const SOURCE = 'reliefweb_rss_feed';
  public const REFRESHER_ID = 'reliefweb_rss_feed';

  private const MAX_CACHE_ITEMS = 50;
  private const MAX_BODY_SIZE = 5242880;
  private const FRESH_TTL = 3600;
  private const STALE_TTL = 86400;

  /**
   * Constructs a ReliefWeb RSS feed service.
   */
  public function __construct(private readonly ClientInterface $httpClient, private readonly LoggerChannelFactoryInterface $loggerFactory, private readonly ?RemoteDataCacheInterface $remoteDataCache = NULL) {}

  /**
   * Get the latest parsed items from a ReliefWeb RSS feed.
   *
   * @param string $feed_url
   *   The ReliefWeb RSS feed URL.
   * @param int $limit
   *   The maximum number of items to return.
   *
   * @return array
   *   Parsed feed items.
   */
  public function getItems(string $feed_url, int $limit): array {
    if (!$this->isValidFeedUrl($feed_url)) {
      return [];
    }

    $feed_url = $this->normalizeFeedUrl($feed_url);
    $limit = max(1, $limit);
    $items = $this->getCachedItems($feed_url);

    if ($items === NULL) {
      $items = $this->fetchRemoteItems($feed_url);
      if ($items === NULL) {
        return [];
      }
      $this->storeCachedItems($feed_url, $items);
    }

    return array_slice($items, 0, $limit);
  }

  /**
   * Fetch fresh parsed feed items without reading the local remote data cache.
   *
   * @param string $feed_url
   *   The ReliefWeb RSS feed URL.
   *
   * @return array|null
   *   Parsed feed items, or NULL if the fetch or parse failed.
   */
  public function fetchRemoteItems(string $feed_url): ?array {
    if (!$this->isValidFeedUrl($feed_url)) {
      return NULL;
    }

    $feed_url = $this->normalizeFeedUrl($feed_url);
    $xml = $this->fetchFeedXml($feed_url);
    if ($xml === NULL) {
      return NULL;
    }

    return $this->parseFeedItems($xml);
  }

  /**
   * Parse RSS feed items.
   *
   * @param string $xml
   *   The RSS feed XML.
   *
   * @return array|null
   *   Parsed feed items, or NULL if the XML is not a usable RSS feed.
   */
  public function parseFeedItems(string $xml): ?array {
    $previous = libxml_use_internal_errors(TRUE);
    $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$feed || empty($feed->channel->item)) {
      return $feed ? [] : NULL;
    }

    $items = [];
    foreach ($feed->channel->item as $rss_item) {
      $title = $this->normalizeText((string) $rss_item->title);
      $url = $this->normalizeFeedUrl((string) $rss_item->link);
      if (!$title || !UrlHelper::isValid($url, TRUE)) {
        continue;
      }

      $timestamp = strtotime((string) $rss_item->pubDate);
      $items[] = [
        'title' => $title,
        'url' => $url,
        'timestamp' => $timestamp ?: NULL,
        'source' => $this->extractSource($rss_item),
      ];

      if (count($items) >= self::MAX_CACHE_ITEMS) {
        break;
      }
    }

    return $items;
  }

  /**
   * Check whether a feed URL points to a ReliefWeb RSS endpoint.
   *
   * @param string $feed_url
   *   The URL to validate.
   *
   * @return bool
   *   TRUE if this is a supported ReliefWeb RSS URL, FALSE otherwise.
   */
  public function isValidFeedUrl(string $feed_url): bool {
    $feed_url = $this->normalizeFeedUrl($feed_url);
    if (!UrlHelper::isValid($feed_url, TRUE)) {
      return FALSE;
    }

    $parts = parse_url($feed_url);
    $scheme = strtolower($parts['scheme'] ?? '');
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';

    return $scheme === 'https' && $host === 'reliefweb.int' && str_ends_with($path, '/rss.xml');
  }

  /**
   * Build the cache tag used for one ReliefWeb RSS feed URL.
   *
   * @param string $feed_url
   *   The ReliefWeb RSS feed URL.
   *
   * @return string
   *   The cache tag.
   */
  public function getCacheTag(string $feed_url): string {
    return self::SOURCE . ':' . hash('sha256', $this->normalizeFeedUrl($feed_url));
  }

  /**
   * Get cached items, queueing or refreshing as needed.
   *
   * @param string $feed_url
   *   The normalized feed URL.
   *
   * @return array|null
   *   Cached items, fresh items, or NULL when the cache cannot be used.
   */
  private function getCachedItems(string $feed_url): ?array {
    if (!$this->remoteDataCache?->isEnabled()) {
      return NULL;
    }

    $cid = $this->remoteDataCache->buildCid(self::SOURCE, $feed_url);
    $cached_item = $this->remoteDataCache->get($cid);
    if (!$cached_item || !is_array($cached_item->getPayload())) {
      return NULL;
    }

    if ($cached_item->isFresh()) {
      return $cached_item->getPayload();
    }

    if ($cached_item->isStale()) {
      $this->remoteDataCache->queueRefresh($cached_item);
      return $cached_item->getPayload();
    }

    $items = $this->fetchRemoteItems($feed_url);
    if ($items !== NULL) {
      $this->storeCachedItems($feed_url, $items);
      return $items;
    }

    return $this->remoteDataCache->canServeExpiredOnError() ? $cached_item->getPayload() : NULL;
  }

  /**
   * Store parsed items in the remote data cache.
   *
   * @param string $feed_url
   *   The normalized feed URL.
   * @param array $items
   *   The parsed feed items.
   */
  private function storeCachedItems(string $feed_url, array $items): void {
    if (!$this->remoteDataCache?->isEnabled()) {
      return;
    }

    $this->remoteDataCache->set($this->remoteDataCache->buildCid(self::SOURCE, $feed_url), array_values($items), [
      'refresher_id' => self::REFRESHER_ID,
      'endpoint_url' => $feed_url,
      'request_body' => '',
      'context' => [],
      'cache_tags' => [
        $this->getCacheTag($feed_url),
      ],
      'fresh_ttl' => self::FRESH_TTL,
      'stale_ttl' => self::STALE_TTL,
    ]);
  }

  /**
   * Fetch the raw RSS XML.
   *
   * @param string $feed_url
   *   The normalized feed URL.
   *
   * @return string|null
   *   The XML response, or NULL on failure.
   */
  private function fetchFeedXml(string $feed_url): ?string {
    try {
      $response = $this->httpClient->request('GET', $feed_url, [
        'connect_timeout' => 3,
        'timeout' => 8,
        'http_errors' => FALSE,
        'headers' => [
          'Accept' => 'application/rss+xml, application/xml;q=0.9, text/xml;q=0.8',
        ],
      ]);
    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('ghi_blocks')->warning('Failed to fetch ReliefWeb RSS feed @url: @message', [
        '@url' => $feed_url,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }

    $status_code = $response->getStatusCode();
    if ($status_code < 200 || $status_code >= 300) {
      $this->loggerFactory->get('ghi_blocks')->warning('Failed to fetch ReliefWeb RSS feed @url: HTTP @status_code', [
        '@url' => $feed_url,
        '@status_code' => $status_code,
      ]);
      return NULL;
    }

    $xml = (string) $response->getBody();
    if (strlen($xml) > self::MAX_BODY_SIZE) {
      $this->loggerFactory->get('ghi_blocks')->warning('Skipped ReliefWeb RSS feed @url because the response is larger than @limit bytes.', [
        '@url' => $feed_url,
        '@limit' => self::MAX_BODY_SIZE,
      ]);
      return NULL;
    }

    return $xml;
  }

  /**
   * Extract source metadata from an RSS item.
   *
   * @param \SimpleXMLElement $rss_item
   *   The RSS item.
   *
   * @return string
   *   The source label.
   */
  private function extractSource(\SimpleXMLElement $rss_item): string {
    $sources = [];
    foreach ($rss_item->author as $author) {
      $source = $this->normalizeText((string) $author);
      if ($source) {
        $sources[] = $source;
      }
    }

    if ($sources) {
      return implode(', ', array_unique($sources));
    }

    return $this->extractSourceFromDescription((string) $rss_item->description);
  }

  /**
   * Extract source metadata from ReliefWeb's HTML description.
   *
   * @param string $description
   *   The RSS item description markup.
   *
   * @return string
   *   The source label.
   */
  private function extractSourceFromDescription(string $description): string {
    if (!$description) {
      return '';
    }

    $dom = Html::load($description);
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " tag ") and contains(concat(" ", normalize-space(@class), " "), " source ")]');
    if (!$nodes || $nodes->length === 0) {
      return '';
    }

    $source = $this->normalizeText($nodes->item(0)->textContent);
    return preg_replace('/^Sources?:\s*/', '', $source) ?? $source;
  }

  /**
   * Normalize feed URLs before validation and caching.
   *
   * @param string $url
   *   The URL.
   *
   * @return string
   *   The trimmed URL.
   */
  private function normalizeFeedUrl(string $url): string {
    return trim($url);
  }

  /**
   * Normalize text extracted from XML or description markup.
   *
   * @param string $text
   *   Raw text.
   *
   * @return string
   *   Normalized text.
   */
  private function normalizeText(string $text): string {
    return trim(preg_replace('/\s+/', ' ', Html::decodeEntities($text)) ?? '');
  }

}
