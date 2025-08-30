<?php

declare(strict_types=1);

namespace Modules\Welcome\Actions;

use BitCore\Application\Actions\Action;
use Psr\Http\Message\ResponseInterface;

class WelcomeAction extends Action
{
    public function action(): ResponseInterface
    {
        return $this->respondWithData([
            'message' => 'Welcome to BitCore'
        ]);
    }
}
