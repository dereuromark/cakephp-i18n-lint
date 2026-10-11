<?php
declare(strict_types=1);

namespace Demo\Controller;

use App\Controller\AppController;

class DemoController extends AppController
{
    public function index(string $name, int $count): void
    {
        $this->set('saved', __d('demo', 'Saved.'));
        $this->set('hello', __d('demo', 'Hello {0}', $name));
        $this->set('items', __dn('demo', '{0} item', '{0} items', $count, $count));
        $this->set('open', __dx('demo', 'menu', 'Open'));
    }
}
