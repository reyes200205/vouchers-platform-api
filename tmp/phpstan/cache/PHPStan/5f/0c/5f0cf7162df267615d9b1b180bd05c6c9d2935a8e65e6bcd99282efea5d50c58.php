<?php declare(strict_types = 1);

// osfsl-C:/Users/jorge/Desktop/Projects/laravel-api-kit/vendor/composer/../laravel/framework/src/Illuminate/Database/Connection.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Connection
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-33be0e00a5f2c1886ae7ab65c355f14eb668bece62abd86f4e0c459c0ba456c2-8.3-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Connection',
        'filename' => 'C:/Users/jorge/Desktop/Projects/laravel-api-kit/vendor/composer/../laravel/framework/src/Illuminate/Database/Connection.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database',
    'name' => 'Illuminate\\Database\\Connection',
    'shortName' => 'Connection',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 1804,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Database\\ConnectionInterface',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\DetectsConcurrencyErrors',
      1 => 'Illuminate\\Database\\DetectsLostConnections',
      2 => 'Illuminate\\Database\\Concerns\\ManagesTransactions',
      3 => 'Illuminate\\Support\\InteractsWithTime',
      4 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'pdo' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'pdo',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The active PDO connection.
 *
 * @var \\PDO|(\\Closure(): \\PDO)
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'readPdo' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'readPdo',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The active PDO connection used for reads.
 *
 * @var \\PDO|(\\Closure(): \\PDO)
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'readPdoConfig' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'readPdoConfig',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 185,
            'startFilePos' => 1533,
            'endTokenPos' => 186,
            'endFilePos' => 1534,
          ),
        ),
        'docComment' => '/**
 * The database connection configuration options for reading.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'database' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'database',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name of the connected database.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'readWriteType' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'readWriteType',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The type of the connection.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tablePrefix' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'tablePrefix',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 78,
            'endLine' => 78,
            'startTokenPos' => 211,
            'startFilePos' => 1877,
            'endTokenPos' => 211,
            'endFilePos' => 1878,
          ),
        ),
        'docComment' => '/**
 * The table prefix for the connection.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'config' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'config',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 85,
            'endLine' => 85,
            'startTokenPos' => 222,
            'startFilePos' => 2001,
            'endTokenPos' => 223,
            'endFilePos' => 2002,
          ),
        ),
        'docComment' => '/**
 * The database connection configuration options.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'reconnector' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'reconnector',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The reconnector instance for the connection.
 *
 * @var (callable(\\Illuminate\\Database\\Connection): mixed)
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queryGrammar' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'queryGrammar',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The query grammar implementation.
 *
 * @var \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 99,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'schemaGrammar' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'schemaGrammar',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The schema grammar implementation.
 *
 * @var \\Illuminate\\Database\\Schema\\Grammars\\Grammar
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 106,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'postProcessor' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'postProcessor',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The query post processor implementation.
 *
 * @var \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 113,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'events' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'events',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher instance.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 120,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fetchMode' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'fetchMode',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\\PDO::FETCH_OBJ',
          'attributes' => 
          array (
            'startLine' => 127,
            'endLine' => 127,
            'startTokenPos' => 269,
            'startFilePos' => 2895,
            'endTokenPos' => 271,
            'endFilePos' => 2908,
          ),
        ),
        'docComment' => '/**
 * The default fetch mode of the connection.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 127,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'transactions' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'transactions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 134,
            'endLine' => 134,
            'startTokenPos' => 282,
            'startFilePos' => 3023,
            'endTokenPos' => 282,
            'endFilePos' => 3023,
          ),
        ),
        'docComment' => '/**
 * The number of active transactions.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 134,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'transactionsManager' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'transactionsManager',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The transaction manager instance.
 *
 * @var \\Illuminate\\Database\\DatabaseTransactionsManager|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 141,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'recordsModified' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'recordsModified',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 148,
            'endLine' => 148,
            'startTokenPos' => 300,
            'startFilePos' => 3327,
            'endTokenPos' => 300,
            'endFilePos' => 3331,
          ),
        ),
        'docComment' => '/**
 * Indicates if changes have been made to the database.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 148,
        'endLine' => 148,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'readOnWriteConnection' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'readOnWriteConnection',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 155,
            'endLine' => 155,
            'startTokenPos' => 311,
            'startFilePos' => 3488,
            'endTokenPos' => 311,
            'endFilePos' => 3492,
          ),
        ),
        'docComment' => '/**
 * Indicates if the connection should use the "write" PDO connection.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 155,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queryLog' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'queryLog',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 162,
            'endLine' => 162,
            'startTokenPos' => 322,
            'startFilePos' => 3669,
            'endTokenPos' => 323,
            'endFilePos' => 3670,
          ),
        ),
        'docComment' => '/**
 * All of the queries run against the connection.
 *
 * @var array{query: string, bindings: array, time: float|null}[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 162,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'loggingQueries' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'loggingQueries',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 169,
            'endLine' => 169,
            'startTokenPos' => 334,
            'startFilePos' => 3797,
            'endTokenPos' => 334,
            'endFilePos' => 3801,
          ),
        ),
        'docComment' => '/**
 * Indicates whether queries are being logged.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 169,
        'endLine' => 169,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'totalQueryDuration' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'totalQueryDuration',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0.0',
          'attributes' => 
          array (
            'startLine' => 176,
            'endLine' => 176,
            'startTokenPos' => 345,
            'startFilePos' => 3943,
            'endTokenPos' => 345,
            'endFilePos' => 3945,
          ),
        ),
        'docComment' => '/**
 * The duration of all executed queries in milliseconds.
 *
 * @var float
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 176,
        'endLine' => 176,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queryDurationHandlers' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'queryDurationHandlers',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 183,
            'endLine' => 183,
            'startTokenPos' => 356,
            'startFilePos' => 4218,
            'endTokenPos' => 357,
            'endFilePos' => 4219,
          ),
        ),
        'docComment' => '/**
 * All of the registered query duration handlers.
 *
 * @var array{has_run: bool, handler: (callable(\\Illuminate\\Database\\Connection, class-string<\\Illuminate\\Database\\Events\\QueryExecuted>): mixed)}[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 183,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pretending' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'pretending',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 190,
            'endLine' => 190,
            'startTokenPos' => 368,
            'startFilePos' => 4345,
            'endTokenPos' => 368,
            'endFilePos' => 4349,
          ),
        ),
        'docComment' => '/**
 * Indicates if the connection is in a "dry run".
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 190,
        'endLine' => 190,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'beforeStartingTransaction' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'beforeStartingTransaction',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 197,
            'endLine' => 197,
            'startTokenPos' => 379,
            'startFilePos' => 4526,
            'endTokenPos' => 380,
            'endFilePos' => 4527,
          ),
        ),
        'docComment' => '/**
 * All of the callbacks that should be invoked before a transaction is started.
 *
 * @var \\Closure[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 197,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'beforeExecutingCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'beforeExecutingCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 204,
            'endLine' => 204,
            'startTokenPos' => 391,
            'startFilePos' => 4755,
            'endTokenPos' => 392,
            'endFilePos' => 4756,
          ),
        ),
        'docComment' => '/**
 * All of the callbacks that should be invoked before a query is executed.
 *
 * @var (\\Closure(string, array, \\Illuminate\\Database\\Connection): mixed)[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 204,
        'endLine' => 204,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'resolvers' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'resolvers',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 211,
            'endLine' => 211,
            'startTokenPos' => 405,
            'startFilePos' => 4873,
            'endTokenPos' => 406,
            'endFilePos' => 4874,
          ),
        ),
        'docComment' => '/**
 * The connection resolvers.
 *
 * @var \\Closure[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 211,
        'endLine' => 211,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'latestPdoTypeRetrieved' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'name' => 'latestPdoTypeRetrieved',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 218,
            'endLine' => 218,
            'startTokenPos' => 417,
            'startFilePos' => 5022,
            'endTokenPos' => 417,
            'endFilePos' => 5025,
          ),
        ),
        'docComment' => '/**
 * The last retrieved PDO read / write type.
 *
 * @var null|\'read\'|\'write\'
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 218,
        'endLine' => 218,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'pdo' => 
          array (
            'name' => 'pdo',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'database' => 
          array (
            'name' => 'database',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 228,
                'endLine' => 228,
                'startTokenPos' => 435,
                'startFilePos' => 5296,
                'endTokenPos' => 435,
                'endFilePos' => 5297,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'tablePrefix' => 
          array (
            'name' => 'tablePrefix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 228,
                'endLine' => 228,
                'startTokenPos' => 442,
                'startFilePos' => 5315,
                'endTokenPos' => 442,
                'endFilePos' => 5316,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 55,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'config' => 
          array (
            'name' => 'config',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 228,
                'endLine' => 228,
                'startTokenPos' => 451,
                'startFilePos' => 5335,
                'endTokenPos' => 452,
                'endFilePos' => 5336,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 74,
            'endColumn' => 91,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new database connection instance.
 *
 * @param  \\PDO|(\\Closure(): \\PDO)  $pdo
 * @param  string  $database
 * @param  string  $tablePrefix
 * @param  array  $config
 */',
        'startLine' => 228,
        'endLine' => 247,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'useDefaultQueryGrammar' => 
      array (
        'name' => 'useDefaultQueryGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the query grammar to the default implementation.
 *
 * @return void
 */',
        'startLine' => 254,
        'endLine' => 257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getDefaultQueryGrammar' => 
      array (
        'name' => 'getDefaultQueryGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default query grammar instance.
 *
 * @return \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'startLine' => 264,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'useDefaultSchemaGrammar' => 
      array (
        'name' => 'useDefaultSchemaGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the schema grammar to the default implementation.
 *
 * @return void
 */',
        'startLine' => 274,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getDefaultSchemaGrammar' => 
      array (
        'name' => 'getDefaultSchemaGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default schema grammar instance.
 *
 * @return \\Illuminate\\Database\\Schema\\Grammars\\Grammar|null
 */',
        'startLine' => 284,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'useDefaultPostProcessor' => 
      array (
        'name' => 'useDefaultPostProcessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the query post processor to the default implementation.
 *
 * @return void
 */',
        'startLine' => 294,
        'endLine' => 297,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getDefaultPostProcessor' => 
      array (
        'name' => 'getDefaultPostProcessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default post processor instance.
 *
 * @return \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'startLine' => 304,
        'endLine' => 307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getSchemaBuilder' => 
      array (
        'name' => 'getSchemaBuilder',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a schema builder instance for the connection.
 *
 * @return \\Illuminate\\Database\\Schema\\Builder
 */',
        'startLine' => 314,
        'endLine' => 321,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'table' => 
      array (
        'name' => 'table',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 330,
            'endLine' => 330,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 330,
                'endLine' => 330,
                'startTokenPos' => 724,
                'startFilePos' => 8046,
                'endTokenPos' => 724,
                'endFilePos' => 8049,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 330,
            'endLine' => 330,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin a fluent query against a database table.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|\\UnitEnum|string  $table
 * @param  string|null  $as
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 330,
        'endLine' => 333,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'query' => 
      array (
        'name' => 'query',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new query builder instance.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 340,
        'endLine' => 345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'selectOne' => 
      array (
        'name' => 'selectOne',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 355,
                'endLine' => 355,
                'startTokenPos' => 806,
                'startFilePos' => 8644,
                'endTokenPos' => 807,
                'endFilePos' => 8645,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'useReadPdo' => 
          array (
            'name' => 'useReadPdo',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 355,
                'endLine' => 355,
                'startTokenPos' => 814,
                'startFilePos' => 8662,
                'endTokenPos' => 814,
                'endFilePos' => 8665,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 55,
            'endColumn' => 72,
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
 * Run a select statement and return a single result.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  bool  $useReadPdo
 * @return mixed
 */',
        'startLine' => 355,
        'endLine' => 360,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'scalar' => 
      array (
        'name' => 'scalar',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 372,
            'endLine' => 372,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 372,
                'endLine' => 372,
                'startTokenPos' => 862,
                'startFilePos' => 9124,
                'endTokenPos' => 863,
                'endFilePos' => 9125,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 372,
            'endLine' => 372,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'useReadPdo' => 
          array (
            'name' => 'useReadPdo',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 372,
                'endLine' => 372,
                'startTokenPos' => 870,
                'startFilePos' => 9142,
                'endTokenPos' => 870,
                'endFilePos' => 9145,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 372,
            'endLine' => 372,
            'startColumn' => 52,
            'endColumn' => 69,
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
 * Run a select statement and return the first column of the first row.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  bool  $useReadPdo
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\MultipleColumnsSelectedException
 */',
        'startLine' => 372,
        'endLine' => 387,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'selectFromWriteConnection' => 
      array (
        'name' => 'selectFromWriteConnection',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 396,
            'endLine' => 396,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 396,
                'endLine' => 396,
                'startTokenPos' => 969,
                'startFilePos' => 9696,
                'endTokenPos' => 970,
                'endFilePos' => 9697,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 396,
            'endLine' => 396,
            'startColumn' => 55,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a select statement against the database.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @return array
 */',
        'startLine' => 396,
        'endLine' => 399,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'select' => 
      array (
        'name' => 'select',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 410,
            'endLine' => 410,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 410,
                'endLine' => 410,
                'startTokenPos' => 1008,
                'startFilePos' => 10041,
                'endTokenPos' => 1009,
                'endFilePos' => 10042,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 410,
            'endLine' => 410,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'useReadPdo' => 
          array (
            'name' => 'useReadPdo',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 410,
                'endLine' => 410,
                'startTokenPos' => 1016,
                'startFilePos' => 10059,
                'endTokenPos' => 1016,
                'endFilePos' => 10062,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 410,
            'endLine' => 410,
            'startColumn' => 52,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'fetchUsing' => 
          array (
            'name' => 'fetchUsing',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 410,
                'endLine' => 410,
                'startTokenPos' => 1025,
                'startFilePos' => 10085,
                'endTokenPos' => 1026,
                'endFilePos' => 10086,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 410,
            'endLine' => 410,
            'startColumn' => 72,
            'endColumn' => 93,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a select statement against the database.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  bool  $useReadPdo
 * @param  array  $fetchUsing
 * @return array
 */',
        'startLine' => 410,
        'endLine' => 430,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'selectResultSets' => 
      array (
        'name' => 'selectResultSets',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 441,
            'endLine' => 441,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 441,
                'endLine' => 441,
                'startTokenPos' => 1168,
                'startFilePos' => 11177,
                'endTokenPos' => 1169,
                'endFilePos' => 11178,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 441,
            'endLine' => 441,
            'startColumn' => 46,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'useReadPdo' => 
          array (
            'name' => 'useReadPdo',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 441,
                'endLine' => 441,
                'startTokenPos' => 1176,
                'startFilePos' => 11195,
                'endTokenPos' => 1176,
                'endFilePos' => 11198,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 441,
            'endLine' => 441,
            'startColumn' => 62,
            'endColumn' => 79,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'fetchUsing' => 
          array (
            'name' => 'fetchUsing',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 441,
                'endLine' => 441,
                'startTokenPos' => 1185,
                'startFilePos' => 11221,
                'endTokenPos' => 1186,
                'endFilePos' => 11222,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 441,
            'endLine' => 441,
            'startColumn' => 82,
            'endColumn' => 103,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a select statement against the database and returns all of the result sets.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  bool  $useReadPdo
 * @param  array  $fetchUsing
 * @return array
 */',
        'startLine' => 441,
        'endLine' => 464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'cursor' => 
      array (
        'name' => 'cursor',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 475,
            'endLine' => 475,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 475,
                'endLine' => 475,
                'startTokenPos' => 1356,
                'startFilePos' => 12178,
                'endTokenPos' => 1357,
                'endFilePos' => 12179,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 475,
            'endLine' => 475,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'useReadPdo' => 
          array (
            'name' => 'useReadPdo',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 475,
                'endLine' => 475,
                'startTokenPos' => 1364,
                'startFilePos' => 12196,
                'endTokenPos' => 1364,
                'endFilePos' => 12199,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 475,
            'endLine' => 475,
            'startColumn' => 52,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'fetchUsing' => 
          array (
            'name' => 'fetchUsing',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 475,
                'endLine' => 475,
                'startTokenPos' => 1373,
                'startFilePos' => 12222,
                'endTokenPos' => 1374,
                'endFilePos' => 12223,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 475,
            'endLine' => 475,
            'startColumn' => 72,
            'endColumn' => 93,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a select statement against the database and returns a generator.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  bool  $useReadPdo
 * @param  array  $fetchUsing
 * @return \\Generator<int, \\stdClass>
 */',
        'startLine' => 475,
        'endLine' => 503,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => true,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'prepared' => 
      array (
        'name' => 'prepared',
        'parameters' => 
        array (
          'statement' => 
          array (
            'name' => 'statement',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'PDOStatement',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 511,
            'endLine' => 511,
            'startColumn' => 33,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Configure the PDO prepared statement.
 *
 * @param  \\PDOStatement  $statement
 * @return \\PDOStatement
 */',
        'startLine' => 511,
        'endLine' => 518,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getPdoForSelect' => 
      array (
        'name' => 'getPdoForSelect',
        'parameters' => 
        array (
          'useReadPdo' => 
          array (
            'name' => 'useReadPdo',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 526,
                'endLine' => 526,
                'startTokenPos' => 1586,
                'startFilePos' => 13891,
                'endTokenPos' => 1586,
                'endFilePos' => 13894,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 526,
            'endLine' => 526,
            'startColumn' => 40,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the PDO connection to use for a select query.
 *
 * @param  bool  $useReadPdo
 * @return \\PDO
 */',
        'startLine' => 526,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'insert' => 
      array (
        'name' => 'insert',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 538,
            'endLine' => 538,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 538,
                'endLine' => 538,
                'startTokenPos' => 1629,
                'startFilePos' => 14183,
                'endTokenPos' => 1630,
                'endFilePos' => 14184,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 538,
            'endLine' => 538,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run an insert statement against the database.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @return bool
 */',
        'startLine' => 538,
        'endLine' => 541,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'update' => 
      array (
        'name' => 'update',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 550,
            'endLine' => 550,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 550,
                'endLine' => 550,
                'startTokenPos' => 1665,
                'startFilePos' => 14456,
                'endTokenPos' => 1666,
                'endFilePos' => 14457,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 550,
            'endLine' => 550,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run an update statement against the database.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @return int
 */',
        'startLine' => 550,
        'endLine' => 553,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 562,
            'endLine' => 562,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 562,
                'endLine' => 562,
                'startTokenPos' => 1701,
                'startFilePos' => 14737,
                'endTokenPos' => 1702,
                'endFilePos' => 14738,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 562,
            'endLine' => 562,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a delete statement against the database.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @return int
 */',
        'startLine' => 562,
        'endLine' => 565,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'statement' => 
      array (
        'name' => 'statement',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 574,
            'endLine' => 574,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 574,
                'endLine' => 574,
                'startTokenPos' => 1737,
                'startFilePos' => 15033,
                'endTokenPos' => 1738,
                'endFilePos' => 15034,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 574,
            'endLine' => 574,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute an SQL statement and return the boolean result.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @return bool
 */',
        'startLine' => 574,
        'endLine' => 589,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'affectingStatement' => 
      array (
        'name' => 'affectingStatement',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 598,
            'endLine' => 598,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 598,
                'endLine' => 598,
                'startTokenPos' => 1854,
                'startFilePos' => 15677,
                'endTokenPos' => 1855,
                'endFilePos' => 15678,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 598,
            'endLine' => 598,
            'startColumn' => 48,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run an SQL statement and get the number of rows affected.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @return int
 */',
        'startLine' => 598,
        'endLine' => 620,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'unprepared' => 
      array (
        'name' => 'unprepared',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 628,
            'endLine' => 628,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a raw, unprepared query against the PDO connection.
 *
 * @param  literal-string  $query
 * @return bool
 */',
        'startLine' => 628,
        'endLine' => 641,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'threadCount' => 
      array (
        'name' => 'threadCount',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the number of open connections for the database.
 *
 * @return int|null
 */',
        'startLine' => 648,
        'endLine' => 653,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'pretend' => 
      array (
        'name' => 'pretend',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 661,
            'endLine' => 661,
            'startColumn' => 29,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback in "dry run" mode.
 *
 * @param  (\\Closure(\\Illuminate\\Database\\Connection): mixed)  $callback
 * @return array{query: string, bindings: array, time: float|null}[]
 */',
        'startLine' => 661,
        'endLine' => 677,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'withoutPretending' => 
      array (
        'name' => 'withoutPretending',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 685,
            'endLine' => 685,
            'startColumn' => 39,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback without "pretending".
 *
 * @param  \\Closure  $callback
 * @return mixed
 */',
        'startLine' => 685,
        'endLine' => 698,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'withFreshQueryLog' => 
      array (
        'name' => 'withFreshQueryLog',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 706,
            'endLine' => 706,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback in "dry run" mode.
 *
 * @param  (\\Closure(): array{query: string, bindings: array, time: float|null}[])  $callback
 * @return array{query: string, bindings: array, time: float|null}[]
 */',
        'startLine' => 706,
        'endLine' => 725,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'bindValues' => 
      array (
        'name' => 'bindValues',
        'parameters' => 
        array (
          'statement' => 
          array (
            'name' => 'statement',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 734,
            'endLine' => 734,
            'startColumn' => 32,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 734,
            'endLine' => 734,
            'startColumn' => 44,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Bind values to their parameters in the given statement.
 *
 * @param  \\PDOStatement  $statement
 * @param  array  $bindings
 * @return void
 */',
        'startLine' => 734,
        'endLine' => 747,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'prepareBindings' => 
      array (
        'name' => 'prepareBindings',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 755,
            'endLine' => 755,
            'startColumn' => 37,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepare the query bindings for execution.
 *
 * @param  array  $bindings
 * @return array
 */',
        'startLine' => 755,
        'endLine' => 771,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 783,
            'endLine' => 783,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 783,
            'endLine' => 783,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 783,
            'endLine' => 783,
            'startColumn' => 47,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a SQL statement and log its execution context.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  \\Closure  $callback
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\QueryException
 */',
        'startLine' => 783,
        'endLine' => 812,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'runQueryCallback' => 
      array (
        'name' => 'runQueryCallback',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 49,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 60,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a SQL statement.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  \\Closure  $callback
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\QueryException
 */',
        'startLine' => 824,
        'endLine' => 858,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'isUniqueConstraintError' => 
      array (
        'name' => 'isUniqueConstraintError',
        'parameters' => 
        array (
          'exception' => 
          array (
            'name' => 'exception',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Exception',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 866,
            'endLine' => 866,
            'startColumn' => 48,
            'endColumn' => 67,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given database exception was caused by a unique constraint violation.
 *
 * @param  \\Exception  $exception
 * @return bool
 */',
        'startLine' => 866,
        'endLine' => 869,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'parseUniqueConstraintViolation' => 
      array (
        'name' => 'parseUniqueConstraintViolation',
        'parameters' => 
        array (
          'exception' => 
          array (
            'name' => 'exception',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Exception',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 877,
            'endLine' => 877,
            'startColumn' => 55,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Extract the index and columns that caused a unique constraint violation.
 *
 * @param  Exception  $exception
 * @return array{index: string|null, columns: list<string>}
 */',
        'startLine' => 877,
        'endLine' => 880,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'logQuery' => 
      array (
        'name' => 'logQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 890,
            'endLine' => 890,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 890,
            'endLine' => 890,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 890,
                'endLine' => 890,
                'startTokenPos' => 3021,
                'startFilePos' => 25021,
                'endTokenPos' => 3021,
                'endFilePos' => 25024,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 890,
            'endLine' => 890,
            'startColumn' => 49,
            'endColumn' => 60,
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
 * Log a query in the connection\'s query log.
 *
 * @param  string  $query
 * @param  array  $bindings
 * @param  float|null  $time
 * @return void
 */',
        'startLine' => 890,
        'endLine' => 905,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getElapsedTime' => 
      array (
        'name' => 'getElapsedTime',
        'parameters' => 
        array (
          'start' => 
          array (
            'name' => 'start',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 913,
            'endLine' => 913,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the elapsed time in milliseconds since a given starting point.
 *
 * @param  float  $start
 * @return float
 */',
        'startLine' => 913,
        'endLine' => 916,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'whenQueryingForLongerThan' => 
      array (
        'name' => 'whenQueryingForLongerThan',
        'parameters' => 
        array (
          'threshold' => 
          array (
            'name' => 'threshold',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 925,
            'endLine' => 925,
            'startColumn' => 47,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'handler' => 
          array (
            'name' => 'handler',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 925,
            'endLine' => 925,
            'startColumn' => 59,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a callback to be invoked when the connection queries for longer than a given amount of time.
 *
 * @param  \\DateTimeInterface|\\Carbon\\CarbonInterval|float|int  $threshold
 * @param  (callable(\\Illuminate\\Database\\Connection, \\Illuminate\\Database\\Events\\QueryExecuted): mixed)  $handler
 * @return void
 */',
        'startLine' => 925,
        'endLine' => 949,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'allowQueryDurationHandlersToRunAgain' => 
      array (
        'name' => 'allowQueryDurationHandlersToRunAgain',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Allow all the query duration handlers to run again, even if they have already run.
 *
 * @return void
 */',
        'startLine' => 956,
        'endLine' => 961,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'totalQueryDuration' => 
      array (
        'name' => 'totalQueryDuration',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the duration of all run queries in milliseconds.
 *
 * @return float
 */',
        'startLine' => 968,
        'endLine' => 971,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'resetTotalQueryDuration' => 
      array (
        'name' => 'resetTotalQueryDuration',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reset the duration of all run queries.
 *
 * @return void
 */',
        'startLine' => 978,
        'endLine' => 981,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'handleQueryException' => 
      array (
        'name' => 'handleQueryException',
        'parameters' => 
        array (
          'e' => 
          array (
            'name' => 'e',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\QueryException',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 994,
            'endLine' => 994,
            'startColumn' => 45,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 994,
            'endLine' => 994,
            'startColumn' => 64,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 994,
            'endLine' => 994,
            'startColumn' => 72,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 994,
            'endLine' => 994,
            'startColumn' => 83,
            'endColumn' => 99,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle a query exception.
 *
 * @param  \\Illuminate\\Database\\QueryException  $e
 * @param  string  $query
 * @param  array  $bindings
 * @param  \\Closure  $callback
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\QueryException
 */',
        'startLine' => 994,
        'endLine' => 1003,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'tryAgainIfCausedByLostConnection' => 
      array (
        'name' => 'tryAgainIfCausedByLostConnection',
        'parameters' => 
        array (
          'e' => 
          array (
            'name' => 'e',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\QueryException',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 57,
            'endColumn' => 73,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 76,
            'endColumn' => 81,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 84,
            'endColumn' => 92,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 95,
            'endColumn' => 111,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle a query exception that occurred during query execution.
 *
 * @param  \\Illuminate\\Database\\QueryException  $e
 * @param  string  $query
 * @param  array  $bindings
 * @param  \\Closure  $callback
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\QueryException
 */',
        'startLine' => 1016,
        'endLine' => 1025,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'reconnect' => 
      array (
        'name' => 'reconnect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reconnect to the database.
 *
 * @return mixed|false
 *
 * @throws \\Illuminate\\Database\\LostConnectionException
 */',
        'startLine' => 1034,
        'endLine' => 1041,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'reconnectIfMissingConnection' => 
      array (
        'name' => 'reconnectIfMissingConnection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reconnect to the database if a PDO connection is missing.
 *
 * @return void
 */',
        'startLine' => 1048,
        'endLine' => 1053,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'disconnect' => 
      array (
        'name' => 'disconnect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disconnect from the underlying PDO connection.
 *
 * @return void
 */',
        'startLine' => 1060,
        'endLine' => 1063,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'beforeStartingTransaction' => 
      array (
        'name' => 'beforeStartingTransaction',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1071,
            'endLine' => 1071,
            'startColumn' => 47,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a hook to be run just before a database transaction is started.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 1071,
        'endLine' => 1076,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'beforeExecuting' => 
      array (
        'name' => 'beforeExecuting',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1084,
            'endLine' => 1084,
            'startColumn' => 37,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a hook to be run just before a database query is executed.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 1084,
        'endLine' => 1089,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'listen' => 
      array (
        'name' => 'listen',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1097,
            'endLine' => 1097,
            'startColumn' => 28,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a database query listener with the connection.
 *
 * @param  \\Closure(\\Illuminate\\Database\\Events\\QueryExecuted)  $callback
 * @return void
 */',
        'startLine' => 1097,
        'endLine' => 1100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'fireConnectionEvent' => 
      array (
        'name' => 'fireConnectionEvent',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1108,
            'endLine' => 1108,
            'startColumn' => 44,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Fire an event for this connection.
 *
 * @param  string  $event
 * @return array|null
 */',
        'startLine' => 1108,
        'endLine' => 1117,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'event' => 
      array (
        'name' => 'event',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1125,
            'endLine' => 1125,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Fire the given event if possible.
 *
 * @param  mixed  $event
 * @return void
 */',
        'startLine' => 1125,
        'endLine' => 1128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'raw' => 
      array (
        'name' => 'raw',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1136,
            'endLine' => 1136,
            'startColumn' => 25,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new raw query expression.
 *
 * @param  literal-string|int|float  $value
 * @return \\Illuminate\\Contracts\\Database\\Query\\Expression
 */',
        'startLine' => 1136,
        'endLine' => 1139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'escape' => 
      array (
        'name' => 'escape',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1150,
            'endLine' => 1150,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'binary' => 
          array (
            'name' => 'binary',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1150,
                'endLine' => 1150,
                'startTokenPos' => 3986,
                'startFilePos' => 32027,
                'endTokenPos' => 3986,
                'endFilePos' => 32031,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1150,
            'endLine' => 1150,
            'startColumn' => 36,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Escape a value for safe SQL embedding.
 *
 * @param  string|float|int|bool|null  $value
 * @param  bool  $binary
 * @return string
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 1150,
        'endLine' => 1173,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'escapeString' => 
      array (
        'name' => 'escapeString',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1181,
            'endLine' => 1181,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Escape a string value for safe SQL embedding.
 *
 * @param  string  $value
 * @return string
 */',
        'startLine' => 1181,
        'endLine' => 1184,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'escapeBool' => 
      array (
        'name' => 'escapeBool',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1192,
            'endLine' => 1192,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Escape a boolean value for safe SQL embedding.
 *
 * @param  bool  $value
 * @return string
 */',
        'startLine' => 1192,
        'endLine' => 1195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'escapeBinary' => 
      array (
        'name' => 'escapeBinary',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1205,
            'endLine' => 1205,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Escape a binary value for safe SQL embedding.
 *
 * @param  string  $value
 * @return string
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 1205,
        'endLine' => 1208,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'hasModifiedRecords' => 
      array (
        'name' => 'hasModifiedRecords',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the database connection has modified any database records.
 *
 * @return bool
 */',
        'startLine' => 1215,
        'endLine' => 1218,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'recordsHaveBeenModified' => 
      array (
        'name' => 'recordsHaveBeenModified',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1226,
                'endLine' => 1226,
                'startTokenPos' => 4292,
                'startFilePos' => 34103,
                'endTokenPos' => 4292,
                'endFilePos' => 34106,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1226,
            'endLine' => 1226,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate if any records have been modified.
 *
 * @param  bool  $value
 * @return void
 */',
        'startLine' => 1226,
        'endLine' => 1231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setRecordModificationState' => 
      array (
        'name' => 'setRecordModificationState',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1239,
            'endLine' => 1239,
            'startColumn' => 48,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the record modification state.
 *
 * @param  bool  $value
 * @return $this
 */',
        'startLine' => 1239,
        'endLine' => 1244,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'forgetRecordModificationState' => 
      array (
        'name' => 'forgetRecordModificationState',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reset the record modification state.
 *
 * @return void
 */',
        'startLine' => 1251,
        'endLine' => 1254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'useWriteConnectionWhenReading' => 
      array (
        'name' => 'useWriteConnectionWhenReading',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1262,
                'endLine' => 1262,
                'startTokenPos' => 4388,
                'startFilePos' => 34874,
                'endTokenPos' => 4388,
                'endFilePos' => 34877,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1262,
            'endLine' => 1262,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that the connection should use the write PDO connection for reads.
 *
 * @param  bool  $value
 * @return $this
 */',
        'startLine' => 1262,
        'endLine' => 1267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getPdo' => 
      array (
        'name' => 'getPdo',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current PDO connection.
 *
 * @return \\PDO
 */',
        'startLine' => 1274,
        'endLine' => 1283,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getRawPdo' => 
      array (
        'name' => 'getRawPdo',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current PDO connection parameter without executing any reconnect logic.
 *
 * @return \\PDO|\\Closure|null
 */',
        'startLine' => 1290,
        'endLine' => 1293,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getReadPdo' => 
      array (
        'name' => 'getReadPdo',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current PDO connection used for reading.
 *
 * @return \\PDO
 */',
        'startLine' => 1300,
        'endLine' => 1318,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getRawReadPdo' => 
      array (
        'name' => 'getRawReadPdo',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current read PDO connection parameter without executing any reconnect logic.
 *
 * @return \\PDO|\\Closure|null
 */',
        'startLine' => 1325,
        'endLine' => 1328,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setPdo' => 
      array (
        'name' => 'setPdo',
        'parameters' => 
        array (
          'pdo' => 
          array (
            'name' => 'pdo',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1336,
            'endLine' => 1336,
            'startColumn' => 28,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the PDO connection.
 *
 * @param  \\PDO|\\Closure|null  $pdo
 * @return $this
 */',
        'startLine' => 1336,
        'endLine' => 1343,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setReadPdo' => 
      array (
        'name' => 'setReadPdo',
        'parameters' => 
        array (
          'pdo' => 
          array (
            'name' => 'pdo',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1351,
            'endLine' => 1351,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the PDO connection used for reading.
 *
 * @param  \\PDO|\\Closure|null  $pdo
 * @return $this
 */',
        'startLine' => 1351,
        'endLine' => 1356,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setReadPdoConfig' => 
      array (
        'name' => 'setReadPdoConfig',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1364,
            'endLine' => 1364,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the read PDO connection configuration.
 *
 * @param  array  $config
 * @return $this
 */',
        'startLine' => 1364,
        'endLine' => 1369,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setReconnector' => 
      array (
        'name' => 'setReconnector',
        'parameters' => 
        array (
          'reconnector' => 
          array (
            'name' => 'reconnector',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1377,
            'endLine' => 1377,
            'startColumn' => 36,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the reconnect instance on the connection.
 *
 * @param  (callable(\\Illuminate\\Database\\Connection): mixed)  $reconnector
 * @return $this
 */',
        'startLine' => 1377,
        'endLine' => 1382,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getName' => 
      array (
        'name' => 'getName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database connection name.
 *
 * @return string|null
 */',
        'startLine' => 1389,
        'endLine' => 1392,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getNameWithReadWriteType' => 
      array (
        'name' => 'getNameWithReadWriteType',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database connection with its read / write type.
 *
 * @return string|null
 */',
        'startLine' => 1399,
        'endLine' => 1404,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getConfig' => 
      array (
        'name' => 'getConfig',
        'parameters' => 
        array (
          'option' => 
          array (
            'name' => 'option',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1412,
                'endLine' => 1412,
                'startTokenPos' => 4871,
                'startFilePos' => 38047,
                'endTokenPos' => 4871,
                'endFilePos' => 38050,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1412,
            'endLine' => 1412,
            'startColumn' => 31,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get an option from the configuration options.
 *
 * @param  string|null  $option
 * @return mixed
 */',
        'startLine' => 1412,
        'endLine' => 1415,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getConnectionDetails' => 
      array (
        'name' => 'getConnectionDetails',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the basic connection information as an array for debugging.
 *
 * @return array
 */',
        'startLine' => 1422,
        'endLine' => 1436,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getDriverName' => 
      array (
        'name' => 'getDriverName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the PDO driver name.
 *
 * @return string
 */',
        'startLine' => 1443,
        'endLine' => 1446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getDriverTitle' => 
      array (
        'name' => 'getDriverTitle',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a human-readable name for the given connection driver.
 *
 * @return string
 */',
        'startLine' => 1453,
        'endLine' => 1456,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getQueryGrammar' => 
      array (
        'name' => 'getQueryGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the query grammar used by the connection.
 *
 * @return \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'startLine' => 1463,
        'endLine' => 1466,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setQueryGrammar' => 
      array (
        'name' => 'setQueryGrammar',
        'parameters' => 
        array (
          'grammar' => 
          array (
            'name' => 'grammar',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Query\\Grammars\\Grammar',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1474,
            'endLine' => 1474,
            'startColumn' => 37,
            'endColumn' => 67,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the query grammar used by the connection.
 *
 * @param  \\Illuminate\\Database\\Query\\Grammars\\Grammar  $grammar
 * @return $this
 */',
        'startLine' => 1474,
        'endLine' => 1479,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getSchemaGrammar' => 
      array (
        'name' => 'getSchemaGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the schema grammar used by the connection.
 *
 * @return \\Illuminate\\Database\\Schema\\Grammars\\Grammar
 */',
        'startLine' => 1486,
        'endLine' => 1489,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setSchemaGrammar' => 
      array (
        'name' => 'setSchemaGrammar',
        'parameters' => 
        array (
          'grammar' => 
          array (
            'name' => 'grammar',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Grammars\\Grammar',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1497,
            'endLine' => 1497,
            'startColumn' => 38,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the schema grammar used by the connection.
 *
 * @param  \\Illuminate\\Database\\Schema\\Grammars\\Grammar  $grammar
 * @return $this
 */',
        'startLine' => 1497,
        'endLine' => 1502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getPostProcessor' => 
      array (
        'name' => 'getPostProcessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the query post processor used by the connection.
 *
 * @return \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'startLine' => 1509,
        'endLine' => 1512,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setPostProcessor' => 
      array (
        'name' => 'setPostProcessor',
        'parameters' => 
        array (
          'processor' => 
          array (
            'name' => 'processor',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Query\\Processors\\Processor',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1520,
            'endLine' => 1520,
            'startColumn' => 38,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the query post processor used by the connection.
 *
 * @param  \\Illuminate\\Database\\Query\\Processors\\Processor  $processor
 * @return $this
 */',
        'startLine' => 1520,
        'endLine' => 1525,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getEventDispatcher' => 
      array (
        'name' => 'getEventDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the event dispatcher used by the connection.
 *
 * @return \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'startLine' => 1532,
        'endLine' => 1535,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setEventDispatcher' => 
      array (
        'name' => 'setEventDispatcher',
        'parameters' => 
        array (
          'events' => 
          array (
            'name' => 'events',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Events\\Dispatcher',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 40,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the event dispatcher instance on the connection.
 *
 * @param  \\Illuminate\\Contracts\\Events\\Dispatcher  $events
 * @return $this
 */',
        'startLine' => 1543,
        'endLine' => 1548,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'unsetEventDispatcher' => 
      array (
        'name' => 'unsetEventDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Unset the event dispatcher for this connection.
 *
 * @return void
 */',
        'startLine' => 1555,
        'endLine' => 1558,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'executeBeginTransactionStatement' => 
      array (
        'name' => 'executeBeginTransactionStatement',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the statement to start a new transaction.
 *
 * @return void
 */',
        'startLine' => 1565,
        'endLine' => 1568,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setTransactionManager' => 
      array (
        'name' => 'setTransactionManager',
        'parameters' => 
        array (
          'manager' => 
          array (
            'name' => 'manager',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1576,
            'endLine' => 1576,
            'startColumn' => 43,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the transaction manager instance on the connection.
 *
 * @param  \\Illuminate\\Database\\DatabaseTransactionsManager  $manager
 * @return $this
 */',
        'startLine' => 1576,
        'endLine' => 1581,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'unsetTransactionManager' => 
      array (
        'name' => 'unsetTransactionManager',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Unset the transaction manager for this connection.
 *
 * @return void
 */',
        'startLine' => 1588,
        'endLine' => 1591,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'pretending' => 
      array (
        'name' => 'pretending',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the connection is in a "dry run".
 *
 * @return bool
 */',
        'startLine' => 1598,
        'endLine' => 1601,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getQueryLog' => 
      array (
        'name' => 'getQueryLog',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the connection query log.
 *
 * @return array{query: string, bindings: array, time: float|null}[]
 */',
        'startLine' => 1608,
        'endLine' => 1611,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getRawQueryLog' => 
      array (
        'name' => 'getRawQueryLog',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the connection query log with embedded bindings.
 *
 * @return array
 */',
        'startLine' => 1618,
        'endLine' => 1627,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'flushQueryLog' => 
      array (
        'name' => 'flushQueryLog',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clear the query log.
 *
 * @return void
 */',
        'startLine' => 1634,
        'endLine' => 1637,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'enableQueryLog' => 
      array (
        'name' => 'enableQueryLog',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable the query log on the connection.
 *
 * @return void
 */',
        'startLine' => 1644,
        'endLine' => 1647,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'disableQueryLog' => 
      array (
        'name' => 'disableQueryLog',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable the query log on the connection.
 *
 * @return void
 */',
        'startLine' => 1654,
        'endLine' => 1657,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'logging' => 
      array (
        'name' => 'logging',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine whether we\'re logging queries.
 *
 * @return bool
 */',
        'startLine' => 1664,
        'endLine' => 1667,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getDatabaseName' => 
      array (
        'name' => 'getDatabaseName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the name of the connected database.
 *
 * @return string
 */',
        'startLine' => 1674,
        'endLine' => 1677,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setDatabaseName' => 
      array (
        'name' => 'setDatabaseName',
        'parameters' => 
        array (
          'database' => 
          array (
            'name' => 'database',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1685,
            'endLine' => 1685,
            'startColumn' => 37,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the name of the connected database.
 *
 * @param  string  $database
 * @return $this
 */',
        'startLine' => 1685,
        'endLine' => 1690,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setReadWriteType' => 
      array (
        'name' => 'setReadWriteType',
        'parameters' => 
        array (
          'readWriteType' => 
          array (
            'name' => 'readWriteType',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1698,
            'endLine' => 1698,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the read / write type of the connection.
 *
 * @param  string|null  $readWriteType
 * @return $this
 */',
        'startLine' => 1698,
        'endLine' => 1703,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'latestReadWriteTypeUsed' => 
      array (
        'name' => 'latestReadWriteTypeUsed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the latest read / write type used.
 *
 * @return \'read\'|\'write\'|null
 */',
        'startLine' => 1710,
        'endLine' => 1713,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getTablePrefix' => 
      array (
        'name' => 'getTablePrefix',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the table prefix for the connection.
 *
 * @return string
 */',
        'startLine' => 1720,
        'endLine' => 1723,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'setTablePrefix' => 
      array (
        'name' => 'setTablePrefix',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1731,
            'endLine' => 1731,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the table prefix in use by the connection.
 *
 * @param  string  $prefix
 * @return $this
 */',
        'startLine' => 1731,
        'endLine' => 1736,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'withoutTablePrefix' => 
      array (
        'name' => 'withoutTablePrefix',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1744,
            'endLine' => 1744,
            'startColumn' => 40,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback without table prefix.
 *
 * @param  \\Closure  $callback
 * @return mixed
 */',
        'startLine' => 1744,
        'endLine' => 1755,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getServerVersion' => 
      array (
        'name' => 'getServerVersion',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the server version for the connection.
 *
 * @return string
 */',
        'startLine' => 1762,
        'endLine' => 1765,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'resolverFor' => 
      array (
        'name' => 'resolverFor',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1774,
            'endLine' => 1774,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1774,
            'endLine' => 1774,
            'startColumn' => 49,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a connection resolver.
 *
 * @param  string  $driver
 * @param  \\Closure  $callback
 * @return void
 */',
        'startLine' => 1774,
        'endLine' => 1777,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      'getResolver' => 
      array (
        'name' => 'getResolver',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1785,
            'endLine' => 1785,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the connection resolver for the given driver.
 *
 * @param  string  $driver
 * @return \\Closure|null
 */',
        'startLine' => 1785,
        'endLine' => 1788,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
        'aliasName' => NULL,
      ),
      '__clone' => 
      array (
        'name' => '__clone',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepare the instance for cloning.
 *
 * @return void
 */',
        'startLine' => 1795,
        'endLine' => 1803,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database',
        'declaringClassName' => 'Illuminate\\Database\\Connection',
        'implementingClassName' => 'Illuminate\\Database\\Connection',
        'currentClassName' => 'Illuminate\\Database\\Connection',
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