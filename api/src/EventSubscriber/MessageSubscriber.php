<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\MessageService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class MessageSubscriber implements EventSubscriberInterface
{
    private $params;
   // private $requestTypeService;
    private $serializer;

    public function __construct(ParameterBagInterface $params, MessageService $messageService, SerializerInterface $serializer)
    {
        $this->params = $params;
        $this->messageService = $messageService;
        $this->serializer = $serializer;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['getMessage', EventPriorities::PRE_VALIDATE],
        ];
    }

    public function getRequestType(GetResponseForControllerResultEvent $event)
    {
        $message = $event->getControllerResult();
        $route = $event->getRequest()->get('_route');
        $method = $event->getRequest()->getMethod();
        $extend = $event->getRequest()->query->get('extend');

        //!$requestType instanceof RequestType || Request::METHOD_GET !== $method ||
        if ($extend != 'true' || $route != 'api_messages_get_item') {
            return $message;
        }

        //var_dump($method);

        //$message = $this->messageService->extendMessage($message);

        return $message;
    }
}
