<?php namespace Aether;

use FastRoute\Dispatcher;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * User: mcfog
 * Date: 14-8-23
 */
class App
{
    const D_APP = 'aether.app';
    const D_ROUTER = 'aether.router';
    const D_EVENT_DISPATCHER = 'aether.event-dispatcher';
    const D_CONFIG_OB_CONTENT = 'aether.config.ob-content';

    /**
     * @var Container
     */
    protected $dependency;

    public function __construct(Container $dependency = null)
    {
        if (is_null($dependency)) {
            $dependency = DependencyProvider::makeContainer();
        }

        $this->dependency = $dependency;

        $this->dependency->offsetSet(self::D_APP, $this);
    }

    public static function main(Container $dependency = null, Context $context = null)
    {
        /**
         * @var App $app
         */
        $app = new static($dependency);

        $app->run($context);
    }

    public function run(Context $context = null)
    {
        if (is_null($context)) {
            $context = Context::current($this);
        }

        set_exception_handler(
            function (\Exception $exception) use ($context) {
                $context->triggerEvent(Event::INTERNAL_ERROR, $exception);
            }
        );
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        );

        $obEnabled = $this->get(App::D_CONFIG_OB_CONTENT);

        $routeInfo = $this->getRouter()->dispatch($context);

        if ($obEnabled) {
            ob_start();
        }

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $context->triggerEvent(Event::NOT_FOUND);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $context->triggerEvent(Event::ACCESS_DENY);
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                if (!is_callable($handler)) {
                    $context->triggerEvent(Event::INTERNAL_ERROR, new \Exception("route not callable"));
                } else {
                    $handler($context, $vars);
                }
                break;
        }

        if ($obEnabled) {
            $content = ob_get_clean();
            if (!empty($content)) {
                $context->getResponse()->setContent($content);
            }
        }

        $context->getResponse()->send();

        restore_exception_handler();
        restore_error_handler();
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->get(self::D_ROUTER);
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->get(self::D_EVENT_DISPATCHER);
    }

    public function get($key)
    {
        return $this->dependency->offsetGet($key);
    }

    public function register(ServiceProviderInterface $provider, array $config = array())
    {
        $this->dependency->register($provider, $config);

        return $this;
    }

    /**
     * @return Container
     */
    public function getDependency()
    {
        return $this->dependency;
    }
}