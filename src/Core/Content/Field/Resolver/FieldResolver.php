<?php

namespace Drupal\drapi\Core\Content\Field\Resolver;

use Drupal\drapi\Core\Content\Field\BooleanField;
use Drupal\drapi\Core\Content\Field\DaterangeField;
use Drupal\drapi\Core\Content\Field\EntityReferenceField;
use Drupal\drapi\Core\Content\Field\FileField;
use Drupal\drapi\Core\Content\Field\FloatField;
use Drupal\drapi\Core\Content\Field\IntegerField;
use Drupal\drapi\Core\Content\Field\LinkField;
use Drupal\drapi\Core\Content\Field\PathField;
use Drupal\drapi\Core\Content\Field\StringField;
use Drupal\drapi\Core\Content\Field\TextField;

class FieldResolver {
  protected const array FIELD_TYPE_HANDLERS = [
    // bool
    'boolean' => BooleanField::class,
    // int
    'integer' => IntegerField::class,
    'list_integer' => IntegerField::class,
    'timestamp' => IntegerField::class,
    'created' => IntegerField::class,
    'changed' => IntegerField::class,
    // float
    'float' => FloatField::class,
    'list_float' => FloatField::class,
    'decimal' => FloatField::class,
    // string
    'string' => StringField::class,
    'string_long' => StringField::class,
    'list_string' => StringField::class,
    'email' => StringField::class,
    'mail' => StringField::class,
    'uuid' => StringField::class,
    'telephone' => StringField::class,
    'datetime' => StringField::class,
    'language' => StringField::class,
    // text
    'text' => TextField::class,
    'text_long' => TextField::class,
    'text_with_summary' => TextField::class,
    // path
    'path' => PathField::class,
    // none
    'password' => null,
    // daterange
    'daterange' => DaterangeField::class,
    // entity reference
    'entity_reference' => EntityReferenceField::class,
    // file
    'file' => FileField::class,
    'image' => FileField::class,
    'link' => LinkField::class,
  ];
  protected array $fields = [];
  protected bool $loadCustom;
  protected bool $loadEntities;
  protected bool $loadProtected;
  protected bool $stripFieldPrefixes;

  public function __construct() {
    $this->loadEntities = true;
    $this->loadCustom = true;
    $this->loadProtected = true;
    $this->stripFieldPrefixes = false;
  }

  public static function make(array $fields, array $options = []): self {
    $instance = new self();
    $instance->handleOptions($options);
    $instance->fields = $fields;
    return $instance;
  }

  public function resolve($depth = 0): array {
    if (empty($this->fields)) return [];

    $resolved = [];
    foreach ($this->fields as $fieldName => $field) {
      $definition = $field->getFieldDefinition();
      $type = $definition->getType();

      if (!in_array($fieldName, $this->getBaseFieldNames()) && !$this->loadCustom) continue;
      if (in_array($fieldName, $this->getProtectedFieldNames()) && !$this->loadProtected) continue;

      if (!isset(self::FIELD_TYPE_HANDLERS[$type])) continue;

      $strippedFieldName = $fieldName;
      if ($this->getStripFieldPrefixes() && str_starts_with($fieldName, 'field_')) $strippedFieldName = substr($fieldName, 6);

      $handler = self::FIELD_TYPE_HANDLERS[$type];
      if ($handler === null) {
        $resolved[$strippedFieldName] = null;
        continue;
      }

      $resolved[$strippedFieldName] = new $handler($field)->getFieldValues([
        'load_entities' => !($depth > 0) && $this->getLoadEntities(),
        'load_custom' => $this->getLoadCustom(),
        'load_protected' => $this->getLoadProtected(),
        'strip_field_prefixes' => $this->getStripFieldPrefixes(),
      ]);
    }

    return $resolved;
  }
  protected function handleOptions(array $options): self {
    if (isset($options['load_entities']) && is_bool($options['load_entities'])) $this->setLoadEntities($options['load_entities']);
    if (isset($options['load_custom']) && is_bool($options['load_custom'])) $this->setLoadCustom($options['load_custom']);
    if (isset($options['load_protected']) && is_bool($options['load_protected'])) $this->setLoadProtected($options['load_protected']);
    if (isset($options['strip_field_prefixes']) && is_bool($options['strip_field_prefixes'])) $this->setStripFieldPrefixes($options['strip_field_prefixes']);

    return $this;
  }
  public function setLoadEntities(bool $loadEntities): self {
    $this->loadEntities = $loadEntities;
    return $this;
  }
  public function setStripFieldPrefixes(bool $stripFieldPrefixes): self {
    $this->stripFieldPrefixes = $stripFieldPrefixes;
    return $this;
  }
  public function setLoadCustom(bool $loadCustom): self {
    $this->loadCustom = $loadCustom;
    return $this;
  }
  public function setLoadProtected(bool $loadProtected): self {
    $this->loadProtected = $loadProtected;
    return $this;
  }

  public function getBaseFieldNames(): array {
    return [
      'nid',
      'vid',
      'info',
      'title',
      'type',
      'langcode',
      'status',
      'promote',
      'sticky',
      'created',
      'changed',
      'path',
    ];
  }
  public function getProtectedFieldNames(): array {
    return [
      'uid',
      'uuid',
      'comment',
      'revision_id',
      'revision_user',
      'content_translation_created',
      'reusable',
      'revision_default',
      'content_translation_source',
      'content_translation_outdated',
      'content_translation_uid',
      'revision_log',
      'revision_uid',
      'preffered_admin_langcode',
      'preferred_langcode',
      'revision_created',
      'default_langcode',
      'revision_translation_affected',
    ];
  }
  public function getLoadEntities(): bool {
    return $this->loadEntities;
  }
  public function getLoadCustom(): bool {
    return $this->loadCustom;
  }
  public function getLoadProtected(): bool {
    return $this->loadProtected;
  }
  public function getStripFieldPrefixes(): bool {
    return $this->stripFieldPrefixes;
  }
}
