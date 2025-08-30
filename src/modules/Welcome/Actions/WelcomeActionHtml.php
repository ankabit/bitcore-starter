<?php

declare(strict_types=1);

namespace Modules\Welcome\Actions;

use BitCore\Application\Actions\Action;
use Psr\Http\Message\ResponseInterface;

class WelcomeAction extends Action
{
    public function action(): ResponseInterface
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Welcome to BitCore</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
                .container { max-width: 600px; margin: 0 auto; }
                h1 { color: #333; }
                p { color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🎉 Welcome to BitCore!</h1>
                <p>Your modular PHP application is up and running.</p>
                <p>This page is served by the Welcome module.</p>
            </div>
        </body>
        </html>
        ';

        return $this->respondWithHtml($html);
    }
}
