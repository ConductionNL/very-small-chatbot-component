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

use Conduction\CommonGroundBundle\Service\CommonGroundService;

// je hebt hier zowieoz nofig de ommon ground service
// je hebt hier zowieoz nodig de ptc service

class QuestionPartsService
{
    private $commongroundService;

    public function __construct(
        CommonGroundService $commongroundService
    ) {
        $this->commongroundService = $commongroundService;
    }

    public function getPart($part)
    {
        $questionParts = [
            'postalcode'  => ['utter'=>'Wat is de postcode?', 'type'=>'postal-code', 'title'=>'postcode'],
            'housenumber' => ['utter'=>'Wat is het huisnummer zonder toevoegingen?', 'type'=>'number', 'title'=>'huisnummer'],
            //'housenumberSufix' => ['utter'=>'Wat is het huisnummer zonder toevoegingen?','type'=>'number','title'=>'huisnummer']
        ];

        if (array_key_exists($part, $questionParts)) {
            return $questionParts[$part];
        }

        return false;
    }

    /*
     * This function gets a total value for parts array
     */
    public function getValue(string $iri, array $parts)
    {
        switch ($iri) {
            // Proccesing bag addresses
            case 'bag/address':
                $addresses = $this->commongroundService->getResourceList(['component'=>'as', 'type'=>'adressen'], ['postcode'=>$parts['postalcode'], 'huisnummer'=>$parts['housenumber']])['hydra:member'];
                if (count($addresses) > 0) {
                    $address = $addresses[0];
                    $readableAddress = $address['huisnummer'].''.$address['huisnummertoevoeging'].' '.$address['straat'].', '.$address['postcode'].' '.$address['woonplaats'];

                    return ['utter'=> $readableAddress, 'value'=> $address['@id']];
                } else {
                    return ['utter'=> 'er kon geen addres worden gevonden voor '.implode(',', $parts), 'value'=> null];
                }
        }
    }
}
