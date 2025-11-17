<?php

namespace Drupal\drapi\Routes\v1\Demo\Page;

use Drupal;
use Drupal\block_content\Entity\BlockContent;
use Drupal\drapi\Core\Content\Field\Resolver\FieldResolver;
use Drupal\drapi\Core\Http\Reply;
use Drupal\drapi\Core\Http\Route\Base\RouteHandler;
use Drupal\drapi\Core\Http\Route\Base\RouteHandlerBase;
use Drupal\node\Entity\Node;

#[RouteHandler(
  id: "drapi:block:blog",
  name: "Drapi Demo Block",
  method: "GET",
  path: "v1/node/blog_page/{blogId}",
  description: "Demo route for content",
  permissions: ["access content"],
  roles: [],
  useMiddleware: ["request", "auth"],
  useCache: true,
),
]
class BlogPageRoute extends RouteHandlerBase {
  public function handle(): Reply {
    $blogId = $this->getUriToken('blogId');
    if (empty($blogId) || !is_numeric($blogId)) {
      return Reply::make([
        'message' => 'Invalid Blog ID',
      ], 403);
    }

    $blog = Node::load($blogId);
    if (empty($blog)) {
      return Reply::make([
        'message' => 'No blog posts found',
      ], 404);
    }

    $this->setCacheTags(["node:$blogId"]);

    if ($blog->hasTranslation($this->language)) {
      $blog = $blog->getTranslation($this->language);
    }

    $fields = $blog->getFields();
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
      'label' => $blog->label(),
      'type' => $blog->getEntityTypeId(),
      'content_type' => $blog->bundle(),
      'fields' => $fields,
    ]);
  }

}
