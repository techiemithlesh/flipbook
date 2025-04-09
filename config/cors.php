<?php

return [

    'paths' => ['api/*', 's3/*', 'upload/*', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag', 'x-amz-meta-custom-header'],

    'max_age' => 0,

    'supports_credentials' => false,

];
