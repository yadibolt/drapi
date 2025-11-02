<?php

namespace Drupal\drapi\Core\Http\Base;

use Drupal;
use Drupal\drapi\Core\Cache\Cache;
use Drupal\drapi\Core\Cache\Enum\CacheIntent;
use Drupal\drapi\Core\Http\Middleware\AuthMiddleware;
use Drupal\drapi\Core\Http\Route\Route;
use Drupal\drapi\Core\Http\Trait\RequestTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use SYmfony\Component\HttpFoundation\Request;

abstract class ReplyBase extends Response {
  use RequestTrait;

  protected const int DEPTH = 512;
  protected const int FLAGS = 0;

  protected array|string $data = [];
  protected bool $responseCached = false;
  protected bool $responseCacheable = false;
  protected string $langcode = 'en';
  protected Route $route;

  public function __construct(array|string $data, int $status = 200, array|ResponseHeaderBag $headers = []) {
    if (!is_array($headers) && $headers instanceof ResponseHeaderBag) {
      if ($headers->get(HTTP_HEADER_CACHE_NAME_DEFAULT) === HTTP_HEADER_CACHED_DEFAULT) {
        if ($headers->get(HTTP_HEADER_CACHE_HIT_NAME_DEFAULT) &&
          $headers->get(HTTP_HEADER_CACHE_HIT_NAME_DEFAULT) === HTTP_HEADER_CACHE_HIT_DEFAULT) {
          $this->responseCached = true;
        }
      }
    }

    parent::__construct(
      content: $this->responseCached ? $data : '',
      status: $status,
      headers: $this->responseCached ? (is_array($headers) ? $headers : $headers->all()) : []
    );

    // we have set all data already for cached response
    // so we return here.
    if ($this->responseCached) return;

    $request = $this->getCurrentRequest();
    $requestMethod = $request->getMethod();

    $this->setLangcode($request);
    $this->setRoute();
    $this->setResponseCacheable();
    $this->setHeaders();
    $this->setStatusCode($status);

    $structuredData = $this->structData($data);
    $this->data = $structuredData; $this->setContent($structuredData);

    // if the response is cacheable, we create a new cache record here.
    // caching responses is limited to GET requests only, with non-error status codes.
    if (strtolower($requestMethod) === 'get' && $this->responseCacheable && $status < 400) {
      $cacheTags = [];
      $userToken = '';
      $usesAuthorizationMiddleware = !empty($this->route->getUseMiddleware()) && in_array(AuthMiddleware::getId(), $this->route->getUseMiddleware());

      if ($usesAuthorizationMiddleware) {
        $authorizationHeader = $request->headers->get('authorization');
        if (!empty($authorizationHeader) && preg_match('/^Bearer\s+(\S+)$/', $authorizationHeader, $matches)) {
          $userToken = $matches[1] ?? '';
        }
      }

      if (!empty($this->route->getCacheTags()) && is_array($this->route->getCacheTags())) {
        $cacheTags = $this->route->getCacheTags() ?? [];
      }

      $cacheIdentifier = $request->getRequestUri();
      if (!empty($userToken)) $cacheIdentifier .= ROUTE_CACHE_TOKEN_ADDER_DEFAULT . $userToken;

      Cache::make()->create($cacheIdentifier, CacheIntent::URL, [
        'data' => $this->data,
        'status' => $status,
        'headers' => $this->headers,
         // used to control the cache flow. if false, the subscriber will replace the headers with the cached ones.
        'headers_replaced' => false,
      ], $cacheTags, $this->getLangcode());
    }
  }
  protected function structData(string|array $data): string {
    // we apply custom structure to the responses.
    if (is_string($data) && json_validate($data, self::DEPTH, self::FLAGS)) {
      $data = json_decode($data, true, self::DEPTH, self::FLAGS);
    }

    if (isset($data['action_id'])) {
      $struct['action_id'] = $data['action_id'];
      unset($data['action_id']);
    }

    if (isset($data['message'])) {
      $struct['message'] = $data['message'] ?: '';
      unset($data['message']);
    }

    $struct['error'] = $this->statusCode >= 400;
    $struct['timestamp'] = time();

    if (!empty($data)) $struct['data'] = $data;
    return json_encode($struct, self::FLAGS, self::DEPTH) ?: "";
  }

  protected function setRoute(): self {
    $this->route = $this->getCurrentRoute();
    return $this;
  }
  protected function setHeaders(): void {
    $this->headers->set('Content-Type', 'application/json');
    $this->headers->set('Content-Language', $this->getLangcode());

    // cache hit
    if ($this->responseCached) {
      $this->headers->set(HTTP_HEADER_CACHEABLE_NAME_DEFAULT, HTTP_HEADER_CACHEABLE_DEFAULT);
      $this->headers->set(HTTP_HEADER_CACHE_NAME_DEFAULT, HTTP_HEADER_CACHED_DEFAULT);
      $this->headers->set(HTTP_HEADER_CACHE_HIT_NAME_DEFAULT, HTTP_HEADER_CACHE_HIT_DEFAULT);
      $this->headers->set('Pragma', 'cache');
      $this->headers->set('Date', gmdate('D, d M Y H:i:s') . ' GMT');
      return;
    }

    // cache did not hit but is cacheable
    if ($this->responseCacheable) {
      $this->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
      $this->headers->set(HTTP_HEADER_CACHEABLE_NAME_DEFAULT, HTTP_HEADER_CACHEABLE_DEFAULT);
      $this->headers->set(HTTP_HEADER_CACHE_NAME_DEFAULT, HTTP_HEADER_NOT_CACHED_DEFAULT);
      $this->headers->set(HTTP_HEADER_CACHE_HIT_NAME_DEFAULT, HTTP_HEADER_CACHE_MISS_DEFAULT);
      $this->headers->set('Pragma', 'no-cache');
      $this->headers->set('Date', gmdate('D, d M Y H:i:s') . ' GMT');
      return;
    }

    // not cacheable
    $this->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
    $this->headers->set(HTTP_HEADER_CACHEABLE_NAME_DEFAULT, HTTP_HEADER_NOT_CACHEABLE_DEFAULT);
    $this->headers->set(HTTP_HEADER_CACHE_NAME_DEFAULT, HTTP_HEADER_NOT_CACHED_DEFAULT);
    $this->headers->set(HTTP_HEADER_CACHE_HIT_NAME_DEFAULT, HTTP_HEADER_CACHE_MISS_DEFAULT);
    $this->headers->set('Date', gmdate('D, d M Y H:i:s') . ' GMT');
    $this->headers->set('Pragma', 'no-cache');
  }
  protected function setResponseCacheable(): void {
    if (empty($this->route)) return;
    $this->responseCacheable = $this->route->getUseCache() ?? false;
  }
  protected function setLangcode(Request $request): void {
    $langcode = $request->headers->get('Accept-Language', 'en');

    if (strlen($langcode) > 2) {
      $langcode = strtolower(substr($langcode, 0, 2));
    }

    $languages = Drupal::languageManager()->getLanguages();
    if (isset($languages[$langcode])) {
      $this->langcode = $langcode;
      return;
    }

    $this->langcode = 'en';
  }
  protected function getRoute(): Route {
    return $this->route;
  }
  protected function getLangcode(): string {
    return $this->langcode;
  }
}
