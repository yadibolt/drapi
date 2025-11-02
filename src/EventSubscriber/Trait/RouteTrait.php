<?php

namespace Drupal\drapi\EventSubscriber\Trait;

use Drupal;
use Drupal\Core\Config\Config;
use Drupal\drapi\Core\Http\Route\Route;
use Symfony\Component\HttpFoundation\Request;

trait RouteTrait {
  /**
   * @return array{0: Route|null, 1: Config}
   */
  public function getCurrentRoute(Request $request): array {
    $configuration = Drupal::configFactory()->get(ROUTE_CONFIG_NAME_DEFAULT);
    $routeRegistry = $configuration->get('route_registry') ?? [];

    $uri = ltrim($request->getRequestUri(), '/');
    $uri = explode('?', $uri)[0];

    $uriParts = mb_split('/', $uri);

    foreach ($routeRegistry as $route) {
      if (!is_string($route)) continue;
      $route = unserialize($route);

      /** @var Route $route */
      if (empty($route->getPath())) continue;

      $parts = mb_split('/', $route->getPath());
      if (count($parts) !== count($uriParts)) continue;

      for ($i = 0; $i < count($parts); $i++) {
        if (str_starts_with($parts[$i], '{') && str_ends_with($parts[$i], '}')) continue;
        if ($parts[$i] !== $uriParts[$i]) continue 2;
      }

      $name = ROUTE_NAME_PREFIX_DEFAULT . ':' . $route->getId();
      $request->attributes->add(['_route' => $name]);

      return [$route, $configuration];
    }

    return [null, $configuration];
  }
}
