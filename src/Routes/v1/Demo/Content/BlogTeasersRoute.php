<?php

namespace Drupal\drapi\Routes\v1\Demo\Content;

use Drupal;
use Drupal\block_content\Entity\BlockContent;
use Drupal\drapi\Core\Content\Field\Resolver\FieldResolver;
use Drupal\drapi\Core\Http\Reply;
use Drupal\drapi\Core\Http\Route\Base\RouteHandler;
use Drupal\drapi\Core\Http\Route\Base\RouteHandlerBase;
use Drupal\node\Entity\Node;

#[RouteHandler(
  id: "drapi:block:blog_teasers",
  name: "Drapi Demo Block",
  method: "GET",
  path: "v1/blog-teasers",
  description: "Demo route for content",
  permissions: ["access content"],
  roles: [],
  useMiddleware: ["request", "auth"],
  useCache: false,
),
]
class BlogTeasersRoute extends RouteHandlerBase {
  public function handle(): Reply {
    $query = Drupal::entityTypeManager()->getStorage('node')->getQuery();
    $postIds = $query
      ->condition('type', 'blog_page')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 3)
      ->accessCheck(false)
      ->execute();

    $nodes = Node::loadMultiple($postIds);
    if (empty($nodes)) return Reply::make([
      'message' => 'No blog posts found',
    ], 404);

    foreach ($postIds as $postId) {
      $this->setCacheTags(["node:$postId"]);
    }

    $posts = [];
    foreach ($nodes as $node) {
      if ($node->hasTranslation($this->language)) {
        $node = $node->getTranslation($this->language);

        $fields = $node->getFields();
        $options = [
          'load_entities' => true,
          'load_custom' => true,
          'load_protected' => false,
          'strip_field_prefixes' => false,
        ];
        $fieldResolver = FieldResolver::make($fields, $options);
        $posts[] = $fieldResolver->resolve();
      }
    }

    return Reply::make([
      'message' => 'Content retrieved successfully.',
      'posts' => $posts,
    ]);
  }

}
