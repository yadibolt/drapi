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
    id: "drapi:content",
    name: "Drapi Demo Content Route",
    method: "GET",
    path: "v1/content",
    description: "Demo route for content",
    permissions: ["access content"],
    roles: [],
    useMiddleware: ["request", "auth"],
    useCache: true,
  ),
]
class ContentRoute extends RouteHandlerBase {
  public function handle(): Reply {
    $this->setCacheTags(['demo']);

    $context = $this->getMiddlewareContext();
    $queryParams = $this->getQueryParams();

    /** @var Subject $user */
    $user = $context['user'] ?? null;

    if (!$user) return Reply::make([
      'message' => 'Server error.',
    ], 500);

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

    if ($entity->hasTranslation($this->language)) {
      $entity = $entity->getTranslation($this->language);
    }

    $account = User::load($user->getId());
    $access = $entity->access('view', $account);

    if (!$access) return Reply::make([
      'message' => 'You do not have access to view this entity.',
    ], 403);

    $fields = $entity->getFields();
    $options = [
      'load_entities' => true,
      'load_custom' => true,
      'load_protected' => false,
      'strip_field_prefixes' => false,
    ];
    $fieldResolver = FieldResolver::make($fields, $options);
    $fields = $fieldResolver->resolve();

    return Reply::make([
      'message' => 'Content retrieved successfully.',
      'label' => $entity->label(),
      'type' => $entity->getEntityTypeId(),
      'content_type' => $entity->bundle(),
      'fields' => $fields,
    ]);
  }
}
