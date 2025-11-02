<?php

namespace Drupal\drapi\Core\Http\Route\Base;

use Drupal;
use Drupal\drapi\Core\Content\Trait\FileTrait;
use Drupal\drapi\Core\Http\Route\Asserters\RouteClassAsserter;
use Drupal\drapi\Core\Http\Route\Asserters\RouteExtendsAsserter;
use Drupal\drapi\Core\Http\Route\Asserters\RouteImplementsAsserter;
use Drupal\drapi\Core\Http\Route\Asserters\RouteMethodAsserter;
use Drupal\drapi\Core\Utility\Enum\LoggerIntent;
use Drupal\drapi\Core\Utility\Logger;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Routing\Route;

abstract class RouteBase {
  use FileTrait;

  public const array ALLOWED_SCHEMES = [
    'http', 'https'
  ];
  public const array ALLOWED_HTTP_METHODS = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
  ];

  protected string $id;
  protected string $name;
  protected string $method;
  protected string $description;
  protected string $path;
  protected array $permissions;
  protected array $roles;
  protected array $useMiddleware;
  protected bool $useCache;
  protected array $cacheTags;
  protected bool $enabled;
  protected string $filePath;
  //
  protected string $classNamespace = '';
  protected string $classNamespaceName = '';
  protected string $classClassName = '';
  protected string $classShortName = '';
  protected array $classInterfaces = [];
  protected array $classPublicMethods = [];
  protected string $classParentClass = '';

  /**
   * @throws Exception
   */
  public function __construct(string $id, string $name, string $method, string $description, string $path, array $permissions, array $roles, array $useMiddleware, bool $useCache, array $cacheTags = [], string $filePath = '') {
    $this->id = $id;
    $this->name = $name;
    $this->method = $method;
    $this->description = $description;
    $this->path = $path;
    $this->permissions = $permissions;
    $this->roles = $roles;
    $this->useMiddleware = $useMiddleware;
    $this->useCache = $useCache;
    $this->cacheTags = $cacheTags;
    $this->enabled = true;
    $this->filePath = $filePath;
    $this->classNamespace = $this->_getClassNamespace();
    $this->classNamespaceName = $this->_getClassNamespaceName();
    $this->classClassName = $this->_getClassClassName();
    $this->classShortName = $this->_getClassShortName();
    $this->classInterfaces = $this->_getClassInterfaces();
    $this->classPublicMethods = $this->_getClassPublicMethods();
    $this->classParentClass = $this->_getParentClassName();

    if (!$this->check() && !empty($filePath)) throw new Exception('Route assertions or validations have failed');
  }
  protected function check(): bool {
    $asserters = [
      [
        'class' => RouteClassAsserter::class, // checks class declaration
        'errorMessage' => 'RouteClassAsserter have failed for ' . $this->filePath,
      ],
      [
        'class' => RouteMethodAsserter::class, // checks for required methods
        'errorMessage' => 'RouteMethodAsserter have failed for ' . $this->filePath,
      ],
      [
        'class' => RouteExtendsAsserter::class, // checks if class extends a base class
        'errorMessage' => 'RouteExtendsAsserter have failed for ' . $this->filePath,
      ],
      [
        'class' => RouteImplementsAsserter::class, // checks if class implements an interface
        'errorMessage' => 'RouteImplementsAsserter have failed for ' . $this->filePath,
      ]
    ];

    foreach ($asserters as $asserter) {
      if (!class_exists($asserter['class'])) {
        Logger::l(
          level: LoggerIntent::CRITICAL, message: 'Asserter class @asserterClass does not exist.', context: ['@asserterClass' => $asserter['class']]
        ); return false;
      }

      $asserterInstance = new $asserter['class']();
      if (!call_user_func([$asserterInstance, 'assert'], $this)) {
        Logger::l(
          level: LoggerIntent::CRITICAL, message: $asserter['errorMessage']
        ); return false;
      }
    }

    return true;
  }
  public function toArray(): array {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'method' => $this->method,
      'description' => $this->description,
      'path' => $this->path,
      'permissions' => $this->permissions,
      'roles' => $this->roles,
      'use_middleware' => $this->useMiddleware,
      'use_cache' => $this->useCache,
      'cache_tags' => $this->cacheTags,
      'enabled' => $this->enabled,
      'class_namespace' => $this->classNamespace,
      'class_namespace_name' => $this->classNamespaceName,
      'class_class_name' => $this->classClassName,
      'class_short_name' => $this->classShortName,
      'class_interfaces' => $this->classInterfaces,
      'class_public_methods' => $this->classPublicMethods,
      'class_parent_class' => $this->classParentClass,
    ];
  }
  public function toSymfonyRoute(): ?Route {
    if (empty($this->classClassName)) {
      Logger::l(
        level: LoggerIntent::CRITICAL, message: 'Cannot create Symfony Route for @routeId because classClassName is empty.', context: ['@routeId' => $this->id]
      ); return null;
    }

    if (!in_array(strtoupper($this->method), self::ALLOWED_HTTP_METHODS)) {
      Logger::l(
        level: LoggerIntent::CRITICAL, message: 'HTTP method @method for route @routeId is not allowed.', context: ['@method' => $this->method, '@routeId' => $this->id]
      ); return null;
    }

    return new Route(
      path: $this->path,
      defaults: [
        '_title' => $this->name,
        '_controller' => $this->classClassName . '::' . 'init',
      ],
      requirements: [
        '_permission' => implode(', ', $this->permissions) ?: '',
      ],
      options: [
        ROUTE_NAME_PREFIX_DEFAULT . ':id' => $this->id,
        'no_cache' => TRUE,
      ],
      schemes: self::ALLOWED_SCHEMES,
      methods: [$this->method],
    );
  }

  public function getId(): string {
    return $this->id;
  }
  public function getName(): string {
    return $this->name;
  }
  public function getMethod(): string {
    return $this->method;
  }
  public function getDescription(): string {
    return $this->description;
  }
  public function getPath(): string {
    return $this->path;
  }
  public function getPermissions(): array {
    return $this->permissions;
  }
  public function getRoles(): array {
    return $this->roles;
  }
  public function getUseMiddleware(): array {
    return $this->useMiddleware;
  }
  public function getUseCache(): bool {
    return $this->useCache;
  }
  public function getCacheTags(): array {
    return $this->cacheTags ?? [];
  }
  public function isEnabled(): bool {
    return $this->enabled;
  }
  public function getFilePath(): string {
    return $this->filePath;
  }
  public function getClassNamespace(): string {
    return $this->classNamespace;
  }
  public function getClassNamespaceName(): string {
    return $this->classNamespaceName;
  }
  public function getClassClassName(): string {
    return $this->classClassName;
  }
  public function getClassShortName(): string {
    return $this->classShortName;
  }
  public function getClassInterfaces(): array {
    return $this->classInterfaces;
  }
  public function getClassPublicMethods(): array {
    return $this->classPublicMethods;
  }
  public function getClassParentClass(): string {
    return $this->classParentClass;
  }

  public function setId(string $id): self {
    $this->id = $id;
    return $this;
  }
  public function setName(string $name): self {
    $this->name = $name;
    return $this;
  }
  public function setMethod(string $method): self {
    $this->method = $method;
    return $this;
  }
  public function setDescription(string $description): self {
    $this->description = $description;
    return $this;
  }
  public function setPath(string $path): self {
    $this->path = $path;
    return $this;
  }
  public function setPermissions(array $permissions): self {
    $this->permissions = $permissions;
    return $this;
  }
  public function setRoles(array $roles): self {
    $this->roles = $roles;
    return $this;
  }
  public function setUseMiddleware(array $useMiddleware): self {
    $this->useMiddleware = $useMiddleware;
    return $this;
  }
  public function setUseCache(bool $useCache): self {
    $this->useCache = $useCache;
    return $this;
  }
  public function setEnabled(bool $enabled): self {
    $this->enabled = $enabled;
    return $this;
  }
  public function setCacheTags(array $cacheTags): self {
    $this->cacheTags = $cacheTags;
    return $this;
  }
  public function setFilePath(string $filePath): self {
    $this->filePath = $filePath;
    return $this;
  }
  public function setClassNamespace(string $classNamespace): self {
    $this->classNamespace = $classNamespace;
    return $this;
  }
  public function setClassNamespaceName(string $classNamespaceName): self {
    $this->classNamespaceName = $classNamespaceName;
    return $this;
  }
  public function setClassClassName(string $classClassName): self {
    $this->classClassName = $classClassName;
    return $this;
  }
  public function setClassShortName(string $classShortName): self {
    $this->classShortName = $classShortName;
    return $this;
  }
  public function setClassInterfaces(array $classInterfaces): self {
    $this->classInterfaces = $classInterfaces;
    return $this;
  }
  public function setClassPublicMethods(array $classPublicMethods): self {
    $this->classPublicMethods = $classPublicMethods;
    return $this;
  }
  public function setClassParentClass(string $classParentClass): self {
    $this->classParentClass = $classParentClass;
    return $this;
  }

  protected function _getClassNamespace(): ?string {
    $content = file_get_contents($this->filePath);
    if (!$content) {
      Logger::l(
        level: LoggerIntent::CRITICAL, message: 'Could not read file at @filePath.', context: ['@filePath' => $this->filePath]
      ); return null;
    }

    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
      return trim($matches[1]);
    }

    return null;
  }
  protected function _getClassNamespaceName(): ?string {
    $reflection = $this->_getClassReflection();
    return $reflection?->getNamespaceName();
  }
  protected function _getClassReflection(): ?ReflectionClass {
    $classNamespace = $this->_getClassNamespace();
    if (!$classNamespace) return null;

    $fileName = pathinfo($this->filePath, PATHINFO_FILENAME);
    $classClassName = $classNamespace . '\\' . $fileName;

    $this->includeFile($this->filePath);

    if (!class_exists($classClassName, false)) {
      Logger::l(
        level: LoggerIntent::CRITICAL, message: 'Class @classClassName does not exist.', context: ['@classClassName' => $classClassName]
      ); return null;
    }

    try {
      return new ReflectionClass($classClassName);
    } catch (ReflectionException $e) {
      Logger::l(
        level: LoggerIntent::CRITICAL, message: 'Could not create ReflectionClass for @classClassName. @error', context: ['@classClassName' => $classClassName, '@error' => $e->getMessage()]
      ); return null;
    }
  }
  protected function _getParentClassName(): ?string {
    $reflection = $this->_getClassReflection();
    if (!$reflection) return null;

    $parent = $reflection->getParentClass();
    return $parent ? $parent->getName() : null;
  }
  protected function _getClassClassName(): ?string {
    $reflection = $this->_getClassReflection();
    return $reflection?->getName();
  }
  protected function _getClassShortName(): ?string {
    $reflection = $this->_getClassReflection();
    return $reflection?->getShortName();
  }
  protected function _getClassInterfaces(): ?array {
    $reflection = $this->_getClassReflection();
    return $reflection?->getInterfaceNames();
  }
  protected function _getClassPublicMethods(): ?array {
    $reflection = $this->_getClassReflection();
    if (!$reflection) return null;

    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    return array_map(fn($method) => $method->getName(), $methods);
  }
}
