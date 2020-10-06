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
use App\Service\QuestionPartsService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\PtcService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use PhpParser\Builder\Property;
use PhpParser\Node\Expr\Array_;

// je hebt hier zowieoz nofig de ommon ground service
// je hebt hier zowieoz nodig de ptc service

class QuestionService
{
    private $finishedRequest;
    private $newrequest;
    private $em;
    private $commongroundService;
    private $ptcService;
    private $questionPartsService;
    private $client;

    public function __construct(EntityManagerInterface $em, CommonGroundService $commongroundService, PtcService $ptcService, QuestionPartsService $questionPartsService)
    {
        $this->finishedRequest = false;
        $this->newrequest = false;
        $this->em = $em;
        $this->commongroundService = $commongroundService;
        $this->ptcService = $ptcService;
        $this->questionPartsService = $questionPartsService;
        $this->client = new Client();
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

        // If the procces is finished we want to inform the user
        $this->finishedRequest = true;

        // wel of geen vragen

        return null;
    }

    public function getUtter(Conversation $conversation, Array $property)
    {
        // Lets see if we need an array of utters
        switch ($property['format']) {
            case 'url':
                // We might have an iri
                if (array_key_exists('iri', $property)) {
                    switch ($property['iri']) {
                        case 'bag/address':
                            $conversation->setQuestionParts(array_merge([$property['id'] => [
                                'postalcode' => null,
                                'housenumber' => null,
                            ]], $conversation->getQuestionParts()));
                            break;
                        case 'brp/ingeschrevenpersoon':
                            $conversation->setQuestionParts(array_merge([], $conversation->getQuestionParts()));
                            break;
                        default:
                            echo "Unknown iri" . $property['iri'];
                    }
                }
        }

        $this->em->persist($conversation);
        $this->em->flush();

        // Lets see if the current property is in the question parts
        if(array_key_exists($property['id'], $conversation->getQuestionParts())){
            // We need to get the first empty question part

            foreach($conversation->getQuestionParts()[$property['id']] as $key => $value) {
                if($value == null){

                    $value = $this->questionPartsService->getPart($key);
                    $responce = [
                        ['text'=> 'Ik heb een vraag over '.$property['title']],
                        ['text'=> $value['utter']]
                    ];

                    return $responce;
                }
            }

            // Everyting looks valid so lets  turn it into a real value and save it
            $value = $this->questionPartsService->getValue($property['iri'], $conversation->getQuestionParts()[$property['id']]);

            if($value != null){
                $request = $conversation->getRequest();
                $request = $this->commongroundService->getResource($request);
                $request['properties'][$property['name']] = $value;
                $this->commongroundService->saveResource($request);


                $responce = [
                    ['text'=> 'Uw gekozen '.$property['title'].' is '. $value['utter']]
                ];

                // Lets get the next property
                $property = $this->commongroundService->getResource($this->getNextQuestion($conversation));

                // And add its utter to the reponce
                $responce = array_merge($responce, $this->getUtter($conversation, $property));

                return $responce;
            }
            else{
                $questionParts = $conversation->getQuestionParts();
                unset($questionParts[$property['id']]);
                $conversation->setQuestionParts($questionParts);
                $this->em->persist($conversation);
                $this->em->flush();

                $responce = [
                    ['text'=> 'Ik heb een vraag over '.$property['title']],
                    ['text'=> $value['utter']]
                ];

                return $responce;
            }
        }


        // Generate a generic responce
        if(array_key_exists('utter', $property) && $property['utter']){
            return [['text'=> $property['utter']]];
        }
        else{
            return [['text'=> $property['title']]];
        }
    }


    /*
     * Determens if the responce is a valid awnser to the last question
     *
     */
    public function getType( array $property)
    {
        $type = false;

        // Detime the enity type that we want the responce to have
        switch ($property['type']) {
            case 'boolean':
                $type = 'intent';
                break;
            case 'string':
                switch ($property['format']) {
                    case 'tel':
                        $type = 'phone-number';
                        break;
                    case 'email':
                        $type = 'email';
                        break;
                    case 'date':
                        $type = 'time';
                        break;
                    case 'date-time':
                        $type = 'time';
                        break;
                    case 'date':
                        $type = 'time';
                        break;
                    case 'url':
                        // We might have an iri
                        if(array_key_exists('iri', $property)){
                            switch ($property['iri']) {
                                case 'bag/address':
                                    $type = 'time';
                                    break;
                                case 'brp/ingeschrevenpersoon':
                                    $type = 'time';
                                    break;
                                default:
                                    echo "Unknown iri".$property['iri'];
                            }
                        }
                        $type = 'time';
                        break;
                    default:
                        echo "Unknown format".$property['format'];
                }
                break;
            default:
                echo "Unknown type".$property['type'];
        }

        // Lets return the type that we are looking for
        return $type;
    }


    /*
     * Gets the message value from nlu based on the type of value expected
     *
     * @param array $message the text message from the user
     * @param array $type the type of entity that we are looking for
     */
    public function getNluValue(string $message, string $type)
    {
        // NLU call
        $response = $this->client->request('POST', 'https://www.develop.virtuele-gemeente-assistent.nl/model/parse',[
            'body'=> json_encode(['text'=>$message])
        ]);

        $statusCode = $response->getStatusCode();
        $nlu = json_decode($response->getBody(), true);

        // Let handle booleans
        if($type == 'intent'){
            // Let check that we have entities

            if(!array_key_exists('intent', $nlu)) return null;

            if($nlu['intent']['name'] == 'inform_affirmative'){
                return true;
            }
            else{
                return false;
            }
        }

        // Let check that we have entities
        if(!array_key_exists('entities', $nlu)) return null;

        // Lets find the first match
        foreach($nlu['entities'] as $entity){
            if($entity['entity'] == $type){
                return $entity['value'];
            }
        }

        // No match found so lets return false
        return null;
    }
}
