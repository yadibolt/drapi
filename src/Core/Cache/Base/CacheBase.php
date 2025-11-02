<?php

namespace Drupal\drapi\Core\Cache\Base;

use Drupal;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drapi\Core\Cache\Enum\CacheIntent;

abstract class CacheBase {
  protected const string CACHE_BIN_KEY = CACHE_BIN_KEY_DEFAULT;
  protected const string CACHE_TAGS_BIN_KEY = CACHE_TAGS_BIN_KEY_DEFAULT;
  protected const int CACHE_DURATION = CACHE_DURATION_DEFAULT;

  protected string $binKey = self::CACHE_BIN_KEY;
  protected int $duration = self::CACHE_DURATION;

  public function __construct(string $binKey = self::CACHE_BIN_KEY, int $duration = self::CACHE_DURATION) {
    if (!empty($binKey)) {
      $this->binKey = $binKey;
    } else {
      $this->binKey = self::CACHE_BIN_KEY;
    }
    if ($duration > 0) {
      $this->duration = $duration;
    } else {
      $this->duration = self::CACHE_DURATION;
    }
  }

  public function get(string $key, CacheIntent $intent, string $langcode = 'en'): mixed {
    $key = $this->makeKey($key, $intent, $langcode);

    $record = Drupal::cache($this->binKey)->get($key);
    if (empty($record) || empty($record->data)) return null;

    return unserialize($record->data);
  }
  public function create(string $key, CacheIntent $intent, mixed $data, array $tags = [], string $langcode = 'en'): bool {
    $key = $this->makeKey($key, $intent, $langcode);

    if ($this->exists($key)) return false;
    $data = serialize($data);

    $tagsCacheBin = Drupal::cache(self::CACHE_TAGS_BIN_KEY);
    foreach ($tags as $tag) {
      $tagRecord = $tagsCacheBin->get($tag);
      $cacheTags = $tagRecord && !empty($tagRecord->data) ? $tagRecord->data : [];
      if (!isset($cacheTags[$key])) $cacheTags[$key] = 1;

      $tagsCacheBin->set($tag, $cacheTags, CACHE::PERMANENT);
    }

    Drupal::cache($this->binKey)->set($key, $data, $this->getCacheDurationTimestamp());

    return true;
  }
  public function delete(string $key, CacheIntent $intent, string $langcode = 'en'): void {
    $key = $this->makeKey($key, $intent, $langcode);
    Drupal::cache($this->binKey)->delete($key);
  }
  public function flush(): void {
    Drupal::cache($this->binKey)->deleteAll();
    Drupal::cache(self::CACHE_TAGS_BIN_KEY)->deleteAll();
  }
  public function invalidateTags(array $tags): void {
    $cacheTagsToInvalidate = [];
    $cacheIdsToInvalidate = [];

    $tagsCacheBin = Drupal::cache(self::CACHE_TAGS_BIN_KEY);
    foreach ($tags as $tag) {
      $tagRecords = $tagsCacheBin->get($tag);
      $records = $tagRecords && !empty($tagRecords->data) ? $tagRecords->data : [];

      if (!empty($records) && is_array($records)) {
        $cacheIdsToInvalidate = array_merge($cacheIdsToInvalidate, array_keys($records));
        $cacheTagsToInvalidate[] = $tag;
      }
    }

    Drupal::cache($this->binKey)->deleteMultiple($cacheIdsToInvalidate);
    $tagsCacheBin->deleteMultiple($cacheTagsToInvalidate);
  }
  public function invalidateEntityTags(EntityInterface|string $entity): void {
    $tagsCacheBin = Drupal::cache(self::CACHE_TAGS_BIN_KEY);

    $cacheTagsToInvalidate = [];
    $cacheIdsToInvalidate = [];

    if (is_string($entity)) {
      $tag = $entity;
      $tagRecords = $tagsCacheBin->get($tag);
      $records = $tagRecords && !empty($tagRecords->data) ? $tagRecords->data : [];

      if (empty($records) || !is_array($records)) return;

      $cacheIdsToInvalidate = array_merge($cacheIdsToInvalidate, array_keys($records));
      $cacheTagsToInvalidate[] = $tag;

      Drupal::cache($this->binKey)->deleteMultiple($cacheIdsToInvalidate);
      $tagsCacheBin->deleteMultiple($cacheTagsToInvalidate);
      return;
    }

    if ($entity->getEntityTypeId() === 'menu_link_content') {
      $tag = 'menu_link_content';
      $tagRecords = $tagsCacheBin->get($tag);
      $records = $tagRecords && !empty($tagRecords->data) ? $tagRecords->data : [];

      if (empty($records) || !is_array($records)) return;

      $cacheIdsToInvalidate = array_merge($cacheIdsToInvalidate, array_keys($records));
      $cacheTagsToInvalidate[] = $tag;

      Drupal::cache($this->binKey)->deleteMultiple($cacheIdsToInvalidate);
      $tagsCacheBin->deleteMultiple($cacheTagsToInvalidate);
      return;
    }

    $tags = $entity->getCacheTags();
    foreach ($tags as $tag) {
      $tagRecords = $tagsCacheBin->get($tag);
      $records = $tagRecords && !empty($tagRecords->data) ? $tagRecords->data : [];

      if (empty($records) || !is_array($records)) return;

      $cacheIdsToInvalidate = array_merge($cacheIdsToInvalidate, array_keys($records));
      $cacheTagsToInvalidate[] = $tag;
    }

    Drupal::cache($this->binKey)->deleteMultiple($cacheIdsToInvalidate);
    $tagsCacheBin->deleteMultiple($cacheTagsToInvalidate);
  }

  protected function exists(string $key): bool {
    $record = Drupal::cache($this->binKey)->get($key);
    return !empty($record);
  }

  protected function makeKey(string $key, CacheIntent $intent, string $langcode): string {
    return "{$this->binKey}_{$intent->value}:$key:$langcode";
  }

  protected function getCacheDurationTimestamp(): int {
    return time() + $this->getCacheDuration();
  }
  public function getCacheBinKey(): string {
    return $this->binKey;
  }
  public function getCacheDuration(): int {
    return $this->duration;
  }

  public function setCacheBinKey(string $binKey): self {
    if (!empty($cacheBinKey)) $this->binKey = $cacheBinKey;
    return $this;
  }
  public function setCacheDuration(int $duration): self {
    if ($duration > 0) $this->duration = $duration;
    return $this;
  }
}
