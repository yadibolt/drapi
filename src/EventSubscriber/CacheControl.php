<?php

namespace Drupal\drapi\EventSubscriber;

use Drupal\drapi\Core\Auth\JWT;
use Drupal\drapi\Core\Cache\Cache;
use Drupal\drapi\Core\Cache\Enum\CacheIntent;
use Drupal\drapi\Core\Http\Middleware\AuthMiddleware;
use Drupal\drapi\Core\Http\Reply;
use Drupal\drapi\Core\Session\Enum\SubjectIntent;
use Drupal\drapi\Core\Session\Session;
use Drupal\drapi\Core\Session\Subject;
use Drupal\drapi\Core\Http\Route\Route;
use Drupal\drapi\EventSubscriber\Trait\RouteTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheControl implements EventSubscriberInterface{
  use RouteTrait;

  protected const int PRIORITY = 999;

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', self::PRIORITY],
    ];
  }

  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) return;

    $request = $event->getRequest();
    $method = $request->getMethod();

    if (strtolower($method) === 'get') {
      $userToken = '';

      $authorizationHeader = $request->headers->get('authorization');
      if (!empty($authorizationHeader) && preg_match('/^Bearer\s+(\S+)$/', $authorizationHeader, $matches)) {
        $userToken = $matches[1] ?? '';
      }

      $cache = Cache::make();
      $cacheIdentifier = $request->getRequestUri();
      $langcode = $request->headers->get('Accept-Language', 'en');

      // 1. with adder
      // with adder, additional checks run before it can be accessed
      if (!empty($userToken)) $cacheIdentifier .= ROUTE_CACHE_TOKEN_ADDER_DEFAULT . $userToken;
      $cacheHit = $cache->get($cacheIdentifier, CacheIntent::URL, $langcode);

      // 2. without adder - default uri with query params
      if (empty($cacheHit)) {
        $cacheIdentifier = $request->getRequestUri();
        $cacheHit = $cache->get($cacheIdentifier, CacheIntent::URL, $langcode);

        // since the adder is not present, this has to be public GET route
        // therefore we can return the cache hit directly
        if (!empty($cacheHit)) {
          $cacheHit = $this->createCachedResponse($cache, $cacheIdentifier, $cacheHit, null);
          if ($cacheHit === null) return;

          $event->setResponse(
            Reply::make($cacheHit['data'], $cacheHit['status'], $cacheHit['headers'])
          );
          $event->stopPropagation();
        }
      }

      if (empty($cacheHit)) return;

      /** @var Route $route */
      [$route, $configuration] = $this->getCurrentRoute($request);

      if (empty($route)) return;

      $rolesEmpty = empty($route->getRoles());
      $permissionsEmpty = empty($route->getPermissions()) || ((count($route->getPermissions()) === 1) && $route->getPermissions()[0] === 'access content');
      $middlewareEmpty = empty($route->getUseMiddleware()) || !in_array(AuthMiddleware::getId(), $route->getUseMiddleware());

      if ($rolesEmpty && $permissionsEmpty && $middlewareEmpty) {
        $cacheHit = $this->createCachedResponse($cache, $cacheIdentifier, $cacheHit, $route);
        if ($cacheHit === null) return;

        $event->setResponse(
          Reply::make($cacheHit['data'], $cacheHit['status'], $cacheHit['headers'])
        );
        $event->stopPropagation();
      } else {
        $subject = Subject::makeAnonymous();
        if (!empty($userToken)) {
          $checked = JWT::check($userToken);
          if (!$checked->isValid() || $checked->isExpired() || $checked->hasError()) return;

          $payload = JWT::payloadFrom($userToken);
          if (!$this->checkPayload($payload)) return;

          if ($payload['data']['type'] === SubjectIntent::ANONYMOUS) return;

          $subject = Session::make($userToken)->find()?->getSubject();
          if (!$subject) return;
          if (!$subject->isActive()) return;
        }

        if (!$this->checkRequirements($subject, $route)) return;

        $cacheHit = $this->createCachedResponse($cache, $cacheIdentifier, $cacheHit, $route);
        if ($cacheHit === null) return;

        $event->setResponse(
          Reply::make($cacheHit['data'], $cacheHit['status'], $cacheHit['headers'])
        );
        $event->stopPropagation();
      }
    }
  }

  protected function getCachedHeaders(ResponseHeaderBag $headers, int $duration): ResponseHeaderBag {
    $headers->set('Cache-Control', 'public, max-age=' . $duration);
    $headers->set(HTTP_HEADER_CACHEABLE_NAME_DEFAULT, HTTP_HEADER_CACHEABLE_DEFAULT);
    $headers->set(HTTP_HEADER_CACHE_NAME_DEFAULT, HTTP_HEADER_CACHED_DEFAULT);
    $headers->set(HTTP_HEADER_CACHE_HIT_NAME_DEFAULT, HTTP_HEADER_CACHE_HIT_DEFAULT);
    $headers->set('Pragma', 'cache');
    $headers->set('Date', gmdate('D, d M Y H:i:s') . ' GMT');

    return $headers;
  }
  protected function checkRequirements(Subject $subject, Route $route): bool {
    $routePermissions = $route->getPermissions() ?? [];
    $routeRoles = $route->getRoles() ?? [];

    $permissions = $subject->getPermissions();
    if (array_any($routePermissions,fn($routePermission) => !in_array($routePermission, $permissions))) {
      return false;
    }

    $roles = $subject->getRoles();
    if (array_any($routeRoles, fn($routeRole) => !in_array($routeRole, $roles))) {
      return false;
    }

    return true;
  }
  protected function checkPayload(array $payload): bool {
    if (empty($payload)) return false;
    if (!isset($payload['data']))

    if (!isset($payload['data']['user_id'])) return false;
    if (!is_numeric($payload['data']['user_id'])) return false;
    if ((int)$payload['data']['user_id'] <= 0) return false;

    if (!isset($payload['data']['type'])) return false;
    if (!is_string($payload['data']['type'])) return false;
    if ($payload['data']['type'] !== SubjectIntent::AUTHENTICATED->value) return false;

    return true;
  }
  protected function createCachedResponse(Cache $cache, string $cacheIdentifier, array $cacheHit, ?Route $route): ?array {
    if ($cacheHit['headers_replaced'] === false) {
      $langcode = 'en';

      if ($cacheHit['headers'] instanceof ResponseHeaderBag) {
        $langcode = $cacheHit['headers']->get('Content-Language') ?? 'en';
        $cacheHit['headers'] = $this->getCachedHeaders($cacheHit['headers'], $cache->getCacheDuration());
      }

      $cacheTags = [];
      if ($route) {
        if (!empty($route->getCacheTags()) && is_array($route->getCacheTags())) {
          $cacheTags = $route->getCacheTags() ?? [];
        }
      }

      if (is_string($cacheHit['data']) && json_validate($cacheHit['data'])) {
        $cacheHit['data'] = json_decode($cacheHit['data'], true);
      }

      $cacheHit['data']['timestamp'] = time();
      $cacheHit['headers_replaced'] = true;

      if (is_array($cacheHit['data'])) $cacheHit['data'] = json_encode($cacheHit['data']);

      $cache->create($cacheIdentifier, CacheIntent::URL, $cacheHit, $cacheTags, $langcode);

      return $cacheHit;
    }

    return null;
  }
}
