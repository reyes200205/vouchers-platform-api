<?php declare(strict_types = 1);

// osfsl-C:/Users/jorge/Desktop/Projects/laravel-api-kit/vendor/composer/../laravel/framework/src/Illuminate/Database/Schema/ForeignKeyDefinition.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Schema\ForeignKeyDefinition
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-b2a994ca42c671cbfd643e9c0190818f6d0c07d5e4a4d0320d9484c3d6af169c-8.3-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'filename' => 'C:/Users/jorge/Desktop/Projects/laravel-api-kit/vendor/composer/../laravel/framework/src/Illuminate/Database/Schema/ForeignKeyDefinition.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Schema',
    'name' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
    'shortName' => 'ForeignKeyDefinition',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @method ForeignKeyDefinition deferrable(bool $value = true) Set the foreign key as deferrable (PostgreSQL)
 * @method ForeignKeyDefinition initiallyImmediate(bool $value = true) Set the default time to check the constraint (PostgreSQL)
 * @method ForeignKeyDefinition lock((\'none\'|\'shared\'|\'default\'|\'exclusive\') $value) Specify the DDL lock mode for the foreign key operation (MySQL)
 * @method ForeignKeyDefinition on(string $table) Specify the referenced table
 * @method ForeignKeyDefinition onDelete((\'cascade\'|\'restrict\'|\'set null\'|\'no action\') $action) Add an ON DELETE action
 * @method ForeignKeyDefinition onUpdate((\'cascade\'|\'restrict\'|\'set null\'|\'no action\') $action) Add an ON UPDATE action
 * @method ForeignKeyDefinition references(string|string[] $columns) Specify the referenced column(s)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 97,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Support\\Fluent',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'cascadeOnUpdate' => 
      array (
        'name' => 'cascadeOnUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that updates should cascade.
 *
 * @return $this
 */',
        'startLine' => 23,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'restrictOnUpdate' => 
      array (
        'name' => 'restrictOnUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that updates should be restricted.
 *
 * @return $this
 */',
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'nullOnUpdate' => 
      array (
        'name' => 'nullOnUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that updates should set the foreign key value to null.
 *
 * @return $this
 */',
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'noActionOnUpdate' => 
      array (
        'name' => 'noActionOnUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that updates should have "no action".
 *
 * @return $this
 */',
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'cascadeOnDelete' => 
      array (
        'name' => 'cascadeOnDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that deletes should cascade.
 *
 * @return $this
 */',
        'startLine' => 63,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'restrictOnDelete' => 
      array (
        'name' => 'restrictOnDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that deletes should be restricted.
 *
 * @return $this
 */',
        'startLine' => 73,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'nullOnDelete' => 
      array (
        'name' => 'nullOnDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that deletes should set the foreign key value to null.
 *
 * @return $this
 */',
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
      'noActionOnDelete' => 
      array (
        'name' => 'noActionOnDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that deletes should have "no action".
 *
 * @return $this
 */',
        'startLine' => 93,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'currentClassName' => 'Illuminate\\Database\\Schema\\ForeignKeyDefinition',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));