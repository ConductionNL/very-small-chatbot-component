<?php

// Conduction/CommonGroundBundle/Service/RequestTypeService.php

/*
 * This file is part of the Conduction Common Ground Bundle
 *
 * (c) Conduction <info@conduction.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\PtcService;
use Doctrine\ORM\EntityManagerInterface;
// je hebt hier zowieoz nofig de ommon ground service
// je hebt hier zowieoz nodig de ptc service

class MessageService
{
    private $newrequest;
    private $em;
    private $commongroundService;
    private $ptcService;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commongroundService, PtcService $ptcService)
    {
        $this->newrequest = false;
        $this->em = $em;
        $this->commongroundService = $commongroundService;
        $this->ptcService = $ptcService;
    }

    public function getResponce(Message $message, string $proccesId)
    {
        $response = [];

        // vraag 1 is er een converstion?
        $conversation = $this->getConversation($message, $proccesId);

        // Lets esteblish if there is a new request
        if($this->newrequest){
            $response[] = [
                'text'=>'Bedankt voor uw verhuis verzoek, om uw aanvraag te kunnen verwerken hebben we een verzoek voor u aangemaakt onder nummer '.$this->newrequest.'. U kunt uw verzoek op ieder moment inzien en wijzigen via onze webstie',
                'buttons'=> [
                    ["title"=>"Verzoek inzien","payload"=>"https://zuid-drecht.nl/"]
                ]
            ];
        }

        // Kijken of er een vraag wordt benatwoord
        if($property = $conversation->getLastQuestion()){

            //Turn the resource into an array
            $property = $this->commongroundService->getResource($property);

            // Get the request
            $request = $conversation->getRequest();
            $request = $this->commongroundService->getResource($request);

            // Write our message to the request
            $request['properties'][$property['name']] = $message->getMessage();

            /* @todo nlu integratie */

            // Save our data
            $this->commongroundService->saveResource($request);
            $response[] = ['text'=> 'Uw gekozen '.$property['title'].' is '. $request['properties'][$property['name']]];
        }

        // set the lats question
        $conversation->setLastQuestion($this->getNextQuestion($conversation));
        // And save it to the database
        $this->em->persist($conversation);
        $this->em->flush();

        $property =$this->commongroundService->getResource($conversation->getLastQuestion());

        // responce genereren
        if(array_key_exists('utter', $property)){
            $response[] = ['text'=> $property['utter']];
        }
        else{
            $response[] = ['text'=> $property['title']];
        }

        return $response;

    }

    public function getConversation(Message $message, string $proccesId)
    {
        // 1. Haal uit DB converstation aan de hand van sender + $proccesId
        $conversation = $this->em->getRepository('App\Entity\Conversation')
        ->findOneBy(['sender'=>$message->getSender(),'proccess'=>$proccesId]);

        if($conversation){
            return $conversation;
        }
        else{
        // 2.b las converstiaon niet bestaad, maar converstaion aan en return deze
        $procces = $this->commongroundService->getResource(['component'=>'ptc','type'=>'process_types','id'=> $proccesId ]);

        $request = [];
        $request['processType'] = $procces['@id'];
        $request['requestType'] = $procces['requestType'];
        $request['organization'] = $this->commongroundService->cleanUrl(['component'=>'wrc','type'=>'organizations','id'=> '4d1eded3-fbdf-438f-9536-8747dd8ab591' ]);
        $request['properties'] = [];

        // Lets create the request
        $request = $this->commongroundService->saveResource($request,['component'=>'vrc','type'=>'requests']);

        // Lets allert that there is a new request
        $this->newrequest = $request['reference'];

        // Lets create the converstation
        $conversation = New Conversation();
        $conversation->setProccess($procces['id']);
        $conversation->setRequest($request['@id']);
        $conversation->setSender($message->getSender());
        $conversation->getLastQuestion(null);

        // And save it to the database
        $this->em->persist($conversation);
        $this->em->flush();

        return $conversation;
        }
    }

    public function getNextQuestion(Conversation $conversation)
    {
        $request = $conversation->getRequest();
        $request = $this->commongroundService->getResource($request);

        $proccess = $request['processType'];
        $proccess = $this->commongroundService->getResource($proccess);

        // last question moet altijd een vtc property zijn
        $proccess = $this->ptcService->extendProcess($proccess, $request);

        foreach ($proccess['stages'] as $stage){
            foreach ($stage['sections'] as $section){
                foreach ($section['propertiesForms'] as $property){
                    // Returnen op de éerste niet valid vraag
                    if(!$property['valid']){
                        return $this->commongroundService->cleanUrl(['component'=>'vtc','type'=>'properties','id'=> $property['id'] ]); ;
                    }
                }
            }
        }

        // wel of geen vragen

        return null;
    }
}
