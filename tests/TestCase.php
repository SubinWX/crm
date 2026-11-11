<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Redirector;

abstract class TestCase extends BaseTestCase
{
    /**
     * Livewire swaps the "redirect" service while a component runs and does not put it back
     * when a component call aborts (403/404). Restore it before every plain HTTP request so
     * tests can mix Livewire calls and page requests.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (! $this->app['redirect'] instanceof Redirector || get_class($this->app['redirect']) !== Redirector::class) {
            $redirector = new Redirector($this->app['url']);
            $redirector->setSession($this->app['session.store']);
            $this->app->instance('redirect', $redirector);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
