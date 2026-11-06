<?php

namespace Okay\Modules\Sviat\NovaPoshta;

return [
    'Sviat_NovaPoshta_cart_settlements' => [
        'slug' => 'ajax/novaposhta/settlements',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CartController',
            'method' => 'settlements',
        ],
    ],
    'Sviat_NovaPoshta_cart_divisions' => [
        'slug' => 'ajax/novaposhta/divisions',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CartController',
            'method' => 'divisions',
        ],
    ],
];
