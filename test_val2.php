<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$validator = validator(
    ['new_values' => ['mobile_phone' => '123']],
    [
        'new_values' => ['required', 'array'],
        'new_values.*' => ['sometimes'],
        'new_values.curp' => ['sometimes', 'nullable', 'string'],
        'new_values.rfc' => ['sometimes', 'nullable', 'string'],
    ]
);

var_dump($validator->passes());
var_dump($validator->validated());

