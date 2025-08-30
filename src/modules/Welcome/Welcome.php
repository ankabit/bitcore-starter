<?php

declare(strict_types=1);

namespace Modules\Welcome;

use BitCore\Application\Services\Modules\AbstractModule;

class Welcome extends AbstractModule
{
    protected $id = 'Welcome';
    protected $version = '1.0.0';
    protected $name = 'Welcome Module';
    protected $description = 'A simple welcome module';
    protected $authorName = 'Your Name';
    protected $authorUrl = 'https://yourwebsite.com';
}
