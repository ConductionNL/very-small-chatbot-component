<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\MessageService;
use App\Entity\Message;
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
            KernelEvents::VIEW => ['getResponce', EventPriorities::PRE_VALIDATE],
        ];
    }

    public function getResponce(GetResponseForControllerResultEvent $event)
    {
        $message = $event->getControllerResult();
        $route = $event->getRequest()->get('_route');
        $method = $event->getRequest()->getMethod();

        // Alleen triggeren op het jusite moment
        if (!$message instanceof Message  || $route != 'post_message_to_proccess' || Request::METHOD_POST !== $method) {
            return $message;
        }

        // controlleren of we triggeren
        var_dump('trigger');

        // test setup
        $message->setResponce('message recieved');

        // wat er eigenlijk moet gebeuren is een responce genereren
        //$proccesId -. get id form request
        //$message->setResponce($this->messageService->getResponce($message, $proccesId));

        return $message;
    }
}
