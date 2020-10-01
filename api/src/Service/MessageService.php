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
    private $em;
    private $commongroundService;
    private $ptcService;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commongroundService, PtcService $ptcService)
    {
        $this->em = $em;
        $this->commongroundService = $commongroundService;
        $this->ptcService = $ptcService;
    }

    public function getResponce(Message $message, string $proccesId)
    {
        // vraag 1 is er een converstion?
        $conversation = $this->getConversation($message, $proccesId);

        // Kijken of er een vraag wordt benatwoord
        if($property = $conversation->getLastQuestion()){

            //Turn the resource into an array
            $property = $this->commongroundService->getResource($property);

            // Get the request
            $request = $conversation->getRequest();

            // Write our message to the request
            $request['properties'][$property['name']] = $message->getMessage();

            // Save our data
            $this->commongroundService->save($request);
        }

        $conversation->setLastQuestion($this->getNextQuestion($conversation));

        $property =$this->commongroundService->getResource($conversation->getLastQuestion());

        // responce genereren
        if(array_key_exists('utter', $property)){
            return $property['utter'];
        }
        else{
            return $property['title'];
        }

    }

    public function getConversation(Message $message, string $proccesId)
    {
        // 1. Haal uit DB converstation aan de hand van sender + $proccesId
        $conversation = $this->em->getRepository('App\Entity\Conversation')
        ->findOneBy(['sender'=>$message->getSender(),'proccess'=>$proccesId]);

        if($conversation['']){
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

        // Verzoek opslaan
        $request = $this->commongroundService->saveResource($request,['component'=>'vrc','type'=>'requests']);

        $conversation = New Conversation();
        $conversation->setProccess($procces['id']);
        $conversation->setRequest($request['@id']);
        $conversation->setSender($message->getSender());
        $conversation->getLastQuestion(null);

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
        $procces = $this->ptcService->extendProcess($proccess, $request);

        foreach ($proccess['stages'] as $stage){
            foreach ($stage['sections'] as $section){
                var_dump($section);
                die;
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
