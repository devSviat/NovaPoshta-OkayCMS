<?php

use Okay\Core\Router;
use Okay\Core\TemplateConfig\Css;

$css = [];
if (Router::getCurrentRouteName() === 'cart') {
    $css[] = new Css('novaposhta_cart.css')->setPosition('head')->setIndividual(true);
}

return $css;
