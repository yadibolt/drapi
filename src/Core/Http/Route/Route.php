<?php

namespace Drupal\drapi\Core\Http\Route;

use Drupal\drapi\Core\Http\Route\Base\RouteBase;
use Drupal\drapi\Core\Utility\Enum\LoggerIntent;
use Drupal\drapi\Core\Utility\Logger;
use Exception;
use ReflectionAttribute;

class Route extends RouteBase {
  /**
   * @throws Exception
   */
  public static function make(string $id, string $name, string $method, string $description, string $path, array $permissions, array $roles, array $useMiddleware, bool $useCache, array $cacheTags = [], string $filePath = ''): self {
    return new self($id, $name, $method, $description, $path, $permissions, $roles, $useMiddleware, $useCache, $cacheTags, $filePath);
  }
  public static function fromAttributes(string $filePath): ?self {
    $attributes = self::getFileAttributes($filePath);

    if (empty($attributes)) return null;
    if (!(reset($attributes) instanceof ReflectionAttribute)) return null;

    $args = reset($attributes)->getArguments();
    if (empty($args)) return null;

    try {
      return self::make(
        id: $args['id'],
        name: $args['name'],
        method: $args['method'],
        description: $args['description'] ?? '',
        path: $args['path'],
        permissions: $args['permissions'] ?? [],
        roles: $args['roles'] ?? [],
        useMiddleware: $args['useMiddleware'] ?? [],
        useCache: $args['useCache'] ?? false,
        cacheTags: [], // cache tags are later set with RouteHandler function setCacheTags()
        filePath: $filePath
      );
    } catch (Exception $e) {
      Logger::l(
        level: LoggerIntent::ERROR,
        message: 'Error creating Route from attributes: @error',
        context: ['@error' => $e->getMessage()]
      );
    }

    return null;
  }
}
