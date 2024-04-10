<?php

namespace trendyminds\orbit;

use Craft;
use craft\base\Plugin;

class Orbit extends Plugin
{
    public function init()
    {
        // Set the controllerNamespace based on whether this is a console or web request
        $this->controllerNamespace = Craft::$app->request->isConsoleRequest
            ? 'trendyminds\\orbit\\console\\controllers'
            : 'trendyminds\\orbit\\controllers';

        parent::init();
    }
}
