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

        if ($route != 'api_messages_post_message_to_proccess_collection') {
            return $message;
        }


        $contentType = $event->getRequest()->headers->get('accept');
        if (!$contentType) {
            $contentType = $event->getRequest()->headers->get('Accept');
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
        // controlleren of we triggeren
        var_dump('trigger');


        // now we need to overide the normal subscriber
        $json = $this->serializer->serialize(
            $message,
            $renderType,
            ['enable_max_depth' => true]
        );
        $response = new Response(
            $json,
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );

        // controlleren of we triggeren hier komt hij niet
        //var_dump('trigger');

        $event->setResponse($response);


        // wat er eigenlijk moet gebeuren is een responce genereren
        //$proccesId -. get id form request
        // controlleren of we triggeren
        //var_dump($proccesId);
        //$message->setResponce($this->messageService->getResponce($message, $proccesId));

        return $message;
    }
}
