<?php

namespace Drupal\drapi\Core\Content\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\drapi\Core\Content\Field\Base\FieldBase;
use Drupal\drapi\Core\Content\Field\Interface\FieldInterface;
use Drupal\drapi\Core\Content\Field\Resolver\FieldResolver;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

class EntityReferenceField extends FieldBase implements FieldInterface {
  public function __construct(FieldItemListInterface $field){
    parent::__construct($field);
  }
  public function getFieldValues(array $options = []): null|int|array {
    $this->handleOptions($options);

    $values = $this->getValues();
    $targetType = $this->field->getFieldDefinition()->getSetting('target_type');

    $arrayValues = [];
    if (count($values) === 1 && !empty($values[0]) && isset($values[0]['target_id'])) {
      $arrayValues[] = (int)$values[0]['target_id'];
    }

    if (count($values) > 1) {
      foreach ($values as $value) {
        if (!empty($value) && isset($value['target_id'])) {
          $arrayValues[] = (int)$value['target_id'];
        }
      }
    }

    if ($this->getLoadEntities()) {
      $entities = $this->getEntityFields($targetType, $arrayValues);
      return $this->flattenValues($entities);
    } else {
      return $this->flattenValues($arrayValues);
    }
  }

  protected function getEntityFields(string $entityType, array $ids): ?array {
    if (empty($ids)) return null;

    $loaderValues = $this->getEntityLoaderValues($entityType, $ids);

    if (empty($loaderValues)) return null;

    $result = [];
    foreach ($loaderValues as $loaderValue) {
      $result[] = FieldResolver::make($loaderValue->getFields(), [
        'load_entities' => $this->getLoadEntities(),
        'load_custom' => $this->getLoadCustom(),
        'load_protected' => $this->getLoadProtected(),
        'strip_field_prefixes' => $this->getStripFieldPrefixes(),
      ])->resolve(1);
    }

    return $result;
  }

  protected function getEntityLoaderValues(string $entityType, $ids): array {
    return match ($entityType) {
      'node' => Node::loadMultiple($ids),
      'user' => User::loadMultiple($ids),
      'user_role' => Role::loadMultiple($ids),
      'taxonomy_term' => Term::loadMultiple($ids),
      'file' => File::loadMultiple($ids),
      'media' => Media::loadMultiple($ids),
      'media_type' => MediaType::loadMultiple($ids),
      default => [],
    };
  }
}
