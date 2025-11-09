<?php

namespace Drupal\drapi\Routes\v1\Demo\Menu;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\drapi\Core\Http\Reply;
use Drupal\drapi\Core\Http\Route\Base\RouteHandler;
use Drupal\drapi\Core\Http\Route\Base\RouteHandlerBase;

#[RouteHandler(
    id: "drapi:menu:main",
    name: "Drapi Demo Main Menu",
    method: "GET",
    path: "v1/main-menu",
    description: "Demo route for content",
    permissions: ["access content"],
    roles: [],
    useMiddleware: ["request", "auth"],
    useCache: true,
  ),
]
class MainMenuRoute extends RouteHandlerBase {
  public function handle(): Reply {
    $this->setCacheTags(['menu_link_content:1']);

    $menu_name = 'main';
    $langcode = $this->getRequestLangcode();

    $menu_tree = \Drupal::service('menu.link_tree');

    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks();

    $tree = $menu_tree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:flatten'],
    ];

    $tree = $menu_tree->transform($tree, $manipulators);

    return Reply::make([
      'message' => 'Menu retrieved successfully.',
      'links' => $this->transformTreeLinks($tree, $langcode),
    ]);
  }

  private function transformTreeLinks(array $tree, string $langcode): array {
    $items = [];

    foreach ($tree as $element) {
      $link = $element->link;

      if ($link->isTranslatable() && $link->getPluginDefinition()['language'] !== $langcode) {
        continue;
      }

      $title = $link->getTitle();
      $url = $link->getUrlObject();

      if ($url->access() === FALSE) {
        continue;
      }

      $children = [];
      if ($element->hasChildren) {
        $children = $this->transformTreeLinks($element->subtree, $langcode);
      }

      $items[] = [
        'title'    => $title,
        'link'     => $url->toString(),
        'children' => $children,
      ];
    }

    return $items;
  }
}
