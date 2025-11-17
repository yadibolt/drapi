<?php

namespace Drupal\drapi\Routes\v1\Demo;

use Drupal\drapi\Core\Content\Entity\Resolver\PathResolver;
use Drupal\drapi\Core\Content\Field\Resolver\FieldResolver;
use Drupal\drapi\Core\Http\Reply;
use Drupal\drapi\Core\Http\Route\Base\RouteHandler;
use Drupal\drapi\Core\Http\Route\Base\RouteHandlerBase;
use Drupal\drapi\Core\Session\Subject;
use Drupal\user\Entity\User;

#[RouteHandler(
  id: "drapi:content:resolver",
  name: "Drapi Demo Content Route",
  method: "GET",
  path: "v1/resolve",
  description: "Demo route for content",
  permissions: ["access content"],
  roles: [],
  useMiddleware: ["request", "auth"],
  useCache: true,
),
]
class ContentResolver extends RouteHandlerBase {
  public function handle(): Reply {
    $queryParams = $this->getQueryParams();

    if (empty($queryParams['dest'])) return Reply::make([
      'message' => 'Required query parameter "dest" is missing.',
    ]);

    $resolver = PathResolver::make($queryParams['dest']);
    $entity = $resolver->resolve();

    if (!$entity) return Reply::make([
      'message' => 'No entity found for the given destination path.',
    ], 404);

    if (!method_exists($entity, 'getFields')) {
      return Reply::make([
        'message' => 'The resolved entity does not support field retrieval.',
      ], 400);
    }

    if (!method_exists($entity, 'hasTranslation') || !method_exists($entity, 'getTranslation')) {
      return Reply::make([
        'message' => 'The resolved entity does not support translations.',
      ], 400);
    }

    $this->setCacheTags(["{$entity->getEntityTypeId()}:{$entity->id()}"]);

    return Reply::make([
      'message' => 'Resolved.',
      'id' => (int) $entity->id(),
      'type' => $entity->getEntityTypeId(),
      'content_type' => $entity->bundle(),
    ]);
  }
}
