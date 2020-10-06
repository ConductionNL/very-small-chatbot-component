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
use App\Service\QuestionService;
use App\Service\QuestionPartsService;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\PtcService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;

// je hebt hier zowieoz nofig de ommon ground service
// je hebt hier zowieoz nodig de ptc service

class MessageService
{
    private $finishedRequest;
    private $newrequest;
    private $em;
    private $commongroundService;
    private $questionService;
    private $ptcService;
    private $questionPartsService;
    private $client;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commongroundService, QuestionService $questionService, PtcService $ptcService, QuestionPartsService $questionPartsService)
    {
        $this->finishedRequest = false;
        $this->newrequest = false;
        $this->em = $em;
        $this->commongroundService = $commongroundService;
        $this->questionService = $questionService;
        $this->ptcService = $ptcService;
        $this->questionPartsService = $questionPartsService;
        $this->client = new Client();
    }

    public function getResponce(Message $message, string $proccesId)
    {
        $response = [];

        $conversation = $this->getConversation($message, $proccesId);

        $proccess = $this->commongroundService->getResource(['component'=>'ptc','type'=>'process_types','id'=> $conversation->getProccess() ]);

        // Lets esteblish if there is a new request
        if($this->newrequest){

            // Let transfer any start data
            if(strpos('/init', $message->getMessage())){

                $message = str_replace("/init","", $message->getMessage());

                $properties = json_decode($message);

                $request = $conversation->getRequest();
                $request['properties'] = $properties;
                $conversation->setRequest($request);
            }

            // Lets give a startup message
            $response[] = [
                'text'=>'Ik ga de '.$proccess['name'].' doorgeven. Jouw aanvraag heeft nummer '.$this->newrequest.' Je kunt je aanvraag altijd bekijken of bewerken op onze website.',
                'buttons'=> [
                    ["title"=>"Aanvraag bekijken","payload"=>"https://ds.zuid-drecht.nl/?responceUrl=http://dev.zuid-drecht.nl/digispoof&backUrl=https://dev.zuid-drecht.nl/ptc/process/".$conversation->getProccess()."?request=".urlencode($conversation->getRequest()),"type"=>"web_url"]
                ]
            ];
        }

        // Kijken of er een vraag wordt benatwoord
        if($property = $conversation->getLastQuestion()){

            //Turn the resource into an array
            $property = $this->commongroundService->getResource($property);

            // Lets see if this is a question part
            if(array_key_exists($property['id'], $conversation->getQuestionParts())){
                $break = false;
                // We need to get the first empty question part
                foreach($conversation->getQuestionParts()[$property['id']] as $key => $value) {
                    if ($value == null) {
                        $questionPartKey = $key;
                        $questionPart = $this->questionPartsService->getPart($key);
                        $type = $questionPart['type'];
                        $break = true;
                        break;
                    }
                }
                if(!$break){
                    // If we get here then the last parts question has been completed so
                    $property = $this->questionService->getNextQuestion($conversation);
                    $conversation->setLastQuestion($property);
                    $property = $this->commongroundService->getResource($property);
                    $type =  $this->questionService->getType($property);
                }
            }
            // If not we get an type normaly
            else{
                $type =  $this->questionService->getType($property);
            }

            $value = $this->questionService->getNluValue($message->getMessage(), $type);

            if($value || (is_bool($value))){
                // Get the request
                $request = $conversation->getRequest();
                $request = $this->commongroundService->getResource($request);

                // Write our message to the request
                if(isset($questionPart)){

                    $questionParts = $conversation->getQuestionParts();
                    $questionParts[$property['id']][$questionPartKey] = $value;
                    $conversation->setQuestionParts($questionParts);
                    $this->em->persist($conversation);
                    $this->em->flush();

                    if(is_bool($value)){
                        if($value){
                            $value = 'ja';
                        }
                        else{
                            $value = 'nee';
                        }
                    }
                    $response[] = ['text'=> 'Uw gekozen '.$questionPart['title'].' is '. $value];
                }
                else{
                    // Save our data
                    $request['properties'][$property['name']] = $value;
                    $this->commongroundService->saveResource($request);

                    if(is_bool($value)){
                        if($value){
                            $value = 'ja';
                        }
                        else{
                            $value = 'nee';
                        }
                    }
                    $response[] = ['text'=> 'Uw gekozen '.$property['title'].' is '. $value];
                }
            }
            else{
                $response[] = ['text'=> 'Ik ben bang dat ik je niet goed begrijp. Kun je het nog eens proberen?'];
                //$response[] = ['text'=> 'kan waarde: "'.$message->getMessage().'" niet omzeten naar type: "'.$type.''];
            }
        }

        // set the lats question
        $conversation->setLastQuestion($this->questionService->getNextQuestion($conversation));

        // And save it to the database
        $this->em->persist($conversation);
        $this->em->flush();

        // If we have a current question we want to utter it
        if($conversation->getLastQuestion()){

            $property = $this->commongroundService->getResource($conversation->getLastQuestion());

            $response= array_merge($response,$this->questionService->getUtter($conversation, $property));
        }
        else{

            // If a login is requered we want to offer the user that option
            //if(in_array($proccess['login'],['always','onSubmit'])){

                $backUrl = "";
                // Add a login option to the responce stack
                $response[] = [
                    'attachment' =>
                        [
                        'type'=> 'template',
                        'payload'=> [
                            'template_type'=> 'generic',
                            'elements'=> [
                                [
                                    "subtitle" => 'Bedankt. Om de '.$proccess['name'].' definitief door te geven, moet je inloggen. Dan weten we zeker dat jij het bent.',
                                    "image_url" => "https://www.develop.virtuele-gemeente-assistent.nl/static/img/digid_eo_rgb_150px.png",
                                    'buttons' => [
                                        [
                                            "title"=> "Verzoek bevestigen",
                                            "url"=>"https://ds.zuid-drecht.nl/?responceUrl=http://dev.zuid-drecht.nl/digispoof&backUrl=https://dev.zuid-drecht.nl/ptc/process/".$conversation->getProccess()."?request=".urlencode($conversation->getRequest()),
                                            "type"=> "web_url"
                                        ],
                                        [
                                            "title"=>"Annuleren",
                                            "type"=> "postback",
                                            "payload"=> "/greet"
                                        ]
                                    ]
                                ],
                            ]
                        ]
                    ]
                ];
            /*}

            // If no login is required we just confirm the recieving og the request
            else{
                // Add a text to the responce stack
                $response[] = ['text'=> 'Bedankt. Je '.$proccess['name'].' is goed doorgekomen. We gaan deze zo snel mogelijk verwerken.'];

                // Get the request
                $request = $conversation->getRequest();
                $request = $this->commongroundService->getResource($request);
                $request['status'] = 'submitted';
                $request = $this->commongroundService->saveResource($request);
            } */
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


}
