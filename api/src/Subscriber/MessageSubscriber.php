<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Service\MessageService;
use App\Entity\Message;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageSubscriber implements EventSubscriberInterface
{
    private $params;
    private $messageService;
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
        $contentType = $event->getRequest()->headers->get('accept');
        $proccesId = $event->getRequest()->attributes->get('id');


        if (!$contentType) {
            $contentType = $event->getRequest()->headers->get('Accept');
        }

        if ($route != 'api_messages_post_message_to_proccess_collection') {
            return $message;
        }

        // Lets set a return content type
        switch ($contentType) {
            case 'application/json':
                $renderType = 'json';
                break;
            case 'application/ld+json':
                $renderType = 'jsonld';
                break;
            case 'application/hal+json':
                $renderType = 'jsonhal';
                break;
            default:
                $contentType = 'application/json';
                $renderType = 'json';
        }

        // now we need to overide the normal subscriber
        $json = $this->serializer->serialize(
            $this->messageService->getResponce($message, $proccesId),
            $renderType,
            ['enable_max_depth' => true]
        );
        $response = new Response(
            $json,
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );

        $event->setResponse($response);
    }
}
