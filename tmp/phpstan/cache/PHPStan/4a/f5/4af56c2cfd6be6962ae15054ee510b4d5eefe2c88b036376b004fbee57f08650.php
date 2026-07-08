<?php declare(strict_types = 1);

// odsl-C:/Users/jorge/Desktop/Projects/laravel-api-kit/vendor/composer/../laravel/framework/src/Illuminate/Support/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-throw_if
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.3-60a6387a3241a9d7780bf04bf2d928ce75e6a37c78cca9d9e3a8ee7599e3f613',
   'data' => 
  array (
    'name' => 'throw_if',
    'parameters' => 
    array (
      'condition' => 
      array (
        'name' => 'condition',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 415,
        'endLine' => 415,
        'startColumn' => 23,
        'endColumn' => 32,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'exception' => 
      array (
        'name' => 'exception',
        'default' => 
        array (
          'code' => '\'RuntimeException\'',
          'attributes' => 
          array (
            'startLine' => 415,
            'endLine' => 415,
            'startTokenPos' => 1785,
            'startFilePos' => 10631,
            'endTokenPos' => 1785,
            'endFilePos' => 10648,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 415,
        'endLine' => 415,
        'startColumn' => 35,
        'endColumn' => 65,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'parameters' => 
      array (
        'name' => 'parameters',
        'default' => NULL,
        'type' => NULL,
        'isVariadic' => true,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 415,
        'endLine' => 415,
        'startColumn' => 68,
        'endColumn' => 81,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => NULL,
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Throw the given exception if the given condition is true.
 *
 * @template TValue
 * @template TParams of mixed
 * @template TException of \\Throwable
 * @template TExceptionValue of TException|class-string<TException>|string
 *
 * @param  TValue  $condition
 * @param  Closure(TParams): TExceptionValue|TExceptionValue  $exception
 * @param  TParams  ...$parameters
 * @return ($condition is true ? never : ($condition is non-empty-mixed ? never : TValue))
 *
 * @throws TException
 */',
    'startLine' => 415,
    'endLine' => 430,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => true,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'throw_if',
        'filename' => 'C:/Users/jorge/Desktop/Projects/laravel-api-kit/vendor/composer/../laravel/framework/src/Illuminate/Support/helpers.php',
      ),
    ),
  ),
));