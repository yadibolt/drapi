<?php

namespace Drupal\drapi\Routes\v1\Demo\Block;

use Drupal\block_content\Entity\BlockContent;
use Drupal\drapi\Core\Content\Field\Resolver\FieldResolver;
use Drupal\drapi\Core\Http\Reply;
use Drupal\drapi\Core\Http\Route\Base\RouteHandler;
use Drupal\drapi\Core\Http\Route\Base\RouteHandlerBase;

#[RouteHandler(
  id: "drapi:block:hero",
  name: "Drapi Demo Block",
  method: "GET",
  path: "v1/hero",
  description: "Demo route for content",
  permissions: ["access content"],
  roles: [],
  useMiddleware: ["request", "auth"],
  useCache: true,
),
]
class HeroBlockRoute extends RouteHandlerBase {
  public function handle(): Reply {
    $this->setCacheTags(['block_content:4']);

    $entity = BlockContent::load(4);
    if (!$entity) return Reply::make([
      "message" => "Block content not found",
    ], 404);

    if (!method_exists($entity, 'getFields')) {
      return Reply::make([
        "message" => "Unsupported feature",
      ], 500);
    }

    if ($entity->hasTranslation($this->language)) {
      $entity = $entity->getTranslation($this->language);
    }

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
